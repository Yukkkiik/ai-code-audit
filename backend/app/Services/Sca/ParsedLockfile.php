<?php

namespace App\Services\Sca;

use App\Enums\ManifestType;

/**
 * Hasil parsing `package-lock.json`. Lockfile v1 / versi tidak dikenal bukan error:
 * `supported` false dan tanpa dependensi, sehingga pemanggil memakai aturan "tanpa lockfile".
 */
final readonly class ParsedLockfile
{
    /**
     * @param  list<ParsedDependency>  $dependencies
     */
    private function __construct(
        public bool $supported,
        public ?ManifestType $type,
        public array $dependencies,
    ) {}

    /**
     * @param  list<ParsedDependency>  $dependencies
     */
    public static function supported(ManifestType $type, array $dependencies): self
    {
        return new self(true, $type, $dependencies);
    }

    public static function unsupported(): self
    {
        return new self(false, null, []);
    }
}
