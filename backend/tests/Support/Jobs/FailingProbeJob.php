<?php

namespace Tests\Support\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Job khusus test yang selalu gagal, untuk membuktikan pencatatan failed_jobs.
 * Bukan bagian dari fitur aplikasi.
 */
class FailingProbeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        throw new RuntimeException('FailingProbeJob sengaja gagal.');
    }
}
