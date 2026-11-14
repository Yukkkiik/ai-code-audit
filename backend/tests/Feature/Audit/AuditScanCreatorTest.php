<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditStatus;
use App\Enums\SourceType;
use App\Models\AuditScan;
use App\Models\User;
use App\Services\Audit\AuditScanCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AuditScanCreatorTest extends TestCase
{
    use RefreshDatabase;

    private AuditScanCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = $this->app->make(AuditScanCreator::class);
    }

    public function test_guest_audit_gets_one_time_token_and_only_its_hash_is_stored(): void
    {
        $created = $this->creator->create(null, SourceType::Zip, 'tugas-akhir', originalFilename: 'tugas-akhir.zip', sourceSizeBytes: 2048);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $created->guestToken);
        $this->assertSame(hash('sha256', $created->guestToken), $created->audit->guest_token_hash);
        $this->assertDatabaseMissing('audit_scans', ['guest_token_hash' => $created->guestToken]);
        $this->assertNull($created->audit->user_id);
    }

    public function test_new_audit_is_queued_with_ulid_and_queued_at(): void
    {
        $created = $this->creator->create(null, SourceType::Zip, 'tugas-akhir');
        $audit = $created->audit->refresh();

        $this->assertSame(AuditStatus::Queued, $audit->status);
        $this->assertNotNull($audit->queued_at);
        $this->assertNull($audit->started_at);
        $this->assertSame(26, strlen($audit->public_id));
    }

    public function test_user_audit_has_owner_and_no_guest_token(): void
    {
        $user = User::factory()->create();

        $created = $this->creator->create($user, SourceType::Git, 'organisasi/repo', repositoryUrl: 'https://github.com/organisasi/repo');

        $this->assertNull($created->guestToken);
        $this->assertNull($created->audit->guest_token_hash);
        $this->assertTrue($created->audit->user->is($user));
        $this->assertSame('https://github.com/organisasi/repo', $created->audit->repository_url);
    }

    public function test_each_guest_audit_gets_a_different_token(): void
    {
        $first = $this->creator->create(null, SourceType::Zip, 'repo-a');
        $second = $this->creator->create(null, SourceType::Zip, 'repo-b');

        $this->assertNotSame($first->guestToken, $second->guestToken);
    }

    public function test_git_audit_requires_repository_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->creator->create(null, SourceType::Git, 'organisasi/repo');
    }

    public function test_zip_audit_must_not_have_repository_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->creator->create(null, SourceType::Zip, 'repo', repositoryUrl: 'https://github.com/organisasi/repo');
    }

    public function test_guest_token_verification(): void
    {
        $created = $this->creator->create(null, SourceType::Zip, 'repo');
        $ownedAudit = AuditScan::factory()->ownedBy()->create();

        $this->assertTrue($created->audit->matchesGuestToken($created->guestToken));
        $this->assertFalse($created->audit->matchesGuestToken(str_repeat('0', 64)));
        $this->assertFalse($created->audit->matchesGuestToken(''));
        $this->assertFalse($ownedAudit->matchesGuestToken($created->guestToken));
    }
}
