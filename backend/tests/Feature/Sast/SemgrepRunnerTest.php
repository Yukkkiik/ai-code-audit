<?php

namespace Tests\Feature\Sast;

use App\Enums\ToolRunStatus;
use App\Services\Sast\SastToolExecutor;
use App\Services\Sast\SemgrepParser;
use App\Services\Sast\SemgrepRunner;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimedOut;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class SemgrepRunnerTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function runner(array $overrides = []): SemgrepRunner
    {
        return new SemgrepRunner(array_merge([
            'binary' => 'semgrep',
            'rules_path' => '/platform/rules',
            'timeout_seconds' => 60,
            'per_file_timeout_seconds' => 10,
            'max_output_bytes' => 1024 * 1024,
            'max_args_bytes' => 12000,
        ], $overrides), new SemgrepParser, new SastToolExecutor);
    }

    private function fixture(): string
    {
        return file_get_contents(__DIR__.'/../../Fixtures/sast/semgrep-output.json');
    }

    public function test_runs_with_hardened_array_arguments_and_clean_environment(): void
    {
        Process::fake(['*' => Process::result($this->fixture())]);
        putenv('APP_SECRET_FOR_TEST=rahasia');

        $result = $this->runner()->run('/tmp/ws', ['src/app.js', '-weird name.ts']);

        putenv('APP_SECRET_FOR_TEST');

        $this->assertSame(ToolRunStatus::Completed, $result->status);
        $this->assertSame(2, $result->filesScanned);
        $this->assertCount(3, $result->findings);
        $this->assertTrue($result->incomplete, 'errors dari Semgrep harus menandai hasil tidak lengkap.');
        $this->assertStringContainsString('1 kesalahan analisis', $result->message);

        Process::assertRan(function (PendingProcess $process) {
            $command = $process->command;

            return is_array($command)
                && $command[0] === 'semgrep'
                && $process->path === '/tmp/ws'
                && in_array('--metrics=off', $command, true)
                && in_array('--disable-nosem', $command, true)
                && in_array('--json', $command, true)
                && $command[array_search('--config', $command, true) + 1] === '/platform/rules'
                && array_slice($command, -3) === ['--', 'src/app.js', '-weird name.ts']
                && ($process->environment['APP_SECRET_FOR_TEST'] ?? null) === false
                && $process->environment['SEMGREP_SEND_METRICS'] === 'off';
        });
    }

    public function test_zero_findings_is_completed_and_complete(): void
    {
        Process::fake(['*' => Process::result('{"results": [], "errors": []}')]);

        $result = $this->runner()->run('/tmp/ws', ['a.js']);

        $this->assertSame(ToolRunStatus::Completed, $result->status);
        $this->assertSame([], $result->findings);
        $this->assertFalse($result->incomplete);
        $this->assertNull($result->message);
    }

    public function test_no_files_is_skipped_without_running_process(): void
    {
        Process::fake();

        $result = $this->runner()->run('/tmp/ws', []);

        $this->assertSame(ToolRunStatus::Skipped, $result->status);
        Process::assertNothingRan();
    }

    public function test_files_are_split_into_batches_by_argument_size(): void
    {
        Process::fake(['*' => Process::result('{"results": [], "errors": []}')]);
        $files = array_map(fn (int $i) => 'dir/file'.$i.'.js', range(1, 10));

        $result = $this->runner(['max_args_bytes' => 40])->run('/tmp/ws', $files);

        $this->assertSame(10, $result->filesScanned);
        // 'dir/fileN.js' = 12 byte + 1; tiga file per batch (39 <= 40) → 4 batch untuk 10 file (file ke-10 = 13 byte).
        Process::assertRanTimes(fn () => true, 4);
    }

    public function test_partial_batch_failure_keeps_findings_and_marks_incomplete(): void
    {
        Process::fake(['*' => Process::sequence()
            ->push(Process::result($this->fixture()))
            ->push(Process::result('', 'boom', 2)),
        ]);

        $result = $this->runner(['max_args_bytes' => 8])->run('/tmp/ws', ['a.js', 'b.js']);

        $this->assertSame(ToolRunStatus::Completed, $result->status);
        $this->assertSame(1, $result->filesScanned);
        $this->assertTrue($result->incomplete);
        $this->assertStringContainsString('kode 2', $result->message);
    }

    public function test_non_zero_exit_code_fails_without_leaking_stderr(): void
    {
        Process::fake(['*' => Process::result('', 'Traceback /home/server/secret/path', 2)]);

        $result = $this->runner()->run('/tmp/ws', ['a.js']);

        $this->assertSame(ToolRunStatus::Failed, $result->status);
        $this->assertSame([], $result->findings);
        $this->assertStringNotContainsString('/home/server', $result->message);
        $this->assertStringContainsString('kode 2', $result->message);
    }

    public function test_truncated_json_fails_the_tool(): void
    {
        Process::fake(['*' => Process::result('{"results": [{"check_id": "a"')]);

        $result = $this->runner()->run('/tmp/ws', ['a.js']);

        $this->assertSame(ToolRunStatus::Failed, $result->status);
        $this->assertStringContainsString('bukan JSON yang valid', $result->message);
    }

    public function test_oversized_output_fails_the_tool(): void
    {
        Process::fake(['*' => Process::result($this->fixture())]);

        $result = $this->runner(['max_output_bytes' => 100])->run('/tmp/ws', ['a.js']);

        $this->assertSame(ToolRunStatus::Failed, $result->status);
        $this->assertStringContainsString('melebihi batas', $result->message);
    }

    public function test_timeout_fails_the_tool(): void
    {
        Process::fake(['*' => fn () => throw new ProcessTimedOutException(
            new SymfonyTimedOut(new SymfonyProcess(['semgrep']), SymfonyTimedOut::TYPE_GENERAL),
            Process::result(''),
        )]);

        $result = $this->runner()->run('/tmp/ws', ['a.js']);

        $this->assertSame(ToolRunStatus::Failed, $result->status);
        $this->assertStringContainsString('waktu habis', $result->message);
    }

    public function test_missing_binary_fails_and_stops_after_first_batch(): void
    {
        Process::fake(['*' => Process::result('', 'not found', 127)]);

        $result = $this->runner(['max_args_bytes' => 8])->run('/tmp/ws', ['a.js', 'b.js', 'c.js']);

        $this->assertSame(ToolRunStatus::Failed, $result->status);
        $this->assertStringContainsString('binary tidak ditemukan', $result->message);
        Process::assertRanTimes(fn () => true, 1);
    }

    public function test_process_runtime_exception_is_treated_as_missing_binary(): void
    {
        Process::fake(['*' => fn () => throw new RuntimeException('proc_open failed')]);

        $result = $this->runner()->run('/tmp/ws', ['a.js']);

        $this->assertSame(ToolRunStatus::Failed, $result->status);
        $this->assertStringContainsString('binary tidak ditemukan', $result->message);
    }

    /**
     * Opsional: butuh binary Semgrep nyata. Memvalidasi sintaks rule platform.
     */
    public function test_platform_rules_are_valid_for_real_semgrep(): void
    {
        $binary = (string) config('audit.sast.semgrep.binary');
        $probe = @shell_exec(escapeshellarg($binary).' --version 2>&1');

        if ($probe === null || preg_match('/^\d+\.\d+/', trim($probe)) !== 1) {
            $this->markTestSkipped('Binary Semgrep tidak tersedia; validasi rule nyata dilewati.');
        }

        $result = Process::timeout(120)->run([$binary, 'scan', '--validate', '--config', config('audit.sast.semgrep.rules_path'), '--metrics=off']);

        $this->assertTrue($result->successful(), $result->errorOutput());
    }
}
