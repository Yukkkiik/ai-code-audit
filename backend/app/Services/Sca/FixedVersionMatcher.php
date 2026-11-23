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
 *
 * Daftar `versions` eksplisit OSV (A-38): versi terpasang terdampak bila ada di interval ATAU kuncinya (string tanpa
 * `+build`) ada di daftar; kandidat `fixed` yang tertulis di daftar dilewati. Ketiadaan di daftar tidak pernah
 * membuktikan "tidak terdampak". Daftar yang tidak diketahui (null) memblokir hasil.
 */
class FixedVersionMatcher
{
    private const RANGE_TYPES = ['SEMVER', 'ECOSYSTEM'];

    private const EVENT_KINDS = ['introduced', 'fixed', 'last_affected'];

    private const MAX_RANGES = 100;

    private const MAX_EVENTS_PER_RANGE = 200;

    /**
     * Anggaran total interval (setelah digabung per advisori) pada pencocokan gabungan. Usulan, belum divalidasi
     * pada data nyata.
     */
    public const MAX_COMBINED_INTERVALS = 20000;

    /**
     * Anggaran total kunci `versions` distinct pada pencocokan gabungan. Usulan, belum divalidasi pada data nyata.
     */
    public const MAX_COMBINED_VERSIONS = 20000;

    /**
     * Pengganti untuk range tersimpan yang tidak dapat dibaca (JSON rusak/bukan array): bentuk yang pasti
     * `unevaluable_range`, agar pemanggil tidak perlu menebak.
     */
    public const UNREADABLE_RANGES = [['type' => 'SEMVER', 'events' => null]];

    /**
     * Urutan alasan bila beberapa advisori undetermined pada pencocokan gabungan; yang pertama menang.
     *
     * @var list<FixMatchReason>
     */
    private const COMBINED_REASON_PRIORITY = [
        FixMatchReason::DetailUnavailable,
        FixMatchReason::InvalidVersion,
        FixMatchReason::UnevaluableRange,
        FixMatchReason::NoRangeData,
        FixMatchReason::NotInListedRanges,
    ];

    /**
     * @param  AdvisoryAffects|list<array{type: string, events: list<array<string, string>>}>|null  $affects  array/null = jalur lama (daftar `versions` kosong; null = detail advisori tidak tersedia)
     */
    public function match(string $installedVersion, AdvisoryAffects|array|null $affects): FixMatch
    {
        $installed = SemverVersion::tryParse($installedVersion);
        $evaluation = $this->evaluate(AdvisoryAffects::from($affects), $installed);

        if ($evaluation instanceof FixMatchReason) {
            return FixMatch::undetermined($installedVersion, $evaluation);
        }

        [$merged, $fixed, $keys, $affected] = $evaluation;

        if (! $affected) {
            return FixMatch::undetermined($installedVersion, FixMatchReason::NotInListedRanges);
        }

        return $this->smallestFix($installedVersion, $installed, $merged, $fixed, $keys);
    }

