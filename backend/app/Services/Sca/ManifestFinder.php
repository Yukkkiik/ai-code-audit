<?php

namespace App\Services\Sca;

use App\Services\Ingestion\RepositoryFileWalker;

/**
 * Mencari proyek npm di workspace. Hanya mencari berkas; isinya tidak dibaca di sini.
 * Lockfile tanpa `package.json` sejajar diabaikan (tidak ada daftar dependensi langsung).
 */
class ManifestFinder
{
    public function __construct(private RepositoryFileWalker $walker) {}

    /**
     * @return list<ManifestProject> terurut berdasarkan direktori
     */
    public function find(string $root): array
    {
        $packageJson = [];
        $lockfile = [];

        foreach ($this->walker->files($root, ['json']) as $path) {
            $directory = dirname($path) === '.' ? '' : dirname($path);

            match (basename($path)) {
                'package.json' => $packageJson[$directory] = $path,
                'package-lock.json' => $lockfile[$directory] = $path,
                default => null,
            };
        }

        ksort($packageJson, SORT_STRING);

        return array_map(
            fn (string $directory, string $path) => new ManifestProject($directory, $path, $lockfile[$directory] ?? null),
            array_keys($packageJson),
            $packageJson,
        );
    }
}
