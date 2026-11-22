<?php

namespace Tests\Unit\Sast;

use App\Enums\CodeLanguage;
use App\Exceptions\SastToolException;
use App\Services\Sast\FileMetrics;
use App\Services\Sast\FunctionMetrics;
use App\Services\Sast\MetricsAggregate;
use App\Services\Sast\MetricsParser;
use App\Services\Sast\MetricValues;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MetricsTest extends TestCase
{
    public function test_halstead_and_maintainability_index_match_manual_calculation(): void
    {
        // n1=3, n2=2, N1=5, N2=4, CC=2, SLOC=3 (dihitung manual):
        //   n = 3+2 = 5, N = 5+4 = 9
        //   V = 9·log2(5)            = 9 × 2,321928 = 20,897353
        //   D = (3/2)·(4/2)          = 1,5 × 2      = 3
        //   E = D·V                  = 62,692059
        //   MI = (171 − 5,2·ln(20,897353) − 0,23·2 − 16,2·ln(3)) · 100/171
        //      = (171 − 15,8057 − 0,46 − 17,7971) · 100/171 = 80,0798
        $values = new MetricValues(loc: 5, sloc: 3, cyclomaticComplexity: 2, distinctOperators: 3, distinctOperands: 2, totalOperators: 5, totalOperands: 4);

        $this->assertEqualsWithDelta(20.897353, $values->volume(), 0.00001);
        $this->assertEqualsWithDelta(3.0, $values->difficulty(), 0.00001);
        $this->assertEqualsWithDelta(62.692059, $values->effort(), 0.0001);
        $this->assertEqualsWithDelta(80.0798, $values->maintainabilityIndex(), 0.001);
    }

    public function test_values_for_function_add_from_real_metrics_script_output(): void
    {
        // function add(a, b) { if (a > 0 && b > 0) { return a + b; } return 0; }  (7 baris, 1 komentar)
        // Keluaran metrics.mjs: loc=7, sloc=6, cc=3 (1 + if + &&), n1=10, n2=4, N1=15, N2=10.
        //   V = 25·log2(14) = 95,18387; D = 5·(10/4) = 12,5; E = 1189,79841
        //   MI = (171 − 5,2·ln(95,18387) − 0,23·3 − 16,2·ln(6)) · 100/171 = 68,768
        $values = new MetricValues(7, 6, 3, 10, 4, 15, 10);

        $this->assertEqualsWithDelta(95.18387, $values->volume(), 0.0001);
        $this->assertEqualsWithDelta(12.5, $values->difficulty(), 0.00001);
        $this->assertEqualsWithDelta(1189.79841, $values->effort(), 0.001);
        $this->assertEqualsWithDelta(68.768, $values->maintainabilityIndex(), 0.001);
    }

    public function test_division_by_zero_and_log_of_zero_are_avoided(): void
    {
        $empty = new MetricValues(0, 0, 1, 0, 0, 0, 0);
        $this->assertSame(0.0, $empty->volume());
        $this->assertSame(0.0, $empty->difficulty());
        $this->assertSame(0.0, $empty->effort());
        $this->assertNull($empty->maintainabilityIndex(), 'file kosong tidak diberi MI.');

        $oneToken = new MetricValues(1, 1, 1, 1, 0, 1, 0); // vocabulary 1 → log2(1) = 0 → V = 0
        $this->assertSame(0.0, $oneToken->volume());
        $this->assertNull($oneToken->maintainabilityIndex());

        $commentsOnly = new MetricValues(2, 0, 1, 5, 5, 9, 9);
        $this->assertNull($commentsOnly->maintainabilityIndex(), 'SLOC 0 → ln(0) tidak terdefinisi.');
    }

    public function test_maintainability_index_is_clamped_between_0_and_100(): void
    {
        $this->assertSame(0.0, (new MetricValues(9000, 9000, 900, 50, 5000, 90000, 90000))->maintainabilityIndex());
        $this->assertLessThanOrEqual(100.0, (new MetricValues(2, 1, 1, 2, 1, 2, 1))->maintainabilityIndex());
    }

    public function test_aggregate_computes_totals_and_sloc_weighted_mi(): void
    {
        $a = new FileMetrics('a.js', CodeLanguage::Javascript, new MetricValues(7, 6, 3, 10, 4, 15, 10), [
            new FunctionMetrics('add', 1, new MetricValues(7, 6, 3, 10, 4, 15, 10)),
            new FunctionMetrics('x', 9, new MetricValues(2, 2, 1, 1, 1, 1, 1)),
        ]);
        $empty = new FileMetrics('e.js', CodeLanguage::Javascript, new MetricValues(0, 0, 1, 0, 0, 0, 0), []);

        $aggregate = MetricsAggregate::from([$a, $empty]);

        $this->assertSame([2, 2, 7, 6, 3], [$aggregate->fileCount, $aggregate->functionCount, $aggregate->loc, $aggregate->sloc, $aggregate->maxCyclomaticComplexity]);
        $this->assertSame(2.0, $aggregate->averageCyclomaticComplexity); // (3 + 1) / 2
        // Hanya a.js punya MI → rata-rata = MI a.js (file kosong tidak ikut dan tidak menarik rata-rata ke 0).
        $this->assertEqualsWithDelta(68.768, $aggregate->averageMaintainabilityIndex, 0.001);
    }

    public function test_aggregate_of_nothing_has_null_averages(): void
    {
        $aggregate = MetricsAggregate::from([]);

        $this->assertSame(0, $aggregate->fileCount);
        $this->assertNull($aggregate->averageCyclomaticComplexity);
        $this->assertNull($aggregate->averageMaintainabilityIndex);
    }

    public function test_parser_reads_script_output(): void
    {
        $json = json_encode([
            'files' => [
                ['path' => 'src/a.js', 'loc' => 7, 'sloc' => 6, 'cc' => 3, 'n1' => 10, 'n2' => 4, 'N1' => 15, 'N2' => 10, 'functions' => [
                    ['name' => 'add', 'line' => 1, 'loc' => 7, 'sloc' => 6, 'cc' => 3, 'n1' => 10, 'n2' => 4, 'N1' => 15, 'N2' => 10],
                    ['name' => "x\x07", 'line' => 0, 'loc' => 1, 'sloc' => 1, 'cc' => 1, 'n1' => 1, 'n2' => 1, 'N1' => 1, 'N2' => 1],
                    ['name' => 'rusak', 'loc' => 'satu'],
                ]],
                ['path' => 'web/b.tsx', 'loc' => 1, 'sloc' => 1, 'cc' => 1, 'n1' => 1, 'n2' => 1, 'N1' => 1, 'N2' => 1, 'functions' => []],
                ['path' => '../luar.js', 'loc' => 1, 'sloc' => 1, 'cc' => 1, 'n1' => 1, 'n2' => 1, 'N1' => 1, 'N2' => 1, 'functions' => []],
                ['path' => 'neg.js', 'loc' => -1, 'sloc' => 1, 'cc' => 1, 'n1' => 1, 'n2' => 1, 'N1' => 1, 'N2' => 1, 'functions' => []],
                'bukan-objek',
            ],
            'errors' => [['path' => 'bad.js', 'reason' => 'parse_error']],
        ]);

        $report = (new MetricsParser)->parse($json);

        $this->assertCount(2, $report->files);
        $this->assertSame(1, $report->errorCount);
        $this->assertSame(3, $report->droppedCount);

        $this->assertSame(CodeLanguage::Javascript, $report->files[0]->language);
        $this->assertSame(CodeLanguage::Typescript, $report->files[1]->language);
        $this->assertSame(['add', 'x'], array_map(fn ($f) => $f->name, $report->files[0]->functions));
        $this->assertSame(1, $report->files[0]->functions[1]->lineStart);
        $this->assertSame(3, $report->files[0]->values->cyclomaticComplexity);
    }

    #[DataProvider('invalidOutputs')]
    public function test_parser_rejects_invalid_output(string $json): void
    {
        $this->expectException(SastToolException::class);

        (new MetricsParser)->parse($json);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidOutputs(): array
    {
        return [
            'empty' => [''],
            'truncated' => ['{"files": [{"path": "a.js"'],
            'no files key' => ['{"errors": []}'],
            'files not a list' => ['{"files": {"a": 1}}'],
        ];
    }
}
