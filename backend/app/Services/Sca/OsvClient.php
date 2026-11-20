<?php

namespace App\Services\Sca;

use App\Enums\OsvFailureReason;
use App\Exceptions\OsvRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Klien OSV.dev `POST /v1/querybatch` (npm saja). Tahan gagal: timeout, retry terbatas dengan backoff
 * eksponensial (hanya untuk koneksi gagal, 429, dan 5xx), validasi respons ketat, pagination, dan anggaran
 * waktu total. Kegagalan satu batch hanya menggagalkan query di dalam batch itu. Klien tidak menyentuh database;
 * pemanggil hanya boleh membuat OsvQuery dari dependensi berversi pasti.
 */
class OsvClient
{
    private const MAX_BATCH_SIZE = 1000;

    private const MAX_ID_LENGTH = 100;

    private const MAX_MODIFIED_LENGTH = 64;

    private const MAX_TOKEN_LENGTH = 4096;

    private string $baseUrl;

    private float $timeout;

    private float $connectTimeout;

    private int $maxAttempts;

    private int $backoffMs;

    private int $batchSize;

    private int $maxPages;

    private int $maxTotalMs;

    /**
     * @param  array{base_url: string, timeout_seconds: int|float, connect_timeout_seconds: int|float, max_attempts: int, backoff_ms: int, batch_size: int, max_pages: int, max_total_seconds: int|float}  $config
     */
    public function __construct(array $config)
    {
        $this->baseUrl = rtrim((string) $config['base_url'], '/');
        $this->timeout = max(0.1, (float) $config['timeout_seconds']);
        $this->connectTimeout = max(0.1, (float) $config['connect_timeout_seconds']);
        $this->maxAttempts = max(1, (int) $config['max_attempts']);
        $this->backoffMs = max(0, (int) $config['backoff_ms']);
        $this->batchSize = min(self::MAX_BATCH_SIZE, max(1, (int) $config['batch_size']));
        $this->maxPages = max(1, (int) $config['max_pages']);
        $this->maxTotalMs = max(0, (int) round((float) $config['max_total_seconds'] * 1000));
    }

    /**
     * @param  list<OsvQuery>  $queries
     * @return list<OsvQueryResult> sejajar dengan urutan $queries
     */
    public function query(array $queries): array
    {
        if ($queries === []) {
            return [];
        }

        $deadlineMs = $this->nowMs() + $this->maxTotalMs;

        // Pasangan (nama, versi) yang sama hanya ditanyakan sekali.
        $unique = [];
        $uniqueIndex = [];
        $slots = [];

        foreach ($queries as $query) {
            $key = $query->name."\0".$query->version;

            if (! isset($uniqueIndex[$key])) {
                $uniqueIndex[$key] = count($unique);
                $unique[] = $query;
            }

            $slots[] = $uniqueIndex[$key];
        }

        /** @var array<int, array<string, OsvVulnerabilityRef>> $found */
        $found = array_fill(0, count($unique), []);
        /** @var array<int, OsvFailureReason> $failures */
        $failures = [];
        /** @var array<int, string> $tokens */
        $tokens = [];
        /** @var array<int, array<string, true>> $seenTokens */
        $seenTokens = [];

        $pending = array_keys($unique);
        $round = 0;

        while ($pending !== []) {
            if ($round >= $this->maxPages) {
                $this->failAll($failures, $pending, OsvFailureReason::PaginationIncomplete);
                break;
            }

            if ($this->remainingMs($deadlineMs) <= 0) {
                $this->failAll($failures, $pending, OsvFailureReason::TimeBudgetExhausted);
                break;
            }

            $round++;
            $next = [];

            foreach (array_chunk($pending, $this->batchSize) as $batch => $chunk) {
                try {
                    $answers = $this->sendBatch($this->payload($unique, $chunk, $tokens), count($chunk), $deadlineMs, $round, $batch);
                } catch (OsvRequestException $e) {
                    $this->failAll($failures, $chunk, $e->reason);

                    continue;
                }

                foreach ($chunk as $position => $index) {
                    [$refs, $token] = $answers[$position];

                    foreach ($refs as $ref) {
                        $found[$index][$ref->id] ??= $ref;
                    }

                    if ($token === null) {
                        continue;
                    }

                    // Token yang sama berulang untuk query yang sama = pagination macet.
                    if (isset($seenTokens[$index][$token])) {
                        $failures[$index] = OsvFailureReason::PaginationIncomplete;

                        continue;
                    }

                    $seenTokens[$index][$token] = true;
                    $tokens[$index] = $token;
                    $next[] = $index;
                }
            }

            $pending = $next;
        }

        return array_map(
            fn (int $index) => isset($failures[$index])
                ? OsvQueryResult::failed($failures[$index])
                : OsvQueryResult::ok(array_values($found[$index])),
            $slots,
        );
    }

    /**
     * @param  array<int, OsvFailureReason>  $failures
     * @param  list<int>  $indexes
     */
    private function failAll(array &$failures, array $indexes, OsvFailureReason $reason): void
    {
        foreach ($indexes as $index) {
            $failures[$index] = $reason;
        }
    }

    /**
     * @param  array<int, OsvQuery>  $unique
     * @param  list<int>  $chunk
     * @param  array<int, string>  $tokens
     * @return list<array<string, mixed>>
     */
    private function payload(array $unique, array $chunk, array $tokens): array
    {
        return array_map(fn (int $index) => [
            'package' => ['name' => $unique[$index]->name, 'ecosystem' => 'npm'],
            'version' => $unique[$index]->version,
        ] + (isset($tokens[$index]) ? ['page_token' => $tokens[$index]] : []), $chunk);
    }

