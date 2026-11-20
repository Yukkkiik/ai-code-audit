<?php

namespace App\Services\Sca;

use InvalidArgumentException;

/**
 * Satu pertanyaan ke OSV: paket npm pada versi pasti. Komponen tanpa versi pasti tidak dapat dibuat
 * (CLAUDE.md §8), sehingga tidak mungkin terkirim ke OSV.
 */
final readonly class OsvQuery
{
    private const MAX_NAME_LENGTH = 214;

    public function __construct(
        public string $name,
        public string $version,
    ) {
        if ($name === '' || strlen($name) > self::MAX_NAME_LENGTH || ! mb_check_encoding($name, 'UTF-8')) {
            throw new InvalidArgumentException('Nama paket tidak valid untuk query OSV.');
        }

        if (ExactVersion::parse($version) !== $version) {
            throw new InvalidArgumentException('Query OSV hanya menerima versi semver pasti.');
        }
    }
}
