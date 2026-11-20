<?php

namespace App\Exceptions;

use App\Enums\OsvFailureReason;
use RuntimeException;

/**
 * Satu permintaan batch ke OSV gagal. Hanya dipakai di dalam OsvClient; pesannya selalu pesan aman dari enum.
 */
class OsvRequestException extends RuntimeException
{
    public function __construct(
        public readonly OsvFailureReason $reason,
        public readonly bool $retryable = false,
        public readonly ?int $status = null,
    ) {
        parent::__construct($reason->message());
    }
}
