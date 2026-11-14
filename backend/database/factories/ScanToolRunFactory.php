<?php

namespace Database\Factories;

use App\Enums\FindingTool;
use App\Enums\ToolRunStatus;
use App\Models\AuditScan;
use App\Models\ScanToolRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScanToolRun>
 */
class ScanToolRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_scan_id' => AuditScan::factory(),
            'tool' => FindingTool::Semgrep,
            'status' => ToolRunStatus::Completed,
            'files_scanned' => fake()->numberBetween(1, 300),
            'findings_count' => fake()->numberBetween(0, 20),
            'duration_ms' => fake()->numberBetween(200, 30_000),
        ];
    }
}
