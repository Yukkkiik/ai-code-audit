<?php

namespace App\Services\Sast;

final readonly class SemgrepReport
{
    /**
     * @param  list<SastFinding>  $findings
     * @param  int  $errorCount  entri `errors` Semgrep (mis. file gagal di-parse): file terkait tidak teranalisis penuh
     * @param  int  $droppedCount  hasil yang dibuang karena path/bentuknya tidak valid
     */
    public function __construct(
        public array $findings,
        public int $errorCount,
        public int $droppedCount,
    ) {}
}
