<?php

namespace App\Models;

use App\Enums\AuditStatus;
use App\Enums\SourceType;
use App\Enums\StageStatus;
use Database\Factories\AuditScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sesi audit: pusat relasi seluruh hasil SAST, SCA, dan AI.
 * `id` tetap auto-increment untuk relasi internal; `public_id` (ULID) dipakai di API.
 */
#[Fillable([
    'user_id', 'guest_token_hash', 'source_type', 'repository_name', 'repository_url',
    'original_filename', 'source_size_bytes', 'commit_sha', 'status', 'sast_status',
    'sca_status', 'sast_error', 'sca_error', 'error_code', 'error_message', 'health_score',
    'health_category', 'queued_at', 'started_at', 'finished_at',
])]
#[Hidden(['guest_token_hash'])]
class AuditScan extends Model
{
    /** @use HasFactory<AuditScanFactory> */
    use HasFactory, HasUlids;

    /**
     * Nilai awal yang sama dengan default kolom di database.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'queued',
        'sast_status' => 'pending',
        'sca_status' => 'pending',
    ];

    /**
     * Kolom yang diisi ULID otomatis.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'status' => AuditStatus::class,
            'sast_status' => StageStatus::class,
            'sca_status' => StageStatus::class,
            'source_size_bytes' => 'integer',
            'health_score' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ScanToolRun, $this>
     */
    public function toolRuns(): HasMany
    {
        return $this->hasMany(ScanToolRun::class);
    }

    /**
     * @return HasMany<ScanFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(ScanFinding::class);
    }

    /**
     * @return HasMany<CodeMetric, $this>
     */
    public function codeMetrics(): HasMany
    {
        return $this->hasMany(CodeMetric::class);
    }

    /**
     * @return HasMany<Dependency, $this>
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(Dependency::class);
    }

    /**
     * @return HasMany<Vulnerability, $this>
     */
    public function vulnerabilities(): HasMany
    {
        return $this->hasMany(Vulnerability::class);
    }

    /**
     * @return HasMany<AiRefactoring, $this>
     */
    public function aiRefactorings(): HasMany
    {
        return $this->hasMany(AiRefactoring::class);
    }
}
