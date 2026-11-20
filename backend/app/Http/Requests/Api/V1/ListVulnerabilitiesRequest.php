<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\VulnerabilitySeverity;
use Illuminate\Validation\Rule;

class ListVulnerabilitiesRequest extends ListAuditChildrenRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->paginationRules() + [
            'severity' => ['sometimes', Rule::enum(VulnerabilitySeverity::class)],
        ];
    }
}