    /**
     * Versi perbaikan gabungan untuk SEMUA advisori satu dependency: nilai `fixed` terkecil di atas versi terpasang
     * yang tidak terdampak oleh advisori mana pun yang diberikan (interval ATAU daftar `versions`). Versi itu tidak
     * ditanyakan ke OSV; advisori yang hanya berlaku pada versi tersebut tidak ada di data ini.
     *
     * Fase 1 mengevaluasi tiap advisori terpisah (`evaluate()`, sama seperti `match()`); satu advisori yang tidak
     * dapat dipastikan membuat seluruh hasil `undetermined`, dengan alasan berprioritas (COMBINED_REASON_PRIORITY).
     * Fase 2 hanya jalan bila semua advisori terdampak. Advisori dibaca satu per satu (boleh generator) dan hanya
     * interval, nilai `fixed`, dan kunci versinya yang disimpan. Hasil tidak bergantung pada urutan advisori.
     *
     * @param  iterable<AdvisoryAffects|list<array{type: string, events: list<array<string, string>>}>|null>  $advisories  array/null = jalur lama
     */
    public function matchCombined(string $installedVersion, iterable $advisories): FixMatch
    {
        $installed = SemverVersion::tryParse($installedVersion);
        $reasons = [];
        $intervals = [];
        $fixed = [];
        $keys = [];
        $total = 0;
        $seen = 0;
        $overBudget = false;

        foreach ($advisories as $advisory) {
            $seen++;
            $evaluation = $this->evaluate(AdvisoryAffects::from($advisory), $installed);

            if ($evaluation instanceof FixMatchReason) {
                $reasons[$evaluation->value] = true;

                continue;
            }

            [$merged, $advisoryFixed, $advisoryKeys, $affected] = $evaluation;
            $total += count($merged);
            $keys += $advisoryKeys;

            if (! $affected) {
                $reasons[FixMatchReason::NotInListedRanges->value] = true;
            }

            // Hanya kandidat (di atas versi terpasang), tanpa duplikat; jumlah distinctnya ikut anggaran.
            foreach ($advisoryFixed as $version) {
                if (! isset($fixed[$version->original]) && $version->compareTo($installed) > 0) {
                    $fixed[$version->original] = $version;
                }
            }

            // Setelah anggaran terlampaui hasilnya sudah pasti undetermined; berhenti menyimpan agar memori terbatas.
            if ($total > self::MAX_COMBINED_INTERVALS || count($fixed) > self::MAX_COMBINED_INTERVALS || count($keys) > self::MAX_COMBINED_VERSIONS) {
                $overBudget = true;
                $intervals = $fixed = $keys = [];
            } elseif (! $overBudget) {
                array_push($intervals, ...$merged);
            }
        }

        if ($seen === 0) {
            $reasons[FixMatchReason::NoRangeData->value] = true;
        }

        // Dihitung dari total semua advisori yang dapat dianalisis (bukan berhenti di tengah), agar urutan tidak berpengaruh.
        if ($overBudget) {
            $reasons[FixMatchReason::UnevaluableRange->value] = true;
        }

        foreach (self::COMBINED_REASON_PRIORITY as $reason) {
            if (isset($reasons[$reason->value])) {
                return FixMatch::undetermined($installedVersion, $reason);
            }
        }

        return $this->smallestFix($installedVersion, $installed, $this->merge($intervals), array_values($fixed), $keys);
    }

    /**
     * Evaluasi satu advisori, urutan pemeriksaan (yang pertama kena menang): detail tidak ada; versi terpasang tidak
     * valid; daftar `versions` tidak diketahui; range tak dapat dievaluasi (memblokir walau versi terpasang tertulis
     * di `versions`); tidak ada range dan `versions` kosong. Selebihnya hasilnya adalah interval gabungan, nilai
     * `fixed`, kunci `versions`, dan apakah versi terpasang terdampak (di interval ATAU kuncinya di `versions`).
     *
     * @return FixMatchReason|array{0: list<array{0: SemverVersion|null, 1: SemverVersion|null, 2: bool}>, 1: list<SemverVersion>, 2: array<string, true>, 3: bool}
     */
    private function evaluate(AdvisoryAffects $affects, ?SemverVersion $installed): FixMatchReason|array
    {
        if ($affects->ranges === null) {
            return FixMatchReason::DetailUnavailable;
        }

        if ($installed === null) {
            return FixMatchReason::InvalidVersion;
        }

        if ($affects->versions === null) {
            return FixMatchReason::UnevaluableRange;
        }

        $analysis = $this->analyze($affects->ranges);

        // Tanpa range SEMVER/ECOSYSTEM: advisori yang hanya memakai `versions` tetap dapat dievaluasi.
        if ($analysis === FixMatchReason::NoRangeData && $affects->versions !== []) {
            $analysis = [[], []];
        }

        if ($analysis instanceof FixMatchReason) {
            return $analysis;
        }

        [$merged, $fixed] = $analysis;
        $keys = $this->versionKeys($affects->versions);

        return [$merged, $fixed, $keys, $this->contains($merged, $installed) || isset($keys[self::key($installed->original)])];
    }

    /**
     * Kunci pencocokan `versions`: string versi dipotong di "+" pertama (metadata build diabaikan).
     */
    private static function key(string $version): string
    {
        return explode('+', $version, 2)[0];
    }

