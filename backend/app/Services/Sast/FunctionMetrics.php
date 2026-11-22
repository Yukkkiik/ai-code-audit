<?php

namespace App\Services\Sast;

final readonly class FunctionMetrics
{
    public function __construct(
        public string $name,
        public int $lineStart,
        public MetricValues $values,
    ) {}
}
