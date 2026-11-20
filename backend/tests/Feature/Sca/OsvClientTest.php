<?php

namespace Tests\Feature\Sca;

use App\Enums\OsvFailureReason;
use App\Services\Sca\OsvClient;
use App\Services\Sca\OsvQuery;
use App\Services\Sca\OsvQueryResult;
use App\Services\Sca\OsvVulnerabilityRef;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OsvClientTest extends TestCase
{
    private const URL = 'https://api.osv.dev/v1/querybatch';

    private const DEFAULTS = [
        'base_url' => 'https://api.osv.dev',
        'timeout_seconds' => 15,
        'connect_timeout_seconds' => 5,
        'max_attempts' => 3,
        'backoff_ms' => 500,
        'batch_size' => 1000,
        'max_pages' => 20,
        'max_total_seconds' => 180,
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

    private function q(string $name = 'express', string $version = '4.18.2'): OsvQuery
    {
        return new OsvQuery($name, $version);
    }

    private function fake(mixed $responses): void
    {
        Http::fake(['api.osv.dev/*' => $responses]);
    }

    /**
     * @param  array<int, mixed>  $results
     */
    private function ok(array $results): mixed
    {
        return Http::response(['results' => $results], 200);
    }

    private function vuln(string $id, ?string $modified = '2024-01-01T00:00:00Z'): array
    {
        return ['id' => $id] + ($modified === null ? [] : ['modified' => $modified]);
    }

    /**
     * @return list<string>
     */
    private function ids(OsvQueryResult $result): array
    {
        return array_map(fn (OsvVulnerabilityRef $v) => $v->id, $result->vulnerabilities);
    }

    /**
     * @return list<array{0: Request, 1: mixed}>
     */
    private function recorded(): array
    {
        return Http::recorded()->all();
    }

    public function test_sends_exact_versions_to_querybatch_with_npm_ecosystem(): void
    {
        $this->fake($this->ok([[]]));

        $this->client()->query([$this->q()]);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request->url() === self::URL
                && $request->method() === 'POST'
                && $request->data() === ['queries' => [['package' => ['name' => 'express', 'ecosystem' => 'npm'], 'version' => '4.18.2']]]
                && $request->hasHeader('Accept', 'application/json');
        });
    }

    public function test_maps_results_by_position(): void
    {
        $this->fake($this->ok([['vulns' => [$this->vuln('GHSA-1'), $this->vuln('CVE-2', null)]], []]));

        $results = $this->client()->query([$this->q('a', '1.0.0'), $this->q('b', '2.0.0')]);

        $this->assertCount(2, $results);
        $this->assertSame(['GHSA-1', 'CVE-2'], $this->ids($results[0]));
        $this->assertSame('2024-01-01T00:00:00Z', $results[0]->vulnerabilities[0]->modified);
        $this->assertNull($results[0]->vulnerabilities[1]->modified);
        $this->assertFalse($results[0]->isFailed());
        $this->assertSame([], $this->ids($results[1]));
    }

    public function test_empty_result_means_ok_with_no_vulnerabilities(): void
    {
        $this->fake($this->ok([[]]));

        $result = $this->client()->query([$this->q()])[0];

        $this->assertFalse($result->isFailed());
        $this->assertNull($result->failure);
        $this->assertSame([], $result->vulnerabilities);
    }

    public function test_empty_input_makes_no_request(): void
    {
        $this->assertSame([], $this->client()->query([]));

        Http::assertNothingSent();
    }

    public function test_duplicate_queries_are_sent_once_and_share_the_result(): void
    {
        $this->fake($this->ok([['vulns' => [$this->vuln('GHSA-1')]], []]));

        $results = $this->client()->query([$this->q('a', '1.0.0'), $this->q('b', '1.0.0'), $this->q('a', '1.0.0')]);

        Http::assertSent(fn (Request $r) => count($r->data()['queries']) === 2);
        $this->assertSame(['GHSA-1'], $this->ids($results[0]));
        $this->assertSame([], $this->ids($results[1]));
        $this->assertSame(['GHSA-1'], $this->ids($results[2]));
    }

    public function test_splits_into_batches_of_the_configured_size(): void
    {
        $this->fake(fn (Request $r) => $this->ok(array_fill(0, count($r->data()['queries']), [])));

        $results = $this->client()->query(array_map(fn ($i) => $this->q("pkg-$i", '1.0.0'), range(1, 2500)));

        $this->assertCount(2500, $results);
        $this->assertSame([1000, 1000, 500], array_map(fn ($pair) => count($pair[0]->data()['queries']), $this->recorded()));
    }

    public function test_batch_size_is_capped_at_the_api_limit(): void
    {
        $this->fake(fn (Request $r) => $this->ok(array_fill(0, count($r->data()['queries']), [])));

        $this->client(['batch_size' => 5000])->query(array_map(fn ($i) => $this->q("pkg-$i", '1.0.0'), range(1, 1001)));

        Http::assertSentCount(2);
    }

    public function test_one_failed_batch_does_not_fail_the_others(): void
    {
        $this->fake(Http::sequence()->push(['results' => [[], []]])->push('', 400));

        $results = $this->client(['batch_size' => 2])->query(array_map(fn ($i) => $this->q("pkg-$i", '1.0.0'), range(1, 4)));

        $this->assertSame([false, false, true, true], array_map(fn ($r) => $r->isFailed(), $results));
        $this->assertSame(OsvFailureReason::ClientError, $results[2]->failure);
        $this->assertSame(OsvFailureReason::ClientError, $results[3]->failure);
    }

    public function test_retries_on_5xx_then_succeeds_with_exponential_backoff(): void
    {
        $this->fake(Http::sequence()->push('', 503)->push('', 502)->push(['results' => [['vulns' => [$this->vuln('GHSA-1')]]]]));

        $result = $this->client()->query([$this->q()])[0];

        $this->assertSame(['GHSA-1'], $this->ids($result));
        Http::assertSentCount(3);
        Sleep::assertSequence([Sleep::for(500)->milliseconds(), Sleep::for(1000)->milliseconds()]);
    }

    public function test_retries_on_429_then_succeeds(): void
    {
        $this->fake(Http::sequence()->push('', 429)->push(['results' => [[]]]));

        $this->assertFalse($this->client()->query([$this->q()])[0]->isFailed());
        Http::assertSentCount(2);
    }

    public function test_reports_rate_limited_when_429_persists(): void
    {
        $this->fake(Http::response('', 429));

        $this->assertSame(OsvFailureReason::RateLimited, $this->client()->query([$this->q()])[0]->failure);
        Http::assertSentCount(3);
    }

    public function test_retries_on_connection_failure_then_succeeds(): void
    {
        $this->fake(Http::sequence()->pushFailedConnection()->push(['results' => [[]]]));

        $this->assertFalse($this->client()->query([$this->q()])[0]->isFailed());
        Http::assertSentCount(2);
    }

    public function test_reports_timeout_when_connection_keeps_failing(): void
    {
        $this->fake(Http::failedConnection());

        $this->assertSame(OsvFailureReason::Timeout, $this->client()->query([$this->q()])[0]->failure);
        Http::assertSentCount(3);
    }

    public function test_gives_up_after_max_attempts_and_marks_batch_failed(): void
    {
        $this->fake(Http::response('SECRET-BODY', 503));

        $result = $this->client()->query([$this->q()])[0];

        $this->assertSame(OsvFailureReason::ServerError, $result->failure);
        $this->assertSame([], $result->vulnerabilities);
        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2);
    }

    #[DataProvider('clientErrors')]
    public function test_does_not_retry_on_other_4xx(int $status): void
    {
        $this->fake(Http::response('', $status));

        $result = $this->client()->query([$this->q()])[0];

        $this->assertSame(OsvFailureReason::ClientError, $result->failure);
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    /**
     * @return array<string, array{int}>
     */
    public static function clientErrors(): array
    {
        return ['400' => [400], '401' => [401], '403' => [403], '404' => [404], '422' => [422]];
    }

    public function test_redirect_is_not_followed_and_tls_verification_stays_on(): void
    {
        $options = [];
        $this->fake(function (Request $request, array $opts) use (&$options) {
            $options = $opts;

            return Http::response('', 302, ['Location' => 'https://evil.test/']);
        });

        $result = $this->client()->query([$this->q()])[0];

        $this->assertSame(OsvFailureReason::ClientError, $result->failure);
        Http::assertSentCount(1);
        $this->assertFalse($options['allow_redirects']);
        $this->assertNotFalse($options['verify'] ?? true);
    }

    public function test_invalid_json_is_not_retried_and_fails_the_batch(): void
    {
        $this->fake(Http::response('<html>not json</html>', 200));

        $result = $this->client()->query([$this->q()])[0];

        $this->assertSame(OsvFailureReason::InvalidResponse, $result->failure);
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    #[DataProvider('wrongCounts')]
    public function test_results_count_mismatch_fails_the_batch(array $results): void
    {
        $this->fake($this->ok($results));

        $outcome = $this->client()->query([$this->q('a', '1.0.0'), $this->q('b', '1.0.0')]);

        foreach ($outcome as $result) {
            $this->assertSame(OsvFailureReason::InvalidResponse, $result->failure);
        }
    }

    /**
     * @return array<string, array{array<int, mixed>}>
     */
    public static function wrongCounts(): array
    {
        return ['fewer' => [[[]]], 'more' => [[[], [], []]], 'none' => [[]]];
    }

    #[DataProvider('malformedResponses')]
    public function test_malformed_responses_fail_the_batch(mixed $body): void
    {
        $this->fake(Http::response($body, 200));

        $this->assertSame(OsvFailureReason::InvalidResponse, $this->client()->query([$this->q()])[0]->failure);
        Http::assertSentCount(1);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedResponses(): array
    {
        $wrap = fn (mixed $entry) => ['results' => [$entry]];

        return [
            'no results key' => [['other' => 1]],
            'results not array' => [['results' => 'x']],
            'results is object' => [['results' => ['a' => []]]],
            'entry scalar' => [$wrap('x')],
            'entry non-empty list' => [$wrap([1])],
            'vulns not array' => [$wrap(['vulns' => 'x'])],
            'vulns is object' => [$wrap(['vulns' => ['a' => ['id' => 'x']]])],
            'vuln scalar' => [$wrap(['vulns' => ['GHSA-1']])],
            'vuln without id' => [$wrap(['vulns' => [['modified' => 'x']]])],
            'id empty' => [$wrap(['vulns' => [['id' => '']]])],
            'id not string' => [$wrap(['vulns' => [['id' => 5]]])],
            'id too long' => [$wrap(['vulns' => [['id' => str_repeat('a', 101)]]])],
            'modified not string' => [$wrap(['vulns' => [['id' => 'a', 'modified' => 5]]])],
            'token not string' => [$wrap(['next_page_token' => 5])],
            'token empty' => [$wrap(['next_page_token' => ''])],
        ];
    }

    public function test_follows_next_page_token_and_merges_vulnerabilities(): void
    {
        $this->fake(Http::sequence()
            ->push(['results' => [['vulns' => [$this->vuln('GHSA-1')], 'next_page_token' => 'T1']]])
            ->push(['results' => [['vulns' => [$this->vuln('GHSA-2')]]]]));

        $result = $this->client()->query([$this->q()])[0];

        $this->assertFalse($result->isFailed());
        $this->assertSame(['GHSA-1', 'GHSA-2'], $this->ids($result));
        $this->assertSame(
            ['queries' => [['package' => ['name' => 'express', 'ecosystem' => 'npm'], 'version' => '4.18.2', 'page_token' => 'T1']]],
            $this->recorded()[1][0]->data(),
        );
    }

    public function test_pagination_only_resends_queries_that_have_a_token(): void
    {
        $this->fake(Http::sequence()
            ->push(['results' => [['next_page_token' => 'T1'], [], ['next_page_token' => 'T3']]])
            ->push(['results' => [[], []]]));

        $this->client()->query([$this->q('a', '1.0.0'), $this->q('b', '1.0.0'), $this->q('c', '1.0.0')]);

        $second = $this->recorded()[1][0]->data()['queries'];
        $this->assertSame(['a', 'c'], array_map(fn ($q) => $q['package']['name'], $second));
        $this->assertSame(['T1', 'T3'], array_map(fn ($q) => $q['page_token'], $second));
    }

    public function test_endless_pagination_is_stopped_and_marked_failed(): void
    {
        $n = 0;
        $this->fake(function () use (&$n) {
            return $this->ok([['vulns' => [$this->vuln('GHSA-'.$n)], 'next_page_token' => 'T'.++$n]]);
        });

        $result = $this->client(['max_pages' => 3])->query([$this->q()])[0];

        $this->assertSame(OsvFailureReason::PaginationIncomplete, $result->failure);
        $this->assertSame([], $result->vulnerabilities);
        Http::assertSentCount(3);
    }

    public function test_repeated_token_for_the_same_query_is_marked_failed(): void
    {
        $this->fake(Http::response(['results' => [['next_page_token' => 'SAME']]], 200));

        $result = $this->client()->query([$this->q()])[0];

        $this->assertSame(OsvFailureReason::PaginationIncomplete, $result->failure);
        Http::assertSentCount(2);
    }

    public function test_same_token_on_different_queries_is_not_a_repeat(): void
    {
        $this->fake(Http::sequence()
            ->push(['results' => [['next_page_token' => 'SAME'], ['next_page_token' => 'SAME']]])
            ->push(['results' => [[], []]]));

        $results = $this->client()->query([$this->q('a', '1.0.0'), $this->q('b', '1.0.0')]);

        $this->assertSame([false, false], array_map(fn ($r) => $r->isFailed(), $results));
    }

    public function test_failed_page_marks_the_query_failed_without_partial_vulnerabilities(): void
    {
        $this->fake(Http::sequence()
            ->push(['results' => [['vulns' => [$this->vuln('GHSA-1')], 'next_page_token' => 'T1'], ['vulns' => [$this->vuln('GHSA-9')]]]])
            ->push('', 500)->push('', 500)->push('', 500));

        $results = $this->client()->query([$this->q('a', '1.0.0'), $this->q('b', '1.0.0')]);

        $this->assertSame(OsvFailureReason::ServerError, $results[0]->failure);
        $this->assertSame([], $results[0]->vulnerabilities);
        $this->assertSame(['GHSA-9'], $this->ids($results[1]));
    }

    public function test_duplicate_vulnerability_ids_are_deduplicated(): void
    {
        $this->fake(Http::sequence()
            ->push(['results' => [['vulns' => [$this->vuln('GHSA-1'), $this->vuln('GHSA-1')], 'next_page_token' => 'T']]])
            ->push(['results' => [['vulns' => [$this->vuln('GHSA-1'), $this->vuln('GHSA-2')]]]]));

        $this->assertSame(['GHSA-1', 'GHSA-2'], $this->ids($this->client()->query([$this->q()])[0]));
    }

    public function test_time_budget_exhausted_midway_fails_only_unsent_batches(): void
    {
        $this->fake(function (Request $request) {
            Carbon::setTestNow(now()->addSeconds(101));

            return $this->ok(array_fill(0, count($request->data()['queries']), []));
        });

        $results = $this->client(['batch_size' => 2, 'max_total_seconds' => 100])
            ->query(array_map(fn ($i) => $this->q("pkg-$i", '1.0.0'), range(1, 4)));

        $this->assertSame([false, false, true, true], array_map(fn ($r) => $r->isFailed(), $results));
        $this->assertSame(OsvFailureReason::TimeBudgetExhausted, $results[2]->failure);
        Http::assertSentCount(1);
    }

    public function test_time_budget_exhausted_before_a_pagination_round_fails_pending_queries(): void
    {
        $this->fake(function () {
            Carbon::setTestNow(now()->addSeconds(101));

            return $this->ok([['vulns' => [$this->vuln('GHSA-1')], 'next_page_token' => 'T1']]);
        });

        $result = $this->client(['max_total_seconds' => 100])->query([$this->q()])[0];

        $this->assertSame(OsvFailureReason::TimeBudgetExhausted, $result->failure);
        $this->assertSame([], $result->vulnerabilities);
        Http::assertSentCount(1);
    }

    public function test_backoff_longer_than_remaining_budget_fails_without_sleeping(): void
    {
        $this->fake(Http::response('', 503));

        $result = $this->client(['max_total_seconds' => 1, 'backoff_ms' => 2000])->query([$this->q()])[0];

        $this->assertSame(OsvFailureReason::TimeBudgetExhausted, $result->failure);
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public function test_zero_budget_sends_nothing(): void
    {
        $results = $this->client(['max_total_seconds' => 0])->query([$this->q('a', '1.0.0'), $this->q('b', '1.0.0')]);

        $this->assertSame([OsvFailureReason::TimeBudgetExhausted, OsvFailureReason::TimeBudgetExhausted], array_map(fn ($r) => $r->failure, $results));
        Http::assertNothingSent();
    }

    public function test_request_timeout_is_capped_by_the_remaining_budget(): void
    {
        $options = [];
        $this->fake(function (Request $request, array $opts) use (&$options) {
            $options = $opts;

            return $this->ok([[]]);
        });

        $this->client(['timeout_seconds' => 15, 'connect_timeout_seconds' => 5, 'max_total_seconds' => 4])->query([$this->q()]);

        $this->assertEqualsWithDelta(4.0, $options['timeout'], 0.001);
        $this->assertLessThanOrEqual(4.0, $options['connect_timeout']);
    }

    public function test_request_uses_configured_timeouts_when_budget_is_large(): void
    {
        $options = [];
        $this->fake(function (Request $request, array $opts) use (&$options) {
            $options = $opts;

            return $this->ok([[]]);
        });

        $this->client()->query([$this->q()]);

        $this->assertEqualsWithDelta(15.0, $options['timeout'], 0.001);
        $this->assertEqualsWithDelta(5.0, $options['connect_timeout'], 0.001);
    }

    public function test_max_attempts_below_one_still_makes_one_attempt(): void
    {
        $this->fake(Http::response('', 503));

        $this->client(['max_attempts' => 0])->query([$this->q()]);

        Http::assertSentCount(1);
    }

    #[DataProvider('invalidQueries')]
    public function test_osv_query_rejects_non_exact_versions_and_bad_names(string $name, string $version): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OsvQuery($name, $version);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidQueries(): array
    {
        return [
            'caret' => ['a', '^1.2.3'], 'tilde' => ['a', '~1.2.3'], 'star' => ['a', '*'], 'latest' => ['a', 'latest'],
            'empty version' => ['a', ''], 'partial' => ['a', '1.2'], 'v prefix' => ['a', 'v1.2.3'], 'equals prefix' => ['a', '=1.2.3'],
            'spaces' => ['a', ' 1.2.3 '], 'empty name' => ['', '1.0.0'], 'long name' => [str_repeat('a', 215), '1.0.0'],
            'invalid utf8 name' => ["a\xFF", '1.0.0'],
        ];
    }

    public function test_osv_query_accepts_prerelease_build_and_longest_name(): void
    {
        $this->assertSame('1.2.3-beta.1+b5', (new OsvQuery(str_repeat('a', 214), '1.2.3-beta.1+b5'))->version);
    }

    public function test_package_name_with_special_characters_stays_in_the_body_only(): void
    {
        $name = '@scope/we ird/../name%2F?x=1#y';
        $this->fake($this->ok([[]]));

        $this->client()->query([$this->q($name, '1.0.0')]);

        Http::assertSent(fn (Request $r) => $r->url() === self::URL && $r->data()['queries'][0]['package']['name'] === $name);
    }

    public function test_failure_messages_are_safe_and_defined_for_every_reason(): void
    {
        foreach (OsvFailureReason::cases() as $reason) {
            $message = OsvQueryResult::failed($reason)->message();

            $this->assertNotSame('', $message);
            $this->assertStringNotContainsString('api.osv.dev', strtolower($message));
            $this->assertStringNotContainsString('http', strtolower($message));
        }

        $this->assertNull(OsvQueryResult::ok([])->message());
    }

    public function test_log_and_results_do_not_contain_response_body_or_package_names(): void
    {
        Log::spy();
        $this->fake(Http::response('SECRET-BODY-MARKER', 503));

        $result = $this->client()->query([$this->q('secret-pkg', '1.0.0')])[0];

        $this->assertStringNotContainsString('SECRET-BODY-MARKER', (string) $result->message());
        Log::shouldHaveReceived('warning')->atLeast()->once();
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context = []) {
            $logged = $message.json_encode($context);
            $this->assertStringNotContainsString('SECRET-BODY-MARKER', $logged);
            $this->assertStringNotContainsString('secret-pkg', $logged);
            $this->assertStringContainsString('server_error', $logged);

            return true;
        });
    }

    public function test_unexpected_exceptions_are_not_swallowed(): void
    {
        $this->fake(fn () => throw new RuntimeException('bug'));

        $this->expectException(RuntimeException::class);

        $this->client()->query([$this->q()]);
    }

    public function test_container_resolves_the_client_from_config(): void
    {
        $this->assertInstanceOf(OsvClient::class, $this->app->make(OsvClient::class));
    }

    public function test_non_https_base_url_is_rejected_outside_the_testing_environment(): void
    {
        config(['audit.sca.osv.base_url' => 'http://api.osv.dev']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(OsvClient::class);
    }

    public function test_non_https_base_url_is_allowed_in_the_testing_environment(): void
    {
        config(['audit.sca.osv.base_url' => 'http://osv.test']);

        $this->assertInstanceOf(OsvClient::class, $this->app->make(OsvClient::class));
    }
}
