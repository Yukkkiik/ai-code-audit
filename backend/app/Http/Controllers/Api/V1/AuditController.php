<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListAuditsRequest;
use App\Http\Resources\AuditCollection;
use App\Http\Resources\AuditResource;
use App\Models\AuditScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuditController extends Controller
{
    /**
     * Riwayat audit milik user yang login (terbaru dulu).
     */
    public function index(ListAuditsRequest $request): AuditCollection
    {
        $audits = $request->user()
            ->auditScans()
            ->withCount(AuditScan::summaryCounts())
            ->latest()
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return new AuditCollection($audits);
    }

    /**
     * Detail audit untuk pemilik atau guest dengan header X-Guest-Token.
     * Akses ditolak dijawab 404 agar keberadaan audit tidak bocor.
     */
    public function show(Request $request, AuditScan $audit): AuditResource
    {
        if (Gate::denies('view', [$audit, $request->header('X-Guest-Token')])) {
            throw new NotFoundHttpException;
        }

        $audit->loadCount(AuditScan::summaryCounts());

        return new AuditResource($audit);
    }
}
