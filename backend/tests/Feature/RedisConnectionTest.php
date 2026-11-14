<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class RedisConnectionTest extends TestCase
{
    public function test_redis_responds_to_ping(): void
    {
        $this->assertContains(Redis::connection()->ping(), [true, '+PONG', 'PONG']);
    }

    /**
     * Pengaman: test tidak boleh menulis ke database Redis development (0 dan 1).
     */
    public function test_tests_run_against_dedicated_redis_databases(): void
    {
        $this->assertSame(14, Redis::connection('default')->client()->getDbNum());
        $this->assertSame(15, Redis::connection('cache')->client()->getDbNum());
    }

    public function test_redis_cache_store_can_put_get_and_forget(): void
    {
        $store = Cache::store('redis');
        $key = 'redis-connection-test:'.Str::uuid();

        $store->put($key, 'nilai', 60);
        $this->assertSame('nilai', $store->get($key));

        $store->forget($key);
        $this->assertNull($store->get($key));
    }

    public function test_redis_session_driver_persists_data_between_requests(): void
    {
        $session = $this->app['session']->driver('redis');
        $session->start();
        $session->put('redis-connection-test', 'nilai');
        $session->save();
        $sessionId = $session->getId();

        $this->app['session']->forgetDrivers();

        $reloaded = $this->app['session']->driver('redis');
        $reloaded->setId($sessionId);
        $reloaded->start();

        $this->assertSame('nilai', $reloaded->get('redis-connection-test'));

        $reloaded->getHandler()->destroy($sessionId);
    }
}
