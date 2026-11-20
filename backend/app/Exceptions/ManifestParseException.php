<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Satu berkas manifest npm tidak dapat dibaca atau tidak valid. Pesan aman ditampilkan
 * ke pengguna: tanpa path server maupun potongan isi berkas.
 */
class ManifestParseException extends RuntimeException
{
    public static function unreadable(): self
    {
        return new self('Berkas manifest tidak dapat dibaca.');
    }

    public static function tooLarge(): self
    {
        return new self('Berkas manifest melebihi batas ukuran yang diizinkan.');
    }

    public static function invalid(): self
    {
        return new self('Berkas manifest bukan JSON yang valid atau strukturnya tidak sesuai.');
    }
}
