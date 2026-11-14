<?php

namespace App\Models;

use App\Enums\AiSource;
use App\Enums\AiStatus;
use Database\Factories\AiRefactoringFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil AI remediation (atau fallback template) untuk tepat satu target:
 * temuan SAST atau kerentanan dependency (CHECK constraint di database).
 */
#[Fillable([
    'audit_scan_id', 'scan_finding_id', 'vulnerability_id', 'requested_by_user_id', 'source',
    'provider', 'model', 'prompt_version', 'status', 'original_code', 'root_cause', 'impact',
    'recommendation', 'suggested_code', 'rationale', 'validation_errors', 'latency_ms',
])]
class AiRefactoring extends Model
{
    /** @use HasFactory<AiRefactoringFactory> */
    use HasFactory;

    /**
     * Nilai awal yang sama dengan default kolom di database.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => AiSource::class,
            'status' => AiStatus::class,
            'validation_errors' => 'array',
            'latency_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AuditScan, $this>
     */
    public function auditScan(): BelongsTo
    {
        return $this->belongsTo(AuditScan::class);
    }

    /**
     * @return BelongsTo<ScanFinding, $this>
     */
    public function scanFinding(): BelongsTo
    {
        return $this->belongsTo(ScanFinding::class);
    }

    /**
     * @return BelongsTo<Vulnerability, $this>
     */
    public function vulnerability(): BelongsTo
    {
        return $this->belongsTo(Vulnerability::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
