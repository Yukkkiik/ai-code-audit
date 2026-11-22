<?php

namespace App\Services\Sast;

/**
 * Hasil seleksi file JS/TS untuk SAST. `files` = path relatif (dipisah "/", terurut).
 */
final readonly class JsTsSelection
{
    /**
     * @param  list<string>  $files
     */
    public function __construct(
        public array $files,
        public int $skippedTooLarge = 0,
        public int $skippedGenerated = 0,
        public int $skippedOverLimit = 0,
    ) {}

    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    public function isTruncated(): bool
    {
        return $this->skippedOverLimit > 0;
    }
}
