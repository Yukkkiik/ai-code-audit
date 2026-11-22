<?php

namespace App\Services\Sast;

/**
 * Hasil run metrik: status eksekusi (selaras dengan tool lain) + metrik per file.
 */
final readonly class MetricsResult
{
    /**
     * @param  list<FileMetrics>  $files
     */
    public function __construct(
        public ToolRunResult $run,
        public array $files,
    ) {}
}
