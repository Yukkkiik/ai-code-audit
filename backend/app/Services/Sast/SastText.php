<?php

namespace App\Services\Sast;

/**
 * Pembersih teks dari repositori target (pesan tool, nama file) sebelum masuk database atau log.
 */
final class SastText
{
    public static function clean(string $text, int $maxChars): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

        return mb_substr(trim($text), 0, $maxChars);
    }

    /**
     * Path relatif aman (dipisah "/"), atau null bila kosong, absolut, memuat ".." / NUL / drive letter.
     */
    public static function relativePath(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);

        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0") || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return null;
        }

        if (! mb_check_encoding($path, 'UTF-8') || mb_strlen($path) > 1024 || in_array('..', explode('/', $path), true)) {
            return null;
        }

        return $path;
    }
}
