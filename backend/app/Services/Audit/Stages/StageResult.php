<?php

namespace App\Services\Audit\Stages;

use App\Enums\StageStatus;

/**
 * Hasil akhir satu tahap pemindaian. `message` adalah pesan aman untuk pengguna
 * (disimpan ke kolom sast_error / sca_error).
 */
final readonly class StageResult
{
    private function __construct(
        public StageStatus $status,
        public ?string $message,
    ) {}

    public static function completed(): self
    {
        return new self(StageStatus::Completed, null);
    }

    public static function partial(string $message): self
    {
        return new self(StageStatus::Partial, $message);
    }

    public static function failed(string $message): self
    {
        return new self(StageStatus::Failed, $message);
    }

    /**
     * Tahap tidak relevan untuk repositori ini (mis. tidak ada file JS/TS atau package.json).
     */
    public static function skipped(?string $reason = null): self
    {
        return new self(StageStatus::Skipped, $reason);
    }
}
