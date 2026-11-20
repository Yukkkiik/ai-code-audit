<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AuditStatus;
use App\Enums\OsvQueryStatus;
use App\Enums\StageStatus;
use App\Enums\VulnerabilitySeverity;
use App\Models\AuditScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsScaResults;
use Tests\TestCase;

class AuditVulnerabilitiesTest extends TestCase
{
    use BuildsScaResults, RefreshDatabase;

    private function url(AuditScan $audit, string $path = '', string $query = ''): string
    {
        return "/api/v1/audits/{$audit->public_id}/vulnerabilities".$path.$query;
    }

    /**
     * @return array<string, mixed>
     */
    private function full(array $override = []): array
    {
        return $override + [
            'osv_id' => 'GHSA-aaaa-0000-0001', 'aliases' => ['CVE-2099-0001'], 'summary' => 'Ringkasan advisori',
            'details' => 'DETAIL-PENUH-RAHASIA-LIST', 'severity_label' => VulnerabilitySeverity::High,
            'severity_vectors' => [['type' => 'CVSS_V3', 'score' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H']],
            'affected_ranges' => [['type' => 'SEMVER', 'events' => [['introduced' => '0'], ['fixed' => '1.2.3']]]],
            'fixed_versions' => ['1.2.3'], 'reference_urls' => ['https://example.test/advisory'],
            'published_at' => '2021-02-15 12:00:00', 'modified_at' => '2024-02-15 05:32:53',
        ];
    }

    public function test_list_has_the_documented_shape_and_never_contains_detail_only_fields(): void
    {
        [$audit, $token] = $this->guestAudit();
        $dependency = $this->dependency($audit, ['name' => 'lodash', 'version' => '4.17.15']);
        $this->vulnerability($dependency, $this->full());

        $response = $this->getJson($this->url($audit), $this->token($token))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'osv_id', 'aliases', 'summary', 'severity', 'detail_available', 'dependency' => ['id', 'name', 'version', 'scope', 'relation'], 'published_at', 'modified_at']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page', 'scan' => ['status', 'message', 'coverage', 'note']]])
            ->assertJsonPath('data.0.osv_id', 'GHSA-aaaa-0000-0001')
            ->assertJsonPath('data.0.severity', 'high')
            ->assertJsonPath('data.0.detail_available', true)
            ->assertJsonPath('data.0.dependency.name', 'lodash')
            ->assertJsonPath('data.0.published_at', '2021-02-15T12:00:00Z');

        foreach (['details', 'severity_vectors', 'affected_ranges', 'fixed_versions', 'osv_fixed_versions', 'reference_urls', 'audit_scan_id'] as $key) {
            $response->assertJsonMissingPath("data.0.$key");
        }

        $this->assertStringNotContainsString('DETAIL-PENUH-RAHASIA-LIST', $response->getContent());
        $this->assertStringNotContainsString('1.2.3', $response->getContent());
    }

    public function test_owner_and_guest_access_and_everyone_else_gets_not_found(): void
    {
        $owner = User::factory()->create();
        $owned = AuditScan::factory()->ownedBy($owner)->create($this->finished());
        $vulnerability = $this->vulnerability($this->dependency($owned), $this->full());
        [$guest, $token] = $this->guestAudit();
        $guestVulnerability = $this->vulnerability($this->dependency($guest), $this->full(['osv_id' => 'GHSA-guest-0000-0001']));

        foreach ([$this->url($guest), $this->url($guest, "/{$guestVulnerability->id}")] as $url) {
            $this->getJson($url)->assertNotFound();
            $this->getJson($url, $this->token('wrong'))->assertNotFound();
            $this->getJson($url.'?guest_token='.$token)->assertNotFound();
        }

        $this->getJson($this->url($guest), $this->token($token))->assertOk();
        $this->getJson($this->url($guest, "/{$guestVulnerability->id}"), $this->token($token))->assertOk();

        // Tanpa login dan dengan token guest: audit milik user tidak terlihat.
        foreach ([$this->url($owned), $this->url($owned, "/{$vulnerability->id}")] as $url) {
            $this->getJson($url)->assertNotFound();
            $this->getJson($url, $this->token($token))->assertNotFound();
        }

        $this->getJson('/api/v1/audits/not-a-ulid/vulnerabilities', $this->token($token))->assertNotFound();

        // Login (kondisi login bertahan sampai akhir test): user lain ditolak, pemilik diterima.
        foreach ([$this->url($owned), $this->url($owned, "/{$vulnerability->id}")] as $url) {
            $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
        }

        $this->actingAs($owner)->getJson($this->url($owned))->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($owner)->getJson($this->url($owned, "/{$vulnerability->id}"))->assertOk();
    }

    public function test_invalid_filters_do_not_reveal_that_an_audit_exists(): void
    {
        [$audit, $token] = $this->guestAudit();

        $this->getJson($this->url($audit, '', '?severity=bogus'))->assertNotFound();
        $this->getJson($this->url($audit, '', '?severity=bogus'), $this->token('wrong'))->assertNotFound();
        $this->getJson($this->url($audit, '', '?severity=bogus'), $this->token($token))->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_running_audits_answer_409_and_ended_audits_with_unfinished_sca_answer_200_with_an_explanation(): void
    {
        [$running, $runningToken] = $this->guestAudit(['status' => AuditStatus::Scanning, 'sca_status' => StageStatus::Running]);
        [$died, $diedToken] = $this->guestAudit(['status' => AuditStatus::Failed, 'sca_status' => StageStatus::Running]);
        $dependency = $this->dependency($died);
        $this->vulnerability($dependency);

        $this->getJson($this->url($running), $this->token($runningToken))->assertStatus(409)->assertJsonPath('error.code', 'AUDIT_NOT_READY');

        $response = $this->getJson($this->url($died), $this->token($diedToken))->assertOk();
        $this->assertStringContainsStringIgnoringCase('pemeriksaan SCA tidak selesai', $response->json('meta.scan.message'));
        $response->assertJsonPath('meta.scan.note', null);
    }

    public function test_unknown_and_missing_detail_are_never_shown_as_low(): void
    {
        [$audit, $token] = $this->guestAudit();
        $dependency = $this->dependency($audit);
        $this->vulnerability($dependency, $this->full(['osv_id' => 'GHSA-unknown-0000-0001', 'severity_label' => VulnerabilitySeverity::Unknown]));
        $this->minimalVulnerability($dependency, 'GHSA-minimal-0000-0002');

        $rows = collect($this->getJson($this->url($audit), $this->token($token))->assertOk()->json('data'))->keyBy('osv_id');

        $this->assertSame('unknown', $rows['GHSA-unknown-0000-0001']['severity']);
        $this->assertTrue($rows['GHSA-unknown-0000-0001']['detail_available']);
        $this->assertSame('unknown', $rows['GHSA-minimal-0000-0002']['severity']);
        $this->assertFalse($rows['GHSA-minimal-0000-0002']['detail_available']);
        $this->assertNull($rows['GHSA-minimal-0000-0002']['summary']);
        $this->assertNull($rows['GHSA-minimal-0000-0002']['aliases']);
        $this->assertNotContains('low', array_column($rows->all(), 'severity'));
    }

    public function test_default_order_puts_unknown_after_high_and_before_medium(): void
    {
        [$audit, $token] = $this->guestAudit();
        $dependency = $this->dependency($audit);
        $this->vulnerability($dependency, $this->full(['osv_id' => 'GHSA-low', 'severity_label' => VulnerabilitySeverity::Low]));
        $this->vulnerability($dependency, $this->full(['osv_id' => 'GHSA-medium', 'severity_label' => VulnerabilitySeverity::Medium]));
        $this->vulnerability($dependency, $this->full(['osv_id' => 'GHSA-unknown', 'severity_label' => VulnerabilitySeverity::Unknown]));
        $this->minimalVulnerability($dependency, 'GHSA-minimal');
        $this->vulnerability($dependency, $this->full(['osv_id' => 'GHSA-high-b', 'severity_label' => VulnerabilitySeverity::High]));
        $this->vulnerability($dependency, $this->full(['osv_id' => 'GHSA-high-a', 'severity_label' => VulnerabilitySeverity::High]));
        $this->vulnerability($dependency, $this->full(['osv_id' => 'GHSA-critical', 'severity_label' => VulnerabilitySeverity::Critical]));

        $order = array_column($this->getJson($this->url($audit), $this->token($token))->assertOk()->json('data'), 'osv_id');

        $this->assertSame(['GHSA-critical', 'GHSA-high-a', 'GHSA-high-b', 'GHSA-minimal', 'GHSA-unknown', 'GHSA-medium', 'GHSA-low'], $order);
    }

    public function test_severity_filter_includes_missing_detail_under_unknown_and_rejects_bad_values(): void
    {
        [$audit, $token] = $this->guestAudit();
        $dependency = $this->dependency($audit);
        $this->vulnerability($dependency, $this->full(['osv_id' => 'GHSA-critical', 'severity_label' => VulnerabilitySeverity::Critical]));
        $this->vulnerability($dependency, $this->full(['osv_id' => 'GHSA-unknown', 'severity_label' => VulnerabilitySeverity::Unknown]));
        $this->minimalVulnerability($dependency, 'GHSA-minimal');

        $ids = fn (string $severity) => array_column($this->getJson($this->url($audit, '', "?severity=$severity"), $this->token($token))->assertOk()->json('data'), 'osv_id');

        $this->assertSame(['GHSA-critical'], $ids('critical'));
        $this->assertSame(['GHSA-minimal', 'GHSA-unknown'], $ids('unknown'));
        $this->assertSame([], $ids('low'));

        foreach (['severity=moderate', 'severity=', 'severity[]=high'] as $bad) {
            $this->getJson($this->url($audit, '', '?'.$bad), $this->token($token))->assertStatus(422);
        }
    }

    public function test_the_same_advisory_appears_once_per_dependency(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->vulnerability($this->dependency($audit, ['name' => 'a-lib']), $this->full());
        $this->vulnerability($this->dependency($audit, ['name' => 'b-lib']), $this->full());

        $response = $this->getJson($this->url($audit), $this->token($token))->assertOk();

        $this->assertSame(['GHSA-aaaa-0000-0001', 'GHSA-aaaa-0000-0001'], array_column($response->json('data'), 'osv_id'));
        $this->assertCount(2, array_unique(array_column($response->json('data'), 'id')));
        $this->assertEqualsCanonicalizing(['a-lib', 'b-lib'], array_column(array_column($response->json('data'), 'dependency'), 'name'));
    }

    public function test_pagination_and_validation(): void
    {
        [$audit, $token] = $this->guestAudit();
        $dependency = $this->dependency($audit);

        foreach (range(1, 20) as $i) {
            $this->vulnerability($dependency, $this->full(['osv_id' => sprintf('GHSA-n-%03d', $i)]));
        }

        $this->getJson($this->url($audit), $this->token($token))->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson($this->url($audit, '', '?page=2&per_page=15'), $this->token($token))->assertOk()->assertJsonCount(5, 'data');
        $this->getJson($this->url($audit, '', '?page=9'), $this->token($token))->assertOk()->assertJsonPath('data', []);

        foreach (['per_page=101', 'per_page=0', 'page=0'] as $bad) {
            $this->getJson($this->url($audit, '', '?'.$bad), $this->token($token))->assertStatus(422);
        }
    }

    public function test_detail_returns_the_full_advisory_with_a_labelled_fixed_versions_list(): void
    {
        [$audit, $token] = $this->guestAudit();
        $dependency = $this->dependency($audit, ['name' => 'lodash', 'version' => '4.17.15']);
        $vulnerability = $this->vulnerability($dependency, $this->full(['details' => "Detail lengkap.\n\nDengan **markdown**."]));

        $response = $this->getJson($this->url($audit, "/{$vulnerability->id}"), $this->token($token))
            ->assertOk()
            ->assertJsonPath('data.id', $vulnerability->id)
            ->assertJsonPath('data.osv_id', 'GHSA-aaaa-0000-0001')
            ->assertJsonPath('data.details', "Detail lengkap.\n\nDengan **markdown**.")
            ->assertJsonPath('data.severity', 'high')
            ->assertJsonPath('data.severity_vectors.0.type', 'CVSS_V3')
            ->assertJsonPath('data.affected_ranges.0.type', 'SEMVER')
            ->assertJsonPath('data.osv_fixed_versions', ['1.2.3'])
            ->assertJsonPath('data.reference_urls', ['https://example.test/advisory'])
            ->assertJsonPath('data.dependency.name', 'lodash')
            ->assertJsonPath('data.dependency.manifest_path', 'package-lock.json')
            ->assertJsonPath('data.detail_available', true);

        $this->assertSame(
            'Semua versi perbaikan yang tercantum pada seluruh range advisori ini, bukan rekomendasi versi untuk dependensi Anda.',
            $response->json('data.osv_fixed_versions_note'),
        );

        foreach (['fixed_versions', 'recommended_version', 'upgrade_to', 'fix_version', 'audit_scan_id'] as $key) {
            $response->assertJsonMissingPath("data.$key");
        }

        $this->assertDoesNotMatchRegularExpression('/recommend|upgrade|saran/i', implode(' ', array_keys($response->json('data'))));
    }

    public function test_detail_distinguishes_no_fixed_versions_from_unavailable_detail(): void
    {
        [$audit, $token] = $this->guestAudit();
        $dependency = $this->dependency($audit);
        $none = $this->vulnerability($dependency, $this->full(['osv_id' => 'GHSA-none-0000-0001', 'fixed_versions' => [], 'affected_ranges' => []]));
        $minimal = $this->minimalVulnerability($dependency);

        $this->getJson($this->url($audit, "/{$none->id}"), $this->token($token))->assertOk()->assertJsonPath('data.osv_fixed_versions', [])->assertJsonPath('data.detail_available', true);

        $this->getJson($this->url($audit, "/{$minimal->id}"), $this->token($token))->assertOk()
            ->assertJsonPath('data.detail_available', false)->assertJsonPath('data.severity', 'unknown')
            ->assertJsonPath('data.osv_fixed_versions', null)->assertJsonPath('data.details', null)
            ->assertJsonPath('data.affected_ranges', null)->assertJsonPath('data.reference_urls', null)->assertJsonPath('data.summary', null);
    }

    public function test_detail_is_looked_up_inside_the_audit_only(): void
    {
        [$audit, $token] = $this->guestAudit();
        [$other, $otherToken] = $this->guestAudit();
        $mine = $this->vulnerability($this->dependency($audit), $this->full());
        $foreign = $this->vulnerability($this->dependency($other), $this->full(['osv_id' => 'GHSA-foreign-0000-0001']));

        $this->getJson($this->url($audit, "/{$mine->id}"), $this->token($token))->assertOk();
        $this->getJson($this->url($audit, "/{$foreign->id}"), $this->token($token))->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->getJson($this->url($other, "/{$foreign->id}"), $this->token($otherToken))->assertOk();
        $this->getJson($this->url($other, "/{$mine->id}"), $this->token($otherToken))->assertNotFound();
        $this->getJson($this->url($audit, '/999999999'), $this->token($token))->assertNotFound();

        foreach (['/abc', '/1.5', '/-1', '/1%20OR%201=1', '/0x1', '/'.str_repeat('9', 30)] as $bad) {
            $this->getJson($this->url($audit, $bad), $this->token($token))->assertNotFound();
        }
    }

    public function test_detail_of_a_running_audit_answers_409(): void
    {
        [$audit, $token] = $this->guestAudit(['status' => AuditStatus::Scanning, 'sca_status' => StageStatus::Running]);

        $this->getJson($this->url($audit, '/1'), $this->token($token))->assertStatus(409)->assertJsonPath('error.code', 'AUDIT_NOT_READY');
    }

    public function test_advisory_text_is_returned_verbatim_as_json_without_html_encoding(): void
    {
        [$audit, $token] = $this->guestAudit();
        $vulnerability = $this->vulnerability($this->dependency($audit), $this->full([
            'summary' => '<script>alert(1)</script> & "kutip"', 'details' => '<img src=x onerror=alert(1)>',
        ]));

        $list = $this->getJson($this->url($audit), $this->token($token))->assertOk();
        $detail = $this->getJson($this->url($audit, "/{$vulnerability->id}"), $this->token($token))->assertOk();

        $this->assertStringContainsString('application/json', $list->headers->get('Content-Type'));
        $this->assertSame('<script>alert(1)</script> & "kutip"', $list->json('data.0.summary'));
        $this->assertSame('<img src=x onerror=alert(1)>', $detail->json('data.details'));
    }

    public function test_when_nothing_was_found_the_note_names_the_checked_count_and_is_not_a_guarantee(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->dependency($audit);
        $this->dependency($audit);
        $this->dependency($audit);

        $response = $this->getJson($this->url($audit), $this->token($token))->assertOk()->assertJsonPath('data', []);

        $this->assertSame(
            'Tidak ditemukan advisori pada OSV.dev untuk 3 dependensi yang diperiksa; ini bukan jaminan bebas kerentanan.',
            $response->json('meta.scan.note'),
        );
        $this->assertStringContainsString('tidak ditemukan advisori pada osv.dev untuk 3 dependensi yang diperiksa', strtolower($response->json('meta.scan.note')));
        $this->assertDoesNotMatchRegularExpression('/\b(aman|bersih)\b/i', $response->json('meta.scan.note'));
    }

    public function test_the_empty_note_also_reports_what_was_not_checked(): void
    {
        [$audit, $token] = $this->guestAudit($this->finished(StageStatus::Partial));
        $this->dependency($audit);
        $this->unresolvedDependency($audit);
        $this->dependency($audit, ['osv_status' => OsvQueryStatus::Failed]);

        $note = $this->getJson($this->url($audit), $this->token($token))->json('meta.scan.note');

        $this->assertSame('Tidak ditemukan advisori pada OSV.dev untuk 1 dependensi yang diperiksa; ini bukan jaminan bebas kerentanan. Cakupan pemeriksaan tidak lengkap: 1 dependensi tidak diperiksa, 1 dependensi gagal diperiksa.', $note);
        $this->assertDoesNotMatchRegularExpression('/\b(aman|bersih)\b/i', $note);
    }

    public function test_when_no_dependency_was_checked_the_note_says_so_instead_of_reporting_zero_advisories(): void
    {
        [$audit, $token] = $this->guestAudit($this->finished(StageStatus::Partial));
        $this->unresolvedDependency($audit);
        $this->unresolvedDependency($audit);

        $note = $this->getJson($this->url($audit), $this->token($token))->assertJsonPath('data', [])->json('meta.scan.note');

        $this->assertStringStartsWith('Tidak ada dependensi yang diperiksa ke OSV.dev; tidak ada hasil kerentanan yang dapat ditampilkan.', $note);
        $this->assertStringNotContainsString('Tidak ditemukan advisori', $note);
        $this->assertStringContainsString('2 dependensi tidak diperiksa', $note);
    }

    public function test_when_advisories_exist_the_note_only_mentions_incomplete_coverage(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->vulnerability($this->dependency($audit), $this->full());

        $this->getJson($this->url($audit), $this->token($token))->assertJsonPath('meta.scan.note', null);

        $this->unresolvedDependency($audit);
        $this->minimalVulnerability($this->dependency($audit, ['osv_status' => OsvQueryStatus::Queried]));
        $this->dependency($audit, ['osv_status' => OsvQueryStatus::Failed]);

        $note = $this->getJson($this->url($audit), $this->token($token))->json('meta.scan.note');

        $this->assertSame('Cakupan pemeriksaan tidak lengkap: 1 dependensi tidak diperiksa, 1 dependensi gagal diperiksa, 1 advisori tanpa detail.', $note);
        $this->assertStringNotContainsString('Tidak ditemukan', $note);
        $this->assertDoesNotMatchRegularExpression('/\b(aman|bersih)\b/i', $note);
    }

    public function test_a_filter_that_hides_every_row_does_not_produce_the_nothing_found_note(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->vulnerability($this->dependency($audit), $this->full(['severity_label' => VulnerabilitySeverity::High]));

        $response = $this->getJson($this->url($audit, '', '?severity=low'), $this->token($token))->assertOk()->assertJsonPath('data', []);

        $response->assertJsonPath('meta.scan.note', null);
        $this->assertSame(1, $response->json('meta.scan.coverage.advisories'));
    }

    public function test_coverage_in_the_vulnerability_list_sums_up_and_ignores_filters(): void
    {
        [$audit, $token] = $this->guestAudit($this->finished(StageStatus::Partial));
        $dependency = $this->dependency($audit);
        $this->dependency($audit, ['osv_status' => OsvQueryStatus::Failed]);
        $this->unresolvedDependency($audit);
        $this->vulnerability($dependency, $this->full(['severity_label' => VulnerabilitySeverity::Critical]));
        $this->minimalVulnerability($dependency);

        $all = $this->getJson($this->url($audit), $this->token($token))->json('meta.scan.coverage');
        $filtered = $this->getJson($this->url($audit, '', '?severity=critical'), $this->token($token))->json('meta.scan.coverage');

        $this->assertSame($all, $filtered);
        $this->assertSame(3, $all['dependencies_total']);
        $this->assertSame($all['dependencies_total'], $all['checked'] + $all['check_failed'] + $all['not_checked']);
        $this->assertSame(2, $all['advisories']);
        $this->assertSame(1, $all['advisories_without_detail']);
    }

    public function test_every_response_carries_no_store_and_nosniff_headers(): void
    {
        [$audit, $token] = $this->guestAudit();
        $vulnerability = $this->vulnerability($this->dependency($audit), $this->full());
        [$running, $runningToken] = $this->guestAudit(['status' => AuditStatus::Scanning, 'sca_status' => StageStatus::Running]);

        $responses = [
            $this->getJson($this->url($audit), $this->token($token)),
            $this->getJson($this->url($audit, "/{$vulnerability->id}"), $this->token($token)),
            $this->getJson($this->url($audit, "/{$vulnerability->id}")),
            $this->getJson($this->url($audit, '/abc'), $this->token($token)),
            $this->getJson($this->url($audit, '', '?severity=x'), $this->token($token)),
            $this->getJson($this->url($running), $this->token($runningToken)),
            $this->getJson('/api/v1/audits/not-a-ulid/vulnerabilities'),
        ];

        foreach ($responses as $response) {
            $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        }
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_rows(): void
    {
        [$small, $smallToken] = $this->guestAudit();
        [$large, $largeToken] = $this->guestAudit();

        foreach (range(1, 3) as $i) {
            $this->vulnerability($this->dependency($small), $this->full(['osv_id' => "GHSA-s-$i"]));
        }

        foreach (range(1, 60) as $i) {
            $this->vulnerability($this->dependency($large), $this->full(['osv_id' => "GHSA-l-$i"]));
        }

        $count = function (AuditScan $audit, string $token): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($this->url($audit, '', '?per_page=100'), $this->token($token))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->assertSame($count($small, $smallToken), $count($large, $largeToken));
    }

    public function test_the_list_query_does_not_load_the_details_column(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->vulnerability($this->dependency($audit), $this->full());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($this->url($audit), $this->token($token))->assertOk();

        $selects = array_filter(array_column(DB::getQueryLog(), 'query'), fn ($q) => str_contains($q, 'from `vulnerabilities`'));
        $this->assertNotEmpty($selects);

        foreach ($selects as $sql) {
            $this->assertStringNotContainsString('`details`', $sql);
            $this->assertStringNotContainsString('select *', strtolower($sql));
        }
    }
}
