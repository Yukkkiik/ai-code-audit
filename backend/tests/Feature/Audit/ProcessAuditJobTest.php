<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditStage;
use App\Enums\AuditStatus;
use App\Enums\SourceType;
use App\Enums\StageStatus;
use App\Jobs\ProcessAuditJob;
use App\Models\AuditScan;
use App\Models\Vulnerability;
use App\Services\Audit\AuditScanCreator;
use App\Services\Audit\AuditStatusManager;
use App\Services\Audit\Stages\AuditStageRunners;
use App\Services\Audit\Stages\StageResult;
use App\Services\Ingestion\AuditWorkspace;
use App\Services\Ingestion\HostResolver;
use App\Services\Ingestion\RepositoryIngestor;
use App\Services\Sca\ScaStageRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\BuildsZipArchives;
use Tests\Support\FakeGitBinary;
use Tests\Support\FakeHostResolver;
use Tests\Support\RecordingStageRunner;
use Tests\Support\UsesTemporaryAuditStorage;
use Tests\TestCase;

class ProcessAuditJobTest extends TestCase
{
    use BuildsZipArchives, FakeGitBinary, RefreshDatabase, UsesTemporaryAuditStorage;

    private RecordingStageRunner $sast;

    private RecordingStageRunner $sca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTemporaryAuditStorage();
        $this->app->instance(HostResolver::class, new FakeHostResolver(['github.com' => ['140.82.112.3']]));
        $this->useRunners(StageResult::completed(), StageResult::completed());
    }

    public function test_zip_audit_is_processed_end_to_end_and_workspace_is_cleaned(): void
    {
        $audit = $this->zipAudit(['proyek-main/package.json' => '{}', 'proyek-main/src/app.js' => 'eval(x);']);

        ProcessAuditJob::dispatchSync($audit->id);

        $audit->refresh();
        $this->assertSame(AuditStatus::Completed, $audit->status);
        $this->assertSame(StageStatus::Completed, $audit->sast_status);
        $this->assertSame(StageStatus::Completed, $audit->sca_status);
        $this->assertNotNull($audit->started_at);
        $this->assertNotNull($audit->finished_at);
        $this->assertSame(10, $audit->source_size_bytes);

        $this->assertCount(1, $this->sast->calls);
        $this->assertTrue($this->sast->calls[0]['root_existed'], 'Runner harus menerima repositori yang masih ada di workspace.');
        $this->assertSame(['package.json', 'src'], $this->sast->calls[0]['files']);
        $this->assertCount(1, $this->sca->calls);

        $workspace = $this->app->make(AuditWorkspace::class);
        $this->assertDirectoryDoesNotExist($workspace->path($audit));
        $this->assertFileDoesNotExist($workspace->uploadPath($audit));
    }

    public function test_git_audit_records_commit_sha_and_size(): void
    {
        $this->useFakeGit('success');
        $audit = $this->app->make(AuditScanCreator::class)
            ->create(null, SourceType::Git, 'org/repo', repositoryUrl: 'https://github.com/org/repo')->audit;

        ProcessAuditJob::dispatchSync($audit->id);

        $audit->refresh();
        $this->assertSame(AuditStatus::Completed, $audit->status);
        $this->assertSame('0123456789abcdef0123456789abcdef01234567', $audit->commit_sha);
        $this->assertGreaterThan(0, $audit->source_size_bytes);
    }

    public function test_default_runners_report_sast_as_unavailable_and_run_the_real_sca_engine(): void
    {
        $this->app->forgetInstance(AuditStageRunners::class);
        $audit = $this->zipAudit(['index.js' => 'x']);

        ProcessAuditJob::dispatchSync($audit->id);

        $audit->refresh();
        $this->assertSame(StageStatus::Failed, $audit->sast_status);
        $this->assertStringContainsString('belum tersedia', $audit->sast_error);
        $this->assertSame(StageStatus::Skipped, $audit->sca_status);
        $this->assertStringContainsString('package.json', $audit->sca_error);
        $this->assertSame(AuditStatus::Failed, $audit->status);
    }

    public function test_sca_runs_end_to_end_through_the_job_while_sast_stays_unavailable(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.osv.dev/v1/querybatch' => Http::response(['results' => [['vulns' => [['id' => 'GHSA-aaaa-0000-0001', 'modified' => '2024-02-15T05:32:53Z']]]]]),
            'api.osv.dev/v1/vulns/*' => Http::response(['id' => 'GHSA-aaaa-0000-0001', 'summary' => 'Contoh advisori', 'database_specific' => ['severity' => 'HIGH']]),
        ]);
        $this->app->forgetInstance(AuditStageRunners::class);
        $audit = $this->zipAudit([
            'proyek/package.json' => json_encode(['dependencies' => ['example-lib' => '^1.0.0']]),
            'proyek/package-lock.json' => json_encode(['lockfileVersion' => 3, 'packages' => [
                '' => ['dependencies' => ['example-lib' => '^1.0.0']],
                'node_modules/example-lib' => ['version' => '1.0.0', 'resolved' => 'https://registry.npmjs.org/example-lib/-/example-lib-1.0.0.tgz'],
            ]]),
            'proyek/index.js' => 'x',
        ]);

        ProcessAuditJob::dispatchSync($audit->id);

        $audit->refresh();
        $this->assertSame(StageStatus::Failed, $audit->sast_status);
        $this->assertSame(StageStatus::Completed, $audit->sca_status);
        $this->assertSame(AuditStatus::Partial, $audit->status);
        $this->assertSame(1, $audit->dependencies()->count());
        $this->assertSame('GHSA-aaaa-0000-0001', Vulnerability::query()->where('audit_scan_id', $audit->id)->sole()->osv_id);
        $this->assertDirectoryDoesNotExist($this->app->make(AuditWorkspace::class)->path($audit));
    }

    public function test_a_crashing_sca_runner_does_not_fail_sast_and_vice_versa(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.osv.dev/*' => fn () => throw new RuntimeException('bug')]);
        $this->app->forgetInstance(AuditStageRunners::class);
        $this->app->instance(AuditStageRunners::class, new AuditStageRunners([
            $this->sast,
            $this->app->make(ScaStageRunner::class),
        ]));
        $audit = $this->zipAudit(['proyek/package.json' => json_encode(['dependencies' => ['example-lib' => '1.0.0']])]);

        ProcessAuditJob::dispatchSync($audit->id);

        $audit->refresh();
        $this->assertSame(StageStatus::Completed, $audit->sast_status);
        $this->assertSame(StageStatus::Failed, $audit->sca_status);
        $this->assertStringNotContainsString('bug', (string) $audit->sca_error);
        $this->assertSame(AuditStatus::Partial, $audit->status);
    }

    public function test_crashing_stage_does_not_fail_the_other_stage(): void
    {
        Log::spy();
        $this->useRunners(StageResult::completed(), new RuntimeException('OSV client meledak: SQLSTATE rahasia'));
        $audit = $this->zipAudit(['index.js' => 'x']);

        ProcessAuditJob::dispatchSync($audit->id);

        $audit->refresh();
        $this->assertSame(StageStatus::Completed, $audit->sast_status);
        $this->assertSame(StageStatus::Failed, $audit->sca_status);
        $this->assertStringNotContainsString('SQLSTATE', $audit->sca_error);
        $this->assertSame(AuditStatus::Partial, $audit->status);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_stage_results_with_messages_are_stored(): void
    {
        $this->useRunners(StageResult::skipped('Tidak ada file JavaScript/TypeScript.'), StageResult::partial('Sebagian query OSV.dev gagal.'));
        $audit = $this->zipAudit(['README.md' => '# halo']);

        ProcessAuditJob::dispatchSync($audit->id);

        $audit->refresh();
        $this->assertSame(StageStatus::Skipped, $audit->sast_status);
        $this->assertSame('Tidak ada file JavaScript/TypeScript.', $audit->sast_error);
        $this->assertSame(StageStatus::Partial, $audit->sca_status);
        $this->assertSame(AuditStatus::Partial, $audit->status);
    }

    public function test_ingestion_failure_fails_audit_without_running_stages(): void
    {
        $audit = $this->zipAudit(['../../evil.txt' => 'jahat']);

        ProcessAuditJob::dispatchSync($audit->id);

        $audit->refresh();
        $this->assertSame(AuditStatus::Failed, $audit->status);
        $this->assertSame('ZIP_SLIP_DETECTED', $audit->error_code);
        $this->assertSame(StageStatus::Skipped, $audit->sast_status);
        $this->assertSame(StageStatus::Skipped, $audit->sca_status);
        $this->assertSame([], $this->sast->calls);
        $this->assertDirectoryDoesNotExist($this->app->make(AuditWorkspace::class)->path($audit));
    }

    public function test_job_for_audit_that_is_no_longer_queued_changes_nothing(): void
    {
        $audit = $this->zipAudit(['index.js' => 'x']);
        $this->app->make(AuditStatusManager::class)->markIngesting($audit);
        $workspace = $this->app->make(AuditWorkspace::class);
        $workspace->create($audit);

        ProcessAuditJob::dispatchSync($audit->id);

        $this->assertSame(AuditStatus::Ingesting, $audit->fresh()->status);
        $this->assertDirectoryExists($workspace->path($audit), 'Job duplikat tidak boleh menghapus workspace milik job yang sedang berjalan.');
        $this->assertSame([], $this->sast->calls);
    }

    public function test_job_for_deleted_audit_is_ignored(): void
    {
        ProcessAuditJob::dispatchSync(999999);

        $this->assertDatabaseEmpty('audit_scans');
    }

    public function test_unexpected_error_marks_audit_failed_and_cleans_up(): void
    {
        $audit = $this->zipAudit(['index.js' => 'x']);
        $this->app->instance(RepositoryIngestor::class, new class extends RepositoryIngestor
        {
            public function __construct() {}

            public function ingest(AuditScan $audit): never
            {
                app(AuditWorkspace::class)->create($audit);

                throw new RuntimeException('Disk penuh');
            }
        });

        try {
            ProcessAuditJob::dispatchSync($audit->id);
            $this->fail('Exception tak terduga seharusnya diteruskan ke queue.');
        } catch (RuntimeException $e) {
            $this->assertSame('Disk penuh', $e->getMessage());
        }

        $audit->refresh();
        $this->assertSame(AuditStatus::Failed, $audit->status);
        $this->assertSame('PROCESSING_FAILED', $audit->error_code);
        $this->assertStringNotContainsString('Disk penuh', $audit->error_message);
        $this->assertDirectoryDoesNotExist($this->app->make(AuditWorkspace::class)->path($audit));
    }

    public function test_timeout_marks_running_audit_as_timed_out(): void
    {
        $audit = $this->zipAudit(['index.js' => 'x']);
        $status = $this->app->make(AuditStatusManager::class);
        $status->markIngesting($audit);
        $status->markScanning($audit);
        $status->startStage($audit, AuditStage::Sast);

        (new ProcessAuditJob($audit->id))->failed(new TimeoutExceededException('timeout'));

        $audit->refresh();
        $this->assertSame(AuditStatus::Failed, $audit->status);
        $this->assertSame('JOB_TIMEOUT', $audit->error_code);
        $this->assertSame(StageStatus::Failed, $audit->sast_status);
        $this->assertSame(StageStatus::Skipped, $audit->sca_status);
    }

    public function test_failed_handler_leaves_finished_audit_untouched(): void
    {
        $audit = AuditScan::factory()->create(['status' => AuditStatus::Completed]);

        (new ProcessAuditJob($audit->id))->failed(new RuntimeException('telat'));

        $this->assertSame(AuditStatus::Completed, $audit->fresh()->status);
        $this->assertNull($audit->fresh()->error_code);
    }

    public function test_retry_and_timeout_policy(): void
    {
        $job = new ProcessAuditJob(1);

        $this->assertSame(1, $job->tries, 'Pipeline tidak boleh diulang otomatis (pemindaian ganda).');
        $this->assertSame((int) config('audit.job.timeout_seconds'), $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertGreaterThan(
            config('audit.job.timeout_seconds'),
            config('queue.connections.redis.retry_after'),
            'retry_after harus lebih besar dari timeout job agar job tidak dijalankan ganda.',
        );
        $this->assertGreaterThan(config('audit.git.clone_timeout_seconds'), config('audit.job.timeout_seconds'));
    }

    public function test_job_payload_contains_only_the_audit_id(): void
    {
        $audit = $this->zipAudit(['index.js' => 'x']);

        $payload = serialize(new ProcessAuditJob($audit->id));

        $this->assertStringContainsString('auditScanId', $payload);
        $this->assertStringNotContainsString((string) $audit->guest_token_hash, $payload);
        $this->assertStringNotContainsString('index.js', $payload);
    }

    public function test_redis_worker_processes_dispatched_audit(): void
    {
        $audit = $this->zipAudit(['index.js' => 'x']);
        $queue = 'audit-job-test-'.Str::uuid();

        ProcessAuditJob::dispatch($audit->id)->onConnection('redis')->onQueue($queue);
        $this->assertSame(AuditStatus::Queued, $audit->fresh()->status);

        Artisan::call('queue:work', ['connection' => 'redis', '--queue' => $queue, '--once' => true]);

        $this->assertSame(AuditStatus::Completed, $audit->fresh()->status);
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    private function useRunners(StageResult|\Throwable $sast, StageResult|\Throwable $sca): void
    {
        $this->sast = new RecordingStageRunner(AuditStage::Sast, $sast);
        $this->sca = new RecordingStageRunner(AuditStage::Sca, $sca);

        $this->app->instance(AuditStageRunners::class, new AuditStageRunners([$this->sast, $this->sca]));
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function zipAudit(array $entries): AuditScan
    {
        $audit = $this->app->make(AuditScanCreator::class)->create(null, SourceType::Zip, 'proyek')->audit;
        $this->app->make(AuditWorkspace::class)
            ->storeUpload($audit, new UploadedFile($this->makeZip($entries), 'proyek.zip', 'application/zip', null, true));

        return $audit;
    }
}
