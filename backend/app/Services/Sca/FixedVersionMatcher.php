<?php

namespace App\Services\Sca;

use App\Enums\FixMatchReason;
use App\Enums\VersionChange;

/**
 * Mencocokkan versi terpasang dengan range afektasi OSV (`affected_ranges` per paket) dan menentukan versi
 * perbaikan untuk advisori itu. Murni: tanpa DB dan tanpa jaringan.
 *
 * Aturan: interval dibangun dari event yang diurutkan menurut semver (urutan kemunculan untuk versi yang sama);
 * `introduced` membuka (inklusif, "0" = tanpa batas bawah), `fixed` menutup (eksklusif), `last_affected`
 * menutup (inklusif) dan bukan versi perbaikan. Interval dari SEMUA range paket digabung. Versi perbaikan =
 * nilai `fixed` terkecil yang lebih besar dari versi terpasang dan sendiri tidak terdampak. Tidak pernah
 * menyimpulkan versi: hanya nilai `fixed` yang tertulis. Range GIT dan tipe lain diabaikan; `ECOSYSTEM`
 * diperlakukan seperti `SEMVER` (asumsi untuk npm). Event `limit`, versi bukan semver ketat, atau event yang
 * tidak berurutan wajar membuat hasil `undetermined`, bukan tebakan.
 */
class FixedVersionMatcher
{
    private const RANGE_TYPES = ['SEMVER', 'ECOSYSTEM'];

    private const EVENT_KINDS = ['introduced', 'fixed', 'last_affected'];

    private const MAX_RANGES = 100;

    private const MAX_EVENTS_PER_RANGE = 200;

    /**
     * @param  list<array{type: string, events: list<array<string, string>>}>|null  $affectedRanges  null = detail advisori tidak tersedia
     */
    public function match(string $installedVersion, ?array $affectedRanges): FixMatch
    {
        if ($affectedRanges === null) {
            return FixMatch::undetermined($installedVersion, FixMatchReason::DetailUnavailable);
        }

        $installed = SemverVersion::tryParse($installedVersion);

        if ($installed === null) {
            return FixMatch::undetermined($installedVersion, FixMatchReason::InvalidVersion);
        }

        if (count($affectedRanges) > self::MAX_RANGES) {
            return FixMatch::undetermined($installedVersion, FixMatchReason::UnevaluableRange);
        }

        $relevant = array_filter($affectedRanges, fn ($range) => is_array($range) && in_array($range['type'] ?? null, self::RANGE_TYPES, true));

        if ($relevant === []) {
            return FixMatch::undetermined($installedVersion, FixMatchReason::NoRangeData);
        }

        $intervals = [];
        $fixed = [];

        foreach ($relevant as $range) {
            $events = $range['events'] ?? null;
            $parsed = is_array($events) && count($events) <= self::MAX_EVENTS_PER_RANGE ? $this->intervals(array_values($events)) : null;

            // Satu range yang tidak dapat dievaluasi membuat jawaban apa pun tidak dapat dipastikan.
            if ($parsed === null) {
                return FixMatch::undetermined($installedVersion, FixMatchReason::UnevaluableRange);
            }

            array_push($intervals, ...$parsed[0]);
            array_push($fixed, ...$parsed[1]);
        }

        $merged = $this->merge($intervals);

        if (! $this->contains($merged, $installed)) {
            return FixMatch::undetermined($installedVersion, FixMatchReason::NotInListedRanges);
        }

        $candidates = array_values(array_filter($fixed, fn (SemverVersion $v) => $v->compareTo($installed) > 0));
        usort($candidates, fn (SemverVersion $a, SemverVersion $b) => $a->compareTo($b));

        foreach ($candidates as $candidate) {
            if (! $this->contains($merged, $candidate)) {
                return FixMatch::fixAvailable($installedVersion, $candidate->original, $this->change($installed, $candidate));
            }
        }

        return FixMatch::noFixRecorded($installedVersion);
    }

