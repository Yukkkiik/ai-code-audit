<?php

namespace Tests\Feature\Api\V1;

use App\Models\AuditScan;
use App\Models\ScanFinding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListAuditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_gets_401_envelope(): void
    {
        $this->getJson('/api/v1/audits')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->get('/api/v1/audits')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_user_sees_only_own_audits_newest_first(): void
    {
        $user = User::factory()->create();
        $older = AuditScan::factory()->ownedBy($user)->create(['created_at' => now()->subDays(2)]);
        $newer = AuditScan::factory()->ownedBy($user)->create(['created_at' => now()->subDay()]);
        AuditScan::factory()->ownedBy()->create();
        AuditScan::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/audits')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->public_id)
            ->assertJsonPath('data.1.id', $older->public_id);
    }

    public function test_pagination_meta_matches_contract(): void
    {
        $user = User::factory()->create();
        AuditScan::factory()->ownedBy($user)->count(5)->create();

        $this->actingAs($user)->getJson('/api/v1/audits?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta', ['current_page' => 2, 'per_page' => 2, 'total' => 5, 'last_page' => 3])
            ->assertJsonMissingPath('links');
    }

    public function test_default_page_size_is_15(): void
    {
        $user = User::factory()->create();
        AuditScan::factory()->ownedBy($user)->count(16)->create();

        $this->actingAs($user)->getJson('/api/v1/audits')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_invalid_pagination_parameters_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/audits?per_page=101')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['per_page']]]);

        $this->actingAs($user)->getJson('/api/v1/audits?per_page=0')->assertUnprocessable();
        $this->actingAs($user)->getJson('/api/v1/audits?page=abc')->assertUnprocessable();
    }

    public function test_list_uses_constant_number_of_queries(): void
    {
        $user = User::factory()->create();
        AuditScan::factory()->ownedBy($user)->count(10)->create()
            ->each(fn (AuditScan $audit) => ScanFinding::factory()->for($audit)->count(3)->create());

        DB::enableQueryLog();
        $this->actingAs($user)->getJson('/api/v1/audits')->assertOk()->assertJsonCount(10, 'data');
        $queries = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(2, $queries, "Daftar audit memakai {$queries} query (indikasi N+1).");
    }
}
