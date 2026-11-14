<?php

namespace App\Models;

use App\Enums\CodeLanguage;
use App\Enums\MetricScope;
use Database\Factories\CodeMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metrik kualitas kode per file atau per fungsi (JS/TS).
 */
#[Fillable([
    'audit_scan_id', 'scope', 'language', 'file_path', 'function_name', 'line_start', 'loc',
    'sloc', 'cyclomatic_complexity', 'halstead_volume', 'halstead_difficulty',
    'halstead_effort', 'maintainability_index',
])]
class CodeMetric extends Model
{
    /** @use HasFactory<CodeMetricFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => MetricScope::class,
            'language' => CodeLanguage::class,
            'line_start' => 'integer',
            'loc' => 'integer',
            'sloc' => 'integer',
            'cyclomatic_complexity' => 'integer',
            'halstead_volume' => 'decimal:2',
            'halstead_difficulty' => 'decimal:2',
            'halstead_effort' => 'decimal:2',
            'maintainability_index' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<AuditScan, $this>
     */
    public function auditScan(): BelongsTo
    {
        return $this->belongsTo(AuditScan::class);
    }
}