    /**
     * @param  list<mixed>  $events
     * @return array{0: list<array{0: SemverVersion|null, 1: SemverVersion|null, 2: bool}>, 1: list<SemverVersion>}|null null = tidak dapat dievaluasi
     */
    private function intervals(array $events): ?array
    {
        $parsed = [];

        foreach ($events as $event) {
            if (! is_array($event) || count($event) !== 1) {
                return null;
            }

            $kind = array_key_first($event);
            $value = $event[$kind];

            if (! in_array($kind, self::EVENT_KINDS, true) || ! is_string($value)) {
                return null;
            }

            $version = $kind === 'introduced' && $value === '0' ? null : SemverVersion::tryParse($value);

            if ($version === null && ! ($kind === 'introduced' && $value === '0')) {
                return null;
            }

            $parsed[] = [$kind, $version];
        }

        if ($parsed === []) {
            return null;
        }

        // Urut menurut versi ("0" paling awal); versi yang sama tetap dalam urutan kemunculan (usort stabil).
        usort($parsed, function (array $a, array $b): int {
            if ($a[1] === null || $b[1] === null) {
                return ($a[1] === null ? 0 : 1) <=> ($b[1] === null ? 0 : 1);
            }

            return $a[1]->compareTo($b[1]);
        });

        $intervals = [];
        $fixed = [];
        $open = false;
        $start = null;

        foreach ($parsed as [$kind, $version]) {
            if ($kind === 'introduced') {
                // introduced berulang sebelum ditutup: yang paling awal dipertahankan.
                if (! $open) {
                    [$open, $start] = [true, $version];
                }

                continue;
            }

            // Penutup tanpa pembuka = data tidak wajar.
            if (! $open) {
                return null;
            }

            $intervals[] = [$start, $version, $kind === 'last_affected'];
            $open = false;

            if ($kind === 'fixed') {
                $fixed[] = $version;
            }
        }

        if ($open) {
            $intervals[] = [$start, null, false];
        }

        return [$intervals, $fixed];
    }

    /**
     * Gabungkan interval yang bertumpang tindih atau bersambung menjadi himpunan terurut yang saling lepas.
     *
     * @param  list<array{0: SemverVersion|null, 1: SemverVersion|null, 2: bool}>  $intervals
     * @return list<array{0: SemverVersion|null, 1: SemverVersion|null, 2: bool}>
     */
    private function merge(array $intervals): array
    {
        // Interval kosong ([X, X) eksklusif) tidak memuat versi apa pun.
        $intervals = array_values(array_filter($intervals, function (array $i): bool {
            if ($i[0] === null || $i[1] === null) {
                return true;
            }

            $order = $i[0]->compareTo($i[1]);

            return $order < 0 || ($order === 0 && $i[2]);
        }));

        usort($intervals, function (array $a, array $b): int {
            if ($a[0] === null || $b[0] === null) {
                return ($a[0] === null ? 0 : 1) <=> ($b[0] === null ? 0 : 1);
            }

            return $a[0]->compareTo($b[0]);
        });

        $merged = [];

        foreach ($intervals as $interval) {
            $last = count($merged) - 1;

            if ($last >= 0 && ($merged[$last][1] === null || $interval[0] === null || $interval[0]->compareTo($merged[$last][1]) <= 0)) {
                if ($merged[$last][1] === null) {
                    continue;
                }

                if ($interval[1] === null) {
                    $merged[$last][1] = null;
                    $merged[$last][2] = false;
                } else {
                    $order = $interval[1]->compareTo($merged[$last][1]);

                    if ($order > 0 || ($order === 0 && $interval[2])) {
                        $merged[$last][1] = $interval[1];
                        $merged[$last][2] = $interval[2];
                    }
                }

                continue;
            }

            $merged[] = $interval;
        }

        return $merged;
    }

    /**
     * Pencarian biner pada interval terurut yang saling lepas.
     *
     * @param  list<array{0: SemverVersion|null, 1: SemverVersion|null, 2: bool}>  $merged
     */
    private function contains(array $merged, SemverVersion $version): bool
    {
        [$low, $high, $found] = [0, count($merged) - 1, -1];

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);

            if ($merged[$mid][0] === null || $merged[$mid][0]->compareTo($version) <= 0) {
                [$found, $low] = [$mid, $mid + 1];
            } else {
                $high = $mid - 1;
            }
        }

        if ($found === -1) {
            return false;
        }

        [, $end, $inclusive] = $merged[$found];

        if ($end === null) {
            return true;
        }

        $order = $version->compareTo($end);

        return $inclusive ? $order <= 0 : $order < 0;
    }

    private function change(SemverVersion $installed, SemverVersion $fixed): VersionChange
    {
        return match (true) {
            $installed->isPrerelease() || $fixed->isPrerelease() => VersionChange::Prerelease,
            $installed->major !== $fixed->major => VersionChange::Major,
            $installed->minor !== $fixed->minor => VersionChange::Minor,
            default => VersionChange::Patch,
        };
    }
}
