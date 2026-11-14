<?php

namespace Tests\Feature\Models;

use App\Enums\AiSource;
use App\Enums\AiStatus;
use App\Enums\CodeLanguage;
use App\Enums\DependencyRelation;
use App\Enums\DependencyScope;
use App\Enums\Ecosystem;
use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\ManifestType;
use App\Enums\MetricScope;
use App\Enums\OsvQueryStatus;
use App\Enums\Severity;
use App\Enums\ToolRunStatus;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;
use App\Enums\VulnerabilitySeverity;
use App\Models\AiRefactoring;
use App\Models\AiUsageLog;
use App\Models\CodeMetric;
use App\Models\Dependency;
use App\Models\ScanFinding;
use App\Models\ScanToolRun;
use App\Models\User;
use App\Models\Vulnerability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditResultModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_tool_run_casts_and_belongs_to_audit(): void
    {
        $run = ScanToolRun::factory()->create()->refresh();

        $this->assertSame(FindingTool::Semgrep, $run->tool);
        $this->assertSame(ToolRunStatus::Completed, $run->status);
        $this->assertTrue($run->auditScan->toolRuns->first()->is($run));
    }

    public function test_scan_finding_casts_and_relations(): void
    {
        $finding = ScanFinding::factory()->create()->refresh();
        AiRefactoring::factory()->for($finding)->create();

        $this->assertSame(FindingTool::Semgrep, $finding->tool);
        $this->assertSame(FindingCategory::Security, $finding->category);
        $this->assertSame(Severity::High, $finding->severity);
        $this->assertNotNull($finding->auditScan);
        $this->assertCount(1, $finding->aiRefactorings);
    }

    public function test_code_metric_casts(): void
    {
        $metric = CodeMetric::factory()->create(['maintainability_index' => 64.5])->refresh();

        $this->assertSame(MetricScope::File, $metric->scope);
        $this->assertSame(CodeLanguage::Javascript, $metric->language);
        $this->assertSame('64.50', $metric->maintainability_index);
        $this->assertNotNull($metric->auditScan);
    }

    public function test_dependency_casts_and_relations(): void
    {
        $dependency = Dependency::factory()->create()->refresh();
        Vulnerability::factory()->for($dependency)->create();

        $this->assertSame(Ecosystem::Npm, $dependency->ecosystem);
        $this->assertSame(DependencyScope::Production, $dependency->scope);
        $this->assertSame(DependencyRelation::Direct, $dependency->relation);
        $this->assertSame(ManifestType::PackageLockV3, $dependency->source_file_type);
        $this->assertSame(VersionStatus::Exact, $dependency->version_status);
        $this->assertSame(OsvQueryStatus::NotQueried, $dependency->osv_status);
        $this->assertNull($dependency->unresolved_reason);
        $this->assertCount(1, $dependency->vulnerabilities);
    }

    public function test_unresolved_dependency_state(): void
    {
        $dependency = Dependency::factory()->unresolved(UnresolvedReason::RangeWithoutLockfile)->create()->refresh();

        $this->assertNull($dependency->version);
        $this->assertSame(VersionStatus::Unresolved, $dependency->version_status);
        $this->assertSame(UnresolvedReason::RangeWithoutLockfile, $dependency->unresolved_reason);
    }

    public function test_vulnerability_casts_json_to_arrays_and_matches_dependency_audit(): void
    {
        $vulnerability = Vulnerability::factory()->create([
            'aliases' => ['CVE-2021-23337'],
            'fixed_versions' => ['4.17.21'],
            'reference_urls' => ['https://osv.dev/vulnerability/GHSA-35jh-r3h4-6jhm'],
            'published_at' => '2021-02-15 00:00:00',
        ])->refresh();

        $this->assertSame(['CVE-2021-23337'], $vulnerability->aliases);
        $this->assertSame(['4.17.21'], $vulnerability->fixed_versions);
        $this->assertSame(VulnerabilitySeverity::High, $vulnerability->severity_label);
        $this->assertInstanceOf(Carbon::class, $vulnerability->published_at);
        $this->assertSame($vulnerability->dependency->audit_scan_id, $vulnerability->audit_scan_id);
    }

    public function test_ai_refactoring_for_finding_and_for_vulnerability(): void
    {
        $user = User::factory()->create();

        $forFinding = AiRefactoring::factory()->create(['requested_by_user_id' => $user->id])->refresh();
        $forVulnerability = AiRefactoring::factory()->forVulnerability()->create()->refresh();

        $this->assertSame(AiSource::Llm, $forFinding->source);
        $this->assertSame(AiStatus::Pending, $forFinding->status);
        $this->assertTrue($forFinding->requestedBy->is($user));
        $this->assertSame($forFinding->scanFinding->audit_scan_id, $forFinding->audit_scan_id);
        $this->assertNull($forFinding->vulnerability_id);

        $this->assertNull($forVulnerability->scan_finding_id);
        $this->assertSame($forVulnerability->vulnerability->audit_scan_id, $forVulnerability->audit_scan_id);
        $this->assertTrue($user->requestedRefactorings->first()->is($forFinding));
    }

    public function test_ai_refactoring_validation_errors_cast_to_array(): void
    {
        $refactoring = AiRefactoring::factory()->create([
            'status' => AiStatus::RejectedValidation,
            'validation_errors' => ['fixed_versions' => 'Versi 9.9.9 tidak ada di data OSV.'],
        ])->refresh();

        $this->assertSame(AiStatus::RejectedValidation, $refactoring->status);
        $this->assertSame(['fixed_versions' => 'Versi 9.9.9 tidak ada di data OSV.'], $refactoring->validation_errors);
    }

    public function test_ai_usage_log_casts_and_belongs_to_user(): void
    {
        $log = AiUsageLog::factory()->create(['usage_date' => '2026-11-14', 'request_count' => 3])->refresh();

        $this->assertSame('2026-11-14', $log->usage_date->toDateString());
        $this->assertSame(3, $log->request_count);
        $this->assertSame(0, $log->failed_count);
        $this->assertTrue($log->user->aiUsageLogs->first()->is($log));
    }
}
