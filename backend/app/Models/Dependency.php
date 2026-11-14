<?php

namespace App\Models;

use App\Enums\DependencyRelation;
use App\Enums\DependencyScope;
use App\Enums\Ecosystem;
use App\Enums\ManifestType;
use App\Enums\OsvQueryStatus;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;
use Database\Factories\DependencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Komponen npm hasil ekstraksi package.json / package-lock.json.
 */
#[Fillable([
    'audit_scan_id', 'ecosystem', 'name', 'version', 'declared_spec', 'scope', 'relation',
    'manifest_path', 'source_file_type', 'lock_path', 'version_status', 'unresolved_reason',
    'osv_status',
])]
class Dependency extends Model
{
    /** @use HasFactory<DependencyFactory> */
    use HasFactory;

    /**
     * Nilai awal yang sama dengan default kolom di database.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'ecosystem' => 'npm',
        'osv_status' => 'not_queried',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ecosystem' => Ecosystem::class,
            'scope' => DependencyScope::class,
            'relation' => DependencyRelation::class,
            'source_file_type' => ManifestType::class,
            'version_status' => VersionStatus::class,
            'unresolved_reason' => UnresolvedReason::class,
            'osv_status' => OsvQueryStatus::class,
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
     * @return HasMany<Vulnerability, $this>
     */
    public function vulnerabilities(): HasMany
    {
        return $this->hasMany(Vulnerability::class);
    }
}
