<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DependencyRelation;
use App\Enums\DependencyScope;
use App\Enums\VersionStatus;
use Illuminate\Validation\Rule;

class ListDependenciesRequest extends ListAuditChildrenRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->paginationRules() + [
            'scope' => ['sometimes', Rule::enum(DependencyScope::class)],
            'relation' => ['sometimes', Rule::enum(DependencyRelation::class)],
            'version_status' => ['sometimes', Rule::enum(VersionStatus::class)],
        ];
    }
}
