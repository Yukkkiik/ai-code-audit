<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_returns_ok_when_database_is_reachable(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertExactJsonStructure([
                'data' => ['status', 'checks' => ['database'], 'time'],
            ])
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.database', 'ok');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $response->json('data.time'),
        );
    }

    public function test_health_returns_503_without_leaking_details_when_database_is_unreachable(): void
    {
        Log::spy();
        config(['database.connections.mysql.port' => 1]);
        DB::purge('mysql');

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503)
            ->assertJsonPath('data.status', 'degraded')
            ->assertJsonPath('data.checks.database', 'fail')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('127.0.0.1')
            ->assertDontSee(config('database.connections.mysql.database'));

        Log::shouldHaveReceived('warning')->once();
    }
}
