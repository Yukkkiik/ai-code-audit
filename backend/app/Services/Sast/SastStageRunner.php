<?php

namespace App\Services\Sast;

use App\Enums\AuditStage;
use App\Enums\ToolRunStatus;
use App\Models\AuditScan;
use App\Services\Audit\Stages\StageResult;
use App\Services\Audit\Stages\StageRunner;
use App\Services\Ingestion\IngestedRepository;

/**
 * Tahap SAST: seleksi file → Semgrep → ESLint → metrik → normalisasi → penyimpanan → skor kesehatan.
 * Kegagalan satu tool tidak menghentikan yang lain. Skor hanya dihitung bila minimal satu tool temuan selesai,
 * sehingga "tool gagal" tidak pernah tampil sebagai "0 temuan". Exception tak terduga tidak ditangkap di sini:
 * ProcessAuditJob mencatat tahap gagal dengan pesan generik tanpa mengganggu tahap lain.
 */
class SastStageRunner implements StageRunner
{
    private const MAX_MESSAGE_CHARS = 500;

    public function __construct(
        private JsTsFileSelector $selector,
        private SemgrepRunner $semgrep,
        private EslintRunner $eslint,
        private MetricsRunner $metrics,
        private FindingNormalizer $normalizer,
        private SastResultStore $results,
        private MetricsStore $metricsStore,
        private HealthScoreCalculator $health,
    ) {}

    public function stage(): AuditStage
    {
        return AuditStage::Sast;
    }

    public function run(AuditScan $audit, IngestedRepository $repository): StageResult
    {
        $root = $repository->rootPath;
        $selection = $this->selector->select($root);

        if ($selection->isEmpty()) {
            $this->clearHealth($audit);

            return StageResult::skipped('Tidak ada file JavaScript/TypeScript pada repositori; analisis kode dilewati.');
        }

        $semgrep = $this->semgrep->run($root, $selection->files);
        $eslint = $this->eslint->run($root, $selection->files);
        $metrics = $this->metrics->run($root, $selection->files);

        $findingToolCompleted = $semgrep->status === ToolRunStatus::Completed || $eslint->status === ToolRunStatus::Completed;

        if (! $findingToolCompleted) {
            $this->clearHealth($audit);

            return StageResult::failed($this->limit('Semua tool analisis gagal. '.$this->notes($semgrep, $eslint, $metrics->run)));
        }

        $normalized = $this->normalizer->normalize([...$semgrep->findings, ...$eslint->findings], $root);

        $this->results->store($audit, ['semgrep' => $semgrep, 'eslint' => $eslint], $normalized->findings);
        $this->metricsStore->store($audit, $metrics->files);

        $aggregate = $metrics->run->status === ToolRunStatus::Completed ? MetricsAggregate::from($metrics->files) : null;
        $score = $this->health->calculate($normalized->findings, $aggregate);

        $audit->update(['health_score' => $score->score, 'health_category' => $score->category->value]);

        $incomplete = $selection->skippedTooLarge > 0 || $selection->skippedOverLimit > 0 || $normalized->truncated > 0;
        $complete = ! $incomplete
            && $this->isComplete($semgrep) && $this->isComplete($eslint) && $this->isComplete($metrics->run);

        $summary = sprintf(
            '%d temuan (Semgrep %d, ESLint %d) pada %d file. Skor kesehatan %d.',
            count($normalized->findings),
            $this->countByTool($normalized->findings, 'semgrep'),
            $this->countByTool($normalized->findings, 'eslint'),
            count($selection->files),
            $score->score,
        );

        $notes = $this->notes($semgrep, $eslint, $metrics->run);

        if ($selection->skippedTooLarge > 0 || $selection->skippedOverLimit > 0) {
            $notes .= sprintf(' %d file dilewati (terlalu besar atau melebihi batas jumlah file).', $selection->skippedTooLarge + $selection->skippedOverLimit);
        }

        if ($normalized->truncated > 0) {
            $notes .= " {$normalized->truncated} temuan dengan severity terendah tidak disimpan (melebihi batas).";
        }

        if ($aggregate === null) {
            $notes .= ' Metrik kode tidak tersedia; skor dihitung tanpa komponen metrik.';
        }

        $message = $this->limit(trim($summary.' '.$notes));

        return $complete ? StageResult::completed($message) : StageResult::partial($message);
    }

    private function isComplete(ToolRunResult $run): bool
    {
        return $run->status === ToolRunStatus::Completed && ! $run->incomplete;
    }

    private function notes(ToolRunResult ...$runs): string
    {
        $messages = [];

        foreach ($runs as $run) {
            if ($run->message !== null && $run->status !== ToolRunStatus::Skipped) {
                $messages[] = $run->message;
            }
        }

        return implode(' ', $messages);
    }

    /**
     * @param  list<SastFinding>  $findings
     */
    private function countByTool(array $findings, string $tool): int
    {
        return count(array_filter($findings, fn (SastFinding $finding) => $finding->tool->value === $tool));
    }

    private function clearHealth(AuditScan $audit): void
    {
        $audit->update(['health_score' => null, 'health_category' => null]);
    }

    private function limit(string $message): string
    {
        return mb_strlen($message) > self::MAX_MESSAGE_CHARS ? mb_substr($message, 0, self::MAX_MESSAGE_CHARS - 1).'…' : $message;
    }
}
