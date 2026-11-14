<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AuditStatus;
use App\Enums\Severity;
use App\Enums\SourceType;
use App\Enums\StageStatus;
use App\Enums\UnresolvedReason;
use App\Models\AuditScan;
use App\Models\Dependency;
use App\Models\ScanFinding;
use App\Models\User;
use App\Models\Vulnerability;
use App\Services\Audit\AuditScanCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_with_correct_token_can_view_audit(): void
    {
        [$audit, $token] = $this->guestAudit();

        $response = $this->getJson("/api/v1/audits/{$audit->public_id}", ['X-Guest-Token' => $token]);

        $response->assertOk()
            ->assertExactJsonStructure(['data' => [
                'id', 'source_type', 'repository_name', 'repository_url', 'commit_sha', 'status',
                'sast' => ['status', 'error'], 'sca' => ['status', 'error'],
                'health_score', 'health_category',
                'summary' => ['findings_by_severity', 'dependencies_total', 'dependencies_unresolved', 'vulnerabilities_total'],
                'error', 'queued_at', 'started_at', 'finished_at', 'created_at',
            ]])
            ->assertJsonPath('data.id', $audit->public_id)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.sast.status', 'pending')
            ->assertJsonPath('data.error', null);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json('data.queued_at'));
    }

    public function test_response_never_exposes_internal_identifiers(): void
    {
        [$audit, $token] = $this->guestAudit();

        $this->getJson("/api/v1/audits/{$audit->public_id}", ['X-Guest-Token' => $token])
            ->assertOk()
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.guest_token_hash')
            ->assertDontSee($audit->guest_token_hash)
            ->assertDontSee('"id":'.$audit->id.',', false);
    }

    public function test_guest_with_wrong_or_missing_token_gets_not_found(): void
    {
        [$audit] = $this->guestAudit();

        $this->getJson("/api/v1/audits/{$audit->public_id}", ['X-Guest-Token' => str_repeat('a', 64)])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->getJson("/api/v1/audits/{$audit->public_id}")->assertNotFound();
    }

    public function test_owner_can_view_own_audit(): void
    {
        $owner = User::factory()->create();
        $audit = AuditScan::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)->getJson("/api/v1/audits/{$audit->public_id}")->assertOk();
    }

    public function test_other_user_and_anonymous_cannot_view_owned_audit(): void
    {
        $audit = AuditScan::factory()->ownedBy()->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/audits/{$audit->public_id}")
            ->assertNotFound();
    }

    public function test_anonymous_cannot_view_owned_audit_even_with_some_token(): void
    {
        $audit = AuditScan::factory()->ownedBy()->create();

        $this->getJson("/api/v1/audits/{$audit->public_id}", ['X-Guest-Token' => str_repeat('a', 64)])
            ->assertNotFound();
    }

    public function test_unknown_or_malformed_audit_id_returns_not_found(): void
    {
        $this->getJson('/api/v1/audits/01jd3x5w8q2g7b9n4k6m1r0t2y')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->getJson('/api/v1/audits/bukan-ulid')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->getJson('/api/v1/audits/1')->assertNotFound();
    }

    public function test_summary_is_null_for_stages_without_results(): void
    {
        [$audit, $token] = $this->guestAudit();
        ScanFinding::factory()->for($audit)->create();

        $this->getJson("/api/v1/audits/{$audit->public_id}", ['X-Guest-Token' => $token])
            ->assertOk()
            ->assertJsonPath('data.summary.findings_by_severity', null)
            ->assertJsonPath('data.summary.dependencies_total', null)
            ->assertJsonPath('data.summary.dependencies_unresolved', null)
            ->assertJsonPath('data.summary.vulnerabilities_total', null);
    }

    public function test_summary_counts_results_of_finished_stages(): void
    {
        [$audit, $token] = $this->guestAudit();
        $audit->forceFill([
            'status' => AuditStatus::Partial,
            'sast_status' => StageStatus::Completed,
            'sca_status' => StageStatus::Partial,
            'sca_error' => 'Sebagian query OSV.dev gagal.',
        ])->save();
        ScanFinding::factory()->for($audit)->count(2)->create(['severity' => Severity::High]);
        ScanFinding::factory()->for($audit)->create(['severity' => Severity::Low]);
        $vulnerable = Dependency::factory()->for($audit)->create();
        Dependency::factory()->for($audit)->unresolved(UnresolvedReason::RangeWithoutLockfile)->create();
        Vulnerability::factory()->for($vulnerable)->count(2)->create();
        ScanFinding::factory()->create(['severity' => Severity::Critical]);

        $this->getJson("/api/v1/audits/{$audit->public_id}", ['X-Guest-Token' => $token])
            ->assertOk()
            ->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.sca.error', 'Sebagian query OSV.dev gagal.')
            ->assertJsonPath('data.summary.findings_by_severity', [
                'critical' => 0, 'high' => 2, 'medium' => 0, 'low' => 1, 'info' => 0,
            ])
            ->assertJsonPath('data.summary.dependencies_total', 2)
            ->assertJsonPath('data.summary.dependencies_unresolved', 1)
            ->assertJsonPath('data.summary.vulnerabilities_total', 2);
    }

    public function test_failed_audit_exposes_safe_error_object(): void
    {
        [$audit, $token] = $this->guestAudit();
        $audit->forceFill([
            'status' => AuditStatus::Failed,
            'error_code' => 'ZIP_SLIP_DETECTED',
            'error_message' => 'Arsip berisi path yang keluar dari direktori kerja.',
        ])->save();

        $this->getJson("/api/v1/audits/{$audit->public_id}", ['X-Guest-Token' => $token])
            ->assertOk()
            ->assertJsonPath('data.error', [
                'code' => 'ZIP_SLIP_DETECTED',
                'message' => 'Arsip berisi path yang keluar dari direktori kerja.',
            ]);
    }

    /**
     * @return array{AuditScan, string}
     */
    private function guestAudit(): array
    {
        $created = $this->app->make(AuditScanCreator::class)->create(null, SourceType::Zip, 'tugas-akhir');

        return [$created->audit, $created->guestToken];
    }
}
