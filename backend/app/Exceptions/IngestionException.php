<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Kegagalan ingestion repositori. `errorCode` dan pesan aman ditampilkan ke
 * pengguna melalui audit_scans.error_code / error_message (API_CONTRACT.md §2.3).
 */
class IngestionException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function zipSlip(): self
    {
        return new self('ZIP_SLIP_DETECTED', 'Arsip berisi path atau symlink yang keluar dari direktori kerja.');
    }

    public static function archiveTooLarge(string $reason): self
    {
        return new self('ARCHIVE_TOO_LARGE', $reason);
    }

    public static function archiveCorrupt(string $reason = 'Arsip ZIP rusak atau tidak dapat dibaca.'): self
    {
        return new self('ARCHIVE_CORRUPT', $reason);
    }

    public static function archiveEncrypted(): self
    {
        return new self('ARCHIVE_ENCRYPTED', 'Arsip ZIP terenkripsi (berpassword) tidak didukung.');
    }

    public static function invalidRepositoryUrl(string $message, ?Throwable $previous = null): self
    {
        return new self('INVALID_REPOSITORY_URL', $message, $previous);
    }

    public static function repositoryNotFound(): self
    {
        return new self('REPOSITORY_NOT_FOUND', 'Repositori tidak ditemukan atau bersifat privat. Hanya repositori publik yang didukung.');
    }

    public static function repositoryTooLarge(): self
    {
        return new self('REPOSITORY_TOO_LARGE', 'Ukuran repositori melebihi batas yang diizinkan.');
    }

    public static function cloneTimeout(?Throwable $previous = null): self
    {
        return new self('CLONE_TIMEOUT', 'Pengambilan repositori melebihi batas waktu.', $previous);
    }

    public static function failed(string $message): self
    {
        return new self('INGESTION_FAILED', $message);
    }
}
