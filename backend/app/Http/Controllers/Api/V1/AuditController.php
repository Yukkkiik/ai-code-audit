<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SourceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListAuditsRequest;
use App\Http\Requests\Api\V1\StoreAuditRequest;
use App\Http\Resources\AuditCollection;
use App\Http\Resources\AuditResource;
use App\Models\AuditScan;
use App\Services\Audit\AuditScanCreator;
use App\Services\Audit\CreatedAuditScan;
use App\Services\Ingestion\AuditWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuditController extends Controller
{
    /**
     * Membuat audit dari ZIP atau URL Git publik. Audit berstatus `queued`;
     * guest menerima `guest_token` satu kali di response ini.
     */
    public function store(StoreAuditRequest $request, AuditScanCreator $creator, AuditWorkspace $workspace): JsonResponse
    {
        $owner = $request->user();

        $created = match ($request->sourceType()) {
            SourceType::Zip => DB::transaction(function () use ($request, $creator, $workspace, $owner) {
                $file = $request->file('file');
                $originalName = Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 255, '');

                $created = $creator->create(
                    $owner,
                    SourceType::Zip,
                    $this->repositoryNameFromFilename($originalName),
                    originalFilename: $originalName,
                    sourceSizeBytes: $file->getSize(),
                );

                $workspace->storeUpload($created->audit, $file);

                return $created;
            }),
            SourceType::Git => $creator->create(
                $owner,
                SourceType::Git,
                $request->gitRepository()->repositoryName,
                repositoryUrl: $request->gitRepository()->url,
            ),
        };

        return $this->createdResponse($request, $created);
    }

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

    private function createdResponse(Request $request, CreatedAuditScan $created): JsonResponse
    {
        $created->audit->loadCount(AuditScan::summaryCounts());

        $data = ['audit' => (new AuditResource($created->audit))->resolve($request)];

        if ($created->guestToken !== null) {
            $data['guest_token'] = $created->guestToken;
        }

        return response()->json(['data' => $data], 202);
    }

    private function repositoryNameFromFilename(string $filename): string
    {
        $name = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($filename, PATHINFO_FILENAME)), '-.');

        return $name === '' ? 'repository' : Str::limit($name, 255, '');
    }
}
