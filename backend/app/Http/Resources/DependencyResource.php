<?php

namespace App\Http\Resources;

use App\Enums\OsvQueryStatus;
use App\Models\Dependency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu dependency. `vulnerabilities_count` hanya berisi angka bila dependency sudah diperiksa ke OSV
 * (`osv_status=queried`); selain itu null, tidak pernah 0, agar tidak terbaca "tidak ada kerentanan".
 * Membutuhkan withCount('vulnerabilities').
 *
 * @mixin Dependency
 */
class DependencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'declared_spec' => $this->declared_spec,
            'scope' => $this->scope->value,
            'relation' => $this->relation->value,
            'manifest_path' => $this->manifest_path,
            'source_file_type' => $this->source_file_type->value,
            'lock_path' => $this->lock_path,
            'version_status' => $this->version_status->value,
            'unresolved_reason' => $this->unresolved_reason?->value,
            'osv_status' => $this->osv_status->value,
            'vulnerabilities_count' => $this->osv_status === OsvQueryStatus::Queried ? (int) $this->vulnerabilities_count : null,
        ];
    }
}
