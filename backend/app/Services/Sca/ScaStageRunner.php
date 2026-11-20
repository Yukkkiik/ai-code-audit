<?php

namespace App\Services\Sca;

use App\Enums\AuditStage;
use App\Enums\OsvFailureReason;
use App\Models\AuditScan;
use App\Models\Dependency;
use App\Services\Audit\Stages\StageResult;
use App\Services\Audit\Stages\StageRunner;
use App\Services\Ingestion\IngestedRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Tahap SCA: manifest → dependencies → OSV querybatch → detail advisori → vulnerabilities.
 * Semua dependensi disimpan lebih dulu (dan sebelum advisori dipetakan, karena penyimpanan ulang menghapus
 * vulnerabilities lama lewat cascade). Hanya versi pasti yang dikirim ke OSV. Exception tak terduga tidak
 * ditangkap di sini: ProcessAuditJob mencatat tahap gagal dengan pesan generik tanpa mengganggu tahap lain.
 */
class ScaStageRunner implements StageRunner
{
    /** Jumlah ID advisori yang diambil, diurai, disimpan, lalu dilepas dari memori per putaran. */
    private const DETAIL_CHUNK_SIZE = 50;

    private const OSV_STATUS_CHUNK_SIZE = 1000;

    public function __construct(
        private DependencyCollector $collector,
        private DependencyStore $dependencies,
        private OsvClient $osv,
        private VulnerabilityStore $vulnerabilities,
    ) {}

    public function stage(): AuditStage
    {
        return AuditStage::Sca;
    }

    public function run(AuditScan $audit, IngestedRepository $repository): StageResult
    {
        $collected = $this->collector->collect($repository->rootPath);

        if (! $collected->foundProjects) {
            return StageResult::skipped('Tidak ada package.json pada repositori; pemeriksaan dependensi dilewati.');
        }

        $this->dependencies->store($audit, $collected->dependencies);

        $manifestNote = $this->manifestNote($collected->failures);

        if ($collected->dependencies === []) {
            return $collected->failures === []
                ? StageResult::completed('Tidak ada dependensi yang dideklarasikan pada package.json.')
                : StageResult::failed('Tidak ada dependensi yang dapat dibaca.'.$manifestNote);
        }

        $exact = Dependency::query()
            ->where('audit_scan_id', $audit->id)
            ->where('version_status', 'exact')
            ->orderBy('id')
            ->get(['id', 'name', 'version'])
            ->values();
        $unresolved = count($collected->dependencies) - $exact->count();

        if ($exact->isEmpty()) {
            return StageResult::partial("Tidak ada dependensi yang dapat diperiksa ke OSV: {$unresolved} dependensi tidak memiliki versi pasti.".$manifestNote);
        }

        $deadline = $this->deadline($audit);

        if ($deadline === null) {
            return StageResult::partial("Waktu pemrosesan audit tidak cukup untuk memeriksa dependensi ke OSV; {$exact->count()} dependensi belum diperiksa.".$this->unresolvedNote($unresolved).$manifestNote);
        }

        $results = $this->osv->query(
            $exact->map(fn (Dependency $dependency) => new OsvQuery($dependency->name, (string) $dependency->version))->all(),
            $deadline,
        );

        $queried = [];
        $queryFailed = [];
        $firstFailure = null;
        $byAdvisory = [];

        foreach ($exact as $position => $dependency) {
            $result = $results[$position];

            if ($result->isFailed()) {
                $queryFailed[] = $dependency->id;
                $firstFailure ??= $result->failure;

                continue;
            }

            $queried[] = $dependency->id;

            foreach ($result->vulnerabilities as $ref) {
                $byAdvisory[$ref->id][] = [$dependency->id, $dependency->name, $ref];
            }
        }

        $this->markOsvStatus($queried, 'queried');
        $this->markOsvStatus($queryFailed, 'failed');

        if ($queried === []) {
            return StageResult::failed('Pemeriksaan ke OSV gagal untuk semua dependensi ('.$firstFailure?->message().')'.$this->unresolvedNote($unresolved).$manifestNote);
        }

        $detailFailures = $this->storeAdvisories($audit, $byAdvisory, $deadline);

        $message = $this->message(count($queried), count($byAdvisory), $unresolved, count($queryFailed), $firstFailure, $detailFailures).$manifestNote;

        return $queryFailed !== [] || $detailFailures > 0 || $collected->failures !== []
            ? StageResult::partial($message)
            : StageResult::completed($message);
    }

