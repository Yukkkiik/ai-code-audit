<?php

namespace Tests\Unit\Sast;

use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\Severity;
use App\Services\Sast\FindingNormalizer;
use App\Services\Sast\SastFinding;
use App\Services\Sast\SnippetReader;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class FindingNormalizerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/normalizer-'.bin2hex(random_bytes(5)).'/repo';
        mkdir($this->root.'/src', 0755, true);
        file_put_contents($this->root.'/src/a.js', "line1\nconst x = eval(input);\n\tline3\x07\nline4\nline5\nline6\nline7\n");
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(dirname($this->root));

        parent::tearDown();
    }

    private function normalizer(int $max = 100): FindingNormalizer
    {
        return new FindingNormalizer(new SnippetReader(3, 10, 1000), $max);
    }

    private function finding(FindingTool $tool = FindingTool::Semgrep, string $rule = 'r', Severity $severity = Severity::High, string $path = 'src/a.js', ?int $line = 2, ?string $cwe = null, string $message = 'm', ?int $lineEnd = null): SastFinding
    {
        return new SastFinding($tool, $rule, FindingCategory::Security, $severity, $path, $line, $lineEnd ?? $line, 1, $message, null, $cwe);
    }

    public function test_exact_duplicates_are_collapsed(): void
    {
        $result = $this->normalizer()->normalize([$this->finding(), $this->finding(), $this->finding(rule: 'other')], $this->root);

        $this->assertCount(2, $result->findings);
        $this->assertSame(1, $result->duplicates);
    }

    public function test_eslint_finding_is_dropped_when_semgrep_reports_same_cwe_at_same_location(): void
    {
        $result = $this->normalizer()->normalize([
            $this->finding(FindingTool::Eslint, 'no-eval', Severity::High, cwe: 'CWE-95'),
            $this->finding(FindingTool::Semgrep, 'js-eval-injection', Severity::High, cwe: 'CWE-95'),
        ], $this->root);

        $this->assertCount(1, $result->findings);
        $this->assertSame(FindingTool::Semgrep, $result->findings[0]->tool);
        $this->assertSame(1, $result->duplicates);
    }

    public function test_different_line_cwe_or_missing_cwe_are_not_cross_tool_duplicates(): void
    {
        $result = $this->normalizer()->normalize([
            $this->finding(FindingTool::Semgrep, 's', cwe: 'CWE-95'),
            $this->finding(FindingTool::Eslint, 'e-other-line', line: 3, cwe: 'CWE-95'),
            $this->finding(FindingTool::Eslint, 'e-other-cwe', cwe: 'CWE-78'),
            $this->finding(FindingTool::Eslint, 'e-no-cwe', cwe: null),
            $this->finding(FindingTool::Eslint, 'e-other-file', path: 'src/b.js', cwe: 'CWE-95'),
        ], $this->root);

        $this->assertCount(5, $result->findings);
        $this->assertSame(0, $result->duplicates);
    }

    public function test_two_eslint_rules_on_same_location_are_both_kept(): void
    {
        $result = $this->normalizer()->normalize([
            $this->finding(FindingTool::Eslint, 'no-eval', cwe: 'CWE-95'),
            $this->finding(FindingTool::Eslint, 'security/detect-eval-with-expression', Severity::Medium, cwe: 'CWE-95'),
        ], $this->root);

        $this->assertCount(2, $result->findings);
    }

    public function test_order_is_deterministic_regardless_of_input_order(): void
    {
        $a = $this->finding(rule: 'a', severity: Severity::Low, line: 5);
        $b = $this->finding(rule: 'b', severity: Severity::High, line: 9);
        $c = $this->finding(rule: 'c', severity: Severity::High, line: 2);
        $d = $this->finding(rule: 'd', severity: Severity::Critical, path: 'z.js', line: 1);

        $one = $this->normalizer()->normalize([$a, $b, $c, $d], $this->root);
        $two = $this->normalizer()->normalize([$d, $c, $b, $a], $this->root);

        $this->assertSame(['d', 'c', 'b', 'a'], array_map(fn ($f) => $f->ruleId, $one->findings));
        $this->assertSame(
            array_map(fn ($f) => $f->fingerprint(), $one->findings),
            array_map(fn ($f) => $f->fingerprint(), $two->findings),
        );
    }

    public function test_truncates_lowest_severity_first(): void
    {
        $result = $this->normalizer(max: 2)->normalize([
            $this->finding(rule: 'low', severity: Severity::Low),
            $this->finding(rule: 'high', severity: Severity::High),
            $this->finding(rule: 'med', severity: Severity::Medium),
        ], $this->root);

        $this->assertSame(['high', 'med'], array_map(fn ($f) => $f->ruleId, $result->findings));
        $this->assertSame(1, $result->truncated);
    }

    public function test_snippet_is_read_from_workspace_limited_and_cleaned(): void
    {
        $result = $this->normalizer()->normalize([$this->finding(line: 2, lineEnd: 6)], $this->root);

        // Maksimal 3 baris (dari baris 2) dan 10 karakter per baris, kontrol \x07 dihapus, tab dipertahankan.
        $this->assertSame("const x = \n\tline3\nline4", $result->findings[0]->codeSnippet);
    }

    public function test_snippet_is_null_for_missing_file_or_out_of_range_or_unknown_line(): void
    {
        $result = $this->normalizer()->normalize([
            $this->finding(rule: 'a', path: 'src/missing.js'),
            $this->finding(rule: 'b', line: 999),
            $this->finding(rule: 'c', line: null),
        ], $this->root);

        foreach ($result->findings as $finding) {
            $this->assertNull($finding->codeSnippet, $finding->ruleId);
        }
    }

    public function test_snippet_is_not_read_from_oversized_file(): void
    {
        file_put_contents($this->root.'/src/big.js', str_repeat("x\n", 600));

        $result = $this->normalizer()->normalize([$this->finding(path: 'src/big.js')], $this->root);

        $this->assertNull($result->findings[0]->codeSnippet);
    }

    public function test_snippet_is_not_read_through_symlink_outside_root(): void
    {
        $outside = dirname($this->root).'/secret.js';
        file_put_contents($outside, "rahasia\nrahasia\n");

        if (! @symlink($outside, $this->root.'/src/link.js')) {
            $this->markTestSkipped('Symlink tidak didukung/diizinkan di environment ini.');
        }

        $result = $this->normalizer()->normalize([$this->finding(path: 'src/link.js')], $this->root);

        $this->assertNull($result->findings[0]->codeSnippet);
    }

    public function test_server_root_path_is_removed_from_messages(): void
    {
        $result = $this->normalizer()->normalize([$this->finding(message: "gagal di {$this->root}/src/a.js")], $this->root);

        $this->assertSame('gagal di ./src/a.js', $result->findings[0]->message);
    }

    public function test_empty_input(): void
    {
        $result = $this->normalizer()->normalize([], $this->root);

        $this->assertSame([], $result->findings);
        $this->assertSame([0, 0], [$result->duplicates, $result->truncated]);
    }
}
