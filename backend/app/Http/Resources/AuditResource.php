<?php

namespace App\Http\Resources;

use App\Enums\Severity;
use App\Enums\StageStatus;
use App\Models\AuditScan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Representasi `Audit` sesuai API_CONTRACT.md §1.
 * Membutuhkan hitungan dari AuditScan::summaryCounts() (withCount / loadCount).
 *
 * @mixin AuditScan
 */
class AuditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'source_type' => $this->source_type->value,
            'repository_name' => $this->repository_name,
            'repository_url' => $this->repository_url,
            'commit_sha' => $this->commit_sha,
            'status' => $this->status->value,
            'sast' => ['status' => $this->sast_status->value, 'error' => $this->sast_error],
            'sca' => ['status' => $this->sca_status->value, 'error' => $this->sca_error],
            'health_score' => $this->health_score,
            'health_category' => $this->health_category,
            'summary' => $this->summary(),
            'error' => $this->error_code === null ? null : [
                'code' => $this->error_code,
                'message' => $this->error_message,
            ],
            'queued_at' => $this->timestamp($this->queued_at),
            'started_at' => $this->timestamp($this->started_at),
            'finished_at' => $this->timestamp($this->finished_at),
            'created_at' => $this->timestamp($this->created_at),
        ];
    }

    /**
     * Hanya tahap yang menghasilkan data (completed/partial) yang dihitung;
     * tahap lain bernilai null agar tidak terbaca sebagai "0 masalah".
     *
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        $sastHasResults = $this->hasResults($this->sast_status);
        $scaHasResults = $this->hasResults($this->sca_status);

        $findings = null;
        if ($sastHasResults) {
            $findings = [];
            foreach (Severity::cases() as $severity) {
                $findings[$severity->value] = (int) $this->{"findings_{$severity->value}_count"};
            }
        }

        return [
            'findings_by_severity' => $findings,
            'dependencies_total' => $scaHasResults ? (int) $this->dependencies_count : null,
            'dependencies_unresolved' => $scaHasResults ? (int) $this->dependencies_unresolved_count : null,
            'vulnerabilities_total' => $scaHasResults ? (int) $this->vulnerabilities_count : null,
        ];
    }

    private function hasResults(StageStatus $status): bool
    {
        return in_array($status, [StageStatus::Completed, StageStatus::Partial], true);
    }

    private function timestamp(?Carbon $value): ?string
    {
        return $value?->utc()->toIso8601ZuluString();
    }
}
