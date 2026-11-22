<?php

namespace Tests\Unit\Sast;

use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\Severity;
use App\Exceptions\SastToolException;
use App\Services\Sast\EslintParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EslintParserTest extends TestCase
{
    private const ROOT = '/srv/ws/repo';

    private function fixture(): string
    {
        // Output ESLint 10.15.0 nyata (config platform) pada repo uji kecil; path dinetralkan ke /srv/ws/repo.
        return file_get_contents(__DIR__.'/../../Fixtures/sast/eslint-real-output.json');
    }

    public function test_parses_real_output_into_uniform_findings(): void
    {
        $report = (new EslintParser)->parse($this->fixture(), self::ROOT);

        // a.js: 6 temuan; b.tsx: 2 temuan; bad.js: hanya parsing error (fatal) → bukan temuan.
        $this->assertCount(8, $report->findings);
        $this->assertSame(1, $report->errorCount);
        $this->assertSame(0, $report->droppedCount);

        [$securityEval, $coreEval, $eqeqeq] = $report->findings;

        $this->assertSame(FindingTool::Eslint, $securityEval->tool);
        $this->assertSame('security/detect-eval-with-expression', $securityEval->ruleId);
        $this->assertSame(FindingCategory::Security, $securityEval->category);
        $this->assertSame(Severity::Medium, $securityEval->severity); // plugin security, severity 1 (warn)
        $this->assertSame('src/a.js', $securityEval->filePath);
        $this->assertSame([1, 1, 11], [$securityEval->lineStart, $securityEval->lineEnd, $securityEval->columnStart]);
        $this->assertSame('CWE-95', $securityEval->cwe);
        $this->assertNull($securityEval->codeSnippet);

        $this->assertSame('no-eval', $coreEval->ruleId);
        $this->assertSame(Severity::High, $coreEval->severity); // aturan keamanan, severity 2 (error)

        $this->assertSame('eqeqeq', $eqeqeq->ruleId);
        $this->assertSame(FindingCategory::Quality, $eqeqeq->category);
        $this->assertSame(Severity::Low, $eqeqeq->severity); // bukan keamanan, severity 1 (warn)
        $this->assertNull($eqeqeq->cwe);

        $this->assertSame('src/b.tsx', $report->findings[6]->filePath);
    }

    public function test_windows_style_absolute_paths_are_relativized(): void
    {
        $json = json_encode([['filePath' => 'C:\\ws\\repo\\src\\a.js', 'messages' => [
            ['ruleId' => 'eqeqeq', 'severity' => 2, 'message' => 'x', 'line' => 1, 'column' => 1],
        ]]]);

        $report = (new EslintParser)->parse($json, 'C:\\ws\\repo');

        $this->assertSame('src/a.js', $report->findings[0]->filePath);
        $this->assertSame(Severity::Medium, $report->findings[0]->severity);
    }

    public function test_zero_findings_is_valid(): void
    {
        $report = (new EslintParser)->parse('[{"filePath": "/srv/ws/repo/a.js", "messages": []}]', self::ROOT);

        $this->assertSame([], $report->findings);
        $this->assertSame(0, $report->errorCount);
        $this->assertSame([], (new EslintParser)->parse('[]', self::ROOT)->findings);
    }

    #[DataProvider('invalidOutputs')]
    public function test_invalid_output_throws(string $json): void
    {
        $this->expectException(SastToolException::class);

        (new EslintParser)->parse($json, self::ROOT);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidOutputs(): array
    {
        return [
            'empty' => [''],
            'truncated' => ['[{"filePath": "/srv/ws/repo/a.js", "messages": [{"ruleId"'],
            'object instead of list' => ['{"results": []}'],
            'scalar' => ['"teks"'],
        ];
    }

    public function test_files_outside_root_and_malformed_messages_are_dropped(): void
    {
        $json = json_encode([
            ['filePath' => '/etc/passwd', 'messages' => [['ruleId' => 'eqeqeq', 'severity' => 2, 'message' => 'x']]],
            ['filePath' => '/srv/ws/repo/../secret.js', 'messages' => [['ruleId' => 'eqeqeq', 'severity' => 2, 'message' => 'x']]],
            ['filePath' => '/srv/ws/repo/ok.js', 'messages' => [
                'bukan-objek',
                ['ruleId' => null, 'severity' => 1, 'message' => 'tanpa rule'],
                ['ruleId' => 'no-empty', 'severity' => 'x', 'message' => "kontrol\x07 ok", 'line' => 0],
            ]],
        ]);

        $report = (new EslintParser)->parse($json, self::ROOT);

        $this->assertSame(4, $report->droppedCount);
        $this->assertCount(1, $report->findings);
        $this->assertSame('ok.js', $report->findings[0]->filePath);
        $this->assertSame('kontrol ok', str_replace("\x07", '', $report->findings[0]->message));
        $this->assertNull($report->findings[0]->lineStart);
        $this->assertSame(Severity::Low, $report->findings[0]->severity);
    }
}
