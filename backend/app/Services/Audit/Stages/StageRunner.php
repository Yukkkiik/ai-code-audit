<?php

namespace App\Services\Audit\Stages;

use App\Enums\AuditStage;
use App\Models\AuditScan;
use App\Services\Ingestion\IngestedRepository;

/**
 * Engine satu tahap pemindaian (SAST di TAHAP 6, SCA di TAHAP 7).
 * Runner menyimpan hasilnya sendiri; status tahap dikelola ProcessAuditJob.
 */
interface StageRunner
{
    public function stage(): AuditStage;

    public function run(AuditScan $audit, IngestedRepository $repository): StageResult;
}
