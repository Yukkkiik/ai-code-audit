<?php

namespace App\Services\Sca;

use App\Exceptions\ManifestParseException;
use JsonException;

/**
 * Membaca satu berkas manifest JSON dengan aman. Berkas harus berada di dalam $root setelah
 * semua symlink diselesaikan (termasuk symlink pada direktori induk), dan batas ukuran
 * ditegakkan saat membaca, bukan hanya lewat filesize().
 */
final class ManifestJsonReader
{
    private const MAX_JSON_DEPTH = 64;

    /**
     * @return mixed hasil json_decode (stdClass atau array sesuai $associative)
     *
     * @throws ManifestParseException
     */
    public static function read(string $root, string $relativePath, int $maxBytes, bool $associative): mixed
    {
        $rootReal = realpath($root);
        $path = rtrim($root, '/').'/'.$relativePath;

        if ($rootReal === false || is_link($path)) {
            throw ManifestParseException::unreadable();
        }

        $real = realpath($path);

        if ($real === false || ! str_starts_with($real, $rootReal.DIRECTORY_SEPARATOR) || ! is_file($real)) {
            throw ManifestParseException::unreadable();
        }

        $size = filesize($real);

        if ($size === false) {
            throw ManifestParseException::unreadable();
        }

        if ($size > $maxBytes) {
            throw ManifestParseException::tooLarge();
        }

        $handle = fopen($real, 'rb');

        if ($handle === false) {
            throw ManifestParseException::unreadable();
        }

        // Berkas bisa membesar setelah filesize(); baca paling banyak batas + 1 byte.
        $contents = stream_get_contents($handle, $maxBytes + 1);
        fclose($handle);

        if ($contents === false) {
            throw ManifestParseException::unreadable();
        }

        if (strlen($contents) > $maxBytes) {
            throw ManifestParseException::tooLarge();
        }

        try {
            return json_decode($contents, $associative, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ManifestParseException::invalid();
        }
    }
}
