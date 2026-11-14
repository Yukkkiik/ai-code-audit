<?php

namespace Tests\Feature\Models;

use App\Enums\AuditStatus;
use App\Enums\SourceType;
use App\Enums\StageStatus;
use App\Models\AiRefactoring;
use App\Models\AuditScan;
use App\Models\CodeMetric;
use App\Models\Dependency;
use App\Models\ScanFinding;
use App\Models\ScanToolRun;
use App\Models\User;
use App\Models\Vulnerability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditScanModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_id_is_generated_as_lowercase_ulid_while_id_stays_incrementing(): void
    {
        $audit = AuditScan::factory()->create();

        $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $audit->public_id);
        $this->assertIsInt($audit->id);
        $this->assertTrue($audit->getIncrementing());
    }

    public function test_guest_token_hash_is_hidden_from_serialization(): void
    {
        $audit = AuditScan::factory()->create();

        $this->assertNotNull($audit->guest_token_hash);
        $this->assertArrayNotHasKey('guest_token_hash', $audit->toArray());
    }

    public function test_status_columns_are_cast_to_enums_with_database_defaults(): void
    {
        $audit = AuditScan::factory()->create()->refresh();

        $this->assertSame(SourceType::Zip, $audit->source_type);
        $this->assertSame(AuditStatus::Queued, $audit->status);
        $this->assertSame(StageStatus::Pending, $audit->sast_status);
        $this->assertSame(StageStatus::Pending, $audit->sca_status);
    }

    public function test_guest_and_owned_factory_states(): void
    {
        $user = User::factory()->create();

        $guestAudit = AuditScan::factory()->create();
        $ownedAudit = AuditScan::factory()->ownedBy($user)->create();

        $this->assertNull($guestAudit->user_id);
        $this->assertNull($ownedAudit->guest_token_hash);
        $this->assertTrue($ownedAudit->user->is($user));
        $this->assertTrue($user->auditScans->first()->is($ownedAudit));
    }

    public function test_audit_scan_has_many_result_relations(): void
    {
        $audit = AuditScan::factory()->create();
        ScanToolRun::factory()->for($audit)->create();
        $finding = ScanFinding::factory()->for($audit)->create();
        CodeMetric::factory()->for($audit)->create();
        $dependency = Dependency::factory()->for($audit)->create();
        Vulnerability::factory()->for($dependency)->create();
        AiRefactoring::factory()->for($finding)->create();

        $audit->refresh();

        $this->assertCount(1, $audit->toolRuns);
        $this->assertCount(1, $audit->findings);
        $this->assertCount(1, $audit->codeMetrics);
        $this->assertCount(1, $audit->dependencies);
        $this->assertCount(1, $audit->vulnerabilities);
        $this->assertCount(1, $audit->aiRefactorings);
    }

    public function test_deleting_audit_model_cascades_to_all_results(): void
    {
        $audit = AuditScan::factory()->create();
        $finding = ScanFinding::factory()->for($audit)->create();
        AiRefactoring::factory()->for($finding)->create();
        Vulnerability::factory()->for(Dependency::factory()->for($audit))->create();

        $audit->delete();

        $this->assertDatabaseEmpty('scan_findings');
        $this->assertDatabaseEmpty('dependencies');
        $this->assertDatabaseEmpty('vulnerabilities');
        $this->assertDatabaseEmpty('ai_refactorings');
    }
}
