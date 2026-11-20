<?php

namespace App\Enums;

/**
 * Sebab satu pertanyaan ke OSV.dev gagal dijawab. Hanya kode ini dan pesan tetap di bawah yang
 * diteruskan keluar klien: isi respons, URL, dan nama paket tidak pernah ikut.
 */
enum OsvFailureReason: string
{
    case Timeout = 'timeout';
    case RateLimited = 'rate_limited';
    case ServerError = 'server_error';
    case ClientError = 'client_error';
    case InvalidResponse = 'invalid_response';
    case PaginationIncomplete = 'pagination_incomplete';
    case TimeBudgetExhausted = 'time_budget_exhausted';

    /**
     * Pesan aman untuk pengguna.
     */
    public function message(): string
    {
        return match ($this) {
            self::Timeout => 'OSV tidak merespons dalam batas waktu.',
            self::RateLimited => 'OSV membatasi jumlah permintaan; pemeriksaan tidak selesai.',
            self::ServerError => 'OSV mengalami gangguan; pemeriksaan tidak selesai.',
            self::ClientError => 'OSV menolak permintaan pemeriksaan.',
            self::InvalidResponse => 'Respons OSV tidak valid.',
            self::PaginationIncomplete => 'Hasil OSV tidak lengkap karena halaman hasil tidak selesai dibaca.',
            self::TimeBudgetExhausted => 'Batas waktu pemeriksaan OSV terlampaui.',
        };
    }
}
