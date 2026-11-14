<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AuditStatus;
use App\Models\AuditScan;
use App\Models\User;
use App\Services\Ingestion\AuditWorkspace;
use App\Services\Ingestion\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsZipArchives;
use Tests\Support\FakeHostResolver;
use Tests\Support\UsesTemporaryAuditStorage;
use Tests\TestCase;

class StoreAuditTest extends TestCase
{
    use BuildsZipArchives, RefreshDatabase, UsesTemporaryAuditStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTemporaryAuditStorage();
        $this->app->instance(HostResolver::class, new FakeHostResolver(['github.com' => ['140.82.112.3']]));
    }

    public function test_guest_can_submit_zip_and_receives_one_time_token(): void
    {
        $response = $this->post('/api/v1/audits', [
            'source_type' => 'zip',
            'file' => $this->zipUpload('Tugas Akhir (final).zip'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(202)
            ->assertJsonPath('data.audit.status', 'queued')
            ->assertJsonPath('data.audit.source_type', 'zip')
            ->assertJsonPath('data.audit.repository_name', 'Tugas-Akhir-final');

        $audit = AuditScan::query()->where('public_id', $response->json('data.audit.id'))->firstOrFail();
        $this->assertTrue($audit->matchesGuestToken($response->json('data.guest_token')));
        $this->assertSame('Tugas Akhir (final).zip', $audit->original_filename);
        $this->assertSame(AuditStatus::Queued, $audit->status);
        $this->assertGreaterThan(0, $audit->source_size_bytes);
        $this->assertFileExists($this->app->make(AuditWorkspace::class)->uploadPath($audit));
    }

    public function test_logged_in_user_audit_has_owner_and_no_guest_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/api/v1/audits', [
            'source_type' => 'zip',
            'file' => $this->zipUpload('repo.zip'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(202)->assertJsonMissingPath('data.guest_token');
        $this->assertSame($user->id, AuditScan::query()->firstOrFail()->user_id);
    }

    public function test_client_filename_with_path_is_reduced_to_basename(): void
    {
        $this->post('/api/v1/audits', [
            'source_type' => 'zip',
            'file' => $this->zipUpload('../../etc/passwd.zip'),
        ], ['Accept' => 'application/json'])->assertStatus(202);

        $audit = AuditScan::query()->firstOrFail();
        $this->assertSame('passwd.zip', $audit->original_filename);
        $this->assertSame('passwd', $audit->repository_name);
    }

    public function test_guest_can_submit_public_git_repository(): void
    {
        $response = $this->postJson('/api/v1/audits', [
            'source_type' => 'git',
            'repository_url' => 'https://GitHub.com/organisasi/contoh-repo.git',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.audit.source_type', 'git')
            ->assertJsonPath('data.audit.repository_name', 'organisasi/contoh-repo')
            ->assertJsonPath('data.audit.repository_url', 'https://github.com/organisasi/contoh-repo.git');
        $this->assertNotNull($response->json('data.guest_token'));
    }

    public function test_source_type_is_required_and_must_be_known(): void
    {
        $this->postJson('/api/v1/audits', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['source_type']]]);

        $this->postJson('/api/v1/audits', ['source_type' => 'svn'])->assertUnprocessable();
    }

    public function test_zip_source_requires_file(): void
    {
        $this->postJson('/api/v1/audits', ['source_type' => 'zip'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['file']]]);
    }

    public function test_file_with_zip_extension_but_other_content_is_rejected(): void
    {
        $this->post('/api/v1/audits', [
            'source_type' => 'zip',
            'file' => UploadedFile::fake()->createWithContent('palsu.zip', '<?php system($_GET["c"]);'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['file']]]);

        $this->assertDatabaseEmpty('audit_scans');
    }

    public function test_non_zip_extension_is_rejected(): void
    {
        $this->post('/api/v1/audits', [
            'source_type' => 'zip',
            'file' => new UploadedFile($this->makeZip(['a.js' => 'x']), 'repo.tar.gz', null, null, true),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_file_larger_than_limit_returns_413(): void
    {
        config(['audit.max_repository_bytes' => 1024]);

        $this->post('/api/v1/audits', [
            'source_type' => 'zip',
            'file' => UploadedFile::fake()->create('besar.zip', 2),
        ], ['Accept' => 'application/json'])
            ->assertStatus(413)
            ->assertJsonPath('error.code', 'PAYLOAD_TOO_LARGE');

        $this->assertDatabaseEmpty('audit_scans');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRepositoryUrls(): array
    {
        return [
            'http' => ['http://github.com/org/repo'],
            'kredensial' => ['https://user:token@github.com/org/repo'],
            'host lain' => ['https://evil.example.com/org/repo'],
            'IP literal' => ['https://127.0.0.1/org/repo'],
            'tanpa nama repo' => ['https://github.com/org'],
        ];
    }

    #[DataProvider('invalidRepositoryUrls')]
    public function test_unsafe_repository_url_is_rejected(string $url): void
    {
        $this->postJson('/api/v1/audits', ['source_type' => 'git', 'repository_url' => $url])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['repository_url']]]);

        $this->assertDatabaseEmpty('audit_scans');
    }

    public function test_repository_host_resolving_to_private_network_is_rejected(): void
    {
        $this->app->instance(HostResolver::class, new FakeHostResolver(['github.com' => ['169.254.169.254']]));

        $this->postJson('/api/v1/audits', ['source_type' => 'git', 'repository_url' => 'https://github.com/org/repo'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['repository_url']]]);
    }

    public function test_git_source_requires_repository_url(): void
    {
        $this->postJson('/api/v1/audits', ['source_type' => 'git'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['repository_url']]]);
    }

    private function zipUpload(string $clientName): UploadedFile
    {
        return new UploadedFile($this->makeZip(['repo/index.js' => 'console.log(1);']), $clientName, 'application/zip', null, true);
    }
}
