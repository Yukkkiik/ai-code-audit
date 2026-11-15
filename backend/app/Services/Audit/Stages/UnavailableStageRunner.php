<?php

namespace App\Services\Audit\Stages;

use App\Enums\AuditStage;
use App\Models\AuditScan;
use App\Services\Ingestion\IngestedRepository;

/**
 * Penanda jujur untuk tahap yang engine-nya belum dibangun: tahap dicatat `failed`
 * (bukan `skipped` atau `completed`) agar audit tidak terlihat sudah diperiksa.
 */
class UnavailableStageRunner implements StageRunner
{
    public function __construct(private AuditStage $stage) {}

    public function stage(): AuditStage
    {
        return $this->stage;
    }

    public function run(AuditScan $audit, IngestedRepository $repository): StageResult
    {
        return StageResult::failed('Engine '.strtoupper($this->stage->value).' belum tersedia di versi platform ini.');
    }
}
