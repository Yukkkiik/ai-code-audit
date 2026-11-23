<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditStage;
use App\Enums\MetricScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListMetricsRequest;
use App\Http\Resources\MetricCollection;
use App\Models\AuditScan;
use App\Services\Audit\AuditAccessGuard;
use App\Services\Sast\SastScanSummary;

class AuditMetricController extends Controller
{
    private const LIST_COLUMNS = ['id', 'scope', 'language', 'file_path', 'function_name', 'line_start', 'loc', 'sloc', 'cyclomatic_complexity',
        'halstead_volume', 'halstead_difficulty', 'halstead_effort', 'maintainability_index'];

    /**
     * Daftar metrik kode (akses diperiksa di ListMetricsRequest::authorize()). Default scope: file.
     */
    public function index(ListMetricsRequest $request, AuditScan $audit, AuditAccessGuard $guard, SastScanSummary $summary): MetricCollection
    {
        $guard->ensureReady($audit, AuditStage::Sast);

        $scope = $request->scope();

        $query = $audit->codeMetrics()->select(self::LIST_COLUMNS)->where('scope', $scope);

        $query = $scope === MetricScope::Function
            ? $query->orderByDesc('cyclomatic_complexity')->orderBy('file_path')->orderBy('line_start')
            : $query->orderBy('file_path');

        return new MetricCollection($query->orderBy('id')->paginate($request->perPage()), $summary->forMetrics($audit, $scope));
    }
}
