<?php

namespace Tests\Feature\Sast;

use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\Severity;
use App\Enums\ToolRunStatus;
use App\Models\AuditScan;
use App\Models\ScanFinding;
use App\Models\ScanToolRun;
use App\Services\Sast\SastFinding;
use App\Services\Sast\SastResultStore;
use App\Services\Sast\ToolRunResult;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SastResultStoreTest extends TestCase
{
    use RefreshDatabase;

    private function finding(string $rule, FindingTool $tool = FindingTool::Semgrep, string $path = 'src/a.js'): SastFinding
    {
        return new SastFinding($tool, $rule, FindingCategory::Security, Severity::High, $path, 3, 4, 5, "pesan {$rule}", "kode\nsnippet", 'CWE-95');
    }

    private function store(AuditScan $audit, array $toolRuns, array $findings): int
    {
        return $this->app->make(SastResultStore::class)->store($audit, $toolRuns, $findings);
    }

    public function test_stores_findings_and_tool_runs_with_all_columns(): void
    {
        $audit = AuditScan::factory()->create();
        $finding = $this->finding('js-eval');

        $count = $this->store($audit, [
            'semgrep' => ToolRunResult::completed([$finding], 7, true, 'Sebagian file tidak diperiksa.', 1200),
            'eslint' => ToolRunResult::failed('ESLint gagal: waktu habis.', 300),
        ], [$finding]);

        $this->assertSame(1, $count);

        $row = ScanFinding::query()->where('audit_scan_id', $audit->id)->sole();
        $this->assertSame(FindingTool::Semgrep, $row->tool);
        $this->assertSame('js-eval', $row->rule_id);
        $this->assertSame(Severity::High, $row->severity);
        $this->assertSame(FindingCategory::Security, $row->category);
        $this->assertSame('src/a.js', $row->file_path);
        $this->assertSame([3, 4, 5], [$row->line_start, $row->line_end, $row->column_start]);
        $this->assertSame("kode\nsnippet", $row->code_snippet);
        $this->assertSame('CWE-95', $row->cwe);
        $this->assertSame($finding->fingerprint(), $row->fingerprint);
        $this->assertSame(64, strlen($row->fingerprint));

        $semgrep = ScanToolRun::query()->where('audit_scan_id', $audit->id)->where('tool', 'semgrep')->sole();
        $this->assertSame(ToolRunStatus::Completed, $semgrep->status);
        $this->assertSame([7, 1], [$semgrep->files_scanned, $semgrep->findings_count]);
        $this->assertSame('Sebagian file tidak diperiksa.', $semgrep->error_message);
        $this->assertSame(1200, $semgrep->duration_ms);
        $this->assertNotNull($semgrep->started_at);

        $eslint = ScanToolRun::query()->where('audit_scan_id', $audit->id)->where('tool', 'eslint')->sole();
        $this->assertSame(ToolRunStatus::Failed, $eslint->status);
        $this->assertNull($eslint->files_scanned);
        $this->assertNull($eslint->findings_count, 'tool gagal tidak boleh tampak "0 temuan".');
        $this->assertSame('ESLint gagal: waktu habis.', $eslint->error_message);
    }

    public function test_skipped_tool_has_no_timing_or_counts(): void
    {
        $audit = AuditScan::factory()->create();

        $this->store($audit, ['semgrep' => ToolRunResult::skipped('Tidak ada file.')], []);

        $run = ScanToolRun::query()->where('audit_scan_id', $audit->id)->sole();
        $this->assertSame(ToolRunStatus::Skipped, $run->status);
        $this->assertNull($run->duration_ms);
        $this->assertNull($run->started_at);
        $this->assertNull($run->findings_count);
    }

    public function test_completed_tool_with_zero_findings_stores_zero(): void
    {
        $audit = AuditScan::factory()->create();

        $this->store($audit, ['eslint' => ToolRunResult::completed([], 3, false, null, 10)], []);

        $run = ScanToolRun::query()->where('audit_scan_id', $audit->id)->sole();
        $this->assertSame(0, $run->findings_count);
        $this->assertNull($run->error_message);
    }

    public function test_storing_again_replaces_previous_data_and_is_idempotent(): void
    {
        $audit = AuditScan::factory()->create();
        $other = AuditScan::factory()->create();
        ScanFinding::factory()->create(['audit_scan_id' => $other->id]);

        $runs = ['semgrep' => ToolRunResult::completed([], 1, false, null, 5)];
        $this->store($audit, $runs, [$this->finding('one'), $this->finding('two')]);
        $this->store($audit, $runs, [$this->finding('one'), $this->finding('two')]);
        $this->store($audit, $runs, [$this->finding('three')]);

        $this->assertSame(['three'], ScanFinding::query()->where('audit_scan_id', $audit->id)->pluck('rule_id')->all());
        $this->assertSame(1, ScanToolRun::query()->where('audit_scan_id', $audit->id)->count());
        $this->assertSame(1, ScanFinding::query()->where('audit_scan_id', $other->id)->count(), 'audit lain tidak boleh tersentuh.');
    }

    public function test_failure_rolls_back_and_keeps_previous_data(): void
    {
        $audit = AuditScan::factory()->create();
        $this->store($audit, [], [$this->finding('old')]);

        try {
            // Fingerprint ganda melanggar UNIQUE → seluruh transaksi dibatalkan.
            $this->store($audit, [], [$this->finding('dup'), $this->finding('dup')]);
            $this->fail('Pelanggaran UNIQUE seharusnya melempar exception.');
        } catch (QueryException) {
            // diharapkan
        }

        $this->assertSame(['old'], ScanFinding::query()->where('audit_scan_id', $audit->id)->pluck('rule_id')->all());
    }

    public function test_inserts_more_than_one_chunk(): void
    {
        $audit = AuditScan::factory()->create();
        $findings = array_map(fn (int $i) => $this->finding('rule-'.$i), range(1, 1100));

        $this->assertSame(1100, $this->store($audit, [], $findings));
        $this->assertSame(1100, ScanFinding::query()->where('audit_scan_id', $audit->id)->count());
    }
}
