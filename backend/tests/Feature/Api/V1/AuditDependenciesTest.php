<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AuditStatus;
use App\Enums\DependencyRelation;
use App\Enums\DependencyScope;
use App\Enums\OsvQueryStatus;
use App\Enums\StageStatus;
use App\Models\AuditScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsScaResults;
use Tests\TestCase;

class AuditDependenciesTest extends TestCase
{
    use BuildsScaResults, RefreshDatabase;

    private function url(AuditScan $audit, string $query = ''): string
    {
        return "/api/v1/audits/{$audit->public_id}/dependencies".$query;
    }

    public function test_guest_with_token_lists_dependencies_with_the_documented_shape(): void
    {
        [$audit, $token] = $this->guestAudit();
        $dependency = $this->dependency($audit, ['name' => 'lodash', 'version' => '4.17.15', 'declared_spec' => '^4.17.0', 'lock_path' => 'node_modules/lodash']);
        $this->vulnerability($dependency);

        $this->getJson($this->url($audit), $this->token($token))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'version', 'declared_spec', 'scope', 'relation', 'manifest_path', 'source_file_type', 'lock_path',
                'version_status', 'unresolved_reason', 'osv_status', 'vulnerabilities_count']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page', 'scan' => ['status', 'message', 'coverage', 'note']]])
            ->assertJsonPath('data.0.name', 'lodash')
            ->assertJsonPath('data.0.scope', 'production')
            ->assertJsonPath('data.0.relation', 'direct')
            ->assertJsonPath('data.0.osv_status', 'queried')
            ->assertJsonPath('data.0.vulnerabilities_count', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.scan.status', 'completed');
    }

