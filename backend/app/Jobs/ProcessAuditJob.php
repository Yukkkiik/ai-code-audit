<?php

namespace App\Jobs;

use App\Enums\AuditStage;
use App\Enums\AuditStatus;
use App\Exceptions\IngestionException;
use App\Exceptions\InvalidAuditTransitionException;
use App\Models\AuditScan;
use App\Services\Audit\AuditStatusManager;
use App\Services\Audit\Stages\AuditStageRunners;
use App\Services\Audit\Stages\StageResult;
use App\Services\Audit\Stages\StageRunner;
use App\Services\Ingestion\AuditWorkspace;
use App\Services\Ingestion\IngestedRepository;
use App\Services\Ingestion\RepositoryIngestor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orkestrator satu audit (D-26): klaim → ingestion → SAST → SCA → finalisasi → cleanup.
 * Payload hanya berisi ID audit; kegagalan satu tahap tidak menggagalkan tahap lain.
 */
class ProcessAuditJob implements ShouldQueue
{
    use Queueable;

    /**
     * Pipeline tidak diulang otomatis agar repositori tidak dipindai ganda.
     */
    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public int $auditScanId)
    {
        $this->timeout = (int) config('audit.job.timeout_seconds');
    }

    public function handle(
        AuditStatusManager $status,
        RepositoryIngestor $ingestor,
        AuditWorkspace $workspace,
        AuditStageRunners $runners,
    ): void {
        $audit = AuditScan::query()->find($this->auditScanId);

        if ($audit === null) {
            Log::warning('ProcessAuditJob: audit tidak ditemukan.', ['audit_scan_id' => $this->auditScanId]);

            return;
        }

        // Klaim audit (queued → ingesting). Gagal berarti audit sudah diproses job lain:
        // jangan ubah status dan jangan sentuh workspace milik job tersebut.
        try {
            $status->markIngesting($audit);
        } catch (InvalidAuditTransitionException $e) {
            Log::warning('ProcessAuditJob: audit sudah tidak berstatus queued, job diabaikan.', [
                'audit' => $audit->public_id,
                'status' => $audit->status->value,
            ]);

            return;
        }

        try {
            try {
                $repository = $ingestor->ingest($audit);
            } catch (IngestionException $e) {
                $status->markFailed($audit, $e->errorCode, $e->getMessage());

                return;
            }

            $status->markScanning($audit, $repository->commitSha, $repository->sizeBytes);

            foreach (AuditStage::cases() as $stage) {
                $this->runStage($status, $runners->for($stage), $audit, $repository);
            }

            $status->finalize($audit);
        } finally {
            $workspace->cleanup($audit);
        }
    }

    /**
     * Dipanggil queue saat handle() melempar exception, job timeout, atau percobaan habis.
     */
    public function failed(?Throwable $exception): void
    {
        $audit = AuditScan::query()->find($this->auditScanId);

        if ($audit === null) {
            return;
        }

        app(AuditWorkspace::class)->cleanup($audit);

        if (in_array($audit->status, [AuditStatus::Completed, AuditStatus::Partial, AuditStatus::Failed], true)) {
            return;
        }

        [$code, $message] = $exception instanceof TimeoutExceededException
            ? ['JOB_TIMEOUT', 'Pemrosesan audit melebihi batas waktu.']
            : ['PROCESSING_FAILED', 'Terjadi kesalahan saat memproses audit.'];

        app(AuditStatusManager::class)->markFailed($audit, $code, $message);
    }

    private function runStage(AuditStatusManager $status, StageRunner $runner, AuditScan $audit, IngestedRepository $repository): void
    {
        $stage = $runner->stage();
        $status->startStage($audit, $stage);

        try {
            $result = $runner->run($audit, $repository);
        } catch (Throwable $e) {
            Log::error("ProcessAuditJob: tahap {$stage->value} gagal karena exception.", [
                'audit' => $audit->public_id,
                'exception' => $e,
            ]);

            $result = StageResult::failed('Terjadi kesalahan internal pada tahap '.strtoupper($stage->value).'.');
        }

        $status->completeStage($audit, $stage, $result->status, $result->message);
    }
}
