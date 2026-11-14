<?php

namespace App\Models;

use App\Enums\FindingTool;
use App\Enums\ToolRunStatus;
use Database\Factories\ScanToolRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status eksekusi satu tool SAST pada satu audit.
 */
#[Fillable([
    'audit_scan_id', 'tool', 'status', 'files_scanned', 'findings_count',
    'error_message', 'duration_ms', 'started_at', 'finished_at',
])]
class ScanToolRun extends Model
{
    /** @use HasFactory<ScanToolRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tool' => FindingTool::class,
            'status' => ToolRunStatus::class,
            'files_scanned' => 'integer',
            'findings_count' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
