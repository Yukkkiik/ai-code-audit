<?php

namespace Tests\Feature\Sast;

use App\Enums\ToolRunStatus;
use App\Services\Sast\EslintParser;
use App\Services\Sast\EslintRunner;
use App\Services\Sast\SastToolExecutor;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class EslintRunnerTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Skrip palsu agar test tidak bergantung pada `npm ci`.
        $this->scriptPath = sys_get_temp_dir().'/eslint-fake-'.bin2hex(random_bytes(4)).'.js';
        file_put_contents($this->scriptPath, '');
    }

    protected function tearDown(): void
    {
        @unlink($this->scriptPath);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function runner(array $overrides = []): EslintRunner
    {
        return new EslintRunner(array_merge([
            'node_binary' => 'node',
            'script_path' => $this->scriptPath,
            'config_path' => '/platform/eslint.config.mjs',
            'timeout_seconds' => 60,
            'max_output_bytes' => 1024 * 1024,
            'max_args_bytes' => 12000,
        ], $overrides), new EslintParser, new SastToolExecutor);
    }

    private function fixture(): string
    {
        return file_get_contents(__DIR__.'/../../Fixtures/sast/eslint-real-output.json');
    }

    public function test_runs_platform_config_only_with_hardened_array_arguments(): void
    {
        Process::fake(['*' => Process::result($this->fixture(), '', 1)]);

        $result = $this->runner()->run('/srv/ws/repo', ['src/a.js', '-weird.ts']);

        $this->assertSame(ToolRunStatus::Completed, $result->status);
        $this->assertSame(2, $result->filesScanned);
        $this->assertCount(8, $result->findings);
        $this->assertTrue($result->incomplete, 'file yang gagal di-parse harus menandai hasil tidak lengkap.');
        $this->assertStringContainsString('1 kesalahan analisis', $result->message);

        Process::assertRan(function (PendingProcess $process) {
            $command = $process->command;

            return is_array($command)
                && $command[0] === 'node'
                && $command[1] === $this->scriptPath
                && $command[array_search('--config', $command, true) + 1] === '/platform/eslint.config.mjs'
                && in_array('--no-config-lookup', $command, true)
                && in_array('--no-inline-config', $command, true)
                && ! in_array('--fix', $command, true)
                && array_slice($command, -3) === ['--', 'src/a.js', '-weird.ts']
                && $process->path === '/srv/ws/repo';
        });
    }

    public function test_zero_findings_is_completed_and_complete(): void
    {
        Process::fake(['*' => Process::result('[{"filePath": "/srv/ws/repo/a.js", "messages": []}]')]);

        $result = $this->runner()->run('/srv/ws/repo', ['a.js']);

        $this->assertSame(ToolRunStatus::Completed, $result->status);
        $this->assertSame([], $result->findings);
        $this->assertFalse($result->incomplete);
    }

    public function test_no_files_is_skipped_without_running_process(): void
    {
        Process::fake();

        $this->assertSame(ToolRunStatus::Skipped, $this->runner()->run('/srv/ws/repo', [])->status);
        Process::assertNothingRan();
    }

    public function test_eslint_not_installed_fails_with_actionable_message(): void
    {
        Process::fake();

        $result = $this->runner(['script_path' => '/tidak/ada/eslint.js'])->run('/srv/ws/repo', ['a.js']);

        $this->assertSame(ToolRunStatus::Failed, $result->status);
        $this->assertStringContainsString('npm ci', $result->message);
        Process::assertNothingRan();
    }

    public function test_crash_exit_code_fails_without_leaking_stderr(): void
    {
        Process::fake(['*' => Process::result('', 'Error: Cannot find module /home/server/x', 2)]);

        $result = $this->runner()->run('/srv/ws/repo', ['a.js']);

        $this->assertSame(ToolRunStatus::Failed, $result->status);
        $this->assertStringNotContainsString('/home/server', $result->message);
        $this->assertStringContainsString('kode 2', $result->message);
    }

    public function test_truncated_json_fails_the_tool(): void
    {
        Process::fake(['*' => Process::result('[{"filePath": "/srv/ws/repo/a.js", "messa', '', 1)]);

        $result = $this->runner()->run('/srv/ws/repo', ['a.js']);

        $this->assertSame(ToolRunStatus::Failed, $result->status);
        $this->assertStringContainsString('bukan JSON yang valid', $result->message);
    }

    /**
     * Integrasi nyata: butuh Node dan `npm ci` di backend/tooling; dilewati bila tidak ada. Repo uji berisi config
     * ESLint dan komentar eslint-disable "jahat" yang tidak boleh berpengaruh.
     */
    public function test_real_eslint_ignores_target_config_and_inline_suppression(): void
    {
        $script = (string) config('audit.sast.eslint.script_path');

        if (! is_file($script) || trim((string) @shell_exec('node --version 2>&1')) === '' || ! str_starts_with(trim((string) @shell_exec('node --version 2>&1')), 'v')) {
            $this->markTestSkipped('Node/ESLint platform tidak terpasang (npm ci di backend/tooling); integrasi nyata dilewati.');
        }

        $root = sys_get_temp_dir().'/eslint-real-'.bin2hex(random_bytes(4));
        mkdir($root.'/src', 0755, true);

        try {
            file_put_contents($root.'/eslint.config.js', 'throw new Error("config repo tidak boleh dimuat");');
            file_put_contents($root.'/src/a.js', "/* eslint-disable */\nconst x = eval(input);\ndebugger;\n");
            file_put_contents($root.'/src/b.ts', "export const f = (a: string): number => eval(a) as number;\n");
            file_put_contents($root.'/src/bad.js', 'function ( {');

            $runner = $this->app->make(EslintRunner::class);
            $result = $runner->run($root, ['src/a.js', 'src/b.ts', 'src/bad.js']);
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }

        $this->assertSame(ToolRunStatus::Completed, $result->status, (string) $result->message);
        $rules = array_map(fn ($finding) => $finding->filePath.':'.$finding->ruleId, $result->findings);

        $this->assertContains('src/a.js:no-eval', $rules, 'eslint-disable di repo target harus diabaikan.');
        $this->assertContains('src/a.js:no-debugger', $rules);
        $this->assertContains('src/b.ts:no-eval', $rules, 'file TypeScript harus diparse parser TS platform.');
        $this->assertTrue($result->incomplete, 'src/bad.js gagal di-parse → hasil tidak lengkap.');
    }
}
