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
     * @param  list<string>  $workspacePaths  key entri workspace (tanpa segmen node_modules), relatif terhadap lockfile
     */
    private function __construct(
        public bool $supported,
        public ?ManifestType $type,
        public array $dependencies,
        public array $workspacePaths,
    ) {}

    /**
     * @param  list<ParsedDependency>  $dependencies
     * @param  list<string>  $workspacePaths
     */
    public static function supported(ManifestType $type, array $dependencies, array $workspacePaths = []): self
    {
        return new self(true, $type, $dependencies, $workspacePaths);
    }

    public static function unsupported(): self
    {
        return new self(false, null, [], []);
    }
}
