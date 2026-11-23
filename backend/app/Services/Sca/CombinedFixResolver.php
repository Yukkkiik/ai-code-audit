<?php

namespace App\Services\Sca;

use App\Enums\FixMatchReason;
use App\Models\Vulnerability;
use Generator;
use Illuminate\Support\Facades\DB;

/**
 * Versi perbaikan gabungan untuk satu dependency: membaca `affected_ranges` semua advisori dependency itu pada
 * audit yang sama (satu query) lalu menyerahkannya ke FixedVersionMatcher::matchCombined().
 */
class CombinedFixResolver
{
    /**
     * Batas advisori per dependency. Usulan, belum divalidasi pada data nyata.
     */
    public const MAX_ADVISORIES = 200;

    /**
     * Kedalaman JSON `affected_ranges` yang valid adalah 4 (list, range, list event, event); sisanya tidak wajar.
     */
    private const JSON_DEPTH = 8;

    public function __construct(private readonly FixedVersionMatcher $matcher) {}

    /**
     * `audit_scan_id` dan `dependency_id` diambil dari baris yang sudah ditemukan, bukan dari input pengguna.
     * `dependency` harus sudah dimuat.
     */
    public function resolve(Vulnerability $vulnerability): FixMatch
    {
        $installed = (string) $vulnerability->dependency->version;

        $rows = DB::table('vulnerabilities')
            ->select(['id', 'affected_ranges'])
            ->where('audit_scan_id', $vulnerability->audit_scan_id)
            ->where('dependency_id', $vulnerability->dependency_id)
            ->orderBy('id')
            ->limit(self::MAX_ADVISORIES + 1)
            ->get()
            ->all();

        if (count($rows) > self::MAX_ADVISORIES) {
            return FixMatch::undetermined($installed, FixMatchReason::UnevaluableRange);
        }

        return $this->matcher->matchCombined($installed, $this->decoded($rows));
    }

    /**
     * Decode satu per satu dan buang baris mentahnya agar hanya satu JSON hidup pada satu waktu.
     *
     * @param  list<object{id: int, affected_ranges: ?string}>  $rows
     * @return Generator<int, array<mixed>|null>
     */
    private function decoded(array $rows): Generator
    {
        while (($row = array_shift($rows)) !== null) {
            $json = $row->affected_ranges;
            unset($row);

            if ($json === null) {
                yield null;

                continue;
            }

            $decoded = json_decode($json, true, self::JSON_DEPTH);
            unset($json);

            // JSON literal `null` sama artinya dengan kolom NULL; selain array (atau JSON rusak) tidak dapat dievaluasi.
            if ($decoded === null && json_last_error() === JSON_ERROR_NONE) {
                yield null;
            } else {
                yield is_array($decoded) ? $decoded : FixedVersionMatcher::UNREADABLE_RANGES;
            }
        }
    }
}
