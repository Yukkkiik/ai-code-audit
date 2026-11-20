<?php

namespace App\Services\Sca;

use App\Enums\OsvFailureReason;

/**
 * Jawaban untuk satu OsvQuery. `ok([])` berarti "tidak ditemukan advisori pada OSV.dev", bukan "aman".
 * Hasil gagal tidak membawa advisori sebagian: hasil yang terpotong tidak boleh terlihat lengkap.
 */
final readonly class OsvQueryResult
{
    /**
     * @param  list<OsvVulnerabilityRef>  $vulnerabilities
     */
    private function __construct(
        public array $vulnerabilities,
        public ?OsvFailureReason $failure,
    ) {}

    /**
     * @param  list<OsvVulnerabilityRef>  $vulnerabilities
     */
    public static function ok(array $vulnerabilities): self
    {
        return new self($vulnerabilities, null);
    }

    public static function failed(OsvFailureReason $reason): self
    {
        return new self([], $reason);
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