    /**
     * Hanya entri semver ketat yang dapat cocok; entri lain (nilai sampah) tidak pernah cocok.
     *
     * @param  list<mixed>  $versions
     * @return array<string, true>
     */
    private function versionKeys(array $versions): array
    {
        $keys = [];

        foreach ($versions as $version) {
            if (is_string($version) && SemverVersion::tryParse($version) !== null) {
                $keys[self::key($version)] = true;
            }
        }

        return $keys;
    }

    /**
     * Evaluasi satu advisori: alasan `undetermined` ATAU [interval gabungan, daftar nilai fixed].
     *
     * @param  list<array{type: string, events: list<array<string, string>>}>  $affectedRanges
     * @return FixMatchReason|array{0: list<array{0: SemverVersion|null, 1: SemverVersion|null, 2: bool}>, 1: list<SemverVersion>}
     */
    private function analyze(array $affectedRanges): FixMatchReason|array
    {
        if (count($affectedRanges) > self::MAX_RANGES) {
            return FixMatchReason::UnevaluableRange;
        }

        $relevant = array_filter($affectedRanges, fn ($range) => is_array($range) && in_array($range['type'] ?? null, self::RANGE_TYPES, true));

        if ($relevant === []) {
            return FixMatchReason::NoRangeData;
        }

        $intervals = [];
        $fixed = [];

        foreach ($relevant as $range) {
            $events = $range['events'] ?? null;
            $parsed = is_array($events) && count($events) <= self::MAX_EVENTS_PER_RANGE ? $this->intervals(array_values($events)) : null;

            // Satu range yang tidak dapat dievaluasi membuat jawaban apa pun tidak dapat dipastikan.
            if ($parsed === null) {
                return FixMatchReason::UnevaluableRange;
            }

            array_push($intervals, ...$parsed[0]);
            array_push($fixed, ...$parsed[1]);
        }

        return [$this->merge($intervals), $fixed];
    }

    /**
     * Versi perbaikan = nilai `fixed` terkecil yang lebih besar dari versi terpasang dan sendiri tidak terdampak
     * (tidak di interval dan kuncinya tidak tertulis di `versions`).
     *
     * @param  list<array{0: SemverVersion|null, 1: SemverVersion|null, 2: bool}>  $merged
     * @param  list<SemverVersion>  $fixed
     * @param  array<string, true>  $keys
     */
    private function smallestFix(string $installedVersion, SemverVersion $installed, array $merged, array $fixed, array $keys): FixMatch
    {
        $candidates = array_values(array_filter($fixed, fn (SemverVersion $v) => $v->compareTo($installed) > 0));
        usort($candidates, fn (SemverVersion $a, SemverVersion $b) => $a->compareTo($b));

        foreach ($candidates as $candidate) {
            if (! $this->contains($merged, $candidate) && ! isset($keys[self::key($candidate->original)])) {
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
        $compare = function (array $a, array $b): int {
            if ($a[1] === null || $b[1] === null) {
                return ($a[1] === null ? 0 : 1) <=> ($b[1] === null ? 0 : 1);
            }

            return $a[1]->compareTo($b[1]);
        };

        // Data OSV hampir selalu sudah terurut: satu lintasan O(n) menggantikan sort bila memang sudah terurut
        // (hasil identik karena usort stabil).
        for ($i = 1, $n = count($parsed); $i < $n; $i++) {
            if ($compare($parsed[$i - 1], $parsed[$i]) > 0) {
                usort($parsed, $compare);

                break;
            }
        }

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

        // Interval identik (range yang sama dituliskan berulang) tidak mengubah hasil; buang sebelum diurutkan.
        $unique = [];

        foreach ($intervals as $interval) {
            $unique[($interval[0]?->original ?? "\0").'|'.($interval[1]?->original ?? "\0").'|'.(int) $interval[2]] = $interval;
        }

        $intervals = array_values($unique);

        $compare = function (array $a, array $b): int {
            if ($a[0] === null || $b[0] === null) {
                return ($a[0] === null ? 0 : 1) <=> ($b[0] === null ? 0 : 1);
            }

            return $a[0]->compareTo($b[0]);
        };

        // Lewati sort bila sudah terurut (hasil identik karena usort stabil).
        for ($i = 1, $n = count($intervals); $i < $n; $i++) {
            if ($compare($intervals[$i - 1], $intervals[$i]) > 0) {
                usort($intervals, $compare);

                break;
            }
        }

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
