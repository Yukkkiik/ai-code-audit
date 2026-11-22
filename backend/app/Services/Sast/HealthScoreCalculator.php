<?php

namespace App\Services\Sast;

use App\Enums\HealthCategory;

/**
 * Skor kesehatan kode (D-10). Murni: tanpa database, jaringan, maupun waktu. Hasil SCA tidak ikut dihitung (A-07).
 *
 *   penalti_temuan = min(batas, Σ bobot[severity] × jumlah)
 *   penalti_MI     = min(batas, faktor × (100 − rata-rata MI))        — 0 bila MI tidak tersedia
 *   penalti_CC     = min(batas, faktor × max(0, rata-rata CC − ambang)) — 0 bila tidak ada fungsi
 *   skor           = bulatkan(clamp(100 − Σ penalti, 0, 100))
 *   kategori       = skor ≥ good_min → good; skor ≥ fair_min → fair; selain itu poor
 *
 * Penalti temuan bersifat absolut (bukan per ukuran kode).
 */
class HealthScoreCalculator
{
    /**
     * @param  array{weights: array<string, int|float>, max_findings_penalty: int|float, mi_factor: int|float, max_mi_penalty: int|float, cc_threshold: int|float, cc_factor: int|float, max_cc_penalty: int|float, good_min: int, fair_min: int}  $config
     */
    public function __construct(private array $config) {}

    /**
     * @param  list<SastFinding>  $findings  temuan ternormalisasi (setelah deduplikasi)
     */
    public function calculate(array $findings, ?MetricsAggregate $metrics): HealthScore
    {
        $weighted = 0.0;

        foreach ($findings as $finding) {
            $weighted += (float) ($this->config['weights'][$finding->severity->value] ?? 0);
        }

        $findingsPenalty = min((float) $this->config['max_findings_penalty'], $weighted);

        $miPenalty = 0.0;
        $averageMi = $metrics?->averageMaintainabilityIndex;

        if ($averageMi !== null) {
            $miPenalty = min((float) $this->config['max_mi_penalty'], $this->config['mi_factor'] * (100 - $averageMi));
        }

        $ccPenalty = 0.0;
        $averageCc = $metrics?->averageCyclomaticComplexity;

        if ($averageCc !== null) {
            $ccPenalty = min((float) $this->config['max_cc_penalty'], $this->config['cc_factor'] * max(0.0, $averageCc - $this->config['cc_threshold']));
        }

        $score = (int) round(max(0.0, min(100.0, 100 - $findingsPenalty - $miPenalty - $ccPenalty)));

        return new HealthScore($score, $this->category($score), $findingsPenalty, $miPenalty, $ccPenalty);
    }

    public function category(int $score): HealthCategory
    {
        return match (true) {
            $score >= $this->config['good_min'] => HealthCategory::Good,
            $score >= $this->config['fair_min'] => HealthCategory::Fair,
            default => HealthCategory::Poor,
        };
    }
}
