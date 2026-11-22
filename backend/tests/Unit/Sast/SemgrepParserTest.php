<?php

namespace Tests\Unit\Sast;

use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\Severity;
use App\Exceptions\SastToolException;
use App\Services\Sast\SemgrepParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SemgrepParserTest extends TestCase
{
    private function fixture(): string
    {
        return file_get_contents(__DIR__.'/../../Fixtures/sast/semgrep-output.json');
    }

    public function test_parses_realistic_output_into_uniform_findings(): void
    {
        $report = (new SemgrepParser)->parse($this->fixture());

        $this->assertCount(3, $report->findings);
        $this->assertSame(1, $report->errorCount);
        $this->assertSame(1, $report->droppedCount);

        [$eval, $html, $hash] = $report->findings;

        $this->assertSame(FindingTool::Semgrep, $eval->tool);
        $this->assertSame('js-eval-injection', $eval->ruleId);
        $this->assertSame(Severity::High, $eval->severity);
        $this->assertSame(FindingCategory::Security, $eval->category);
        $this->assertSame('src/app.js', $eval->filePath);
        $this->assertSame([12, 12, 5], [$eval->lineStart, $eval->lineEnd, $eval->columnStart]);
        $this->assertSame('eval() mengeksekusi string sebagai kode.', $eval->message);
        $this->assertNull($eval->codeSnippet);
        $this->assertSame('CWE-95', $eval->cwe);

        $this->assertSame('js-innerhtml-assignment', $html->ruleId);
        $this->assertSame(Severity::Medium, $html->severity);
        $this->assertSame('web/view.tsx', $html->filePath);
        $this->assertSame([3, 4], [$html->lineStart, $html->lineEnd]);
        $this->assertSame('el.innerHTML = userInput;', $html->codeSnippet);
        $this->assertSame('CWE-79', $html->cwe);

        $this->assertSame(Severity::Low, $hash->severity);
        $this->assertSame(FindingCategory::BestPractice, $hash->category);
        $this->assertNull($hash->cwe);
    }

    public function test_zero_results_is_valid(): void
    {
        $report = (new SemgrepParser)->parse('{"results": [], "errors": []}');

        $this->assertSame([], $report->findings);
        $this->assertSame(0, $report->errorCount);
    }

    #[DataProvider('invalidOutputs')]
    public function test_invalid_output_throws(string $json): void
    {
        $this->expectException(SastToolException::class);

        (new SemgrepParser)->parse($json);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidOutputs(): array
    {
        return [
            'empty' => [''],
            'truncated' => ['{"results": [{"check_id": "a"'],
            'not an object' => ['"teks"'],
            'no results key' => ['{"errors": []}'],
            'results not a list' => ['{"results": {"a": 1}}'],
        ];
    }

    public function test_malformed_entries_are_dropped_not_fatal(): void
    {
        $json = json_encode(['results' => [
            'bukan-objek',
            ['check_id' => 'x.rule', 'path' => '/abs/path.js', 'extra' => []],
            ['check_id' => '', 'path' => 'a.js'],
            ['check_id' => 'x.rule', 'path' => 'ok.js', 'start' => ['line' => 0, 'col' => 'a'], 'extra' => ['severity' => 'APA']],
        ]]);

        $report = (new SemgrepParser)->parse($json);

        $this->assertSame(3, $report->droppedCount);
        $this->assertCount(1, $report->findings);
        $this->assertNull($report->findings[0]->lineStart);
        $this->assertSame(Severity::Low, $report->findings[0]->severity);
        $this->assertSame('rule', $report->findings[0]->ruleId);
        $this->assertSame('rule', $report->findings[0]->message);
    }
}
