<?php

namespace App\Services\Sast;

/**
 * Mengambil cuplikan kode di sekitar temuan dari workspace. Hanya file biasa di dalam root (symlink ditolak),
 * dan hanya file yang tidak melebihi batas ukuran seleksi. Baris dipotong dan dibersihkan dari karakter kontrol.
 */
class SnippetReader
{
    private ?string $cachedPath = null;

    /** @var list<string> */
    private array $cachedLines = [];

    public function __construct(
        private int $maxLines,
        private int $maxLineChars,
        private int $maxFileBytes,
    ) {}

    public function read(string $root, string $relativePath, ?int $lineStart, ?int $lineEnd): ?string
    {
        if ($lineStart === null || SastText::relativePath($relativePath) === null) {
            return null;
        }

        $lines = $this->lines($root, $relativePath);

        $first = $lineStart - 1;
        $last = min(($lineEnd ?? $lineStart) - 1, $first + $this->maxLines - 1, count($lines) - 1);

        if ($first < 0 || $first > $last) {
            return null;
        }

        $snippet = array_map(
            fn (string $line) => mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $line) ?? '', 0, $this->maxLineChars),
            array_slice($lines, $first, $last - $first + 1),
        );

        return rtrim(implode("\n", $snippet)) ?: null;
    }

    /**
     * @return list<string>
     */
    private function lines(string $root, string $relativePath): array
    {
        if ($this->cachedPath === $root.'/'.$relativePath) {
            return $this->cachedLines;
        }

        $this->cachedPath = $root.'/'.$relativePath;
        $this->cachedLines = [];

        $full = $root.'/'.$relativePath;
        $realRoot = realpath($root);
        $real = realpath($full);

        if ($realRoot === false || $real === false || is_link($full) || ! is_file($real)
            || ! str_starts_with($real, $realRoot.DIRECTORY_SEPARATOR)
            || (int) filesize($real) > $this->maxFileBytes) {
            return [];
        }

        $content = @file_get_contents($real);

        if ($content === false) {
            return [];
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        return $this->cachedLines = preg_split('/\R/u', $content) ?: [];
    }
}
