<?php

namespace Tests\Feature\Api\V1;

use App\Models\AuditScan;
use App\Models\User;
use App\Services\Ingestion\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

class GeneralRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'audit.rate_limits.guest_requests_per_minute' => 3,
            'audit.rate_limits.user_requests_per_minute' => 5,
        ]);
        $this->app->instance(HostResolver::class, new FakeHostResolver(['github.com' => ['140.82.112.3']]));
        Queue::fake();
    }

    public function test_guest_is_limited_per_minute_with_uniform_error_and_headers(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/v1/audits/01JD3X5W8Q2G7B9N4K6M1R0T2Y')->assertNotFound();
        }

        $response = $this->getJson('/api/v1/audits/01JD3X5W8Q2G7B9N4K6M1R0T2Y')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertJsonPath('error.details', null)
            ->assertHeader('X-RateLimit-Limit', '3')
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
    }

    public function test_result_endpoints_are_covered_for_guests_with_a_valid_token(): void
    {
        $response = $this->postJson('/api/v1/audits', ['source_type' => 'git', 'repository_url' => 'https://github.com/org/repo'])->assertStatus(202);
        $path = '/api/v1/audits/'.$response->json('data.audit.id').'/findings';
        $headers = ['X-Guest-Token' => $response->json('data.guest_token')];
        AuditScan::query()->update(['sast_status' => 'completed']);

        // POST di atas sudah memakai 1 dari 3 jatah menit ini.
        $this->getJson($path, $headers)->assertOk();
        $this->getJson($path, $headers)->assertOk();
        $this->getJson($path, $headers)->assertStatus(429);
    }

    public function test_user_has_own_higher_limit_and_guest_ip_is_independent(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk();
        }
        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertStatus(429);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/audits/01JD3X5W8Q2G7B9N4K6M1R0T2Y')->assertNotFound();
    }

    public function test_different_guest_ips_are_counted_separately(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->getJson('/api/v1/audits/x')->assertNotFound();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->getJson('/api/v1/audits/x')->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])->getJson('/api/v1/audits/x')->assertNotFound();
    }

    public function test_health_is_not_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/health')->assertStatus(200);
        }
    }

    public function test_general_limit_does_not_use_the_daily_audit_quota(): void
    {
        config(['audit.rate_limits.guest_requests_per_minute' => 100]);

        for ($i = 0; $i < 20; $i++) {
            $this->getJson('/api/v1/audits/x')->assertNotFound();
        }

        $this->postJson('/api/v1/audits', ['source_type' => 'git', 'repository_url' => 'https://github.com/org/repo'])->assertStatus(202);
        $this->postJson('/api/v1/audits', ['source_type' => 'git', 'repository_url' => 'https://github.com/org/repo'])->assertStatus(202);
    }
}
