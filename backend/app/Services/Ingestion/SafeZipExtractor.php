<?php

namespace App\Services\Ingestion;

use App\Exceptions\IngestionException;
use ZipArchive;

/**
 * Ekstraksi ZIP tak tepercaya. Seluruh entri divalidasi SEBELUM ada file yang ditulis
 * (path traversal, path absolut, symlink, enkripsi, jumlah entri, ukuran, rasio kompresi);
 * saat menulis, jumlah byte tetap dihitung karena ukuran yang dideklarasikan arsip bisa palsu.
 * Kode di dalam arsip tidak pernah dijalankan.
 */
class SafeZipExtractor
{
    private const CHUNK_BYTES = 65536;

    private const S_IFMT = 0170000;

    private const S_IFLNK = 0120000;

    /**
     * @return int jumlah byte yang ditulis
     */
    public function extract(string $archivePath, string $destination): int
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw IngestionException::archiveCorrupt();
        }

        try {
            $entries = $this->inspect($zip, (int) filesize($archivePath));
            $written = 0;

            foreach ($entries as $entry) {
                $written += $this->writeEntry($zip, $entry, $destination, $written);
            }

            return $written;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<array{index: int, path: string, directory: bool, size: int}>
     */
    private function inspect(ZipArchive $zip, int $archiveBytes): array
    {
        $maxBytes = (int) config('audit.max_repository_bytes');

        if ($zip->numFiles > (int) config('audit.zip.max_entries')) {
            throw IngestionException::archiveTooLarge('Jumlah file di dalam arsip melebihi batas.');
        }

        $entries = [];
        $declaredTotal = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);

            if ($stat === false) {
                throw IngestionException::archiveCorrupt();
            }

            $path = $this->normalizePath($stat['name']);

            if ($this->isSymlink($zip, $index)) {
                throw IngestionException::zipSlip();
            }

            if ($stat['encryption_method'] !== ZipArchive::EM_NONE) {
                throw IngestionException::archiveEncrypted();
            }

            $declaredTotal += $stat['size'];

            if ($declaredTotal > $maxBytes) {
                throw IngestionException::archiveTooLarge('Ukuran hasil ekstraksi arsip melebihi batas.');
            }

            if ($path === null || $this->isIgnored($path)) {
                continue;
            }

            $entries[] = [
                'index' => $index,
                'path' => $path,
                'directory' => str_ends_with(str_replace('\\', '/', $stat['name']), '/'),
                'size' => $stat['size'],
            ];
        }

        if ($archiveBytes > 0 && $declaredTotal / $archiveBytes > (int) config('audit.zip.max_compression_ratio')) {
            throw IngestionException::archiveTooLarge('Rasio kompresi arsip tidak wajar (indikasi zip bomb).');
        }

        return $entries;
    }

    /**
     * Path relatif yang aman, atau null untuk entri root ("./").
     */
    private function normalizePath(string $name): ?string
    {
        if ($name === '' || str_contains($name, "\0")) {
            throw IngestionException::zipSlip();
        }

        $name = str_replace('\\', '/', $name);

        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) === 1) {
            throw IngestionException::zipSlip();
        }

        $segments = [];

        foreach (explode('/', $name) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw IngestionException::zipSlip();
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes);

        return $operatingSystem === ZipArchive::OPSYS_UNIX
            && (($attributes >> 16) & self::S_IFMT) === self::S_IFLNK;
    }

    private function isIgnored(string $path): bool
    {
        $ignored = (array) config('audit.ignored_directories');

        foreach (explode('/', $path) as $segment) {
            if (in_array($segment, $ignored, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{index: int, path: string, directory: bool, size: int}  $entry
     */
    private function writeEntry(ZipArchive $zip, array $entry, string $destination, int $writtenSoFar): int
    {
        $target = $destination.'/'.$entry['path'];

        if ($entry['directory']) {
            $this->ensureDirectory($target, $destination);

            return 0;
        }

        $this->ensureDirectory(dirname($target), $destination);

        $input = $zip->getStreamIndex($entry['index']);
        // Mode "x": gagal bila file sudah ada (entri duplikat), tidak pernah menimpa.
        $output = $input === false ? false : @fopen($target, 'xb');

        if ($input === false || $output === false) {
            throw IngestionException::archiveCorrupt('Arsip berisi entri yang tidak dapat diekstrak.');
        }

        $maxBytes = (int) config('audit.max_repository_bytes');
        $entryBytes = 0;

        try {
            while (! feof($input)) {
                $chunk = fread($input, self::CHUNK_BYTES);

                if ($chunk === false) {
                    throw IngestionException::archiveCorrupt();
                }

                $entryBytes += strlen($chunk);

                if ($entryBytes > $entry['size'] || $writtenSoFar + $entryBytes > $maxBytes) {
                    throw IngestionException::archiveTooLarge('Isi arsip melebihi ukuran yang dideklarasikan atau batas maksimum.');
                }

                fwrite($output, $chunk);
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        chmod($target, 0644);

        return $entryBytes;
    }

    private function ensureDirectory(string $directory, string $destination): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw IngestionException::archiveCorrupt('Arsip berisi struktur direktori yang tidak valid.');
        }

        $real = realpath($directory);
        $root = realpath($destination);

        if ($real === false || $root === false || ($real !== $root && ! str_starts_with($real, $root.DIRECTORY_SEPARATOR))) {
            throw IngestionException::zipSlip();
        }
    }
}
