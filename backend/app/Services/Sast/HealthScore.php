<?php

namespace App\Services\Sast;

use App\Enums\HealthCategory;

/**
 * Skor kesehatan kode 0–100 beserta rincian penaltinya (untuk pesan dan debugging).
 */
final readonly class HealthScore
{
    public function __construct(
        public int $score,
        public HealthCategory $category,
        public float $findingsPenalty,
        public float $maintainabilityPenalty,
        public float $complexityPenalty,
    ) {}
}
