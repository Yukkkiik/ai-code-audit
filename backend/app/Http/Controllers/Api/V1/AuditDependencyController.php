<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListDependenciesRequest;
use App\Http\Resources\DependencyCollection;
use App\Models\AuditScan;
use App\Services\Audit\AuditAccessGuard;
use App\Services\Sca\ScaScanSummary;

class AuditDependencyController extends Controller
{
    /**
     * Daftar dependency audit (akses diperiksa di ListDependenciesRequest::authorize()).
     */
    public function index(ListDependenciesRequest $request, AuditScan $audit, AuditAccessGuard $guard, ScaScanSummary $summary): DependencyCollection
    {
        $guard->ensureReady($audit);

        $dependencies = $audit->dependencies()
            ->withCount('vulnerabilities')
            ->when($request->validated('scope'), fn ($query, $scope) => $query->where('scope', $scope))
            ->when($request->validated('relation'), fn ($query, $relation) => $query->where('relation', $relation))
            ->when($request->validated('version_status'), fn ($query, $status) => $query->where('version_status', $status))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage());

        return new DependencyCollection($dependencies, $summary->build($audit, vulnerabilityList: false));
    }
}