    /**
     * Mengambil detail advisori per potongan, menyimpannya, lalu melepaskan memori sebelum potongan berikutnya.
     * Detail yang gagal tetap disimpan sebagai baris minimal.
     *
     * @param  array<array-key, list<array{0: int, 1: string, 2: OsvVulnerabilityRef}>>  $byAdvisory
     * @return int jumlah advisori yang detailnya tidak berhasil diambil
     */
    private function storeAdvisories(AuditScan $audit, array $byAdvisory, CarbonInterface $deadline): int
    {
        $failures = 0;

        foreach (array_chunk(array_map('strval', array_keys($byAdvisory)), self::DETAIL_CHUNK_SIZE) as $ids) {
            $details = $this->osv->fetchVulnerabilities($ids, $deadline);
            $matches = [];

            foreach ($ids as $id) {
                $detail = $details[$id];
                $failures += $detail->isFailed() ? 1 : 0;

                foreach ($byAdvisory[$id] as [$dependencyId, $name, $ref]) {
                    $matches[] = new VulnerabilityMatch($dependencyId, $name, $ref, $detail->detail);
                }
            }

            $this->vulnerabilities->store($audit, $matches);
            unset($details, $matches);
        }

        return $failures;
    }

    /**
     * Batas akhir pemanggilan OSV: anggaran OSV, dibatasi sisa waktu job dikurangi margin.
     * null jika waktu job tidak cukup lagi. `started_at` kosong berarti hanya anggaran OSV yang berlaku.
     */
    private function deadline(AuditScan $audit): ?CarbonInterface
    {
        $budget = $this->osv->budgetDeadline();

        if ($audit->started_at === null) {
            return $budget;
        }

        $jobSeconds = (int) config('audit.job.timeout_seconds') - (int) config('audit.sca.job_margin_seconds');
        $jobDeadline = Carbon::instance($audit->started_at)->addSeconds($jobSeconds);

        if ($jobDeadline->lessThanOrEqualTo(Carbon::now())) {
            return null;
        }

        return $jobDeadline->lessThan($budget) ? $jobDeadline : $budget;
    }

    /**
     * @param  list<int>  $dependencyIds
     */
    private function markOsvStatus(array $dependencyIds, string $status): void
    {
        foreach (array_chunk($dependencyIds, self::OSV_STATUS_CHUNK_SIZE) as $chunk) {
            Dependency::query()->whereIn('id', $chunk)->update(['osv_status' => $status]);
        }
    }

    private function message(int $checked, int $advisories, int $unresolved, int $queryFailed, ?OsvFailureReason $failure, int $detailFailures): string
    {
        $message = "{$checked} dependensi diperiksa ke OSV; ".($advisories === 0
            ? 'tidak ditemukan advisori pada OSV.dev.'
            : "{$advisories} advisori ditemukan.");

        $message .= $this->unresolvedNote($unresolved);

        if ($queryFailed > 0) {
            $message .= " {$queryFailed} dependensi gagal diperiksa ke OSV (".$failure?->message().').';
        }

        if ($detailFailures > 0) {
            $message .= " Detail {$detailFailures} advisori tidak dapat diambil.";
        }

        return $message;
    }

    private function unresolvedNote(int $unresolved): string
    {
        return $unresolved > 0 ? " {$unresolved} dependensi tidak diperiksa karena versi tidak pasti." : '';
    }

    /**
     * @param  list<ManifestFailure>  $failures
     */
    private function manifestNote(array $failures): string
    {
        return $failures === [] ? '' : ' '.count($failures).' manifest gagal dibaca: '.$failures[0]->message;
    }
}
