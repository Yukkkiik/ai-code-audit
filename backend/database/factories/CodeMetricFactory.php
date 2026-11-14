<?php

namespace Database\Factories;

use App\Enums\CodeLanguage;
use App\Enums\MetricScope;
use App\Models\AuditScan;
use App\Models\CodeMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodeMetric>
 */
class CodeMetricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_scan_id' => AuditScan::factory(),
            'scope' => MetricScope::File,
            'language' => CodeLanguage::Javascript,
            'file_path' => 'src/'.fake()->word().'.js',
            'loc' => 120,
            'sloc' => 95,
            'cyclomatic_complexity' => 4,
            'halstead_volume' => 850.25,
            'halstead_difficulty' => 12.5,
            'halstead_effort' => 10628.13,
            'maintainability_index' => 68.4,
        ];
    }
}
