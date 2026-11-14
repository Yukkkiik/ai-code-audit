<?php

namespace Tests\Feature\Ingestion;

use App\Models\AuditScan;
use App\Services\Ingestion\AuditWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\Support\UsesTemporaryAuditStorage;
use Tests\TestCase;

class AuditWorkspaceTest extends TestCase
{
    use RefreshDatabase, UsesTemporaryAuditStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTemporaryAuditStorage();
    }

    public function test_create_makes_private_per_audit_directories(): void
    {
        $audit = AuditScan::factory()->create();
        $workspace = $this->app->make(AuditWorkspace::class);

        $workspace->create($audit);

        $this->assertSame($this->auditStorageRoot.'/workspaces/'.$audit->public_id, $workspace->path($audit));
        $this->assertDirectoryExists($workspace->sourcePath($audit));
        $this->assertDirectoryExists($workspace->homePath($audit));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($workspace->path($audit))), -4));
    }

    public function test_workspace_cannot_be_created_twice(): void
    {
        $audit = AuditScan::factory()->create();
        $workspace = $this->app->make(AuditWorkspace::class);
        $workspace->create($audit);

        $this->expectException(LogicException::class);

        $workspace->create($audit);
    }

    public function test_store_upload_moves_file_to_private_uploads_directory(): void
    {
        $audit = AuditScan::factory()->create();
        $workspace = $this->app->make(AuditWorkspace::class);

        $workspace->storeUpload($audit, UploadedFile::fake()->createWithContent('repo.zip', "PK\x03\x04isi"));

        $this->assertSame($this->auditStorageRoot.'/uploads/'.$audit->public_id.'.zip', $workspace->uploadPath($audit));
        $this->assertSame("PK\x03\x04isi", file_get_contents($workspace->uploadPath($audit)));
    }

    public function test_cleanup_removes_workspace_and_upload(): void
    {
        $audit = AuditScan::factory()->create();
        $workspace = $this->app->make(AuditWorkspace::class);
        $workspace->create($audit);
        $workspace->storeUpload($audit, UploadedFile::fake()->createWithContent('repo.zip', 'PK'));
        File::put($workspace->sourcePath($audit).'/index.js', 'x');

        $this->assertTrue($workspace->cleanup($audit));

        $this->assertDirectoryDoesNotExist($workspace->path($audit));
        $this->assertFileDoesNotExist($workspace->uploadPath($audit));
    }

    public function test_cleanup_does_not_follow_symlinks_outside_workspace(): void
    {
        $audit = AuditScan::factory()->create();
        $workspace = $this->app->make(AuditWorkspace::class);
        $workspace->create($audit);
        $outside = $this->auditStorageRoot.'/di-luar-workspace';
        File::ensureDirectoryExists($outside);
        File::put($outside.'/penting.txt', 'jangan dihapus');
        symlink($outside, $workspace->sourcePath($audit).'/link-keluar');
        symlink($outside.'/penting.txt', $workspace->sourcePath($audit).'/link-file');

        $workspace->cleanup($audit);

        $this->assertDirectoryDoesNotExist($workspace->path($audit));
        $this->assertSame('jangan dihapus', file_get_contents($outside.'/penting.txt'));
    }

    public function test_cleanup_without_existing_files_is_harmless(): void
    {
        $audit = AuditScan::factory()->create();

        $this->assertTrue($this->app->make(AuditWorkspace::class)->cleanup($audit));
    }

    public function test_path_requires_valid_ulid_public_id(): void
    {
        $audit = AuditScan::factory()->make(['public_id' => '../../etc']);

        $this->expectException(LogicException::class);

        $this->app->make(AuditWorkspace::class)->path($audit);
    }

    public function test_builtin_local_storage_file_route_is_disabled(): void
    {
        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Route::has('storage.local.upload'));
    }
}
