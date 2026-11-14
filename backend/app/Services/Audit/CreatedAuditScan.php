<?php

namespace App\Services\Audit;

use App\Models\AuditScan;

/**
 * Hasil pembuatan audit. `guestToken` (plain text) hanya tersedia di sini,
 * satu kali, untuk dikirim ke guest; database hanya menyimpan hash-nya.
 */
final readonly class CreatedAuditScan
{
    public function __construct(
        public AuditScan $audit,
        public ?string $guestToken,
    ) {}
}
