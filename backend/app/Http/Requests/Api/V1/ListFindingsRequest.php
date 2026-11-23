<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\Severity;
use Illuminate\Validation\Rule;

class ListFindingsRequest extends ListAuditChildrenRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->paginationRules() + [
            'severity' => ['sometimes', Rule::enum(Severity::class)],
            'tool' => ['sometimes', Rule::enum(FindingTool::class)],
            'category' => ['sometimes', Rule::enum(FindingCategory::class)],
        ];
    }
}
