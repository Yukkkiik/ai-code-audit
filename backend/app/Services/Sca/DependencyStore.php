<?php

namespace App\Services\Sca;

use App\Models\AuditScan;
use App\Models\Dependency;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan dependensi satu audit. Baris lama audit itu diganti dalam transaksi yang sama,
 * sehingga memanggil ulang tidak menggandakan data dan kegagalan insert tidak meninggalkan data setengah jadi.
 */
class DependencyStore
{
    private const CHUNK_SIZE = 500;

    /**
     * @param  list<ParsedDependency>  $dependencies
     * @return int jumlah baris tersimpan
     */
    public function store(AuditScan $audit, array $dependencies): int
    {
        return DB::transaction(function () use ($audit, $dependencies) {
            Dependency::query()->where('audit_scan_id', $audit->id)->delete();

            $now = now()->toDateTimeString();

            foreach (array_chunk($dependencies, self::CHUNK_SIZE) as $chunk) {
                Dependency::query()->insert(array_map(fn (ParsedDependency $dep) => [
                    'audit_scan_id' => $audit->id,
                    'ecosystem' => 'npm',
                    'name' => $dep->name,
                    'version' => $dep->version,
                    'declared_spec' => $dep->declaredSpec,
                    'scope' => $dep->scope->value,
                    'relation' => $dep->relation->value,
                    'manifest_path' => $dep->manifestPath,
                    'source_file_type' => $dep->sourceFileType->value,
                    'lock_path' => $dep->lockPath,
                    'version_status' => $dep->versionStatus->value,
                    'unresolved_reason' => $dep->unresolvedReason?->value,
                    'osv_status' => 'not_queried',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            return count($dependencies);
        });
    }
}