    /**
     * @param  list<array<string, mixed>>  $queries
     * @return list<array{0: list<OsvVulnerabilityRef>, 1: string|null}>
     *
     * @throws OsvRequestException
     */
    private function sendBatch(array $queries, int $count, int $deadlineMs, int $round, int $batch): array
    {
        for ($attempt = 1; ; $attempt++) {
            $remainingMs = $this->remainingMs($deadlineMs);

            if ($remainingMs <= 0) {
                throw $this->giveUp(OsvFailureReason::TimeBudgetExhausted, null, $attempt, $batch, $round);
            }

            $status = null;

            try {
                $response = $this->request($remainingMs)->post('/v1/querybatch', ['queries' => $queries]);
                $status = $response->status();

                return $this->parse($response, $count);
            } catch (ConnectionException) {
                $reason = OsvFailureReason::Timeout;
                $retryable = true;
            } catch (OsvRequestException $e) {
                [$reason, $retryable] = [$e->reason, $e->retryable];
            }

            if (! $retryable || $attempt >= $this->maxAttempts) {
                throw $this->giveUp($reason, $status, $attempt, $batch, $round);
            }

            Log::warning('OsvClient: percobaan gagal, akan dicoba ulang.', $this->context($reason, $status, $attempt, $batch, $round));

            $backoffMs = $this->backoffMs * (2 ** ($attempt - 1));

            // Tidak tidur jika sisa anggaran tidak cukup untuk menunggu lalu mencoba lagi.
            if ($backoffMs >= $this->remainingMs($deadlineMs)) {
                throw $this->giveUp(OsvFailureReason::TimeBudgetExhausted, $status, $attempt, $batch, $round);
            }

            Sleep::for($backoffMs)->milliseconds();
        }
    }

    private function request(int $remainingMs): PendingRequest
    {
        $timeout = min($this->timeout, $remainingMs / 1000);

        return Http::baseUrl($this->baseUrl)
            ->withoutRedirecting()
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->connectTimeout(min($this->connectTimeout, $timeout));
    }

    /**
     * @return list<array{0: list<OsvVulnerabilityRef>, 1: string|null}>
     *
     * @throws OsvRequestException
     */
    private function parse(Response $response, int $count): array
    {
        $status = $response->status();

        if ($status === 429) {
            throw new OsvRequestException(OsvFailureReason::RateLimited, true, $status);
        }

        if ($status >= 500) {
            throw new OsvRequestException(OsvFailureReason::ServerError, true, $status);
        }

        if ($status < 200 || $status >= 300) {
            throw new OsvRequestException(OsvFailureReason::ClientError, false, $status);
        }

        $results = $response->json('results');

        if (! is_array($results) || ! array_is_list($results) || count($results) !== $count) {
            throw $this->invalid($status);
        }

        return array_map(fn (mixed $entry) => $this->entry($entry, $status), $results);
    }

    /**
     * @return array{0: list<OsvVulnerabilityRef>, 1: string|null}
     */
    private function entry(mixed $entry, int $status): array
    {
        if (! is_array($entry) || ($entry !== [] && array_is_list($entry))) {
            throw $this->invalid($status);
        }

        $vulns = $entry['vulns'] ?? [];

        if (! is_array($vulns) || ($vulns !== [] && ! array_is_list($vulns))) {
            throw $this->invalid($status);
        }

        $refs = [];

        foreach ($vulns as $vuln) {
            $id = is_array($vuln) ? ($vuln['id'] ?? null) : null;
            $modified = is_array($vuln) ? ($vuln['modified'] ?? null) : null;

            if (! is_string($id) || $id === '' || strlen($id) > self::MAX_ID_LENGTH
                || ($modified !== null && (! is_string($modified) || strlen($modified) > self::MAX_MODIFIED_LENGTH))) {
                throw $this->invalid($status);
            }

            $refs[] = new OsvVulnerabilityRef($id, $modified);
        }

        $token = $entry['next_page_token'] ?? null;

        if ($token !== null && (! is_string($token) || $token === '' || strlen($token) > self::MAX_TOKEN_LENGTH)) {
            throw $this->invalid($status);
        }

        return [$refs, $token];
    }

    private function invalid(int $status): OsvRequestException
    {
        return new OsvRequestException(OsvFailureReason::InvalidResponse, false, $status);
    }

    private function giveUp(OsvFailureReason $reason, ?int $status, int $attempt, int $batch, int $round): OsvRequestException
    {
        Log::warning('OsvClient: batch gagal.', $this->context($reason, $status, $attempt, $batch, $round));

        return new OsvRequestException($reason, false, $status);
    }

    /**
     * Konteks log: tanpa isi respons dan tanpa nama paket.
     *
     * @return array<string, int|string|null>
     */
    private function context(OsvFailureReason $reason, ?int $status, int $attempt, int $batch, int $round): array
    {
        return ['reason' => $reason->value, 'status' => $status, 'attempt' => $attempt, 'batch' => $batch, 'round' => $round];
    }

    private function nowMs(): int
    {
        return Carbon::now()->getTimestampMs();
    }

    private function remainingMs(int $deadlineMs): int
    {
        return $deadlineMs - $this->nowMs();
    }
}
