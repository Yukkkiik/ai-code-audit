<?php

namespace App\Services\Sca;

/**
 * Satu manifest yang gagal dibaca. Path relatif repositori dan pesan dibersihkan dari karakter
 * kontrol/newline (agar aman dicatat dan ditampilkan) dan pesan dibatasi 300 karakter.
 */
final readonly class ManifestFailure
{
    private const MAX_LENGTH = 300;

    public string $path;

    public string $message;

    public function __construct(string $path, string $reason)
    {
        $this->path = mb_substr(self::clean($path), 0, self::MAX_LENGTH);
        $this->message = mb_substr($this->path.': '.self::clean($reason), 0, self::MAX_LENGTH);
    }

    private static function clean(string $text): string
    {
        return (string) preg_replace('/[\p{Cc}\p{Cf}]/u', '', mb_scrub($text, 'UTF-8'));
    }
}
