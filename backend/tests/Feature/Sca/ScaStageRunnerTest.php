<?php

namespace Tests\Feature\Sca;

use App\Enums\AuditStage;
use App\Enums\OsvQueryStatus;
use App\Enums\StageStatus;
use App\Enums\VulnerabilitySeverity;
use App\Models\AuditScan;
use App\Models\Dependency;
use App\Models\Vulnerability;
use App\Services\Audit\AuditStatusManager;
use App\Services\Audit\Stages\AuditStageRunners;
use App\Services\Audit\Stages\StageResult;
use App\Services\Ingestion\IngestedRepository;
use App\Services\Sca\ScaStageRunner;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

class ScaStageRunnerTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Sleep::fake(syncWithCarbon: true);
        $this->root = sys_get_temp_dir().'/sca-runner-'.bin2hex(random_bytes(5));
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function write(string $path, array|string $content): void
    {
        $full = $this->root.'/'.$path;
        is_dir(dirname($full)) || mkdir(dirname($full), 0755, true);
        file_put_contents($full, is_string($content) ? $content : json_encode($content));
    }

    /**
     * @param  array<string, string>  $versions  nama => versi
     */
    private function lockProject(array $versions, string $dir = ''): void
    {
        $prefix = $dir === '' ? '' : $dir.'/';
        $packages = ['' => ['dependencies' => array_map(fn ($v) => "^$v", $versions)]];

        foreach ($versions as $name => $version) {
            $packages["node_modules/$name"] = ['version' => $version, 'resolved' => "https://registry.npmjs.org/$name/-/$name-$version.tgz"];
        }

        $this->write($prefix.'package.json', ['dependencies' => array_map(fn ($v) => "^$v", $versions)]);
        $this->write($prefix.'package-lock.json', ['lockfileVersion' => 3, 'packages' => $packages]);
    }

    /**
     * @return array<string, mixed>
     */
    private function advisory(string $id): array
    {
        return ['id' => $id] + json_decode(file_get_contents(__DIR__.'/../../Fixtures/Sca/osv-detail-handwritten.json'), true);
    }

    /**
     * @param  array<string, list<string>>  $matches  "nama@versi" => daftar ID advisori
     */
    private function fakeOsv(array $matches = [], ?callable $query = null, ?callable $vuln = null): void
    {
        Http::fake([
            'api.osv.dev/v1/querybatch' => $query ?? function (Request $r) use ($matches) {
                return Http::response(['results' => array_map(function ($q) use ($matches) {
                    $ids = $matches[$q['package']['name'].'@'.$q['version']] ?? [];

                    return $ids === [] ? [] : ['vulns' => array_map(fn ($id) => ['id' => $id, 'modified' => '2024-02-15T05:32:53Z'], $ids)];
                }, $r->data()['queries'])]);
            },
            'api.osv.dev/v1/vulns/*' => $vuln ?? fn (Request $r) => Http::response($this->advisory(basename($r->url())), 200),
        ]);
    }

    private function audit(array $attributes = []): AuditScan
    {
        return AuditScan::factory()->create($attributes + ['started_at' => now()]);
    }

    private function runStage(AuditScan $audit): StageResult
    {
        return $this->app->make(ScaStageRunner::class)->run($audit, new IngestedRepository($this->root, 0, null));
    }

    private function sentTo(string $suffix): int
    {
        return Http::recorded(fn (Request $r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), $suffix) || str_contains($r->url(), $suffix))->count();
    }

    public function test_runner_serves_the_sca_stage(): void
    {
        $this->assertSame(AuditStage::Sca, $this->app->make(ScaStageRunner::class)->stage());
    }

    public function test_the_container_registers_this_runner_for_the_sca_stage(): void
    {
        $this->assertInstanceOf(ScaStageRunner::class, $this->app->make(AuditStageRunners::class)->for(AuditStage::Sca));
    }

    public function test_no_package_json_is_skipped_without_any_request(): void
    {
        $this->write('src/index.js', 'x');

        $result = $this->runStage($this->audit());

        $this->assertSame(StageStatus::Skipped, $result->status);
        $this->assertStringContainsString('package.json', $result->message);
        Http::assertNothingSent();
        $this->assertSame(0, Dependency::query()->count());
    }

    public function test_lockfile_project_is_completed_with_dependencies_and_vulnerabilities(): void
    {
        $this->lockProject(['example-lib' => '1.0.0', 'clean-lib' => '2.0.0']);
        $this->fakeOsv(['example-lib@1.0.0' => ['GHSA-aaaa-0000-0001']]);
        $audit = $this->audit();

        $result = $this->runStage($audit);

        $this->assertSame(StageStatus::Completed, $result->status);
        $this->assertSame(2, Dependency::query()->where('audit_scan_id', $audit->id)->count());
        $this->assertSame([OsvQueryStatus::Queried], Dependency::query()->pluck('osv_status')->unique()->values()->all());
        $row = Vulnerability::query()->sole();
        $this->assertSame('GHSA-aaaa-0000-0001', $row->osv_id);
        $this->assertSame('example-lib', $row->dependency->name);
        $this->assertSame(['1.2.3'], $row->fixed_versions);
        $this->assertSame(VulnerabilitySeverity::High, $row->severity_label);
        $this->assertStringContainsString('2 dependensi diperiksa ke OSV', $result->message);
        $this->assertStringContainsString('1 advisori ditemukan', $result->message);
    }

    public function test_empty_osv_result_is_completed_and_never_described_as_safe(): void
    {
        $this->lockProject(['clean-lib' => '2.0.0']);
        $this->fakeOsv();

        $result = $this->runStage($this->audit());

        $this->assertSame(StageStatus::Completed, $result->status);
        $this->assertSame(0, Vulnerability::query()->count());
        $this->assertStringContainsString('tidak ditemukan advisori pada OSV.dev', $result->message);
        $this->assertStringNotContainsStringIgnoringCase('aman', $result->message);
    }

    public function test_only_unresolved_dependencies_is_partial_with_a_clear_message_and_no_request(): void
    {
        $this->write('package.json', ['dependencies' => ['a' => '^1.0.0', 'b' => 'latest', 'c' => 'github:x/y']]);

        $result = $this->runStage($audit = $this->audit());

        $this->assertSame(StageStatus::Partial, $result->status);
        $this->assertStringContainsString('3 dependensi tidak memiliki versi pasti', $result->message);
        $this->assertStringContainsString('Tidak ada dependensi yang dapat diperiksa', $result->message);
        Http::assertNothingSent();
        $this->assertSame(3, Dependency::query()->where('audit_scan_id', $audit->id)->count());
        $this->assertSame([OsvQueryStatus::NotQueried], Dependency::query()->pluck('osv_status')->unique()->values()->all());
    }

    public function test_some_checked_and_some_unresolved_is_completed_with_the_unresolved_count_in_the_message(): void
    {
        $this->write('package.json', ['dependencies' => ['example-lib' => '1.0.0', 'b' => '^1.0.0', 'c' => 'latest']]);
        $this->fakeOsv(['example-lib@1.0.0' => ['GHSA-aaaa-0000-0001']]);

        $result = $this->runStage($this->audit());

        $this->assertSame(StageStatus::Completed, $result->status);
        $this->assertStringContainsString('1 dependensi diperiksa ke OSV', $result->message);
        $this->assertStringContainsString('2 dependensi tidak diperiksa karena versi tidak pasti', $result->message);
    }

    public function test_unresolved_components_are_never_sent_to_osv(): void
    {
        $this->write('package.json', ['dependencies' => ['example-lib' => '1.0.0', 'b' => '^1.0.0', 'c' => 'latest', 'd' => 'github:x/y']]);
        $this->fakeOsv();

        $this->runStage($this->audit());

        Http::assertSent(function (Request $r) {
            return str_ends_with($r->url(), '/querybatch')
                && array_map(fn ($q) => $q['package']['name'], $r->data()['queries']) === ['example-lib'];
        });
    }

    public function test_the_completed_message_is_persisted_by_the_status_manager(): void
    {
        $this->lockProject(['example-lib' => '1.0.0']);
        $this->fakeOsv();
        $audit = $this->audit(['status' => 'scanning', 'sca_status' => StageStatus::Pending]);
        $manager = $this->app->make(AuditStatusManager::class);

        $manager->startStage($audit, AuditStage::Sca);
        $result = $this->runStage($audit);
        $manager->completeStage($audit, AuditStage::Sca, $result->status, $result->message);

        $this->assertSame(StageStatus::Completed, $audit->fresh()->sca_status);
        $this->assertStringContainsString('1 dependensi diperiksa ke OSV', $audit->fresh()->sca_error);
    }

    public function test_osv_down_fails_the_stage_but_keeps_the_dependencies(): void
    {
        $this->lockProject(['a' => '1.0.0', 'b' => '2.0.0']);
        $this->fakeOsv(query: fn () => Http::response('', 503));
        $audit = $this->audit();

        $result = $this->runStage($audit);

        $this->assertSame(StageStatus::Failed, $result->status);
        $this->assertSame(2, Dependency::query()->where('audit_scan_id', $audit->id)->count());
        $this->assertSame([OsvQueryStatus::Failed], Dependency::query()->pluck('osv_status')->unique()->values()->all());
        $this->assertSame(0, Vulnerability::query()->count());
        $this->assertStringContainsString('gangguan', $result->message);
    }

    public function test_partially_failed_queries_are_partial(): void
    {
        config(['audit.sca.osv.batch_size' => 1]);
        $this->lockProject(['example-lib' => '1.0.0', 'other-lib' => '2.0.0']);
        $calls = 0;
        $this->fakeOsv(query: function (Request $r) use (&$calls) {
            return ++$calls === 1
                ? Http::response(['results' => [['vulns' => [['id' => 'GHSA-aaaa-0000-0001']]]]])
                : Http::response('', 400);
        });

        $result = $this->runStage($this->audit());

        $this->assertSame(StageStatus::Partial, $result->status);
        $this->assertSame(OsvQueryStatus::Queried, Dependency::query()->where('name', 'example-lib')->sole()->osv_status);
        $this->assertSame(OsvQueryStatus::Failed, Dependency::query()->where('name', 'other-lib')->sole()->osv_status);
        $this->assertSame(1, Vulnerability::query()->count());
        $this->assertStringContainsString('1 dependensi gagal diperiksa ke OSV', $result->message);
    }

    public function test_corrupt_lockfile_is_partial_and_the_reason_is_visible(): void
    {
        $this->write('package.json', ['dependencies' => ['example-lib' => '1.0.0', 'b' => '^1.0.0']]);
        $this->write('package-lock.json', '{"lockfileVersion":3,');
        $this->fakeOsv();

        $result = $this->runStage($audit = $this->audit());

        $this->assertSame(StageStatus::Partial, $result->status);
        $this->assertStringContainsString('1 manifest gagal dibaca', $result->message);
        $this->assertStringContainsString('package-lock.json', $result->message);
        $this->assertSame('lockfile_unreadable', Dependency::query()->where('audit_scan_id', $audit->id)->where('name', 'b')->sole()->unresolved_reason->value);
    }

    public function test_all_manifests_broken_fails_with_the_reason(): void
    {
        $this->write('package.json', '{');

        $result = $this->runStage($this->audit());

        $this->assertSame(StageStatus::Failed, $result->status);
        $this->assertStringContainsString('package.json', $result->message);
        Http::assertNothingSent();
    }

    public function test_package_without_dependencies_is_completed(): void
    {
        $this->write('package.json', ['name' => 'x']);

        $result = $this->runStage($this->audit());

        $this->assertSame(StageStatus::Completed, $result->status);
        $this->assertStringContainsString('Tidak ada dependensi', $result->message);
        Http::assertNothingSent();
    }

    public function test_failed_detail_keeps_a_minimal_row_and_makes_the_stage_partial(): void
    {
        $this->lockProject(['example-lib' => '1.0.0']);
        $this->fakeOsv(['example-lib@1.0.0' => ['GHSA-aaaa-0000-0001', 'GHSA-aaaa-0000-0002']], vuln: function (Request $r) {
            $id = basename($r->url());

            return $id === 'GHSA-aaaa-0000-0002' ? Http::response('', 404) : Http::response($this->advisory($id), 200);
        });

        $result = $this->runStage($this->audit());

        $this->assertSame(StageStatus::Partial, $result->status);
        $this->assertSame(2, Vulnerability::query()->count());
        $this->assertNotNull(Vulnerability::query()->where('osv_id', 'GHSA-aaaa-0000-0001')->sole()->severity_label);
        $minimal = Vulnerability::query()->where('osv_id', 'GHSA-aaaa-0000-0002')->sole();
        $this->assertNull($minimal->severity_label);
        $this->assertNull($minimal->summary);
        $this->assertNotNull($minimal->modified_at);
        $this->assertStringContainsString('Detail 1 advisori tidak dapat diambil', $result->message);
    }

    public function test_the_same_advisory_for_two_dependencies_is_fetched_once_and_stored_twice(): void
    {
        $this->lockProject(['example-lib' => '1.0.0', 'multi' => '1.0.0']);
        $this->fakeOsv(['example-lib@1.0.0' => ['GHSA-aaaa-0000-0001'], 'multi@1.0.0' => ['GHSA-aaaa-0000-0001']]);

        $this->runStage($this->audit());

        $this->assertSame(2, Vulnerability::query()->count());
        $this->assertSame(1, $this->sentTo('/v1/vulns/'));
        $this->assertSame(['1.2.3'], Vulnerability::query()->whereRelation('dependency', 'name', 'example-lib')->sole()->fixed_versions);
        $this->assertSame(['1.0.1', '2.0.5'], Vulnerability::query()->whereRelation('dependency', 'name', 'multi')->sole()->fixed_versions);
    }

    public function test_details_are_stored_per_chunk_of_fifty_before_the_next_chunk_is_fetched(): void
    {
        $matches = [];
        $versions = [];

        foreach (range(1, 120) as $i) {
            $versions["pkg-$i"] = '1.0.0';
            $matches["pkg-$i@1.0.0"] = [sprintf('GHSA-aaaa-0000-%04d', $i)];
        }

        $this->lockProject($versions);
        $stored = [];
        $count = 0;
        $this->fakeOsv($matches, vuln: function (Request $r) use (&$stored, &$count) {
            $count++;

            if (in_array($count, [51, 101], true)) {
                $stored[$count] = Vulnerability::query()->count();
            }

            return Http::response($this->advisory(basename($r->url())), 200);
        });

        $result = $this->runStage($this->audit());

        $this->assertSame(StageStatus::Completed, $result->status);
        $this->assertSame([51 => 50, 101 => 100], $stored);
        $this->assertSame(120, Vulnerability::query()->count());
        $this->assertSame(120, $this->sentTo('/v1/vulns/'));
    }

    public function test_time_budget_exhausted_midway_leaves_minimal_rows_and_a_partial_stage(): void
    {
        $matches = [];
        $versions = [];

        foreach (range(1, 60) as $i) {
            $versions["pkg-$i"] = '1.0.0';
            $matches["pkg-$i@1.0.0"] = [sprintf('GHSA-aaaa-0000-%04d', $i)];
        }

        $this->lockProject($versions);
        Carbon::setTestNow('2026-01-01 00:00:00');
        $count = 0;
        $this->fakeOsv($matches, vuln: function (Request $r) use (&$count) {
            if (++$count === 3) {
                Carbon::setTestNow(now()->addSeconds(181));
            }

            return Http::response($this->advisory(basename($r->url())), 200);
        });

        $result = $this->runStage($this->audit(['started_at' => null]));

        $this->assertSame(StageStatus::Partial, $result->status);
        $this->assertSame(60, Vulnerability::query()->count());
        $this->assertSame(3, Vulnerability::query()->whereNotNull('severity_label')->count());
        $this->assertSame(57, Vulnerability::query()->whereNull('severity_label')->count());
        $this->assertSame(3, $this->sentTo('/v1/vulns/'));
        $this->assertStringContainsString('Detail 57 advisori tidak dapat diambil', $result->message);
    }

    public function test_old_dependencies_and_vulnerabilities_are_replaced_before_osv_results_are_mapped(): void
    {
        $this->lockProject(['example-lib' => '1.0.0']);
        $this->fakeOsv(['example-lib@1.0.0' => ['GHSA-new-0000-0001']]);
        $audit = $this->audit();
        $old = Dependency::factory()->create(['audit_scan_id' => $audit->id, 'name' => 'old-dep']);
        Vulnerability::factory()->create(['dependency_id' => $old->id, 'osv_id' => 'GHSA-old-0000-0001']);

        $this->runStage($audit);

        $this->assertSame(['example-lib'], Dependency::query()->where('audit_scan_id', $audit->id)->pluck('name')->all());
        $this->assertSame(['GHSA-new-0000-0001'], Vulnerability::query()->pluck('osv_id')->all());
    }

    public function test_dependencies_are_stored_before_osv_is_called(): void
    {
        $this->lockProject(['example-lib' => '1.0.0']);
        $seen = null;
        $this->fakeOsv(query: function () use (&$seen) {
            $seen = Dependency::query()->count();

            return Http::response(['results' => [[]]]);
        });

        $this->runStage($this->audit());

        $this->assertSame(1, $seen);
    }

    public function test_same_package_in_two_projects_is_queried_once_and_stored_per_project(): void
    {
        $this->lockProject(['example-lib' => '1.0.0'], 'a');
        $this->lockProject(['example-lib' => '1.0.0'], 'b');
        $this->fakeOsv(['example-lib@1.0.0' => ['GHSA-aaaa-0000-0001']]);

        $this->runStage($audit = $this->audit());

        $this->assertSame(2, Dependency::query()->where('audit_scan_id', $audit->id)->count());
        $this->assertSame(2, Vulnerability::query()->count());
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/querybatch') && count($r->data()['queries']) === 1);
    }

    public function test_job_time_nearly_used_up_sends_nothing_and_reports_the_reason(): void
    {
        $this->lockProject(['example-lib' => '1.0.0']);
        $this->fakeOsv();
        $audit = $this->audit(['started_at' => now()->subSeconds(580)]);

        $result = $this->runStage($audit);

        $this->assertSame(StageStatus::Partial, $result->status);
        Http::assertNothingSent();
        $this->assertSame([OsvQueryStatus::NotQueried], Dependency::query()->pluck('osv_status')->unique()->values()->all());
        $this->assertSame(1, Dependency::query()->where('audit_scan_id', $audit->id)->count());
        $this->assertStringContainsString('Waktu pemrosesan', $result->message);
        $this->assertStringContainsString('1 dependensi belum diperiksa', $result->message);
    }

    public function test_job_deadline_caps_the_osv_request_timeout(): void
    {
        $this->lockProject(['example-lib' => '1.0.0']);
        Carbon::setTestNow('2026-01-01 00:00:00');
        $options = [];
        $this->fakeOsv(query: function (Request $r, array $opts) use (&$options) {
            $options = $opts;

            return Http::response(['results' => [[]]]);
        });

        $this->runStage($this->audit(['started_at' => now()->subSeconds(560)]));

        $this->assertEqualsWithDelta(10.0, $options['timeout'], 0.001);
    }

    public function test_missing_started_at_uses_the_default_osv_budget(): void
    {
        $this->lockProject(['example-lib' => '1.0.0']);
        Carbon::setTestNow('2026-01-01 00:00:00');
        $options = [];
        $this->fakeOsv(query: function (Request $r, array $opts) use (&$options) {
            $options = $opts;

            return Http::response(['results' => [[]]]);
        });

        $result = $this->runStage($this->audit(['started_at' => null]));

        $this->assertSame(StageStatus::Completed, $result->status);
        $this->assertEqualsWithDelta(15.0, $options['timeout'], 0.001);
    }

    public function test_message_starts_with_the_counts_and_contains_no_server_path(): void
    {
        $this->write('a/package.json', '{');
        $this->lockProject(['example-lib' => '1.0.0'], 'b');
        $this->fakeOsv();

        $result = $this->runStage($this->audit());

        $this->assertStringStartsWith('1 dependensi diperiksa ke OSV', $result->message);
        $this->assertStringNotContainsString($this->root, $result->message);
        $this->assertStringNotContainsString(sys_get_temp_dir(), $result->message);
    }

    public function test_unexpected_exceptions_are_not_swallowed(): void
    {
        $this->lockProject(['example-lib' => '1.0.0']);
        $this->fakeOsv(query: fn () => throw new RuntimeException('bug'));

        $this->expectException(RuntimeException::class);

        $this->runStage($this->audit());
    }

    public function test_osv_status_of_unresolved_dependencies_stays_not_queried_next_to_queried_ones(): void
    {
        $this->write('package.json', ['dependencies' => ['example-lib' => '1.0.0', 'b' => '^1.0.0']]);
        $this->fakeOsv();

        $this->runStage($this->audit());

        $this->assertSame(OsvQueryStatus::Queried, Dependency::query()->where('name', 'example-lib')->sole()->osv_status);
        $this->assertSame(OsvQueryStatus::NotQueried, Dependency::query()->where('name', 'b')->sole()->osv_status);
    }
}
