<?php

namespace App\Services\Sast;

use App\Services\Ingestion\RepositoryFileWalker;

/**
 * Memilih file JS/TS yang dianalisis SAST. Hanya membaca metadata file (ukuran), tidak membaca isi.
 * Symlink tidak diikuti (RepositoryFileWalker) dan realpath setiap file diverifikasi berada di dalam root.
 */
class JsTsFileSelector
{
    private RepositoryFileWalker $walker;

    /**
     * @param  list<string>  $extensions  tanpa titik, tidak peka huruf besar
     * @param  list<string>  $ignoredDirectories
     * @param  list<string>  $ignoredSuffixes  mis. ".min.js", ".d.ts" (tidak peka huruf besar)
     */
    public function __construct(
        private array $extensions,
        array $ignoredDirectories,
        private array $ignoredSuffixes,
        private int $maxFiles,
        private int $maxFileBytes,
    ) {
        $this->walker = new RepositoryFileWalker($ignoredDirectories);
        $this->ignoredSuffixes = array_map('strtolower', $ignoredSuffixes);
    }

    public function select(string $root): JsTsSelection
    {
        $realRoot = realpath($root);

        if ($realRoot === false) {
            return new JsTsSelection([]);
        }

        $files = [];
        $tooLarge = 0;
        $generated = 0;
        $overLimit = 0;

        foreach ($this->walker->files($realRoot, $this->extensions) as $path) {
            if ($this->isGenerated($path)) {
                $generated++;

                continue;
            }

            $full = $realRoot.'/'.$path;
            $size = @filesize($full);
            $real = realpath($full);

            if ($size === false || $size === 0 || $real === false || ! str_starts_with($real, $realRoot.DIRECTORY_SEPARATOR)) {
                continue;
            }

            if ($size > $this->maxFileBytes) {
                $tooLarge++;

                continue;
            }

            if (count($files) >= $this->maxFiles) {
                $overLimit++;

                continue;
            }

            $files[] = $path;
        }

        return new JsTsSelection($files, $tooLarge, $generated, $overLimit);
    }

    private function isGenerated(string $path): bool
    {
        $name = strtolower(basename($path));

        foreach ($this->ignoredSuffixes as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
