<?php

namespace App\Services\Audit;

use App\Enums\AuditStage;
use App\Enums\AuditStatus;
use App\Enums\StageStatus;
use App\Models\AuditScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Akses dan kesiapan resource turunan audit (dependencies, vulnerabilities).
 * Penolakan selalu 404 agar keberadaan audit tidak bocor (sama seperti AuditController::show).
 */
class AuditAccessGuard
{
    /**
     * @throws NotFoundHttpException bukan pemilik, token guest salah/hilang
     */
    public function authorize(Request $request, AuditScan $audit): void
    {
        if (Gate::denies('view', [$audit, $request->header('X-Guest-Token')])) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * 409 AUDIT_NOT_READY hanya untuk audit yang masih berjalan dan tahap yang diminta (default SCA) belum selesai.
     *
     * @throws HttpException
     */
    public function ensureReady(AuditScan $audit, AuditStage $stage = AuditStage::Sca): void
    {
        $running = in_array($audit->status, [AuditStatus::Queued, AuditStatus::Ingesting, AuditStatus::Scanning], true);
        $stageUnfinished = in_array($audit->{$stage->statusColumn()}, [StageStatus::Pending, StageStatus::Running], true);

        if ($running && $stageUnfinished) {
            throw new HttpException(409);
        }
    }
}
