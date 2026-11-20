<?php

namespace App\Services\Sca;

use App\Enums\AuditStatus;
use App\Enums\StageStatus;
use App\Models\AuditScan;

/**
 * `meta.scan` pada daftar hasil SCA: status tahap, pesan, cakupan, dan catatan yang disusun dari hitungan
 * agar hasil kosong atau tidak lengkap tidak terbaca sebagai "bebas kerentanan". Teks catatan tidak pernah
 * memakai kata "aman" atau "bersih".
 */
class ScaScanSummary
{
    private const UNFINISHED_MESSAGE = 'Pemeriksaan SCA tidak selesai karena pemrosesan audit berhenti sebelum tahap ini selesai.';

    /**
     * @param  bool  $vulnerabilityList  true untuk daftar kerentanan (catatan "tidak ditemukan advisori" berlaku)
     * @return array{status: string, message: string|null, coverage: array<string, int>, note: string|null}
     */
    public function build(AuditScan $audit, bool $vulnerabilityList): array
    {
        $coverage = ScaCoverage::of($audit);

        // Audit sudah berakhir tetapi tahap SCA tidak pernah selesai (mis. job mati): tanpa catatan yang terbaca bersih.
        $unfinished = in_array($audit->status, [AuditStatus::Completed, AuditStatus::Partial, AuditStatus::Failed], true)
            && in_array($audit->sca_status, [StageStatus::Pending, StageStatus::Running], true);

        return [
            'status' => $audit->sca_status->value,
            'message' => $unfinished ? self::UNFINISHED_MESSAGE : $audit->sca_error,
            'coverage' => $coverage->toArray(),
            'note' => $unfinished ? null : $this->note($audit, $coverage, $vulnerabilityList),
        ];
    }

    private function note(AuditScan $audit, ScaCoverage $coverage, bool $vulnerabilityList): ?string
    {
        $parts = [];

        // Total advisori seluruh audit, bukan hasil filter: filter yang menyembunyikan semua baris tidak berarti "tidak ada".
        if ($vulnerabilityList && $coverage->advisories === 0) {
            $parts[] = $coverage->checked > 0
                ? "Tidak ditemukan advisori pada OSV.dev untuk {$coverage->checked} dependensi yang diperiksa; ini bukan jaminan bebas kerentanan."
                : 'Tidak ada dependensi yang diperiksa ke OSV.dev; tidak ada hasil kerentanan yang dapat ditampilkan.';
        }

        $incomplete = $this->incomplete($audit, $coverage);

        if ($incomplete !== null) {
            $parts[] = $incomplete;
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    private function incomplete(AuditScan $audit, ScaCoverage $coverage): ?string
    {
        $parts = array_filter([
            $coverage->notChecked > 0 ? "{$coverage->notChecked} dependensi tidak diperiksa" : null,
            $coverage->checkFailed > 0 ? "{$coverage->checkFailed} dependensi gagal diperiksa" : null,
            $coverage->advisoriesWithoutDetail > 0 ? "{$coverage->advisoriesWithoutDetail} advisori tanpa detail" : null,
        ]);

        if ($parts !== []) {
            return 'Cakupan pemeriksaan tidak lengkap: '.implode(', ', $parts).'.';
        }

        return in_array($audit->sca_status, [StageStatus::Partial, StageStatus::Failed], true)
            ? 'Pemeriksaan tidak lengkap; lihat message untuk alasannya.'
            : null;
    }
}
