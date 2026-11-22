<?php

namespace Tests\Feature\Sast;

use App\Enums\CodeLanguage;
use App\Enums\MetricScope;
use App\Enums\ToolRunStatus;
use App\Models\AuditScan;
use App\Models\CodeMetric;
use App\Services\Sast\FileMetrics;
use App\Services\Sast\FunctionMetrics;
use App\Services\Sast\MetricsParser;
use App\Services\Sast\MetricsRunner;
use App\Services\Sast\MetricsStore;
use App\Services\Sast\MetricValues;
use App\Services\Sast\SastToolExecutor;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class MetricsRunnerTest extends TestCase
{
    use RefreshDatabase;

    private string $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = sys_get_temp_dir().'/metrics-fake-'.bin2hex(random_bytes(4)).'.js';
        file_put_contents($this->fake, '');
    }

    protected function tearDown(): void
    {
        @unlink($this->fake);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function runner(array $overrides = []): MetricsRunner
    {
        return new MetricsRunner(array_merge([
            'node_binary' => 'node',
            'script_path' => $this->fake,
            'typescript_path' => $this->fake,
            'timeout_seconds' => 60,
            'max_output_bytes' => 1024 * 1024,
            'max_args_bytes' => 12000,
            'max_file_bytes' => 1048576,
        ], $overrides), new MetricsParser, new SastToolExecutor);
    }

    private function scriptOutput(): string
    {
        return json_encode(['files' => [
            ['path' => 'a.js', 'loc' => 7, 'sloc' => 6, 'cc' => 3, 'n1' => 10, 'n2' => 4, 'N1' => 15, 'N2' => 10, 'functions' => [
                ['name' => 'add', 'line' => 1, 'loc' => 7, 'sloc' => 6, 'cc' => 3, 'n1' => 10, 'n2' => 4, 'N1' => 15, 'N2' => 10],
            ]],
        ], 'errors' => [['path' => 'bad.js', 'reason' => 'parse_error']]]);
    }

    public function test_runs_script_with_array_arguments_and_collects_metrics(): void
    {
        Process::fake(['*' => Process::result($this->scriptOutput())]);

        $result = $this->runner()->run('/srv/ws/repo', ['a.js', 'bad.js']);

        $this->assertSame(ToolRunStatus::Completed, $result->run->status);
        $this->assertTrue($result->run->incomplete, 'file yang gagal di-parse harus menandai hasil tidak lengkap.');
        $this->assertCount(1, $result->files);
        $this->assertSame('a.js', $result->files[0]->path);

        Process::assertRan(function (PendingProcess $process) {
            $command = $process->command;

            return is_array($command)
                && $command[0] === 'node'
                && $command[1] === $this->fake
                && array_slice($command, -3) === ['--', 'a.js', 'bad.js']
                && $command[array_search('--max-bytes', $command, true) + 1] === '1048576'
                && $process->path === '/srv/ws/repo';
        });
    }

    public function test_tooling_not_installed_fails_with_actionable_message(): void
    {
        Process::fake();

        $result = $this->runner(['typescript_path' => '/tidak/ada/typescript.js'])->run('/srv/ws/repo', ['a.js']);

        $this->assertSame(ToolRunStatus::Failed, $result->run->status);
        $this->assertStringContainsString('npm ci', $result->run->message);
        $this->assertSame([], $result->files);
        Process::assertNothingRan();
    }

    public function test_no_files_is_skipped(): void
    {
        Process::fake();

        $this->assertSame(ToolRunStatus::Skipped, $this->runner()->run('/srv/ws/repo', [])->run->status);
        Process::assertNothingRan();
    }

    public function test_crash_and_invalid_json_fail_without_metrics(): void
    {
        Process::fake(['*' => Process::result('', 'RangeError: /home/server/x', 134)]);
        $crash = $this->runner()->run('/srv/ws/repo', ['a.js']);

        $this->assertSame(ToolRunStatus::Failed, $crash->run->status);
        $this->assertStringNotContainsString('/home/server', $crash->run->message);

        Process::fake(['*' => Process::result('{"files": [{"path"')]);
        $truncated = $this->runner()->run('/srv/ws/repo', ['a.js']);

        $this->assertSame(ToolRunStatus::Failed, $truncated->run->status);
        $this->assertSame([], $truncated->files);
    }

    /**
     * Integrasi nyata: butuh Node dan `npm ci` di backend/tooling. Nilai dihitung manual (lihat MetricsTest).
     */
    public function test_real_script_measures_known_fixture(): void
    {
        if (! is_file((string) config('audit.sast.metrics.typescript_path')) || ! str_starts_with(trim((string) @shell_exec('node --version 2>&1')), 'v')) {
            $this->markTestSkipped('Node/typescript platform tidak terpasang (npm ci di backend/tooling); integrasi nyata dilewati.');
        }

        $root = sys_get_temp_dir().'/metrics-real-'.bin2hex(random_bytes(4));
        mkdir($root.'/src', 0755, true);

        try {
            file_put_contents($root.'/src/a.js', "function add(a, b) {\n  // komentar\n  if (a > 0 && b > 0) {\n    return a + b;\n  }\n  return 0;\n}\n");
            file_put_contents($root.'/src/view.tsx', "export const View = ({ items }: { items: string[] }) => <ul>{items.map((i) => <li key={i}>{i ?? 'x'}</li>)}</ul>;\n");
            file_put_contents($root.'/src/bad.js', 'function ( {');
            file_put_contents($root.'/src/empty.js', '');
            file_put_contents($root.'/src/comment.js', "// hanya komentar\n/** doc */\n");

            $result = $this->app->make(MetricsRunner::class)->run($root, ['src/a.js', 'src/view.tsx', 'src/bad.js', 'src/empty.js', 'src/comment.js']);
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }

        $this->assertSame(ToolRunStatus::Completed, $result->run->status, (string) $result->run->message);
        $this->assertTrue($result->run->incomplete, 'src/bad.js gagal di-parse.');

        $byPath = [];
        foreach ($result->files as $file) {
            $byPath[$file->path] = $file;
        }

        $this->assertSame(['src/a.js', 'src/comment.js', 'src/empty.js', 'src/view.tsx'], array_keys($byPath = $this->sorted($byPath)));

        // Nilai manual: lihat komentar di MetricsTest::test_values_for_function_add_from_real_metrics_script_output.
        $a = $byPath['src/a.js']->values;
        $this->assertSame([7, 6, 3, 10, 4, 15, 10], [$a->loc, $a->sloc, $a->cyclomaticComplexity, $a->distinctOperators, $a->distinctOperands, $a->totalOperators, $a->totalOperands]);
        $this->assertSame('add', $byPath['src/a.js']->functions[0]->name);
        $this->assertSame(CodeLanguage::Javascript, $byPath['src/a.js']->language);

        $this->assertSame(CodeLanguage::Typescript, $byPath['src/view.tsx']->language);
        // CC view.tsx = 1 + satu `??` = 2 (arrow function dan JSX bukan titik keputusan); 2 fungsi arrow terdeteksi.
        $this->assertSame(2, $byPath['src/view.tsx']->values->cyclomaticComplexity);
        $this->assertCount(2, $byPath['src/view.tsx']->functions);

        $this->assertSame([0, 0, 1], [$byPath['src/empty.js']->values->loc, $byPath['src/empty.js']->values->sloc, $byPath['src/empty.js']->values->cyclomaticComplexity]);
        $this->assertSame([2, 0], [$byPath['src/comment.js']->values->loc, $byPath['src/comment.js']->values->sloc]);
        $this->assertNull($byPath['src/comment.js']->values->maintainabilityIndex());
    }

    /**
     * @param  array<string, FileMetrics>  $files
     * @return array<string, FileMetrics>
     */
    private function sorted(array $files): array
    {
        ksort($files);

        return $files;
    }

    private function file(string $path, int $cc, array $functionCcs = []): FileMetrics
    {
        return new FileMetrics(
            $path,
            CodeLanguage::Javascript,
            new MetricValues(7, 6, $cc, 10, 4, 15, 10),
            array_map(fn (int $fcc, int $i) => new FunctionMetrics("f{$i}", $i + 1, new MetricValues(3, 3, $fcc, 3, 2, 5, 4)), $functionCcs, array_keys($functionCcs)),
        );
    }

    public function test_store_saves_file_and_function_rows_with_halstead_and_mi(): void
    {
        $audit = AuditScan::factory()->create();

        $count = (new MetricsStore(100))->store($audit, [$this->file('a.js', 3, [2])]);

        $this->assertSame(2, $count);

        $file = CodeMetric::query()->where('audit_scan_id', $audit->id)->where('scope', MetricScope::File->value)->sole();
        $this->assertSame([7, 6, 3], [$file->loc, $file->sloc, $file->cyclomatic_complexity]);
        $this->assertSame('95.18', $file->halstead_volume);
        $this->assertSame('12.50', $file->halstead_difficulty);
        $this->assertSame('1189.80', $file->halstead_effort);
        $this->assertSame('68.77', $file->maintainability_index);
        $this->assertNull($file->function_name);

        $function = CodeMetric::query()->where('audit_scan_id', $audit->id)->where('scope', MetricScope::Function->value)->sole();
        $this->assertSame(['f0', 1, 'a.js', CodeLanguage::Javascript], [$function->function_name, $function->line_start, $function->file_path, $function->language]);
        $this->assertSame('80.08', $function->maintainability_index);
    }

    public function test_store_keeps_null_mi_for_empty_file_and_caps_function_rows_by_complexity(): void
    {
        $audit = AuditScan::factory()->create();
        $empty = new FileMetrics('e.js', CodeLanguage::Javascript, new MetricValues(0, 0, 1, 0, 0, 0, 0), []);

        $this->assertSame(4, (new MetricsStore(2))->store($audit, [$empty, $this->file('a.js', 9, [1, 7, 4])]));

        $this->assertNull(CodeMetric::query()->where('file_path', 'e.js')->sole()->maintainability_index);
        $this->assertSame([7, 4], CodeMetric::query()->where('scope', 'function')->orderByDesc('cyclomatic_complexity')->pluck('cyclomatic_complexity')->all());
    }

    public function test_store_is_idempotent_and_does_not_touch_other_audits(): void
    {
        $audit = AuditScan::factory()->create();
        $other = AuditScan::factory()->create();
        $store = new MetricsStore(100);

        $store->store($other, [$this->file('o.js', 1)]);
        $store->store($audit, [$this->file('a.js', 3, [2]), $this->file('b.js', 1)]);
        $store->store($audit, [$this->file('c.js', 2)]);

        $this->assertSame(['c.js'], CodeMetric::query()->where('audit_scan_id', $audit->id)->pluck('file_path')->all());
        $this->assertSame(1, CodeMetric::query()->where('audit_scan_id', $other->id)->count());
    }

    public function test_store_clamps_values_to_decimal_column_limits(): void
    {
        $audit = AuditScan::factory()->create();
        $huge = new FileMetrics('h.js', CodeLanguage::Javascript, new MetricValues(1, 1, 1, 100000, 1, 4000000000, 4000000000), []);

        (new MetricsStore(100))->store($audit, [$huge]);

        $row = CodeMetric::query()->where('audit_scan_id', $audit->id)->sole();
        $this->assertSame('99999999.99', $row->halstead_difficulty);
        $this->assertSame('999999999999.99', $row->halstead_effort);
    }
}
