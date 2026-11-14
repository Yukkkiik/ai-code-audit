<?php

namespace Tests\Feature\Ingestion;

use App\Exceptions\IngestionException;
use App\Models\AuditScan;
use App\Services\Ingestion\AuditWorkspace;
use App\Services\Ingestion\HostResolver;
use App\Services\Ingestion\RepositoryIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\BuildsZipArchives;
use Tests\Support\FakeGitBinary;
use Tests\Support\FakeHostResolver;
use Tests\Support\UsesTemporaryAuditStorage;
use Tests\TestCase;

class RepositoryIngestorTest extends TestCase
{
    use BuildsZipArchives, FakeGitBinary, RefreshDatabase, UsesTemporaryAuditStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTemporaryAuditStorage();
        $this->app->instance(HostResolver::class, new FakeHostResolver(['github.com' => ['140.82.112.3']]));
    }

    public function test_zip_ingestion_extracts_into_workspace_and_uses_single_top_folder_as_root(): void
    {
        $audit = AuditScan::factory()->create();
        $this->storeZip($audit, ['tugas-main/package.json' => '{}', 'tugas-main/src/app.js' => 'x']);

        $result = $this->app->make(RepositoryIngestor::class)->ingest($audit);

        $workspace = $this->app->make(AuditWorkspace::class);
        $this->assertSame($workspace->sourcePath($audit).'/tugas-main', $result->rootPath);
        $this->assertFileExists($result->rootPath.'/src/app.js');
        $this->assertSame(3, $result->sizeBytes);
        $this->assertNull($result->commitSha);
    }

    public function test_zip_with_multiple_top_level_entries_uses_source_as_root(): void
    {
        $audit = AuditScan::factory()->create();
        $this->storeZip($audit, ['package.json' => '{}', 'src/app.js' => 'x']);

        $result = $this->app->make(RepositoryIngestor::class)->ingest($audit);

        $this->assertSame($this->app->make(AuditWorkspace::class)->sourcePath($audit), $result->rootPath);
    }

    public function test_missing_upload_fails_ingestion(): void
    {
        $audit = AuditScan::factory()->create();

        $this->expectException(IngestionException::class);

        $this->app->make(RepositoryIngestor::class)->ingest($audit);
    }

    public function test_git_ingestion_clones_records_commit_and_removes_git_directory(): void
    {
        $this->useFakeGit('success');
        $audit = AuditScan::factory()->fromGit()->create(['repository_url' => 'https://github.com/org/repo']);

        $result = $this->app->make(RepositoryIngestor::class)->ingest($audit);

        $this->assertSame('0123456789abcdef0123456789abcdef01234567', $result->commitSha);
        $this->assertFileExists($result->rootPath.'/src/index.js');
        $this->assertDirectoryDoesNotExist($result->rootPath.'/.git');
    }

    public function test_git_clone_larger_than_limit_is_rejected(): void
    {
        $this->useFakeGit('large');
        config(['audit.max_repository_bytes' => 32 * 1024]);
        $audit = AuditScan::factory()->fromGit()->create(['repository_url' => 'https://github.com/org/repo']);

        try {
            $this->app->make(RepositoryIngestor::class)->ingest($audit);
            $this->fail('IngestionException tidak dilempar.');
        } catch (IngestionException $e) {
            $this->assertSame('REPOSITORY_TOO_LARGE', $e->errorCode);
        }
    }

    public function test_git_url_is_revalidated_before_cloning(): void
    {
        $this->useFakeGit('success');
        $this->app->instance(HostResolver::class, new FakeHostResolver(['github.com' => ['10.0.0.8']]));
        $audit = AuditScan::factory()->fromGit()->create(['repository_url' => 'https://github.com/org/repo']);

        try {
            $this->app->make(RepositoryIngestor::class)->ingest($audit);
            $this->fail('IngestionException tidak dilempar.');
        } catch (IngestionException $e) {
            $this->assertSame('INVALID_REPOSITORY_URL', $e->errorCode);
            $this->assertSame('', $this->fakeGitCalls(), 'git tidak boleh dijalankan untuk URL yang tidak lolos validasi.');
        }
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function storeZip(AuditScan $audit, array $entries): void
    {
        $zip = $this->makeZip($entries);
        $this->app->make(AuditWorkspace::class)
            ->storeUpload($audit, new UploadedFile($zip, 'repo.zip', 'application/zip', null, true));
    }
}
