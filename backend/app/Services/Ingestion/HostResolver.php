<?php

namespace App\Services\Ingestion;

interface HostResolver
{
    /**
     * Seluruh alamat IP (IPv4 & IPv6) untuk sebuah host; kosong jika tidak dapat di-resolve.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
