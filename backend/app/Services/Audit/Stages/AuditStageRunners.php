<?php

namespace App\Services\Audit\Stages;

use App\Enums\AuditStage;
use InvalidArgumentException;

/**
 * Daftar runner per tahap. Setiap AuditStage wajib punya tepat satu runner.
 */
class AuditStageRunners
{
    /**
     * @var array<string, StageRunner>
     */
    private array $runners = [];

    /**
     * @param  iterable<StageRunner>  $runners
     */
    public function __construct(iterable $runners)
    {
        foreach ($runners as $runner) {
            $this->runners[$runner->stage()->value] = $runner;
        }

        foreach (AuditStage::cases() as $stage) {
            if (! isset($this->runners[$stage->value])) {
                throw new InvalidArgumentException("Runner untuk tahap {$stage->value} belum didaftarkan.");
            }
        }
    }

    public function for(AuditStage $stage): StageRunner
    {
        return $this->runners[$stage->value];
    }
}
