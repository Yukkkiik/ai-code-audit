<?php

namespace Tests\Unit\Sast;

use App\Enums\CodeLanguage;
use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\HealthCategory;
use App\Enums\Severity;
use App\Services\Sast\FileMetrics;
use App\Services\Sast\FunctionMetrics;
use App\Services\Sast\HealthScoreCalculator;
use App\Services\Sast\MetricsAggregate;
use App\Services\Sast\MetricValues;
use App\Services\Sast\SastFinding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HealthScoreCalculatorTest extends TestCase
{
    private function calculator(): HealthScoreCalculator
    {
        return new HealthScoreCalculator([
            'weights' => ['critical' => 15, 'high' => 8, 'medium' => 3, 'low' => 1, 'info' => 0],
            'max_findings_penalty' => 60,
            'mi_factor' => 0.3,
            'max_mi_penalty' => 30,
            'cc_threshold' => 5,
            'cc_factor' => 2,
            'max_cc_penalty' => 10,
            'good_min' => 80,
            'fair_min' => 50,
        ]);
    }

    /**
     * @param  list<Severity>  $severities
     * @return list<SastFinding>
     */
    private function findings(array $severities): array
    {
        return array_map(
            fn (Severity $severity, int $i) => new SastFinding(FindingTool::Semgrep, 'r'.$i, FindingCategory::Security, $severity, 'a.js', $i + 1, $i + 1, 1, 'm'),
            $severities,
            array_keys($severities),
        );
    }

    /**
     * Agregat dengan MI dan CC rata-rata yang bisa ditentukan: satu file (MI file = MI fungsi) berisi satu fungsi.
     */
    private function metrics(int $functionCc): MetricsAggregate
    {
        // MI file = 68,768 (lihat MetricsTest); rata-rata CC = CC fungsi.
        return MetricsAggregate::from([new FileMetrics('a.js', CodeLanguage::Javascript, new MetricValues(7, 6, 3, 10, 4, 15, 10), [
            new FunctionMetrics('f', 1, new MetricValues(3, 3, $functionCc, 3, 2, 5, 4)),
        ])]);
    }

    public function test_no_findings_and_no_metrics_scores_100(): void
    {
        $score = $this->calculator()->calculate([], null);

        $this->assertSame(100, $score->score);
        $this->assertSame(HealthCategory::Good, $score->category);
        $this->assertSame([0.0, 0.0, 0.0], [$score->findingsPenalty, $score->maintainabilityPenalty, $score->complexityPenalty]);
    }

    public function test_findings_penalty_uses_severity_weights(): void
    {
        // 1 critical (15) + 2 high (16) + 1 medium (3) + 2 low (2) + 5 info (0) = 36 → skor 64.
        $score = $this->calculator()->calculate($this->findings([
            Severity::Critical, Severity::High, Severity::High, Severity::Medium, Severity::Low, Severity::Low,
            Severity::Info, Severity::Info, Severity::Info, Severity::Info, Severity::Info,
        ]), null);

        $this->assertSame(36.0, $score->findingsPenalty);
        $this->assertSame(64, $score->score);
        $this->assertSame(HealthCategory::Fair, $score->category);
    }

    public function test_findings_penalty_is_capped_and_score_never_below_40_without_metrics(): void
    {
        $score = $this->calculator()->calculate($this->findings(array_fill(0, 100, Severity::Critical)), null);

        $this->assertSame(60.0, $score->findingsPenalty);
        $this->assertSame(40, $score->score);
        $this->assertSame(HealthCategory::Poor, $score->category);
    }

    public function test_metrics_penalties_are_computed_from_averages(): void
    {
        // rata-rata MI 68,768 → 0,3 × 31,232 = 9,3696; rata-rata CC 9 → 2 × (9 − 5) = 8. Skor = 100 − 9,3696 − 8 = 82,63 → 83.
        $score = $this->calculator()->calculate([], $this->metrics(9));

        $this->assertEqualsWithDelta(9.3696, $score->maintainabilityPenalty, 0.001);
        $this->assertSame(8.0, $score->complexityPenalty);
        $this->assertSame(83, $score->score);
    }

    public function test_complexity_below_threshold_has_no_penalty_and_is_capped_above(): void
    {
        $this->assertSame(0.0, $this->calculator()->calculate([], $this->metrics(5))->complexityPenalty);
        $this->assertSame(0.0, $this->calculator()->calculate([], $this->metrics(1))->complexityPenalty);
        $this->assertSame(10.0, $this->calculator()->calculate([], $this->metrics(500))->complexityPenalty);
    }

    public function test_metrics_without_functions_or_mi_add_no_penalty(): void
    {
        $empty = MetricsAggregate::from([new FileMetrics('e.js', CodeLanguage::Javascript, new MetricValues(0, 0, 1, 0, 0, 0, 0), [])]);

        $score = $this->calculator()->calculate([], $empty);

        $this->assertSame([0.0, 0.0, 100], [$score->maintainabilityPenalty, $score->complexityPenalty, $score->score]);
    }

    public function test_score_is_clamped_to_zero(): void
    {
        $calculator = new HealthScoreCalculator([
            'weights' => ['critical' => 500, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0],
            'max_findings_penalty' => 500, 'mi_factor' => 0, 'max_mi_penalty' => 0, 'cc_threshold' => 0,
            'cc_factor' => 0, 'max_cc_penalty' => 0, 'good_min' => 80, 'fair_min' => 50,
        ]);

        $this->assertSame(0, $calculator->calculate($this->findings([Severity::Critical]), null)->score);
    }

    #[DataProvider('categoryBoundaries')]
    public function test_category_boundaries(int $score, HealthCategory $expected): void
    {
        $this->assertSame($expected, $this->calculator()->category($score));
    }

    /**
     * @return array<string, array{int, HealthCategory}>
     */
    public static function categoryBoundaries(): array
    {
        return [
            '100' => [100, HealthCategory::Good],
            '80 (batas bawah good)' => [80, HealthCategory::Good],
            '79' => [79, HealthCategory::Fair],
            '50 (batas bawah fair)' => [50, HealthCategory::Fair],
            '49' => [49, HealthCategory::Poor],
            '0' => [0, HealthCategory::Poor],
        ];
    }
}
