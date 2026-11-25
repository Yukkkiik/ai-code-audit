<?php

namespace Tests\Feature\Api\V1;

use App\Enums\StageStatus;
use App\Models\AuditScan;
use App\Models\Dependency;
use App\Models\ScanFinding;
use App\Models\User;
use App\Models\Vulnerability;
use App\Services\Ingestion\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

class GuestModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, new FakeHostResolver(['github.com' => ['140.82.112.3']]));
        Queue::fake();
    }

    public function test_guest_full_flow_create_then_read_every_endpoint(): void
    {
        [$audit, $token] = $this->submitAsGuest();
        $this->finishWithResults($audit);

        foreach ($this->auditEndpoints($audit) as $path) {
            $this->getJson($path, ['X-Guest-Token' => $token])->assertOk();
        }
    }

    public function test_guest_token_is_returned_once_and_stored_hashed(): void
    {
        [$audit, $token] = $this->submitAsGuest();

        $this->assertSame(hash('sha256', $token), $audit->guest_token_hash);
        $this->assertNotSame($token, $audit->guest_token_hash);

        $this->getJson("/api/v1/audits/{$audit->public_id}", ['X-Guest-Token' => $token])
            ->assertOk()
            ->assertJsonMissingPath('data.guest_token')
            ->assertDontSee($token);
    }

    public function test_guest_token_of_one_audit_cannot_read_another_guest_audit(): void
    {
        [, $tokenA] = $this->submitAsGuest();
        [$auditB] = $this->submitAsGuest();
        $this->finishWithResults($auditB);

        foreach ($this->auditEndpoints($auditB) as $path) {
            $this->getJson($path, ['X-Guest-Token' => $tokenA])->assertNotFound();
        }
    }

    public function test_logged_in_user_cannot_read_guest_audit_without_token(): void
    {
        [$audit] = $this->submitAsGuest();
        $this->finishWithResults($audit);
        $user = User::factory()->create();

        foreach ($this->auditEndpoints($audit) as $path) {
            $this->actingAs($user)->getJson($path)->assertNotFound();
        }
    }

    public function test_guest_token_cannot_read_user_owned_audit(): void
    {
        [, $token] = $this->submitAsGuest();
        $audit = AuditScan::factory()->ownedBy()->create(['sast_status' => StageStatus::Completed, 'sca_status' => StageStatus::Completed]);
        $this->finishWithResults($audit);

        foreach ($this->auditEndpoints($audit) as $path) {
            $this->getJson($path, ['X-Guest-Token' => $token])->assertNotFound();
        }
    }

    public function test_guest_token_in_query_string_is_rejected(): void
    {
        [$audit, $token] = $this->submitAsGuest();
        $this->finishWithResults($audit);

        foreach ($this->auditEndpoints($audit) as $path) {
            $this->getJson("{$path}?guest_token={$token}&token={$token}")->assertNotFound();
        }
    }

    public function test_guest_has_no_history_or_profile(): void
    {
        $this->getJson('/api/v1/audits')->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_result_endpoints_are_not_cacheable_for_guest(): void
    {
        [$audit, $token] = $this->submitAsGuest();
        $this->finishWithResults($audit);

        foreach (array_slice($this->auditEndpoints($audit), 1) as $path) {
            $this->getJson($path, ['X-Guest-Token' => $token])
                ->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    /**
     * @return array{0: AuditScan, 1: string}
     */
    private function submitAsGuest(): array
    {
        $response = $this->postJson('/api/v1/audits', ['source_type' => 'git', 'repository_url' => 'https://github.com/org/repo'])
            ->assertStatus(202);

        return [
            AuditScan::query()->where('public_id', $response->json('data.audit.id'))->firstOrFail(),
            $response->json('data.guest_token'),
        ];
    }

    private function finishWithResults(AuditScan $audit): void
    {
        $audit->update(['sast_status' => StageStatus::Completed, 'sca_status' => StageStatus::Completed]);
        ScanFinding::factory()->for($audit)->create();
        Vulnerability::factory()->for(Dependency::factory()->for($audit)->create())->create();
    }

    /**
     * @return list<string> path pertama = detail audit
     */
    private function auditEndpoints(AuditScan $audit): array
    {
        $base = "/api/v1/audits/{$audit->public_id}";
        $vulnerabilityId = Vulnerability::query()->where('audit_scan_id', $audit->id)->value('id');

        return [
            $base,
            "{$base}/findings",
            "{$base}/metrics",
            "{$base}/dependencies",
            "{$base}/vulnerabilities",
            "{$base}/vulnerabilities/{$vulnerabilityId}",
        ];
    }
}
