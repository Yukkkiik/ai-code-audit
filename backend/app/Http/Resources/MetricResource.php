<?php

namespace App\Http\Resources;

use App\Models\CodeMetric;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu baris metrik (file atau fungsi). Nilai desimal keluar sebagai string dua desimal (cast `decimal:2`).
 *
 * @mixin CodeMetric
 */
class MetricResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'scope' => $this->scope->value,
            'language' => $this->language->value,
            'file_path' => $this->file_path,
            'function_name' => $this->function_name,
            'line_start' => $this->line_start,
            'loc' => $this->loc,
            'sloc' => $this->sloc,
            'cyclomatic_complexity' => $this->cyclomatic_complexity,
            'halstead_volume' => $this->halstead_volume,
            'halstead_difficulty' => $this->halstead_difficulty,
            'halstead_effort' => $this->halstead_effort,
            'maintainability_index' => $this->maintainability_index,
        ];
    }
}
