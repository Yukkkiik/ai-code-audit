<?php

namespace App\Services\Ingestion;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Penelusuran file repositori secara rekursif: symlink tidak pernah diikuti maupun didaftar,
 * dan direktori yang diabaikan (node_modules, vendor, .git) dilewati di kedalaman mana pun.
 */
class RepositoryFileWalker
{
    /**
     * @param  list<string>  $ignoredDirectories
     */
    public function __construct(private array $ignoredDirectories) {}

    /**
     * @param  list<string>|null  $extensions  filter ekstensi (tanpa titik, tidak peka huruf besar)
     * @return list<string> path relatif terhadap $root, dipisah "/", terurut
     */
    public function files(string $root, ?array $extensions = null): array
    {
        $root = rtrim($root, '/');
        $extensions = $extensions === null ? null : array_map('strtolower', $extensions);

        $filter = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function (SplFileInfo $file): bool {
                if ($file->isLink()) {
                    return false;
                }

                if ($file->isDir()) {
                    return ! in_array($file->getFilename(), $this->ignoredDirectories, true);
                }

                return $file->isFile();
            },
        );

        $files = [];

        foreach (new RecursiveIteratorIterator($filter) as $file) {
            if ($extensions !== null && ! in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
        }

        sort($files, SORT_STRING);

        return $files;
    }
}
