<?php

namespace Tests\Feature\Ingestion;

use App\Exceptions\IngestionException;
use App\Services\Ingestion\GitRepositoryCloner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\FakeGitBinary;
use Tests\TestCase;

class GitRepositoryClonerTest extends TestCase
{
    use FakeGitBinary;

    private string $destination;

    private string $home;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/clone-test-'.Str::random(10);
        $this->destination = $base.'/source';
        $this->home = $base.'/home';
        File::ensureDirectoryExists($this->home);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($base));
    }

    public function test_successful_clone_returns_commit_sha(): void
    {
        $this->useFakeGit('success');

        $sha = $this->cloner()->clone('https://github.com/org/repo', $this->destination, $this->home);

        $this->assertSame('0123456789abcdef0123456789abcdef01234567', $sha);
        $this->assertFileExists($this->destination.'/src/index.js');
    }

    public function test_clone_uses_hardened_arguments(): void
    {
        $this->useFakeGit('success');

        $this->cloner()->clone('https://github.com/org/repo', $this->destination, $this->home);
        $calls = $this->fakeGitCalls();

        foreach ([
            'core.hooksPath=/dev/null', 'core.symlinks=false', 'core.fsmonitor=false',
            'protocol.allow=never', 'protocol.https.allow=always', 'http.followRedirects=false',
            'transfer.fsckObjects=true', 'submodule.recurse=false',
        ] as $config) {
            $this->assertStringContainsString("ARG {$config}\n", $calls, "Opsi git {$config} tidak dipakai.");
        }

        foreach (['--depth', '--no-tags', '--single-branch', '--no-recurse-submodules', '--template='] as $flag) {
            $this->assertStringContainsString("ARG {$flag}\n", $calls);
        }

        $this->assertStringContainsString("ARG --\nARG https://github.com/org/repo\nARG {$this->destination}\n", $calls, 'URL harus berada setelah "--".');
    }

    public function test_clone_runs_with_isolated_environment_without_app_secrets(): void
    {
        $this->useFakeGit('success');
        $_ENV['DB_PASSWORD'] = $_SERVER['DB_PASSWORD'] = 'rahasia-database';

        try {
            $this->cloner()->clone('https://github.com/org/repo', $this->destination, $this->home);
        } finally {
            unset($_ENV['DB_PASSWORD'], $_SERVER['DB_PASSWORD']);
        }

        $calls = $this->fakeGitCalls();
        $this->assertStringNotContainsString('rahasia-database', $calls);
        $this->assertStringNotContainsString('ENV APP_KEY=', $calls);
        $this->assertStringContainsString('ENV GIT_TERMINAL_PROMPT=0', $calls);
        $this->assertStringContainsString('ENV GIT_CONFIG_NOSYSTEM=1', $calls);
        $this->assertStringContainsString('ENV GIT_ALLOW_PROTOCOL=https', $calls);
        $this->assertStringContainsString("ENV HOME={$this->home}", $calls);
        $this->assertStringContainsString('ENV PATH=', $calls);
    }

    public function test_missing_or_private_repository_maps_to_repository_not_found(): void
    {
        $this->useFakeGit('not_found');

        $this->assertIngestionFails('REPOSITORY_NOT_FOUND', fn () => $this->cloner()->clone('https://github.com/org/privat', $this->destination, $this->home));
    }

    public function test_other_git_failures_map_to_generic_ingestion_error_without_leaking_stderr(): void
    {
        $this->useFakeGit('network_error');

        try {
            $this->cloner()->clone('https://github.com/org/repo', $this->destination, $this->home);
            $this->fail('IngestionException tidak dilempar.');
        } catch (IngestionException $e) {
            $this->assertSame('INGESTION_FAILED', $e->errorCode);
            $this->assertStringNotContainsString('Could not resolve host', $e->getMessage());
        }
    }

    public function test_slow_clone_times_out(): void
    {
        $this->useFakeGit('slow');
        config(['audit.git.clone_timeout_seconds' => 1]);

        $this->assertIngestionFails('CLONE_TIMEOUT', fn () => $this->cloner()->clone('https://github.com/org/repo', $this->destination, $this->home));
    }

    private function cloner(): GitRepositoryCloner
    {
        return $this->app->make(GitRepositoryCloner::class);
    }

    private function assertIngestionFails(string $expectedCode, \Closure $callback): void
    {
        try {
            $callback();
        } catch (IngestionException $e) {
            $this->assertSame($expectedCode, $e->errorCode);

            return;
        }

        $this->fail("IngestionException [{$expectedCode}] tidak dilempar.");
    }
}
