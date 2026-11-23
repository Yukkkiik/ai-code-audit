<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AuditStatus;
use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\Severity;
use App\Enums\StageStatus;
use App\Enums\ToolRunStatus;
use App\Models\AuditScan;
use App\Models\ScanFinding;
use App\Models\ScanToolRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsScaResults;
use Tests\TestCase;

class AuditFindingsTest extends TestCase
{
    use BuildsScaResults, RefreshDatabase;

    private function url(AuditScan $audit, string $query = ''): string
    {
        return "/api/v1/audits/{$audit->public_id}/findings".$query;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function finding(AuditScan $audit, array $attributes = []): ScanFinding
    {
        return ScanFinding::factory()->create($attributes + ['audit_scan_id' => $audit->id]);
    }

    private function toolRun(AuditScan $audit, FindingTool $tool, ToolRunStatus $status = ToolRunStatus::Completed, ?int $findings = 1): ScanToolRun
    {
        return ScanToolRun::factory()->create([
            'audit_scan_id' => $audit->id, 'tool' => $tool, 'status' => $status,
            'findings_count' => $findings, 'files_scanned' => 3, 'error_message' => $status === ToolRunStatus::Failed ? 'ESLint gagal: waktu habis.' : null,
        ]);
    }

    public function test_guest_with_token_lists_findings_with_the_documented_shape(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->finding($audit, ['rule_id' => 'js-eval-injection', 'file_path' => 'src/a.js', 'line_start' => 4, 'line_end' => 4, 'cwe' => 'CWE-95']);
        $this->toolRun($audit, FindingTool::Semgrep);

        $this->getJson($this->url($audit), $this->token($token))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'tool', 'rule_id', 'category', 'severity', 'file_path', 'line_start', 'line_end', 'column_start', 'code_snippet', 'message', 'cwe']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page', 'scan' => ['status', 'message', 'health_score', 'health_category', 'tools', 'note']]])
            ->assertJsonPath('data.0.tool', 'semgrep')
            ->assertJsonPath('data.0.rule_id', 'js-eval-injection')
            ->assertJsonPath('data.0.severity', 'high')
            ->assertJsonPath('data.0.category', 'security')
            ->assertJsonPath('data.0.file_path', 'src/a.js')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.scan.status', 'completed')
            ->assertJsonPath('meta.scan.tools.0.tool', 'semgrep')
            ->assertJsonPath('meta.scan.tools.0.findings_count', 1);
    }

