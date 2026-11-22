<?php

namespace App\Services\Sca;

/**
 * Versi semver ketat (semver.org 2.0.0) dengan perbandingan presedensi. Tanpa awalan `v`/`=`, tanpa spasi,
 * tanpa nol di depan, wajib tiga segmen. Metadata `+build` diabaikan dalam perbandingan. Angka dibandingkan
 * sebagai string (panjang lalu leksikal) sehingga tidak overflow.
 */
final readonly class SemverVersion
{
    private const MAX_BYTES = 100;

    private const IDENTIFIER = '(?:0|[1-9]\d*|\d*[A-Za-z-][0-9A-Za-z-]*)';

    /**
     * @param  list<string>  $prerelease
     */
    private function __construct(
        public string $original,
        public string $major,
        public string $minor,
        public string $patch,
        public array $prerelease,
    ) {}

    public static function tryParse(string $version): ?self
    {
        if (strlen($version) > self::MAX_BYTES) {
            return null;
        }

        $pattern = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-('.self::IDENTIFIER.'(?:\.'.self::IDENTIFIER.')*))?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/D';

        if (preg_match($pattern, $version, $m) !== 1) {
            return null;
        }

        return new self($version, $m[1], $m[2], $m[3], isset($m[4]) && $m[4] !== '' ? explode('.', $m[4]) : []);
    }

    public function isPrerelease(): bool
    {
        return $this->prerelease !== [];
    }

    /**
     * @return int negatif, 0, atau positif (dalam presedensi semver)
     */
    public function compareTo(self $other): int
    {
        foreach ([[$this->major, $other->major], [$this->minor, $other->minor], [$this->patch, $other->patch]] as [$a, $b]) {
            $order = self::compareNumeric($a, $b);

            if ($order !== 0) {
                return $order;
            }
        }

        return self::comparePrerelease($this->prerelease, $other->prerelease);
    }

    private static function compareNumeric(string $a, string $b): int
    {
        return strlen($a) <=> strlen($b) ?: strcmp($a, $b) <=> 0;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function comparePrerelease(array $a, array $b): int
    {
        // Versi tanpa prerelease lebih besar daripada versi berprerelease.
        if ($a === [] || $b === []) {
            return ($a === [] ? 1 : 0) <=> ($b === [] ? 1 : 0);
        }

        foreach (array_map(null, array_slice($a, 0, min(count($a), count($b))), array_slice($b, 0, min(count($a), count($b)))) as [$x, $y]) {
            $xNumeric = ctype_digit($x);
            $yNumeric = ctype_digit($y);

            $order = match (true) {
                $xNumeric && $yNumeric => self::compareNumeric($x, $y),
                $xNumeric => -1,
                $yNumeric => 1,
                default => strcmp($x, $y) <=> 0,
            };

            if ($order !== 0) {
                return $order;
            }
        }

        return count($a) <=> count($b);
    }
}
