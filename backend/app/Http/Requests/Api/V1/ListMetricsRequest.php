<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\MetricScope;
use Illuminate\Validation\Rule;

class ListMetricsRequest extends ListAuditChildrenRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->paginationRules() + [
            'scope' => ['sometimes', Rule::enum(MetricScope::class)],
        ];
    }

    public function scope(): MetricScope
    {
        return MetricScope::from($this->validated('scope', MetricScope::File->value));
    }
}
