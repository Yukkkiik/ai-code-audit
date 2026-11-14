<?php

namespace Tests\Support;

use App\Services\Ingestion\HostResolver;

/**
 * Resolver DNS palsu untuk test (tidak menyentuh jaringan).
 */
class FakeHostResolver implements HostResolver
{
    /**
     * @param  array<string, list<string>>  $records  host => daftar IP
     */
    public function __construct(private array $records = []) {}

    public function resolve(string $host): array
    {
        return $this->records[$host] ?? [];
    }
}
