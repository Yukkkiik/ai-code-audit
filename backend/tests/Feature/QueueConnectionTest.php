<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Jobs\FailingProbeJob;
use Tests\Support\Jobs\QueueProbeJob;
use Tests\TestCase;

class QueueConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_redis_queue_connection_uses_default_redis_connection(): void
    {
        $this->assertSame('redis', config('queue.connections.redis.driver'));
        $this->assertSame('default', config('queue.connections.redis.connection'));
    }

    public function test_job_is_stored_in_redis_until_a_worker_processes_it(): void
    {
        $queue = 'queue-connection-test-'.Str::uuid();
        $marker = 'queue-probe:'.Str::uuid();
        $redisQueue = Queue::connection('redis');

        QueueProbeJob::dispatch($marker)->onConnection('redis')->onQueue($queue);

        $this->assertSame(1, $redisQueue->size($queue));
        $this->assertNull(Cache::store('redis')->get($marker));

        $exitCode = Artisan::call('queue:work', [
            'connection' => 'redis',
            '--queue' => $queue,
            '--once' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('processed', Cache::store('redis')->get($marker));
        $this->assertSame(0, $redisQueue->size($queue));

        Cache::store('redis')->forget($marker);
    }

    public function test_failed_job_is_recorded_in_failed_jobs_table(): void
    {
        $queue = 'queue-failure-test-'.Str::uuid();

        FailingProbeJob::dispatch()->onConnection('redis')->onQueue($queue);

        Artisan::call('queue:work', [
            'connection' => 'redis',
            '--queue' => $queue,
            '--once' => true,
        ]);

        $failed = DB::table('failed_jobs')->where('queue', $queue)->first();

        $this->assertNotNull($failed, 'Job gagal tidak tercatat di failed_jobs.');
        $this->assertSame('redis', $failed->connection);
        $this->assertStringContainsString('FailingProbeJob sengaja gagal.', $failed->exception);
        $this->assertSame(0, Queue::connection('redis')->size($queue));
    }
}
