<?php

namespace App\Services\Sca;

use App\Enums\OsvFailureReason;

/**
 * Jawaban pengambilan detail satu advisori: detail, atau alasan gagal (tanpa isi respons).
 */
final readonly class OsvDetailResult
{
    private function __construct(
        public ?OsvVulnerabilityDetail $detail,
        public ?OsvFailureReason $failure,
    ) {}

    public static function ok(OsvVulnerabilityDetail $detail): self
    {
        return new self($detail, null);
    }

    public static function failed(OsvFailureReason $reason): self
    {
        return new self(null, $reason);
    }

    public function isFailed(): bool
    {
        return $this->failure !== null;
    }

    public function message(): ?string
    {
        return $this->failure?->message();
    }
}