    public function test_owner_lists_findings(): void
    {
        $user = User::factory()->create();
        $audit = AuditScan::factory()->ownedBy($user)->create($this->finished());
        $this->finding($audit);

        $this->actingAs($user)->getJson($this->url($audit))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_access_is_denied_with_not_found_for_everyone_else(): void
    {
        [$audit, $token] = $this->guestAudit();
        [, $otherToken] = $this->guestAudit();
        $owned = AuditScan::factory()->ownedBy(User::factory()->create())->create($this->finished());

        $this->getJson($this->url($audit))->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson($this->url($audit), $this->token('wrong'))->assertNotFound();
        $this->getJson($this->url($audit), $this->token($otherToken))->assertNotFound();
        $this->getJson($this->url($audit, '?guest_token='.$token))->assertNotFound();
        $this->getJson($this->url($owned), $this->token($token))->assertNotFound();
        $this->getJson($this->url($owned))->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson($this->url($owned))->assertNotFound();
        $this->getJson('/api/v1/audits/not-a-ulid/findings', $this->token($token))->assertNotFound();
        $this->getJson('/api/v1/audits/01JD3X5W8Q2G7B9N4K6M1R0T2Y/findings', $this->token($token))->assertNotFound();
    }

    public function test_invalid_query_is_422_only_after_access_passes(): void
    {
        [$audit, $token] = $this->guestAudit();

        foreach (['severity=bogus', 'tool=bogus', 'category=bogus', 'per_page=101', 'per_page=0', 'page=0', 'page=abc'] as $query) {
            $this->getJson($this->url($audit, '?'.$query))->assertNotFound();
            $this->getJson($this->url($audit, '?'.$query), $this->token('wrong'))->assertNotFound();
            $this->getJson($this->url($audit, '?'.$query), $this->token($token))
                ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED')->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_a_running_audit_with_unfinished_sast_answers_409(): void
    {
        foreach ([[AuditStatus::Queued, StageStatus::Pending], [AuditStatus::Ingesting, StageStatus::Pending], [AuditStatus::Scanning, StageStatus::Pending], [AuditStatus::Scanning, StageStatus::Running]] as [$status, $sast]) {
            [$audit, $token] = $this->guestAudit(['status' => $status, 'sast_status' => $sast]);

            $this->getJson($this->url($audit), $this->token($token))
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'AUDIT_NOT_READY')
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_a_scanning_audit_whose_sast_stage_already_finished_is_readable_even_if_sca_is_pending(): void
    {
        [$audit, $token] = $this->guestAudit(['status' => AuditStatus::Scanning, 'sast_status' => StageStatus::Completed, 'sca_status' => StageStatus::Pending]);
        $this->finding($audit);

        $this->getJson($this->url($audit), $this->token($token))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_an_ended_audit_with_unfinished_sast_answers_200_with_an_explanation_and_no_note(): void
    {
        foreach ([[AuditStatus::Failed, StageStatus::Pending], [AuditStatus::Partial, StageStatus::Running], [AuditStatus::Completed, StageStatus::Pending]] as [$status, $sast]) {
            [$audit, $token] = $this->guestAudit(['status' => $status, 'sast_status' => $sast]);

            $this->getJson($this->url($audit), $this->token($token))
                ->assertOk()
                ->assertJsonPath('data', [])
                ->assertJsonPath('meta.scan.message', 'Pemeriksaan SAST tidak selesai karena pemrosesan audit berhenti sebelum tahap ini selesai.')
                ->assertJsonPath('meta.scan.note', null);
        }
    }

    public function test_sca_endpoints_keep_their_behaviour_when_sast_is_unfinished(): void
    {
        [$audit, $token] = $this->guestAudit(['status' => AuditStatus::Scanning, 'sast_status' => StageStatus::Running, 'sca_status' => StageStatus::Completed]);

        $this->getJson("/api/v1/audits/{$audit->public_id}/dependencies", $this->token($token))->assertOk();
    }

    public function test_filters_pagination_and_severity_order(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->finding($audit, ['severity' => Severity::Low, 'file_path' => 'a.js', 'line_start' => 1, 'tool' => FindingTool::Eslint, 'category' => FindingCategory::Quality]);
        $this->finding($audit, ['severity' => Severity::Info, 'file_path' => 'a.js', 'line_start' => 2]);
        $this->finding($audit, ['severity' => Severity::Critical, 'file_path' => 'z.js', 'line_start' => 9]);
        $this->finding($audit, ['severity' => Severity::High, 'file_path' => 'b.js', 'line_start' => 20]);
        $this->finding($audit, ['severity' => Severity::High, 'file_path' => 'b.js', 'line_start' => 3]);
        $this->finding($audit, ['severity' => Severity::Medium, 'file_path' => 'c.js', 'line_start' => 1, 'tool' => FindingTool::Eslint]);

        $rows = fn (string $query) => array_map(fn (array $row) => $row['severity'].':'.$row['file_path'].':'.$row['line_start'],
            $this->getJson($this->url($audit, $query), $this->token($token))->assertOk()->json('data'));

        $this->assertSame(['critical:z.js:9', 'high:b.js:3', 'high:b.js:20', 'medium:c.js:1', 'low:a.js:1', 'info:a.js:2'], $rows(''));
        $this->assertSame(['high:b.js:3', 'high:b.js:20'], $rows('?severity=high'));
        $this->assertSame(['medium:c.js:1', 'low:a.js:1'], $rows('?tool=eslint'));
        $this->assertSame(['low:a.js:1'], $rows('?category=quality'));
        $this->assertSame(['high:b.js:20', 'medium:c.js:1'], $rows('?per_page=2&page=2'));
        $this->assertSame([], $rows('?per_page=2&page=4'));

        $this->getJson($this->url($audit, '?per_page=2&page=2'), $this->token($token))
            ->assertJsonPath('meta.total', 6)->assertJsonPath('meta.last_page', 3)->assertJsonPath('meta.per_page', 2)->assertJsonPath('meta.current_page', 2);
    }

    public function test_a_failed_tool_reports_null_counts_and_is_called_unfinished_in_the_note(): void
    {
        [$audit, $token] = $this->guestAudit(['sast_status' => StageStatus::Partial, 'sast_error' => '1 temuan. ESLint gagal: waktu habis.']);
        $this->finding($audit);
        $this->toolRun($audit, FindingTool::Semgrep, ToolRunStatus::Completed, 1);
        // Nilai tersimpan yang tidak konsisten tidak boleh keluar sebagai angka.
        $this->toolRun($audit, FindingTool::Eslint, ToolRunStatus::Failed, 7);

        $response = $this->getJson($this->url($audit), $this->token($token))->assertOk();

        $response->assertJsonPath('meta.scan.tools.0.tool', 'eslint')
            ->assertJsonPath('meta.scan.tools.0.status', 'failed')
            ->assertJsonPath('meta.scan.tools.0.findings_count', null)
            ->assertJsonPath('meta.scan.tools.0.files_scanned', null)
            ->assertJsonPath('meta.scan.message', '1 temuan. ESLint gagal: waktu habis.');
        $this->assertStringContainsString('ESLint: tidak selesai', $response->json('meta.scan.note'));
        $this->assertStringContainsString('Pemeriksaan tidak lengkap', $response->json('meta.scan.note'));
    }

    public function test_failed_and_skipped_sast_without_tool_runs_answer_200_with_honest_notes(): void
    {
        [$failed, $token] = $this->guestAudit(['sast_status' => StageStatus::Failed, 'sast_error' => 'Semua tool analisis gagal.']);
        $response = $this->getJson($this->url($failed), $this->token($token))->assertOk()
            ->assertJsonPath('data', [])->assertJsonPath('meta.scan.tools', [])->assertJsonPath('meta.scan.message', 'Semua tool analisis gagal.');
        $this->assertStringContainsStringIgnoringCase('gagal', $response->json('meta.scan.note'));
        $this->assertStringContainsString('bukan berarti kode bebas masalah', $response->json('meta.scan.note'));

        [$skipped, $skipToken] = $this->guestAudit(['sast_status' => StageStatus::Skipped]);
        $response = $this->getJson($this->url($skipped), $this->token($skipToken))->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.scan.tools', []);
        $this->assertStringContainsString('Tidak ada file JavaScript/TypeScript yang dianalisis', $response->json('meta.scan.note'));
    }

    public function test_zero_findings_from_a_completed_tool_is_not_a_guarantee(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->toolRun($audit, FindingTool::Semgrep, ToolRunStatus::Completed, 0);
        $this->toolRun($audit, FindingTool::Eslint, ToolRunStatus::Completed, 0);

        $note = $this->getJson($this->url($audit), $this->token($token))->assertOk()->assertJsonPath('data', [])->json('meta.scan.note');

        $this->assertStringContainsString('bukan jaminan bebas kerentanan', $note);
        $this->assertStringContainsString('Semgrep: 0 temuan', $note);
    }

    public function test_note_counts_cover_the_whole_audit_not_the_filter_or_page(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->finding($audit, ['severity' => Severity::High]);
        $this->finding($audit, ['severity' => Severity::Low]);
        $this->toolRun($audit, FindingTool::Semgrep, ToolRunStatus::Completed, 2);
        $this->toolRun($audit, FindingTool::Eslint, ToolRunStatus::Completed, 0);

        $all = $this->getJson($this->url($audit), $this->token($token))->json('meta.scan');

        foreach (['?severity=critical', '?per_page=1&page=2', '?tool=eslint'] as $query) {
            $this->assertSame($all, $this->getJson($this->url($audit, $query), $this->token($token))->assertOk()->json('meta.scan'), $query);
        }

        $this->assertStringNotContainsString('bukan jaminan', $all['note']);
    }

    public function test_hostile_snippet_stays_data_and_internal_fields_are_not_exposed(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->finding($audit, ['code_snippet' => '<script>alert(1)</script>', 'message' => '"><img src=x onerror=alert(1)>', 'file_path' => 'src/x.js']);

        $response = $this->getJson($this->url($audit), $this->token($token))->assertOk();

        $response->assertJsonPath('data.0.code_snippet', '<script>alert(1)</script>')
            ->assertJsonPath('data.0.message', '"><img src=x onerror=alert(1)>')
            ->assertJsonMissingPath('data.0.fingerprint')
            ->assertJsonMissingPath('data.0.audit_scan_id')
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissingPath('data.0.updated_at');
        $this->assertStringNotContainsString(storage_path(), $response->getContent());
        $this->assertStringNotContainsString($audit->guest_token_hash, $response->getContent());
    }

    public function test_health_values_match_the_audit_resource(): void
    {
        [$audit, $token] = $this->guestAudit(['health_score' => 73, 'health_category' => 'fair']);

        $this->getJson($this->url($audit), $this->token($token))
            ->assertJsonPath('meta.scan.health_score', 73)->assertJsonPath('meta.scan.health_category', 'fair');
        $this->getJson("/api/v1/audits/{$audit->public_id}", $this->token($token))
            ->assertJsonPath('data.health_score', 73)->assertJsonPath('data.health_category', 'fair');
    }

    public function test_responses_carry_no_store_and_nosniff_headers(): void
    {
        [$audit, $token] = $this->guestAudit();
        [$running, $runningToken] = $this->guestAudit(['status' => AuditStatus::Scanning, 'sast_status' => StageStatus::Running]);

        foreach ([
            $this->getJson($this->url($audit), $this->token($token)),
            $this->getJson($this->url($audit)),
            $this->getJson($this->url($running), $this->token($runningToken)),
            $this->getJson($this->url($audit, '?severity=x'), $this->token($token)),
        ] as $response) {
            $response->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_query_count_does_not_depend_on_the_number_of_findings(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->toolRun($audit, FindingTool::Semgrep);
        $this->toolRun($audit, FindingTool::Eslint);

        $count = function () use ($audit, $token): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($this->url($audit), $this->token($token))->assertOk();

            return count(DB::getQueryLog());
        };

        ScanFinding::factory()->count(3)->create(['audit_scan_id' => $audit->id]);
        $few = $count();
        ScanFinding::factory()->count(27)->create(['audit_scan_id' => $audit->id]);

        $this->assertSame($few, $count());
    }
}
