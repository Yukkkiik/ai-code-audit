<?php

namespace Database\Factories;

use App\Enums\AiSource;
use App\Enums\AiStatus;
use App\Models\AiRefactoring;
use App\Models\ScanFinding;
use App\Models\Vulnerability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiRefactoring>
 */
class AiRefactoringFactory extends Factory
{
    /**
     * Default: remediation untuk temuan SAST. `audit_scan_id` diambil dari target.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scan_finding_id' => ScanFinding::factory(),
            'vulnerability_id' => null,
            'audit_scan_id' => fn (array $attributes) => $attributes['scan_finding_id'] !== null
                ? ScanFinding::query()->findOrFail($attributes['scan_finding_id'])->audit_scan_id
                : Vulnerability::query()->findOrFail($attributes['vulnerability_id'])->audit_scan_id,
            'requested_by_user_id' => null,
            'source' => AiSource::Llm,
            'provider' => 'test-provider',
            'model' => 'test-model',
            'prompt_version' => 'v1',
            'status' => AiStatus::Pending,
            'original_code' => 'eval(userInput);',
        ];
    }

    /**
     * Remediation untuk kerentanan dependency (bukan temuan SAST).
     */
    public function forVulnerability(): static
    {
        return $this->state(fn () => [
            'scan_finding_id' => null,
            'vulnerability_id' => Vulnerability::factory(),
            'original_code' => null,
        ]);
    }
}
