<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListFindingsRequest;
use App\Http\Resources\FindingCollection;
use App\Models\AuditScan;
use App\Services\Audit\AuditAccessGuard;
use App\Services\Sast\SastScanSummary;

class AuditFindingController extends Controller
{
    /**
     * Urutan severity eksplisit (kolom berupa string, bukan ENUM MySQL).
     */
    private const SEVERITY_ORDER = "CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END";

    private const LIST_COLUMNS = ['id', 'tool', 'rule_id', 'category', 'severity', 'file_path', 'line_start', 'line_end', 'column_start', 'code_snippet', 'message', 'cwe'];

    /**
     * Daftar temuan SAST (akses diperiksa di ListFindingsRequest::authorize()).
     */
    public function index(ListFindingsRequest $request, AuditScan $audit, AuditAccessGuard $guard, SastScanSummary $summary): FindingCollection
    {
        $guard->ensureReady($audit, AuditStage::Sast);

        $findings = $audit->findings()
            ->select(self::LIST_COLUMNS)
            ->when($request->validated('severity'), fn ($query, $severity) => $query->where('severity', $severity))
            ->when($request->validated('tool'), fn ($query, $tool) => $query->where('tool', $tool))
            ->when($request->validated('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderByRaw(self::SEVERITY_ORDER)
            ->orderBy('file_path')
            ->orderBy('line_start')
            ->orderBy('id')
            ->paginate($request->perPage());

        return new FindingCollection($findings, $summary->forFindings($audit));
    }
}
