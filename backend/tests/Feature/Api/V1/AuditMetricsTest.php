<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AuditStatus;
use App\Enums\CodeLanguage;
use App\Enums\MetricScope;
use App\Enums\StageStatus;
use App\Models\AuditScan;
use App\Models\CodeMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsScaResults;
use Tests\TestCase;

class AuditMetricsTest extends TestCase
{
    use BuildsScaResults, RefreshDatabase;

    private function url(AuditScan $audit, string $query = ''): string
    {
        return "/api/v1/audits/{$audit->public_id}/metrics".$query;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function metric(AuditScan $audit, array $attributes = []): CodeMetric
    {
        return CodeMetric::factory()->create($attributes + ['audit_scan_id' => $audit->id]);
    }

    public function test_guest_lists_file_metrics_by_default_with_the_documented_shape(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->metric($audit, ['file_path' => 'src/a.ts', 'language' => CodeLanguage::Typescript, 'cyclomatic_complexity' => 9]);
        $this->metric($audit, ['scope' => MetricScope::Function, 'function_name' => 'run', 'line_start' => 3]);

        $this->getJson($this->url($audit), $this->token($token))
            ->assertOk()
            ->assertJsonStructure(['data' => [['scope', 'language', 'file_path', 'function_name', 'line_start', 'loc', 'sloc', 'cyclomatic_complexity',
                'halstead_volume', 'halstead_difficulty', 'halstead_effort', 'maintainability_index']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page', 'scan' => ['status', 'message', 'health_score', 'health_category', 'tools', 'note']]])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.scope', 'file')
            ->assertJsonPath('data.0.language', 'typescript')
            ->assertJsonPath('data.0.file_path', 'src/a.ts')
            ->assertJsonPath('data.0.cyclomatic_complexity', 9)
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.audit_scan_id');
    }

    public function test_decimal_values_are_strings_with_two_decimals(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->metric($audit, ['halstead_volume' => 850.25, 'halstead_difficulty' => 12.5, 'halstead_effort' => 10628.13, 'maintainability_index' => 68.4]);

        $this->getJson($this->url($audit), $this->token($token))
            ->assertJsonPath('data.0.halstead_volume', '850.25')
            ->assertJsonPath('data.0.halstead_difficulty', '12.50')
            ->assertJsonPath('data.0.halstead_effort', '10628.13')
            ->assertJsonPath('data.0.maintainability_index', '68.40');
    }

    public function test_scope_ordering(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->metric($audit, ['file_path' => 'b.js']);
        $this->metric($audit, ['file_path' => 'a.js']);
        $function = fn (string $path, int $line, int $cc) => $this->metric($audit, ['scope' => MetricScope::Function, 'file_path' => $path, 'line_start' => $line, 'function_name' => "f{$line}", 'cyclomatic_complexity' => $cc]);
        $function('b.js', 5, 3);
        $function('a.js', 9, 12);
        $function('a.js', 2, 3);
        $function('c.js', 1, 7);

        $this->assertSame(['a.js', 'b.js'], array_column($this->getJson($this->url($audit, '?scope=file'), $this->token($token))->json('data'), 'file_path'));
        $this->assertSame(['f9', 'f1', 'f2', 'f5'], array_column($this->getJson($this->url($audit, '?scope=function'), $this->token($token))->json('data'), 'function_name'));
        $this->assertSame(['f1'], array_column($this->getJson($this->url($audit, '?scope=function&per_page=1&page=2'), $this->token($token))->json('data'), 'function_name'));
        $this->assertSame([], $this->getJson($this->url($audit, '?scope=function&page=9'), $this->token($token))->json('data'));
    }

    public function test_access_matrix_and_validation_order(): void
    {
        [$audit, $token] = $this->guestAudit();
        [, $otherToken] = $this->guestAudit();
        $owned = AuditScan::factory()->ownedBy($owner = User::factory()->create())->create($this->finished());

        $this->getJson($this->url($audit))->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson($this->url($audit), $this->token($otherToken))->assertNotFound();
        $this->getJson($this->url($audit, '?guest_token='.$token))->assertNotFound();
        $this->getJson($this->url($owned), $this->token($token))->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson($this->url($owned))->assertNotFound();
        $this->actingAs($owner)->getJson($this->url($owned))->assertOk();
        $this->getJson('/api/v1/audits/not-a-ulid/metrics', $this->token($token))->assertNotFound();

        foreach (['scope=bogus', 'per_page=101', 'page=0'] as $query) {
            $this->getJson($this->url($audit, '?'.$query))->assertNotFound();
            $this->getJson($this->url($audit, '?'.$query), $this->token($token))->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }
    }

    public function test_running_audit_is_409_and_ended_audit_with_unfinished_sast_is_200_with_message(): void
    {
        [$running, $token] = $this->guestAudit(['status' => AuditStatus::Scanning, 'sast_status' => StageStatus::Running]);
        $this->getJson($this->url($running), $this->token($token))->assertStatus(409)->assertJsonPath('error.code', 'AUDIT_NOT_READY');

        [$ended, $endedToken] = $this->guestAudit(['status' => AuditStatus::Failed, 'sast_status' => StageStatus::Pending]);
        $this->getJson($this->url($ended), $this->token($endedToken))->assertOk()
            ->assertJsonPath('meta.scan.message', 'Pemeriksaan SAST tidak selesai karena pemrosesan audit berhenti sebelum tahap ini selesai.')
            ->assertJsonPath('meta.scan.note', null);
    }

    public function test_no_stored_metric_rows_is_not_a_low_complexity_signal(): void
    {
        [$audit, $token] = $this->guestAudit();

        $note = $this->getJson($this->url($audit), $this->token($token))->assertOk()->assertJsonPath('data', [])->json('meta.scan.note');

        $this->assertStringContainsString('Tidak ada metrik kode yang tersimpan; ini bukan indikasi kompleksitas rendah. Lihat message.', $note);
    }

    public function test_function_scope_mentions_the_storage_limit_and_file_scope_does_not(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->metric($audit);

        $function = $this->getJson($this->url($audit, '?scope=function'), $this->token($token))->assertOk()->json('meta.scan.note');
        $file = $this->getJson($this->url($audit), $this->token($token))->assertOk()->json('meta.scan.note');

        $this->assertStringContainsString('Hanya fungsi dengan kompleksitas tertinggi', $function);
        $this->assertStringNotContainsStringIgnoringCase('hanya fungsi', (string) $file);
    }

    public function test_note_counts_the_whole_audit_not_the_filtered_scope(): void
    {
        [$audit, $token] = $this->guestAudit();
        $this->metric($audit);

        // scope=function kosong, tetapi ada baris file: catatan "tidak ada metrik" tidak boleh muncul.
        $note = $this->getJson($this->url($audit, '?scope=function'), $this->token($token))->assertOk()->assertJsonPath('data', [])->json('meta.scan.note');

        $this->assertStringNotContainsString('Tidak ada metrik kode', $note);
    }

    public function test_failed_or_skipped_sast_gives_the_status_note_never_a_clean_looking_one(): void
    {
        foreach ([StageStatus::Failed, StageStatus::Skipped] as $status) {
            [$audit, $token] = $this->guestAudit(['sast_status' => $status]);

            $note = $this->getJson($this->url($audit), $this->token($token))->assertOk()->assertJsonPath('meta.scan.tools', [])->json('meta.scan.note');

            $this->assertNotEmpty($note);
            $this->assertDoesNotMatchRegularExpression('/aman|bersih/i', $note);
        }
    }

    public function test_query_count_does_not_depend_on_the_number_of_rows(): void
    {
        [$audit, $token] = $this->guestAudit();

        $count = function () use ($audit, $token): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($this->url($audit), $this->token($token))->assertOk();

            return count(DB::getQueryLog());
        };

        CodeMetric::factory()->count(3)->create(['audit_scan_id' => $audit->id]);
        $few = $count();
        CodeMetric::factory()->count(27)->create(['audit_scan_id' => $audit->id]);

        $this->assertSame($few, $count());
    }
}
