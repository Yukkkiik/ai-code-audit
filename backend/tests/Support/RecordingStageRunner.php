<?php

namespace Tests\Support;

use App\Enums\AuditStage;
use App\Models\AuditScan;
use App\Services\Audit\Stages\StageResult;
use App\Services\Audit\Stages\StageRunner;
use App\Services\Ingestion\IngestedRepository;
use Throwable;

/**
 * StageRunner palsu untuk test: mengembalikan hasil yang ditentukan (atau melempar exception)
 * dan mencatat repositori yang diterimanya, termasuk apakah root masih ada saat dijalankan.
 */
class RecordingStageRunner implements StageRunner
{
    /**
     * @var list<array{audit_id: int, root: string, root_existed: bool, files: list<string>}>
     */
    public array $calls = [];

    public function __construct(
        private AuditStage $stage,
        private StageResult|Throwable $outcome,
    ) {}

    public function stage(): AuditStage
    {
        return $this->stage;
    }

    public function run(AuditScan $audit, IngestedRepository $repository): StageResult
    {
        $this->calls[] = [
            'audit_id' => $audit->id,
            'root' => $repository->rootPath,
            'root_existed' => is_dir($repository->rootPath),
            'files' => is_dir($repository->rootPath) ? array_values(array_diff(scandir($repository->rootPath), ['.', '..'])) : [],
        ];

        if ($this->outcome instanceof Throwable) {
            throw $this->outcome;
        }

        return $this->outcome;
    }
}