    public function test_owner_lists_dependencies(): void
    {
        $user = User::factory()->create();
        $audit = AuditScan::factory()->ownedBy($user)->create($this->finished());
        $this->dependency($audit);

        $this->actingAs($user)->getJson($this->url($audit))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_items_never_expose_internal_identifiers_of_the_audit_or_user(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->dependency($audit);

        $response = $this->getJson($this->url($audit), $this->token($token))->assertOk();

        $response->assertJsonMissingPath('data.0.audit_scan_id')->assertJsonMissingPath('data.0.user_id')->assertJsonMissingPath('meta.audit_scan_id');
        $this->assertStringNotContainsString($audit->guest_token_hash, $response->getContent());
    }

    public function test_access_is_denied_with_not_found_for_everyone_else(): void
    {
        [$audit, $token] = $this->guestAudit();
        [$otherAudit, $otherToken] = $this->guestAudit();
        $owner = User::factory()->create();
        $owned = AuditScan::factory()->ownedBy($owner)->create($this->finished());

        $this->getJson($this->url($audit))->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->getJson($this->url($audit), $this->token('wrong'))->assertNotFound();
        $this->getJson($this->url($audit), $this->token($otherToken))->assertNotFound();
        $this->getJson($this->url($audit, '?guest_token='.$token))->assertNotFound();
        $this->getJson($this->url($owned), $this->token($token))->assertNotFound();
        $this->getJson($this->url($owned))->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson($this->url($owned))->assertNotFound();
        $this->getJson('/api/v1/audits/not-a-ulid/dependencies', $this->token($token))->assertNotFound();
        $this->getJson('/api/v1/audits/01JD3X5W8Q2G7B9N4K6M1R0T2Y/dependencies', $this->token($token))->assertNotFound();
    }

    public function test_invalid_filters_do_not_reveal_that_an_audit_exists(): void
    {
        [$audit, $token] = $this->guestAudit();

        $this->getJson($this->url($audit, '?scope=bogus'))->assertNotFound();
        $this->getJson($this->url($audit, '?scope=bogus'), $this->token('wrong'))->assertNotFound();
        $this->getJson($this->url($audit, '?scope=bogus'), $this->token($token))->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_a_running_audit_answers_409_audit_not_ready(): void
    {
        foreach ([[AuditStatus::Queued, StageStatus::Pending], [AuditStatus::Ingesting, StageStatus::Pending], [AuditStatus::Scanning, StageStatus::Pending], [AuditStatus::Scanning, StageStatus::Running]] as [$status, $sca]) {
            [$audit, $token] = $this->guestAudit(['status' => $status, 'sca_status' => $sca]);

            $this->getJson($this->url($audit), $this->token($token))
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'AUDIT_NOT_READY')
                ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
        }
    }

    public function test_a_scanning_audit_whose_sca_stage_already_finished_is_readable(): void
    {
        [$audit, $token] = $this->guestAudit(['status' => AuditStatus::Scanning, 'sca_status' => StageStatus::Completed]);
        $this->dependency($audit);

        $this->getJson($this->url($audit), $this->token($token))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_an_ended_audit_with_a_pending_or_running_sca_stage_answers_200_with_an_explanation_and_no_clean_looking_note(): void
    {
        foreach ([[AuditStatus::Failed, StageStatus::Pending], [AuditStatus::Partial, StageStatus::Running], [AuditStatus::Completed, StageStatus::Pending]] as [$status, $sca]) {
            [$audit, $token] = $this->guestAudit(['status' => $status, 'sca_status' => $sca]);

            $response = $this->getJson($this->url($audit), $this->token($token))->assertOk();

            $this->assertStringContainsStringIgnoringCase('pemeriksaan SCA tidak selesai', $response->json('meta.scan.message'));
            $response->assertJsonPath('meta.scan.note', null)->assertJsonPath('data', []);
        }
    }

    public function test_default_pagination_and_stable_order_by_name_then_id(): void
    {
        [$audit, $token] = $this->guestAudit();

        foreach (range(1, 20) as $i) {
            $this->dependency($audit, ['name' => sprintf('pkg-%02d', $i % 10)]);
        }

        $first = $this->getJson($this->url($audit), $this->token($token))->assertOk();
        $this->assertCount(15, $first->json('data'));
        $first->assertJsonPath('meta.per_page', 15)->assertJsonPath('meta.total', 20)->assertJsonPath('meta.last_page', 2);
        $names = array_column($first->json('data'), 'name');
        $sorted = $names;
        sort($sorted);
        $this->assertSame($sorted, $names);

        $second = $this->getJson($this->url($audit, '?page=2&per_page=5'), $this->token($token))->assertOk();
        $this->assertCount(5, $second->json('data'));
        $this->assertSame(array_slice(array_column($this->getJson($this->url($audit, '?per_page=100'), $this->token($token))->json('data'), 'id'), 5, 5), array_column($second->json('data'), 'id'));
    }

    public function test_invalid_pagination_is_rejected_and_pages_beyond_the_end_are_empty(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->dependency($audit);

        foreach (['per_page=101', 'per_page=0', 'page=0', 'page=abc', 'per_page=x'] as $query) {
            $this->getJson($this->url($audit, '?'.$query), $this->token($token))->assertStatus(422);
        }

        $this->getJson($this->url($audit, '?page=9'), $this->token($token))->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 1);
    }

    public function test_filters_by_scope_relation_and_version_status(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->dependency($audit, ['name' => 'a-prod-direct']);
        $this->dependency($audit, ['name' => 'b-dev-transitive', 'scope' => DependencyScope::Development, 'relation' => DependencyRelation::Transitive]);
        $this->unresolvedDependency($audit, ['name' => 'c-unresolved']);

        $names = fn (string $query) => array_column($this->getJson($this->url($audit, $query), $this->token($token))->assertOk()->json('data'), 'name');

        $this->assertSame(['b-dev-transitive'], $names('?scope=development'));
        $this->assertSame(['b-dev-transitive'], $names('?relation=transitive'));
        $this->assertSame(['c-unresolved'], $names('?version_status=unresolved'));
        $this->assertSame(['a-prod-direct', 'b-dev-transitive'], $names('?version_status=exact'));
        $this->assertSame([], $names('?scope=peer'));

        foreach (['scope=x', 'relation=x', 'version_status=x', 'scope[]=production'] as $bad) {
            $this->getJson($this->url($audit, '?'.$bad), $this->token($token))->assertStatus(422);
        }
    }

    public function test_vulnerabilities_count_is_null_unless_the_dependency_was_checked_against_osv(): void
    {
        [$audit, $token] = $this->guestAudit();
        $checkedNone = $this->dependency($audit, ['name' => 'a-checked-clean']);
        $checkedTwo = $this->dependency($audit, ['name' => 'b-checked-two']);
        $this->vulnerability($checkedTwo);
        $this->vulnerability($checkedTwo);
        $this->dependency($audit, ['name' => 'c-failed', 'osv_status' => OsvQueryStatus::Failed]);
        $this->unresolvedDependency($audit, ['name' => 'd-unresolved']);

        $rows = collect($this->getJson($this->url($audit), $this->token($token))->assertOk()->json('data'))->keyBy('name');

        $this->assertSame(0, $rows['a-checked-clean']['vulnerabilities_count']);
        $this->assertSame(2, $rows['b-checked-two']['vulnerabilities_count']);
        $this->assertNull($rows['c-failed']['vulnerabilities_count']);
        $this->assertNull($rows['d-unresolved']['vulnerabilities_count']);
        $this->assertNotNull($checkedNone);
    }

    public function test_coverage_sums_up_and_unresolved_is_part_of_not_checked(): void
    {
        [$audit, $token] = $this->guestAudit($this->finished(StageStatus::Partial));
        $this->dependency($audit);
        $this->dependency($audit);
        $this->dependency($audit, ['osv_status' => OsvQueryStatus::Failed]);
        $this->dependency($audit, ['osv_status' => OsvQueryStatus::NotQueried]);
        $this->unresolvedDependency($audit);
        $this->unresolvedDependency($audit);

        $coverage = $this->getJson($this->url($audit), $this->token($token))->assertOk()->json('meta.scan.coverage');

        $this->assertSame(6, $coverage['dependencies_total']);
        $this->assertSame(2, $coverage['checked']);
        $this->assertSame(1, $coverage['check_failed']);
        $this->assertSame(3, $coverage['not_checked']);
        $this->assertSame(2, $coverage['unresolved']);
        $this->assertSame($coverage['dependencies_total'], $coverage['checked'] + $coverage['check_failed'] + $coverage['not_checked']);
        $this->assertLessThanOrEqual($coverage['not_checked'], $coverage['unresolved']);
    }

    public function test_coverage_covers_the_whole_audit_and_is_not_changed_by_filters_or_pages(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->dependency($audit, ['name' => 'a']);
        $this->dependency($audit, ['name' => 'b', 'scope' => DependencyScope::Development]);
        $this->unresolvedDependency($audit, ['name' => 'c']);

        $all = $this->getJson($this->url($audit), $this->token($token))->json('meta.scan.coverage');

        foreach (['?scope=development', '?version_status=unresolved', '?per_page=1&page=2', '?scope=peer'] as $query) {
            $this->assertSame($all, $this->getJson($this->url($audit, $query), $this->token($token))->assertOk()->json('meta.scan.coverage'), $query);
        }

        $this->assertSame(3, $all['dependencies_total']);
    }

    public function test_coverage_does_not_include_other_audits(): void
    {
        [$audit, $token] = $this->guestAudit();
        [$other] = $this->guestAudit();
        $this->dependency($audit);
        $this->dependency($other);
        $this->dependency($other);

        $this->assertSame(1, $this->getJson($this->url($audit), $this->token($token))->json('meta.scan.coverage.dependencies_total'));
    }

    public function test_an_incomplete_scan_note_is_built_from_the_counts_and_never_says_safe_or_clean(): void
    {
        [$audit, $token] = $this->guestAudit($this->finished(StageStatus::Partial));
        $this->dependency($audit);
        $this->dependency($audit, ['osv_status' => OsvQueryStatus::Failed]);
        $this->unresolvedDependency($audit);
        $this->unresolvedDependency($audit);

        $note = $this->getJson($this->url($audit), $this->token($token))->json('meta.scan.note');

        $this->assertSame('Cakupan pemeriksaan tidak lengkap: 2 dependensi tidak diperiksa, 1 dependensi gagal diperiksa.', $note);
        $this->assertDoesNotMatchRegularExpression('/\b(aman|bersih)\b/i', $note);
    }

    public function test_a_complete_scan_has_no_note_and_a_partial_scan_without_counts_still_explains(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->dependency($audit);

        $this->getJson($this->url($audit), $this->token($token))->assertJsonPath('meta.scan.note', null);

        [$partial, $partialToken] = $this->guestAudit($this->finished(StageStatus::Partial) + ['sca_error' => '1 manifest gagal dibaca: package.json: rusak.']);
        $this->dependency($partial);

        $response = $this->getJson($this->url($partial), $this->token($partialToken));
        $this->assertSame('Pemeriksaan tidak lengkap; lihat message untuk alasannya.', $response->json('meta.scan.note'));
        $this->assertSame('1 manifest gagal dibaca: package.json: rusak.', $response->json('meta.scan.message'));
    }

    public function test_failed_and_skipped_scans_report_their_status_and_message(): void
    {
        [$failed, $failedToken] = $this->guestAudit($this->finished(StageStatus::Failed) + ['sca_error' => 'Pemeriksaan ke OSV gagal untuk semua dependensi.']);
        $this->dependency($failed, ['osv_status' => OsvQueryStatus::Failed]);
        [$skipped, $skippedToken] = $this->guestAudit($this->finished(StageStatus::Skipped) + ['sca_error' => 'Tidak ada package.json pada repositori; pemeriksaan dependensi dilewati.']);

        $this->getJson($this->url($failed), $this->token($failedToken))->assertOk()
            ->assertJsonPath('meta.scan.status', 'failed')->assertJsonPath('meta.scan.message', 'Pemeriksaan ke OSV gagal untuk semua dependensi.')
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.vulnerabilities_count', null);
        $this->getJson($this->url($skipped), $this->token($skippedToken))->assertOk()
            ->assertJsonPath('meta.scan.status', 'skipped')->assertJsonPath('data', [])->assertJsonPath('meta.scan.note', null);
    }

    public function test_every_response_carries_no_store_and_nosniff_headers(): void
    {
        [$audit, $token] = $this->guestAudit();
        [$running, $runningToken] = $this->guestAudit(['status' => AuditStatus::Scanning, 'sca_status' => StageStatus::Running]);

        $responses = [
            $this->getJson($this->url($audit), $this->token($token)),
            $this->getJson($this->url($audit)),
            $this->getJson($this->url($audit, '?scope=x'), $this->token($token)),
            $this->getJson($this->url($running), $this->token($runningToken)),
            $this->getJson('/api/v1/audits/not-a-ulid/dependencies'),
        ];

        foreach ($responses as $response) {
            $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        }
    }

    public function test_advisory_style_text_is_returned_verbatim_as_json_data(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->dependency($audit, ['declared_spec' => 'git+https://x.test/<script>alert(1)</script>#"q"']);

        $response = $this->getJson($this->url($audit), $this->token($token))->assertOk();

        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('git+https://x.test/<script>alert(1)</script>#"q"', $response->json('data.0.declared_spec'));
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_rows(): void
    {
        [$small, $smallToken] = $this->guestAudit();
        [$large, $largeToken] = $this->guestAudit();
        foreach (range(1, 3) as $i) {
            $this->vulnerability($this->dependency($small));
        }
        foreach (range(1, 40) as $i) {
            $this->vulnerability($this->dependency($large));
        }

        $count = function (AuditScan $audit, string $token): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($this->url($audit), $this->token($token))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->assertSame($count($small, $smallToken), $count($large, $largeToken));
    }

    public function test_existing_audit_endpoint_is_unchanged(): void
    {
        [$audit, $token] = $this->guestAudit();

        $this->getJson("/api/v1/audits/{$audit->public_id}", $this->token($token))->assertOk()->assertJsonPath('data.id', $audit->public_id);
    }
}
