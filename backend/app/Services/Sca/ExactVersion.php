<?php

namespace App\Services\Sca;

/**
 * Satu-satunya definisi "versi semver pasti" untuk semua parser SCA.
 */
final class ExactVersion
{
    private const SEMVER = '(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?';

    /**
     * @return string|null versi tanpa awalan `=`/`v`; null jika bukan versi pasti
     */
    public static function parse(string $spec): ?string
    {
        return preg_match('/^=?v?('.self::SEMVER.')$/', trim($spec), $m) === 1 ? $m[1] : null;
    }
}
