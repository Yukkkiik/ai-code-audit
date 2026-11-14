<?php

namespace App\Services\Audit;

use App\Enums\AuditStage;
use App\Enums\AuditStatus;
use App\Enums\StageStatus;
use App\Exceptions\InvalidAuditTransitionException;
use App\Models\AuditScan;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan status audit (state machine di FLOWS.md §1).
 * Setiap transisi membaca baris terbaru dengan row lock, sehingga model yang
 * sudah usang atau job yang dijalankan ulang tidak dapat melompati alur.
 */
class AuditStatusManager
{
    private const ERROR_MAX_LENGTH = 500;

    public function markIngesting(AuditScan $audit): void
    {
        $this->transition($audit, function (AuditScan $current) {
            $this->expectStatus($current, AuditStatus::Queued);

            return ['status' => AuditStatus::Ingesting, 'started_at' => now()];
        });
    }

    public function markScanning(AuditScan $audit): void
    {
        $this->transition($audit, function (AuditScan $current) {
            $this->expectStatus($current, AuditStatus::Ingesting);

            return ['status' => AuditStatus::Scanning];
        });
    }

    public function startStage(AuditScan $audit, AuditStage $stage): void
    {
        $this->transition($audit, function (AuditScan $current) use ($stage) {
            $this->expectStatus($current, AuditStatus::Scanning);
            $this->expectStageStatus($current, $stage, [StageStatus::Pending]);

            return [$stage->statusColumn() => StageStatus::Running];
        });
    }

    /**
     * @param  StageStatus  $result  completed | partial | failed | skipped
     * @param  string|null  $error  pesan aman untuk pengguna (bukan stack trace)
     */
    public function completeStage(AuditScan $audit, AuditStage $stage, StageStatus $result, ?string $error = null): void
    {
        if (! $this->isFinalStageStatus($result)) {
            throw new InvalidAuditTransitionException("Status akhir tahap {$stage->value} tidak boleh [{$result->value}].");
        }

        $this->transition($audit, function (AuditScan $current) use ($stage, $result, $error) {
            $this->expectStatus($current, AuditStatus::Scanning);
            $this->expectStageStatus($current, $stage, [StageStatus::Pending, StageStatus::Running]);

            return [
                $stage->statusColumn() => $result,
                $stage->errorColumn() => $this->limitError($error),
            ];
        });
    }

    public function finalize(AuditScan $audit): void
    {
        $this->transition($audit, function (AuditScan $current) {
            $this->expectStatus($current, AuditStatus::Scanning);

            foreach (AuditStage::cases() as $stage) {
                if (! $this->isFinalStageStatus($current->{$stage->statusColumn()})) {
                    throw new InvalidAuditTransitionException("Tahap {$stage->value} belum selesai; audit tidak dapat difinalisasi.");
                }
            }

            return [
                'status' => self::resolveFinalStatus($current->sast_status, $current->sca_status),
                'finished_at' => now(),
            ];
        });
    }

    /**
     * Kegagalan level audit (mis. ingestion gagal, job timeout).
     * Tahap yang belum mulai menjadi `skipped`, tahap yang sedang berjalan menjadi `failed`.
     */
    public function markFailed(AuditScan $audit, string $errorCode, string $message): void
    {
        $this->transition($audit, function (AuditScan $current) use ($errorCode, $message) {
            $this->expectStatus($current, AuditStatus::Queued, AuditStatus::Ingesting, AuditStatus::Scanning);

            $changes = [
                'status' => AuditStatus::Failed,
                'error_code' => $errorCode,
                'error_message' => $this->limitError($message),
                'finished_at' => now(),
            ];

            foreach (AuditStage::cases() as $stage) {
                $changes[$stage->statusColumn()] = match ($current->{$stage->statusColumn()}) {
                    StageStatus::Pending => StageStatus::Skipped,
                    StageStatus::Running => StageStatus::Failed,
                    default => $current->{$stage->statusColumn()},
                };
            }

            return $changes;
        });
    }

    /**
     * Aturan status akhir (FLOWS.md §1):
     * tanpa tahap gagal/parsial → completed; ada tahap yang menghasilkan data → partial; selain itu → failed.
     */
    public static function resolveFinalStatus(StageStatus $sast, StageStatus $sca): AuditStatus
    {
        $stages = [$sast, $sca];

        if (! in_array(StageStatus::Failed, $stages, true) && ! in_array(StageStatus::Partial, $stages, true)) {
            return AuditStatus::Completed;
        }

        $producedResults = in_array(StageStatus::Completed, $stages, true) || in_array(StageStatus::Partial, $stages, true);

        return $producedResults ? AuditStatus::Partial : AuditStatus::Failed;
    }

    /**
     * @param  Closure(AuditScan): array<string, mixed>  $apply
     */
    private function transition(AuditScan $audit, Closure $apply): void
    {
        DB::transaction(function () use ($audit, $apply) {
            $current = AuditScan::query()->whereKey($audit->getKey())->lockForUpdate()->firstOrFail();

            $current->forceFill($apply($current))->save();
        });

        $audit->refresh();
    }

    private function expectStatus(AuditScan $audit, AuditStatus ...$allowed): void
    {
        if (! in_array($audit->status, $allowed, true)) {
            $expected = implode(' / ', array_map(fn (AuditStatus $status) => $status->value, $allowed));

            throw new InvalidAuditTransitionException("Audit {$audit->public_id} berstatus [{$audit->status->value}], seharusnya [{$expected}].");
        }
    }

    /**
     * @param  list<StageStatus>  $allowed
     */
    private function expectStageStatus(AuditScan $audit, AuditStage $stage, array $allowed): void
    {
        $current = $audit->{$stage->statusColumn()};

        if (! in_array($current, $allowed, true)) {
            throw new InvalidAuditTransitionException("Tahap {$stage->value} pada audit {$audit->public_id} berstatus [{$current->value}].");
        }
    }

    private function isFinalStageStatus(StageStatus $status): bool
    {
        return ! in_array($status, [StageStatus::Pending, StageStatus::Running], true);
    }

    private function limitError(?string $error): ?string
    {
        return $error === null ? null : mb_substr($error, 0, self::ERROR_MAX_LENGTH);
    }
}
