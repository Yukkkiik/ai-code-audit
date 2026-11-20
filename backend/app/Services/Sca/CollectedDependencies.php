<?php

namespace App\Services\Sca;

/**
 * Hasil gabungan seluruh proyek npm dalam satu audit. `foundProjects` false berarti tidak ada
 * package.json (tahap SCA dilewati). `failures` berisi manifest yang gagal dibaca; dependensi
 * dari manifest lain tetap ada, sehingga pemanggil dapat menyatakan tahap sebagai sebagian.
 */
final readonly class CollectedDependencies
{
    /**
     * @param  list<ParsedDependency>  $dependencies
     * @param  list<ManifestFailure>  $failures
     */
    public function __construct(
        public bool $foundProjects,
        public array $dependencies,
        public array $failures,
    ) {}
}
