<?php

namespace Database\Factories;

use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\Severity;
use App\Models\AuditScan;
use App\Models\ScanFinding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScanFinding>
 */
class ScanFindingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_scan_id' => AuditScan::factory(),
            'tool' => FindingTool::Semgrep,
            'rule_id' => 'javascript.lang.security.audit.eval-detected',
            'category' => FindingCategory::Security,
            'severity' => Severity::High,
            'file_path' => 'src/'.fake()->word().'.js',
            'line_start' => $line = fake()->numberBetween(1, 500),
            'line_end' => $line,
            'code_snippet' => 'eval(userInput);',
            'message' => 'Penggunaan eval() dengan input pengguna berisiko code injection.',
            'cwe' => 'CWE-95',
            'fingerprint' => hash('sha256', (string) Str::uuid()),
        ];
    }
}
