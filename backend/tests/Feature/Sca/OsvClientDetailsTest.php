<?php

namespace Tests\Feature\Sca;

use App\Enums\OsvFailureReason;
use App\Services\Sca\OsvClient;
use App\Services\Sca\OsvDetailResult;
use App\Services\Sca\OsvQuery;
use App\Services\Sca\OsvVulnerabilityParser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OsvClientDetailsTest extends TestCase
{
    private const ID = 'GHSA-xxxx-0000-yyyy';

    private const DEFAULTS = [
        'base_url' => 'https://api.osv.dev', 'timeout_seconds' => 15, 'connect_timeout_seconds' => 5, 'max_attempts' => 3,
        'backoff_ms' => 500, 'batch_size' => 1000, 'max_pages' => 20, 'max_total_seconds' => 180,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Sleep::fake(syncWithCarbon: true);
        Carbon::setTestNow('2026-01-01 00:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function client(array $config = []): OsvClient
    {
        return new OsvClient($config + self::DEFAULTS);
    }

    /**
     * @return array<string, mixed>
     */
    private function advisory(string $id = self::ID): array
    {
        return ['id' => $id] + json_decode(file_get_contents(__DIR__.'/../../Fixtures/Sca/osv-detail-handwritten.json'), true);
    }

    private function fake(mixed $responses): void
    {
        Http::fake(['api.osv.dev/*' => $responses]);
    }

    private function lastSegment(Request $request): string
    {
        return rawurldecode(basename(parse_url($request->url(), PHP_URL_PATH)));
    }

    public function test_requests_the_advisory_with_get_and_parses_it(): void
    {
        $this->fake(Http::response($this->advisory(), 200));

        $result = $this->client()->fetchVulnerabilities([self::ID])[self::ID];

        $this->assertInstanceOf(OsvDetailResult::class, $result);
        $this->assertFalse($result->isFailed());
        $this->assertSame(self::ID, $result->detail->id);
        $this->assertSame('Command injection in example-lib', $result->detail->summary);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.osv.dev/v1/vulns/'.self::ID && $r->method() === 'GET' && $r->hasHeader('Accept', 'application/json'));
    }

    #[DataProvider('unsafeIds')]
    public function test_unsafe_ids_are_never_requested(string $id): void
    {
        $result = $this->client()->fetchVulnerabilities([$id])[$id];

        $this->assertSame(OsvFailureReason::InvalidResponse, $result->failure);
        $this->assertNull($result->detail);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeIds(): array
    {
        return [
            'slash' => ['a/b'], 'traversal' => ['../etc/passwd'], 'dot dot' => ['..'], 'space' => ['a b'], 'query' => ['GHSA-1?x=1'],
            'fragment' => ['GHSA-1#x'], 'empty' => [''], 'leading dash' => ['-GHSA'], 'leading dot' => ['.hidden'], 'percent' => ['a%2Fb'],
            'newline' => ["GHSA-1\n"], 'backslash' => ['a\\b'], 'too long' => [str_repeat('a', 101)], 'unicode' => ['GHSA-é'], 'at' => ['a@b'],
        ];
    }

    public function test_id_with_colon_is_percent_encoded_in_the_path(): void
    {
        $this->fake(fn (Request $r) => Http::response($this->advisory($this->lastSegment($r)), 200));

        $result = $this->client()->fetchVulnerabilities(['ALSA-2021:1234'])['ALSA-2021:1234'];

        $this->assertFalse($result->isFailed());
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.osv.dev/v1/vulns/ALSA-2021%3A1234');
    }

    public function test_duplicate_ids_are_requested_once(): void
    {
        $this->fake(fn (Request $r) => Http::response($this->advisory($this->lastSegment($r)), 200));

        $results = $this->client()->fetchVulnerabilities([self::ID, self::ID, 'GHSA-other-0000-aaaa']);

        $this->assertCount(2, $results);
        Http::assertSentCount(2);
    }

    public function test_empty_input_makes_no_request(): void
    {
        $this->assertSame([], $this->client()->fetchVulnerabilities([]));

        Http::assertNothingSent();
    }

    public function test_retries_on_5xx_with_backoff_then_succeeds(): void
    {
        $this->fake(Http::sequence()->push('', 503)->push('', 502)->push($this->advisory()));

        $this->assertFalse($this->client()->fetchVulnerabilities([self::ID])[self::ID]->isFailed());
        Http::assertSentCount(3);
        Sleep::assertSequence([Sleep::for(500)->milliseconds(), Sleep::for(1000)->milliseconds()]);
    }

    public function test_retries_on_429_and_connection_failure(): void
    {
        $this->fake(Http::sequence()->push('', 429)->pushFailedConnection()->push($this->advisory()));

        $this->assertFalse($this->client()->fetchVulnerabilities([self::ID])[self::ID]->isFailed());
        Http::assertSentCount(3);
    }

    public function test_gives_up_after_max_attempts(): void
    {
        $this->fake(Http::response('', 500));

        $this->assertSame(OsvFailureReason::ServerError, $this->client()->fetchVulnerabilities([self::ID])[self::ID]->failure);
        Http::assertSentCount(3);
    }

    #[DataProvider('clientErrors')]
    public function test_other_4xx_is_not_retried(int $status): void
    {
        $this->fake(Http::response('', $status));

        $this->assertSame(OsvFailureReason::ClientError, $this->client()->fetchVulnerabilities([self::ID])[self::ID]->failure);
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    /**
     * @return array<string, array{int}>
     */
    public static function clientErrors(): array
    {
        return ['404' => [404], '400' => [400], '403' => [403]];
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_bodies_fail_without_retry(mixed $body): void
    {
        $this->fake(Http::response($body, 200));

        $this->assertSame(OsvFailureReason::InvalidResponse, $this->client()->fetchVulnerabilities([self::ID])[self::ID]->failure);
        Http::assertSentCount(1);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidBodies(): array
    {
        return [
            'not json' => ['<html>x</html>'], 'list' => [[1, 2]], 'scalar' => ['"x"'], 'id mismatch' => [['id' => 'GHSA-someone-else']],
            'no id' => [['summary' => 'x']], 'id not string' => [['id' => 5]],
        ];
    }

    public function test_redirect_is_not_followed(): void
    {
        $options = [];
        $this->fake(function (Request $r, array $opts) use (&$options) {
            $options = $opts;

            return Http::response('', 302, ['Location' => 'https://evil.test/']);
        });

        $this->assertSame(OsvFailureReason::ClientError, $this->client()->fetchVulnerabilities([self::ID])[self::ID]->failure);
        $this->assertFalse($options['allow_redirects']);
        Http::assertSentCount(1);
    }

    public function test_one_failure_does_not_fail_the_others(): void
    {
        $this->fake(function (Request $r) {
            $id = $this->lastSegment($r);

            return $id === 'GHSA-bad-0000-aaaa' ? Http::response('', 404) : Http::response($this->advisory($id), 200);
        });

        $results = $this->client()->fetchVulnerabilities([self::ID, 'GHSA-bad-0000-aaaa', 'GHSA-good-0000-bbbb']);

        $this->assertFalse($results[self::ID]->isFailed());
        $this->assertTrue($results['GHSA-bad-0000-aaaa']->isFailed());
        $this->assertFalse($results['GHSA-good-0000-bbbb']->isFailed());
    }

    public function test_time_budget_exhausted_midway_fails_unsent_ids_without_requests_or_log_noise(): void
    {
        Log::spy();
        $this->fake(function (Request $r) {
            Carbon::setTestNow(now()->addSeconds(101));

            return Http::response($this->advisory($this->lastSegment($r)), 200);
        });
        $ids = ['GHSA-aaaa-0000-0001', 'GHSA-aaaa-0000-0002', 'GHSA-aaaa-0000-0003', 'GHSA-aaaa-0000-0004'];

        $results = $this->client(['max_total_seconds' => 100])->fetchVulnerabilities($ids);

        $this->assertFalse($results[$ids[0]]->isFailed());

        foreach (array_slice($ids, 1) as $id) {
            $this->assertSame(OsvFailureReason::TimeBudgetExhausted, $results[$id]->failure);
        }

        Http::assertSentCount(1);
        Log::shouldNotHaveReceived('warning');
    }

    public function test_an_explicit_deadline_is_respected(): void
    {
        $results = $this->client()->fetchVulnerabilities([self::ID], Carbon::now()->subSecond());

        $this->assertSame(OsvFailureReason::TimeBudgetExhausted, $results[self::ID]->failure);
        Http::assertNothingSent();
    }

    public function test_request_timeout_is_capped_by_the_explicit_deadline(): void
    {
        $options = [];
        $this->fake(function (Request $r, array $opts) use (&$options) {
            $options = $opts;

            return Http::response($this->advisory(), 200);
        });

        $this->client()->fetchVulnerabilities([self::ID], Carbon::now()->addSeconds(7));

        $this->assertEqualsWithDelta(7.0, $options['timeout'], 0.001);
    }

    public function test_budget_deadline_is_now_plus_the_configured_total(): void
    {
        $this->assertSame(Carbon::now()->addSeconds(180)->getTimestamp(), $this->client()->budgetDeadline()->getTimestamp());
        $this->assertSame(Carbon::now()->addSeconds(30)->getTimestamp(), $this->client(['max_total_seconds' => 30])->budgetDeadline()->getTimestamp());
    }

    public function test_query_also_accepts_an_explicit_deadline(): void
    {
        $results = $this->client()->query([new OsvQuery('a', '1.0.0')], Carbon::now()->subSecond());

        $this->assertSame(OsvFailureReason::TimeBudgetExhausted, $results[0]->failure);
        Http::assertNothingSent();
    }

    public function test_failure_logs_contain_no_response_body(): void
    {
        Log::spy();
        $this->fake(Http::response('SECRET-BODY-MARKER', 503));

        $result = $this->client()->fetchVulnerabilities([self::ID])[self::ID];

        $this->assertStringNotContainsString('SECRET-BODY-MARKER', (string) $result->message());
        Log::shouldHaveReceived('warning')->atLeast()->once();
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context = []) {
            $this->assertStringNotContainsString('SECRET-BODY-MARKER', $message.json_encode($context));

            return true;
        });
    }

    public function test_failed_detail_results_have_safe_messages(): void
    {
        $this->assertSame(OsvFailureReason::Timeout->message(), OsvDetailResult::failed(OsvFailureReason::Timeout)->message());
        $this->assertNull(OsvDetailResult::ok(OsvVulnerabilityParser::parse($this->advisory(), self::ID))->message());
    }
}
