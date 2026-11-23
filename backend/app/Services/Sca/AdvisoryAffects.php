<?php

namespace App\Services\Sca;

/**
 * Data afektasi satu advisori untuk satu paket: `ranges` (null = detail advisori tidak tersedia) dan `versions`,
 * daftar versi eksplisit OSV (null = TIDAK DIKETAHUI, bukan kosong; `[]` = OSV tidak mencantumkan). Daftar yang
 * tidak diketahui memblokir pencocokan; ketiadaan versi pada daftar tidak pernah membuktikan "tidak terdampak".
 */
final readonly class AdvisoryAffects
{
    /**
     * @param  list<array{type: string, events: list<array<string, string>>}>|null  $ranges
     * @param  list<string>|null  $versions
     */
    private function __construct(public ?array $ranges, public ?array $versions) {}

    /**
     * Jalur lama (sebelum daftar `versions` disimpan): daftar versi dianggap kosong.
     *
     * @param  list<array{type: string, events: list<array<string, string>>}>|null  $ranges
     */
    public static function fromRanges(?array $ranges): self
    {
        return new self($ranges, []);
    }

    /**
     * @param  list<array{type: string, events: list<array<string, string>>}>|null  $ranges
     * @param  list<string>|null  $versions
     */
    public static function of(?array $ranges, ?array $versions): self
    {
        return new self($ranges, $versions);
    }

    /**
     * Titik masuk tunggal untuk masukan lama (array rentang atau null) maupun AdvisoryAffects.
     *
     * @param  self|list<array{type: string, events: list<array<string, string>>}>|null  $value
     */
    public static function from(self|array|null $value): self
    {
        return $value instanceof self ? $value : self::fromRanges($value);
    }
}
