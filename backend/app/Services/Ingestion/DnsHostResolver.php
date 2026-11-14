<?php

namespace App\Services\Ingestion;

class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];

        // dns_get_record() memunculkan warning bila host tidak punya record AAAA;
        // nilai false ditangani eksplisit di bawah (berarti tidak ada IPv6).
        $records = @dns_get_record($host, DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
