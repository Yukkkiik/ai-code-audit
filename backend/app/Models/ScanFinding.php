<?php

namespace App\Models;

use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\Severity;
use Database\Factories\ScanFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Temuan SAST yang sudah dinormalisasi.
 */
#[Fillable([
    'audit_scan_id', 'tool', 'rule_id', 'category', 'severity', 'file_path', 'line_start',
    'line_end', 'column_start', 'code_snippet', 'message', 'cwe', 'fingerprint',
])]
class ScanFinding extends Model
{
    /** @use HasFactory<ScanFindingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tool' => FindingTool::class,
            'category' => FindingCategory::class,
            'severity' => Severity::class,
            'line_start' => 'integer',
            'line_end' => 'integer',
            'column_start' => 'integer',
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
     * @return HasMany<AiRefactoring, $this>
     */
    public function aiRefactorings(): HasMany
    {
        return $this->hasMany(AiRefactoring::class);
    }
}
