<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\ProcessAuditJob;
use App\Models\AuditScan;
use App\Models\User;
use App\Services\Access\AuditSubmissionLimiter;
use App\Services\Ingestion\HostResolver;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

class AuditRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const GUEST_IP = '203.0.113.7';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, new FakeHostResolver(['github.com' => ['140.82.112.3']]));
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-25 10:00:00', 'Asia/Jakarta'));
    }

    public function test_guest_is_limited_to_two_audits_per_day_and_rejection_creates_nothing(): void
    {
        $this->submit()->assertStatus(202);
        $this->submit()->assertStatus(202);

        $this->submit()
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertHeader('X-RateLimit-Limit', '2')
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->assertSame(2, AuditScan::query()->count());
        Queue::assertPushed(ProcessAuditJob::class, 2);
    }

    public function test_retry_after_is_seconds_until_midnight_in_jakarta(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 23:00:00', 'Asia/Jakarta'));
        $this->submit()->assertStatus(202);
        $this->submit()->assertStatus(202);

        $retryAfter = (int) $this->submit()->assertStatus(429)->headers->get('Retry-After');

        $this->assertEqualsWithDelta(3600, $retryAfter, 2);
    }

    public function test_limit_resets_at_midnight_in_jakarta(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 23:59:00', 'Asia/Jakarta'));
        $this->submit()->assertStatus(202);
        $this->submit()->assertStatus(202);
        $this->submit()->assertStatus(429);

        $this->travelTo(Carbon::parse('2026-11-26 00:00:01', 'Asia/Jakarta'));

        $this->submit()->assertStatus(202);
    }

    public function test_user_is_limited_to_ten_audits_per_day(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->submit(user: $user)->assertStatus(202);
        }

        $this->submit(user: $user)->assertStatus(429)->assertHeader('X-RateLimit-Limit', '10');
    }

    public function test_ips_and_users_are_counted_separately_and_do_not_share_quota(): void
    {
        $this->submit()->assertStatus(202);
        $this->submit()->assertStatus(202);
        $this->submit()->assertStatus(429);

        $this->submit(ip: '203.0.113.8')->assertStatus(202);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $this->submit(user: $userA)->assertStatus(202);
        }
        $this->submit(user: $userA)->assertStatus(429);
        $this->submit(user: $userB)->assertStatus(202);
        $this->submit()->assertStatus(429);
    }

    public function test_rejected_validation_does_not_consume_quota(): void
    {
        $this->postJson('/api/v1/audits', ['source_type' => 'git', 'repository_url' => 'http://github.com/org/repo'], $this->server())->assertUnprocessable();
        $this->postJson('/api/v1/audits', ['source_type' => 'git', 'repository_url' => 'https://evil.example.com/org/repo'], $this->server())->assertUnprocessable();
        $this->postJson('/api/v1/audits', ['source_type' => 'zip'], $this->server())->assertUnprocessable();

        $this->submit()->assertStatus(202);
        $this->submit()->assertStatus(202);
    }

    public function test_failed_dispatch_gives_the_quota_back(): void
    {
        $calls = 0;
        $this->mock(Dispatcher::class, function ($mock) use (&$calls) {
            $mock->shouldReceive('dispatch')->andReturnUsing(function () use (&$calls) {
                if ($calls++ === 0) {
                    throw new RuntimeException('queue down');
                }
            });
        });

        $this->submit()->assertStatus(500);
        $this->submit()->assertStatus(202);
        $this->submit()->assertStatus(202);
        $this->submit()->assertStatus(429);
    }

    public function test_cache_keys_never_contain_the_raw_ip(): void
    {
        $this->submit()->assertStatus(202);

        $storage = (new ReflectionProperty(Cache::getStore(), 'storage'))->getValue(Cache::getStore());
        $keys = array_keys($storage);

        $this->assertNotEmpty(array_filter($keys, fn (string $key) => str_contains($key, 'audit-submit:guest:')));
        foreach ($keys as $key) {
            $this->assertStringNotContainsString(self::GUEST_IP, $key);
        }
    }

    public function test_forged_forwarded_for_header_does_not_change_the_counted_ip(): void
    {
        $this->submit(headers: ['X-Forwarded-For' => '198.51.100.1'])->assertStatus(202);
        $this->submit(headers: ['X-Forwarded-For' => '198.51.100.2'])->assertStatus(202);

        $this->submit(headers: ['X-Forwarded-For' => '198.51.100.3'])->assertStatus(429);
    }

    public function test_guest_fingerprint_is_a_keyed_hash_not_the_ip(): void
    {
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => self::GUEST_IP]);

        $fingerprint = AuditSubmissionLimiter::guestFingerprint($request);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fingerprint);
        $this->assertNotSame(hash('sha256', self::GUEST_IP), $fingerprint);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function submit(?User $user = null, string $ip = self::GUEST_IP, array $headers = []): TestResponse
    {
        // actingAs bertahan antar request dalam satu test; guest harus benar-benar tanpa login.
        $this->app['auth']->forgetGuards();
        $request = $user === null ? $this : $this->actingAs($user);

        return $request->postJson(
            '/api/v1/audits',
            ['source_type' => 'git', 'repository_url' => 'https://github.com/org/repo'],
            $this->server($ip) + $headers,
        );
    }

    /**
     * @return array<string, string>
     */
    private function server(string $ip = self::GUEST_IP): array
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return [];
    }
}
