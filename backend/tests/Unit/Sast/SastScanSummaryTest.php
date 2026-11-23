<?php

namespace Tests\Unit\Sast;

use App\Enums\AuditStatus;
use App\Enums\FindingTool;
use App\Enums\MetricScope;
use App\Enums\StageStatus;
use App\Enums\ToolRunStatus;
use App\Models\AuditScan;
use App\Models\CodeMetric;
use App\Models\ScanFinding;
use App\Models\ScanToolRun;
use App\Services\Sast\SastScanSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SastScanSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function audit(StageStatus $sast, AuditStatus $status = AuditStatus::Completed): AuditScan
    {
        return AuditScan::factory()->create(['status' => $status, 'sast_status' => $sast, 'sast_error' => 'pesan tahap']);
    }

    private function toolRun(AuditScan $audit, FindingTool $tool, ToolRunStatus $status, ?int $findings): void
    {
        ScanToolRun::factory()->create(['audit_scan_id' => $audit->id, 'tool' => $tool, 'status' => $status, 'findings_count' => $findings, 'files_scanned' => $findings === null ? null : 2]);
    }

    private function summary(): SastScanSummary
    {
        return new SastScanSummary;
    }

    /**
     * @return iterable<string, array{StageStatus, list<array{FindingTool, ToolRunStatus, int|null}>, int}>
     */
    public static function combinations(): iterable
    {
        yield 'completed dua tool dengan temuan' => [StageStatus::Completed, [[FindingTool::Semgrep, ToolRunStatus::Completed, 2], [FindingTool::Eslint, ToolRunStatus::Completed, 1]], 3];
        yield 'completed nol temuan' => [StageStatus::Completed, [[FindingTool::Semgrep, ToolRunStatus::Completed, 0], [FindingTool::Eslint, ToolRunStatus::Completed, 0]], 0];
        yield 'partial eslint gagal' => [StageStatus::Partial, [[FindingTool::Semgrep, ToolRunStatus::Completed, 1], [FindingTool::Eslint, ToolRunStatus::Failed, null]], 1];
        yield 'partial eslint tidak tercatat' => [StageStatus::Partial, [[FindingTool::Semgrep, ToolRunStatus::Completed, 0]], 0];
        yield 'partial tool dilewati' => [StageStatus::Partial, [[FindingTool::Semgrep, ToolRunStatus::Skipped, null], [FindingTool::Eslint, ToolRunStatus::Completed, 4]], 4];
        yield 'failed tanpa tool run' => [StageStatus::Failed, [], 0];
        yield 'skipped tanpa tool run' => [StageStatus::Skipped, [], 0];
    }

    /**
     * @param  list<array{FindingTool, ToolRunStatus, int|null}>  $runs
     */
    #[DataProvider('combinations')]
    public function test_notes_never_use_clean_or_safe_wording(StageStatus $sast, array $runs, int $stored): void
    {
        $audit = $this->audit($sast);
        foreach ($runs as [$tool, $status, $count]) {
            $this->toolRun($audit, $tool, $status, $count);
        }
        ScanFinding::factory()->count($stored)->create(['audit_scan_id' => $audit->id]);

        foreach ([$this->summary()->forFindings($audit), $this->summary()->forMetrics($audit, MetricScope::File), $this->summary()->forMetrics($audit, MetricScope::Function)] as $scan) {
            $this->assertDoesNotMatchRegularExpression('/aman|bersih/i', (string) $scan['note']);
        }
    }

    public function test_tools_are_listed_in_tool_order_with_null_counts_unless_completed(): void
    {
        $audit = $this->audit(StageStatus::Partial);
        $this->toolRun($audit, FindingTool::Semgrep, ToolRunStatus::Completed, 2);
        ScanToolRun::factory()->create(['audit_scan_id' => $audit->id, 'tool' => FindingTool::Eslint, 'status' => ToolRunStatus::Skipped, 'findings_count' => 5, 'files_scanned' => 5, 'error_message' => 'dilewati']);

        $scan = $this->summary()->forFindings($audit);

        $this->assertSame([
            ['tool' => 'eslint', 'status' => 'skipped', 'files_scanned' => null, 'findings_count' => null, 'error_message' => 'dilewati'],
            ['tool' => 'semgrep', 'status' => 'completed', 'files_scanned' => 2, 'findings_count' => 2, 'error_message' => null],
        ], $scan['tools']);
    }

    public function test_failed_and_skipped_notes_do_not_depend_on_tool_runs(): void
    {
        $failed = $this->summary()->forFindings($this->audit(StageStatus::Failed));
        $skipped = $this->summary()->forFindings($this->audit(StageStatus::Skipped));

        $this->assertSame([], $failed['tools']);
        $this->assertSame('Analisis SAST gagal; tidak ada hasil yang dapat ditampilkan. Ini bukan berarti kode bebas masalah. Lihat message.', $failed['note']);
        $this->assertSame('Tidak ada file JavaScript/TypeScript yang dianalisis; tidak ada hasil SAST.', $skipped['note']);
        $this->assertSame('pesan tahap', $failed['message']);
    }

    public function test_partial_note_names_unfinished_tools_and_points_to_the_message(): void
    {
        $audit = $this->audit(StageStatus::Partial);
        $this->toolRun($audit, FindingTool::Semgrep, ToolRunStatus::Completed, 1);
        ScanFinding::factory()->create(['audit_scan_id' => $audit->id]);

        $note = $this->summary()->forFindings($audit)['note'];

        $this->assertSame('Semgrep: 1 temuan. ESLint: tidak selesai. Pemeriksaan tidak lengkap; lihat message.', $note);
    }

    public function test_completed_note_with_findings_has_no_guarantee_sentence_and_zero_findings_has_one(): void
    {
        $withFindings = $this->audit(StageStatus::Completed);
        $this->toolRun($withFindings, FindingTool::Semgrep, ToolRunStatus::Completed, 1);
        $this->toolRun($withFindings, FindingTool::Eslint, ToolRunStatus::Completed, 0);
        ScanFinding::factory()->create(['audit_scan_id' => $withFindings->id]);

        $empty = $this->audit(StageStatus::Completed);
        $this->toolRun($empty, FindingTool::Semgrep, ToolRunStatus::Completed, 0);
        $this->toolRun($empty, FindingTool::Eslint, ToolRunStatus::Completed, 0);

        $this->assertSame('Semgrep: 1 temuan. ESLint: 0 temuan.', $this->summary()->forFindings($withFindings)['note']);
        $this->assertSame('Semgrep: 0 temuan. ESLint: 0 temuan. Tidak ada temuan tersimpan; ini bukan jaminan bebas kerentanan.', $this->summary()->forFindings($empty)['note']);
    }

    public function test_unfinished_stage_on_an_ended_audit_has_no_note(): void
    {
        foreach ([StageStatus::Pending, StageStatus::Running] as $sast) {
            $scan = $this->summary()->forFindings($this->audit($sast, AuditStatus::Failed));

            $this->assertNull($scan['note']);
            $this->assertSame('Pemeriksaan SAST tidak selesai karena pemrosesan audit berhenti sebelum tahap ini selesai.', $scan['message']);
        }
    }

    public function test_metrics_notes(): void
    {
        $audit = $this->audit(StageStatus::Completed);

        $this->assertSame('Tidak ada metrik kode yang tersimpan; ini bukan indikasi kompleksitas rendah. Lihat message.', $this->summary()->forMetrics($audit, MetricScope::File)['note']);

        CodeMetric::factory()->create(['audit_scan_id' => $audit->id]);

        $this->assertNull($this->summary()->forMetrics($audit, MetricScope::File)['note']);
        $this->assertSame('Hanya fungsi dengan kompleksitas tertinggi (hingga batas penyimpanan) yang disimpan.', $this->summary()->forMetrics($audit, MetricScope::Function)['note']);
    }
}
