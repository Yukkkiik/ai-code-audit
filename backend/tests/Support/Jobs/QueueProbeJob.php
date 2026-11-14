<?php

namespace Tests\Support\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Job khusus test untuk membuktikan jalur dispatch → Redis → worker.
 * Bukan bagian dari fitur aplikasi.
 */
class QueueProbeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $markerKey) {}

    public function handle(): void
    {
        Cache::store('redis')->put($this->markerKey, 'processed', 60);
    }
}
