<?php

namespace Tests\Feature\Sast;

use App\Enums\AuditStage;
use App\Enums\Severity;
use App\Enums\StageStatus;
use App\Enums\ToolRunStatus;
use App\Models\AuditScan;
use App\Models\CodeMetric;
use App\Models\ScanFinding;
use App\Models\ScanToolRun;
use App\Services\Audit\Stages\AuditStageRunners;
use App\Services\Audit\Stages\StageResult;
use App\Services\Ingestion\IngestedRepository;
use App\Services\Sast\SastStageRunner;
use App\Services\Sast\SemgrepRunner;
use App\Services\Sast\ToolRunResult;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SastStageRunnerTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/sast-stage-'.bin2hex(random_bytes(4));
        $this->root = $this->base.'/repo';
        mkdir($this->root.'/src', 0755, true);

        // Skrip palsu agar test tidak bergantung pada `npm ci`; nama berkas dipakai sebagai pola Process::fake.
        file_put_contents($this->base.'/eslint-fake.js', '');
        file_put_contents($this->base.'/metrics-fake.js', '');
        config([
            'audit.sast.eslint.script_path' => $this->base.'/eslint-fake.js',
            'audit.sast.metrics.script_path' => $this->base.'/metrics-fake.js',
            'audit.sast.metrics.typescript_path' => $this->base.'/metrics-fake.js',
        ]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->base);

        parent::tearDown();
    }

    private function runner(): SastStageRunner
    {
        return $this->app->make(SastStageRunner::class);
    }

    private function runStage(?AuditScan $audit = null): StageResult
    {
        return $this->runner()->run($audit ?? $this->audit, new IngestedRepository($this->root, 100, null));
    }

    private AuditScan $audit;

    protected function afterRefreshingDatabase(): void
    {
        $this->audit = AuditScan::factory()->create(['health_score' => 77, 'health_category' => 'fair']);
    }

    private function jsFile(): void
    {
        file_put_contents($this->root.'/src/a.js', "function add(a, b) {\n  const x = eval(a);\n  debugger;\n  return a + b;\n}\n");
    }

    /**
     * @param  array<string, mixed>|null  $semgrep
     * @param  array<string, mixed>|null  $eslint
     * @param  array<string, mixed>|null  $metrics
     */
    private function fakeTools(?array $semgrep = null, ?array $eslint = null, ?array $metrics = null): void
    {
        $semgrepOk = json_encode(['results' => [[
            'check_id' => 'tooling.semgrep.js-eval-injection', 'path' => 'src/a.js',
            'start' => ['line' => 2, 'col' => 13], 'end' => ['line' => 2, 'col' => 20],
            'extra' => ['message' => 'eval berbahaya', 'severity' => 'ERROR', 'metadata' => ['cwe' => ['CWE-95: Eval Injection']], 'lines' => 'requires login'],
        ]], 'errors' => []]);

        $eslintOk = json_encode([['filePath' => $this->root.'/src/a.js', 'messages' => [
            ['ruleId' => 'no-eval', 'severity' => 2, 'message' => '`eval` can be harmful.', 'line' => 2, 'column' => 13, 'endLine' => 2],
            ['ruleId' => 'no-debugger', 'severity' => 1, 'message' => 'Unexpected debugger statement.', 'line' => 3, 'column' => 7, 'endLine' => 3],
        ]]]);

        $metricsOk = json_encode(['files' => [
            ['path' => 'src/a.js', 'loc' => 7, 'sloc' => 6, 'cc' => 3, 'n1' => 10, 'n2' => 4, 'N1' => 15, 'N2' => 10, 'functions' => [
                ['name' => 'add', 'line' => 1, 'loc' => 7, 'sloc' => 6, 'cc' => 3, 'n1' => 10, 'n2' => 4, 'N1' => 15, 'N2' => 10],
            ]],
        ], 'errors' => []]);

        Process::fake([
            '*eslint-fake*' => Process::result(...($eslint ?? ['output' => $eslintOk])),
            '*metrics-fake*' => Process::result(...($metrics ?? ['output' => $metricsOk])),
            '*' => Process::result(...($semgrep ?? ['output' => $semgrepOk])),
        ]);
    }

    public function test_is_registered_as_the_sast_engine(): void
    {
        $this->assertInstanceOf(SastStageRunner::class, $this->app->make(AuditStageRunners::class)->for(AuditStage::Sast));
        $this->assertSame(AuditStage::Sast, $this->runner()->stage());
    }

    public function test_repository_without_js_ts_files_is_skipped_and_clears_health(): void
    {
        file_put_contents($this->root.'/README.md', 'x');
        Process::fake();

        $result = $this->runStage();

        $this->assertSame(StageStatus::Skipped, $result->status);
        $this->audit->refresh();
        $this->assertNull($this->audit->health_score);
        $this->assertNull($this->audit->health_category);
        Process::assertNothingRan();
    }

    public function test_all_tools_complete_stores_everything_and_scores(): void
    {
        $this->jsFile();
        $this->fakeTools();

        $result = $this->runStage();

        // Temuan: Semgrep eval (high, CWE-95) menggantikan ESLint no-eval (CWE-95, lokasi sama); no-debugger warn → low.
        // Penalti temuan = 8 + 1 = 9. MI file a.js = 68,768 → 0,3 × 31,232 = 9,3696; CC rata-rata 3 ≤ 5 → 0.
        // Skor = 100 − 9 − 9,3696 = 81,63 → 82 → good.
        $this->assertSame(StageStatus::Completed, $result->status);
        $this->assertStringContainsString('2 temuan (Semgrep 1, ESLint 1)', $result->message);
        $this->assertStringContainsString('Skor kesehatan 82', $result->message);

        $this->audit->refresh();
        $this->assertSame(82, $this->audit->health_score);
        $this->assertSame('good', $this->audit->health_category);

        $this->assertSame(['js-eval-injection', 'no-debugger'], ScanFinding::query()->where('audit_scan_id', $this->audit->id)->orderBy('id')->pluck('rule_id')->all());
        $this->assertStringContainsString('eval(a)', (string) ScanFinding::query()->where('rule_id', 'js-eval-injection')->value('code_snippet'));
        $this->assertSame(2, CodeMetric::query()->where('audit_scan_id', $this->audit->id)->count());
        $this->assertSame(2, ScanToolRun::query()->where('audit_scan_id', $this->audit->id)->where('status', ToolRunStatus::Completed->value)->count());

        Process::assertRan(fn (PendingProcess $p) => is_array($p->command) && $p->command[0] === 'semgrep');
    }

    public function test_one_finding_tool_failing_is_partial_but_still_scored(): void
    {
        $this->jsFile();
        $this->fakeTools(semgrep: ['output' => '', 'errorOutput' => 'boom', 'exitCode' => 2]);

        $result = $this->runStage();

        $this->assertSame(StageStatus::Partial, $result->status);
        $this->assertStringContainsString('Semgrep gagal', $result->message);
        $this->audit->refresh();
        $this->assertNotNull($this->audit->health_score);

        $semgrep = ScanToolRun::query()->where('audit_scan_id', $this->audit->id)->where('tool', 'semgrep')->sole();
        $this->assertSame(ToolRunStatus::Failed, $semgrep->status);
        $this->assertNull($semgrep->findings_count, 'tool gagal tidak boleh tampak "0 temuan".');
        $this->assertSame(['no-eval' => Severity::High, 'no-debugger' => Severity::Low], ScanFinding::query()->where('audit_scan_id', $this->audit->id)->orderBy('id')->get()->pluck('severity', 'rule_id')->all());
    }

    public function test_all_finding_tools_failing_is_failed_with_no_score_and_no_findings(): void
    {
        $this->jsFile();
        $this->fakeTools(semgrep: ['output' => '', 'exitCode' => 2], eslint: ['output' => '', 'exitCode' => 2]);

        $result = $this->runStage();

        $this->assertSame(StageStatus::Failed, $result->status);
        $this->audit->refresh();
        $this->assertNull($this->audit->health_score);
        $this->assertNull($this->audit->health_category);
        $this->assertSame(0, ScanFinding::query()->where('audit_scan_id', $this->audit->id)->count());
    }

    public function test_metrics_failing_is_partial_and_score_has_no_metric_component(): void
    {
        $this->jsFile();
        $this->fakeTools(metrics: ['output' => '', 'exitCode' => 134]);

        $result = $this->runStage();

        $this->assertSame(StageStatus::Partial, $result->status);
        $this->assertStringContainsString('Metrik kode tidak tersedia', $result->message);
        $this->assertSame(91, $this->audit->refresh()->health_score); // 100 − (8 + 1)
        $this->assertSame(0, CodeMetric::query()->where('audit_scan_id', $this->audit->id)->count());
    }

    public function test_incomplete_analysis_is_partial(): void
    {
        $this->jsFile();
        $this->fakeTools(semgrep: ['output' => json_encode(['results' => [], 'errors' => [['type' => 'PartialParsing']]])]);

        $result = $this->runStage();

        $this->assertSame(StageStatus::Partial, $result->status);
        $this->assertStringContainsString('1 kesalahan analisis', $result->message);
    }

    public function test_skipped_oversized_files_make_the_stage_partial(): void
    {
        $this->jsFile();
        file_put_contents($this->root.'/src/big.js', str_repeat('a', (int) config('audit.sast.max_file_bytes') + 1));
        $this->fakeTools();

        $result = $this->runStage();

        $this->assertSame(StageStatus::Partial, $result->status);
        $this->assertStringContainsString('1 file dilewati', $result->message);
    }

    public function test_running_twice_is_idempotent(): void
    {
        $this->jsFile();
        $this->fakeTools();

        $this->runStage();
        $this->runStage();

        $this->assertSame(2, ScanFinding::query()->where('audit_scan_id', $this->audit->id)->count());
        $this->assertSame(2, CodeMetric::query()->where('audit_scan_id', $this->audit->id)->count());
        $this->assertSame(2, ScanToolRun::query()->where('audit_scan_id', $this->audit->id)->count());
    }

    /**
     * Uji ujung ke ujung lewat runner SAST yang terdaftar di container: repo contoh kecil dengan ESLint dan metrik NYATA.
     * Semgrep memakai pengganti karena binary tidak selalu ada. Dilewati bila Node/tooling platform tidak terpasang.
     */
    public function test_end_to_end_with_real_eslint_and_metrics_and_stubbed_semgrep(): void
    {
        $realEslint = base_path('tooling/node_modules/eslint/bin/eslint.js');
        $realTypescript = base_path('tooling/node_modules/typescript/lib/typescript.js');

        if (! is_file($realEslint) || ! is_file($realTypescript) || ! str_starts_with(trim((string) @shell_exec('node --version 2>&1')), 'v')) {
            $this->markTestSkipped('Node/tooling platform tidak terpasang (npm ci di backend/tooling); uji ujung ke ujung dilewati.');
        }

        config([
            'audit.sast.eslint.script_path' => $realEslint,
            'audit.sast.metrics.script_path' => base_path('tooling/metrics.mjs'),
            'audit.sast.metrics.typescript_path' => $realTypescript,
        ]);

        $this->app->instance(SemgrepRunner::class, new class extends SemgrepRunner
        {
            public function __construct() {}

            public function run(string $root, array $files): ToolRunResult
            {
                return ToolRunResult::completed([], count($files), false, null, 1);
            }
        });
        $this->app->forgetInstance(AuditStageRunners::class);

        $this->jsFile();
        file_put_contents($this->root.'/src/view.tsx', "export const View = ({ items }: { items: string[] }) => <ul>{items.map((i) => <li key={i}>{i}</li>)}</ul>;\n");
        file_put_contents($this->root.'/src/bad.js', 'function ( {');
        file_put_contents($this->root.'/eslint.config.js', 'throw new Error("config repo tidak boleh dimuat");');

        $result = $this->app->make(AuditStageRunners::class)->for(AuditStage::Sast)
            ->run($this->audit, new IngestedRepository($this->root, 100, null));

        $this->assertSame(StageStatus::Partial, $result->status, 'src/bad.js gagal di-parse → tidak lengkap. '.$result->message);

        $rules = ScanFinding::query()->where('audit_scan_id', $this->audit->id)->pluck('rule_id')->all();
        $this->assertContains('no-eval', $rules);
        $this->assertContains('no-debugger', $rules);

        // bad.js gagal di-parse (tidak punya metrik); eslint.config.js milik repo hanya dianalisis sebagai kode biasa, tidak dimuat.
        $this->assertSame(
            ['eslint.config.js', 'src/a.js', 'src/view.tsx'],
            CodeMetric::query()->where('audit_scan_id', $this->audit->id)->where('scope', 'file')->orderBy('file_path')->pluck('file_path')->all(),
        );

        $this->audit->refresh();
        $this->assertNotNull($this->audit->health_score);
        $this->assertContains($this->audit->health_category, ['good', 'fair', 'poor']);
    }
}
