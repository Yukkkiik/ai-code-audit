<?php

namespace App\Services\Sast;

/**
 * Metrik satu file atau satu fungsi. Nilai mentah (hitungan) berasal dari tooling/metrics.mjs; turunan dihitung di sini
 * dan murni (tanpa efek samping).
 *
 * Halstead: n = n1 + n2, N = N1 + N2, V = N·log2(n), D = (n1/2)·(N2/n2), E = D·V.
 * MI = max(0, (171 − 5.2·ln V − 0.23·CC − 16.2·ln SLOC) · 100/171), dibatasi 100; null bila V ≤ 0 atau SLOC < 1
 * (logaritma tidak terdefinisi: file kosong/hanya komentar tidak diberi MI).
 */
final readonly class MetricValues
{
    public function __construct(
        public int $loc,
        public int $sloc,
        public int $cyclomaticComplexity,
        public int $distinctOperators,
        public int $distinctOperands,
        public int $totalOperators,
        public int $totalOperands,
    ) {}

    public function volume(): float
    {
        $vocabulary = $this->distinctOperators + $this->distinctOperands;

        return $vocabulary < 2 ? 0.0 : ($this->totalOperators + $this->totalOperands) * log($vocabulary, 2);
    }

    public function difficulty(): float
    {
        return $this->distinctOperands === 0
            ? 0.0
            : ($this->distinctOperators / 2) * ($this->totalOperands / $this->distinctOperands);
    }

    public function effort(): float
    {
        return $this->difficulty() * $this->volume();
    }

    public function maintainabilityIndex(): ?float
    {
        $volume = $this->volume();

        if ($volume <= 0 || $this->sloc < 1) {
            return null;
        }

        $raw = 171 - 5.2 * log($volume) - 0.23 * $this->cyclomaticComplexity - 16.2 * log($this->sloc);

        return min(100.0, max(0.0, $raw * 100 / 171));
    }
}
