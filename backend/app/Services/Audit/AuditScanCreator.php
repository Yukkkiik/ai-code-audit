<?php

namespace App\Services\Audit;

use App\Enums\AuditStatus;
use App\Enums\SourceType;
use App\Models\AuditScan;
use App\Models\User;
use InvalidArgumentException;

/**
 * Membuat sesi audit baru berstatus `queued`.
 * Validasi isi ZIP / URL Git dilakukan oleh modul ingestion sebelum service ini dipanggil.
 */
class AuditScanCreator
{
    public function create(
        ?User $owner,
        SourceType $sourceType,
        string $repositoryName,
        ?string $repositoryUrl = null,
        ?string $originalFilename = null,
        ?int $sourceSizeBytes = null,
    ): CreatedAuditScan {
        if ($sourceType === SourceType::Git && $repositoryUrl === null) {
            throw new InvalidArgumentException('Audit dari Git wajib memiliki repository_url.');
        }

        if ($sourceType === SourceType::Zip && $repositoryUrl !== null) {
            throw new InvalidArgumentException('Audit dari ZIP tidak boleh memiliki repository_url.');
        }

        $guestToken = $owner === null ? bin2hex(random_bytes(32)) : null;

        $audit = AuditScan::query()->create([
            'user_id' => $owner?->id,
            'guest_token_hash' => $guestToken === null ? null : AuditScan::hashGuestToken($guestToken),
            'source_type' => $sourceType,
            'repository_name' => $repositoryName,
            'repository_url' => $repositoryUrl,
            'original_filename' => $originalFilename,
            'source_size_bytes' => $sourceSizeBytes,
            'status' => AuditStatus::Queued,
            'queued_at' => now(),
        ]);

        return new CreatedAuditScan($audit, $guestToken);
    }
}
