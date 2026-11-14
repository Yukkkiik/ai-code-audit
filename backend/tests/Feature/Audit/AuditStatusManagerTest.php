<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditStage;
use App\Enums\AuditStatus;
use App\Enums\StageStatus;
use App\Exceptions\InvalidAuditTransitionException;
use App\Models\AuditScan;
use App\Services\Audit\AuditStatusManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditStatusManagerTest extends TestCase
{
    use RefreshDatabase;

    private AuditStatusManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->app->make(AuditStatusManager::class);
    }

    public function test_full_successful_lifecycle(): void
    {
        $audit = AuditScan::factory()->create();

        $this->manager->markIngesting($audit);
        $this->assertSame(AuditStatus::Ingesting, $audit->status);
        $this->assertNotNull($audit->started_at);

        $this->manager->markScanning($audit);
        $this->manager->startStage($audit, AuditStage::Sast);
        $this->assertSame(StageStatus::Running, $audit->sast_status);
        $this->manager->completeStage($audit, AuditStage::Sast, StageStatus::Completed);

        $this->manager->startStage($audit, AuditStage::Sca);
        $this->manager->completeStage($audit, AuditStage::Sca, StageStatus::Completed);

        $this->manager->finalize($audit);

        $this->assertSame(AuditStatus::Completed, $audit->status);
        $this->assertNotNull($audit->finished_at);
        $this->assertSame(AuditStatus::Completed, $audit->fresh()->status);
    }

    /**
     * @return array<string, array{StageStatus, StageStatus, AuditStatus}>
     */
    public static function finalStatusCases(): array
    {
        return [
            'keduanya completed' => [StageStatus::Completed, StageStatus::Completed, AuditStatus::Completed],
            'keduanya skipped' => [StageStatus::Skipped, StageStatus::Skipped, AuditStatus::Completed],
            'SAST completed, SCA skipped' => [StageStatus::Completed, StageStatus::Skipped, AuditStatus::Completed],
            'SAST completed, SCA failed' => [StageStatus::Completed, StageStatus::Failed, AuditStatus::Partial],
            'SAST partial, SCA completed' => [StageStatus::Partial, StageStatus::Completed, AuditStatus::Partial],
            'SAST failed, SCA completed' => [StageStatus::Failed, StageStatus::Completed, AuditStatus::Partial],
            'SAST failed, SCA partial' => [StageStatus::Failed, StageStatus::Partial, AuditStatus::Partial],
            'keduanya failed' => [StageStatus::Failed, StageStatus::Failed, AuditStatus::Failed],
            'SAST failed, SCA skipped' => [StageStatus::Failed, StageStatus::Skipped, AuditStatus::Failed],
            'SAST skipped, SCA failed' => [StageStatus::Skipped, StageStatus::Failed, AuditStatus::Failed],
        ];
    }

    #[DataProvider('finalStatusCases')]
    public function test_final_status_follows_flows_rules(StageStatus $sast, StageStatus $sca, AuditStatus $expected): void
    {
        $audit = $this->scanningAudit();
        $this->manager->completeStage($audit, AuditStage::Sast, $sast);
        $this->manager->completeStage($audit, AuditStage::Sca, $sca);

        $this->manager->finalize($audit);

        $this->assertSame($expected, $audit->status);
    }

    public function test_stage_error_is_stored_and_truncated_to_column_length(): void
    {
        $audit = $this->scanningAudit();
        $this->manager->startStage($audit, AuditStage::Sca);

        $this->manager->completeStage($audit, AuditStage::Sca, StageStatus::Failed, str_repeat('é', 600));

        $this->assertSame(StageStatus::Failed, $audit->sca_status);
        $this->assertSame(500, mb_strlen($audit->sca_error));
        $this->assertNull($audit->sast_error);
    }

    public function test_stage_can_be_skipped_without_running(): void
    {
        $audit = $this->scanningAudit();

        $this->manager->completeStage($audit, AuditStage::Sca, StageStatus::Skipped);

        $this->assertSame(StageStatus::Skipped, $audit->sca_status);
    }

    public function test_mark_failed_during_ingestion_skips_both_stages(): void
    {
        $audit = AuditScan::factory()->create();
        $this->manager->markIngesting($audit);

        $this->manager->markFailed($audit, 'ZIP_SLIP_DETECTED', 'Arsip berisi path yang keluar dari direktori kerja.');

        $this->assertSame(AuditStatus::Failed, $audit->status);
        $this->assertSame('ZIP_SLIP_DETECTED', $audit->error_code);
        $this->assertSame(StageStatus::Skipped, $audit->sast_status);
        $this->assertSame(StageStatus::Skipped, $audit->sca_status);
        $this->assertNotNull($audit->finished_at);
    }

    public function test_mark_failed_during_scanning_keeps_finished_stage_and_fails_running_stage(): void
    {
        $audit = $this->scanningAudit();
        $this->manager->completeStage($audit, AuditStage::Sast, StageStatus::Completed);
        $this->manager->startStage($audit, AuditStage::Sca);

        $this->manager->markFailed($audit, 'JOB_TIMEOUT', 'Pemrosesan audit melebihi batas waktu.');

        $this->assertSame(StageStatus::Completed, $audit->sast_status);
        $this->assertSame(StageStatus::Failed, $audit->sca_status);
    }

    public function test_cannot_skip_lifecycle_steps(): void
    {
        $audit = AuditScan::factory()->create();

        $this->expectException(InvalidAuditTransitionException::class);

        $this->manager->markScanning($audit);
    }

    public function test_cannot_start_stage_before_scanning(): void
    {
        $audit = AuditScan::factory()->create();
        $this->manager->markIngesting($audit);

        $this->expectException(InvalidAuditTransitionException::class);

        $this->manager->startStage($audit, AuditStage::Sast);
    }

    public function test_cannot_complete_stage_with_non_final_status(): void
    {
        $audit = $this->scanningAudit();

        $this->expectException(InvalidAuditTransitionException::class);

        $this->manager->completeStage($audit, AuditStage::Sast, StageStatus::Running);
    }

    public function test_cannot_finalize_while_a_stage_is_unfinished(): void
    {
        $audit = $this->scanningAudit();
        $this->manager->completeStage($audit, AuditStage::Sast, StageStatus::Completed);
        $this->manager->startStage($audit, AuditStage::Sca);

        $this->expectException(InvalidAuditTransitionException::class);

        $this->manager->finalize($audit);
    }

    public function test_finished_audit_cannot_be_marked_failed(): void
    {
        $audit = $this->scanningAudit();
        $this->manager->completeStage($audit, AuditStage::Sast, StageStatus::Completed);
        $this->manager->completeStage($audit, AuditStage::Sca, StageStatus::Completed);
        $this->manager->finalize($audit);

        $this->expectException(InvalidAuditTransitionException::class);

        $this->manager->markFailed($audit, 'LATE_ERROR', 'Tidak boleh mengubah audit yang sudah selesai.');
    }

    public function test_transition_uses_current_database_state_not_stale_model(): void
    {
        $audit = AuditScan::factory()->create();
        $staleCopy = AuditScan::query()->findOrFail($audit->id);

        $this->manager->markIngesting($audit);

        $this->expectException(InvalidAuditTransitionException::class);

        $this->manager->markIngesting($staleCopy);
    }

    private function scanningAudit(): AuditScan
    {
        $audit = AuditScan::factory()->create();
        $this->manager->markIngesting($audit);
        $this->manager->markScanning($audit);

        return $audit;
    }
}
