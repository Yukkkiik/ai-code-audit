<?php

namespace App\Http\Resources;

use App\Models\ScanFinding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu temuan SAST. `message` dan `code_snippet` berasal dari repositori target (data tidak tepercaya): dikirim
 * apa adanya dan wajib di-escape oleh klien. Tanpa fingerprint, `audit_scan_id`, maupun timestamp internal.
 *
 * @mixin ScanFinding
 */
class FindingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tool' => $this->tool->value,
            'rule_id' => $this->rule_id,
            'category' => $this->category->value,
            'severity' => $this->severity->value,
            'file_path' => $this->file_path,
            'line_start' => $this->line_start,
            'line_end' => $this->line_end,
            'column_start' => $this->column_start,
            'code_snippet' => $this->code_snippet,
            'message' => $this->message,
            'cwe' => $this->cwe,
        ];
    }
}
