<?php

namespace App\Services\Sast;

final readonly class NormalizedFindings
{
    /**
     * @param  list<SastFinding>  $findings  terurut: severity menurun, lalu path, baris, rule
     * @param  int  $duplicates  temuan yang dibuang sebagai duplikat
     * @param  int  $truncated  temuan yang dibuang karena melewati batas jumlah (severity terendah dibuang lebih dulu)
     */
    public function __construct(
        public array $findings,
        public int $duplicates,
        public int $truncated,
    ) {}
}
