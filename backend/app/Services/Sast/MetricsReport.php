<?php

namespace App\Services\Sast;

final readonly class MetricsReport
{
    /**
     * @param  list<FileMetrics>  $files
     * @param  int  $errorCount  file yang tidak dapat dianalisis (gagal di-parse, terlalu besar, path tidak aman)
     * @param  int  $droppedCount  entri yang dibuang karena bentuknya tidak valid
     */
    public function __construct(
        public array $files,
        public int $errorCount,
        public int $droppedCount,
    ) {}
}
