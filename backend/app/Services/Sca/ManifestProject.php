<?php

namespace App\Services\Sca;

/**
 * Satu proyek npm: `package.json` beserta `package-lock.json` yang sejajar (jika ada).
 * Semua path relatif terhadap akar repositori, dipisah "/"; `directory` kosong = akar.
 */
final readonly class ManifestProject
{
    public function __construct(
        public string $directory,
        public string $packageJsonPath,
        public ?string $lockfilePath,
    ) {}
}
