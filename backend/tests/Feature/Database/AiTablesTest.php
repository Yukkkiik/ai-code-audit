<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithSchema;
use Tests\TestCase;

class AiTablesTest extends TestCase
{
    use InteractsWithSchema, RefreshDatabase;

    public function test_ai_refactorings_table_matches_approved_schema(): void
    {
        $this->assertTableColumns('ai_refactorings', [
            'id' => ['bigint unsigned', false],
            'audit_scan_id' => ['bigint unsigned', false],
            'scan_finding_id' => ['bigint unsigned', true],
            'vulnerability_id' => ['bigint unsigned', true],
            'requested_by_user_id' => ['bigint unsigned', true],
            'source' => ['varchar(20)', false],
            'provider' => ['varchar(50)', true],
            'model' => ['varchar(100)', true],
            'prompt_version' => ['varchar(20)', false],
            'status' => ['varchar(25)', false],
            'original_code' => ['text', true],
            'root_cause' => ['text', true],
            'impact' => ['text', true],
            'recommendation' => ['text', true],
            'suggested_code' => ['text', true],
            'rationale' => ['text', true],
            'validation_errors' => ['json', true],
            'latency_ms' => ['int unsigned', true],
            'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);

        $this->assertColumnDefault('ai_refactorings', 'status', 'pending');
        $this->assertForeignKey('ai_refactorings', 'audit_scan_id', 'audit_scans', 'cascade');
        $this->assertForeignKey('ai_refactorings', 'scan_finding_id', 'scan_findings', 'cascade');
        $this->assertForeignKey('ai_refactorings', 'vulnerability_id', 'vulnerabilities', 'cascade');
        $this->assertForeignKey('ai_refactorings', 'requested_by_user_id', 'users', 'set null');
        $this->assertIndex('ai_refactorings', ['audit_scan_id']);
        $this->assertIndex('ai_refactorings', ['scan_finding_id']);
        $this->assertIndex('ai_refactorings', ['vulnerability_id']);
        $this->assertCheckConstraint('ai_refactorings', 'chk_ai_refactorings_single_target');
    }

    public function test_ai_usage_logs_table_matches_approved_schema(): void
    {
        $this->assertTableColumns('ai_usage_logs', [
            'id' => ['bigint unsigned', false],
            'user_id' => ['bigint unsigned', false],
            'usage_date' => ['date', false],
            'request_count' => ['int unsigned', false],
            'success_count' => ['int unsigned', false],
            'failed_count' => ['int unsigned', false],
            'fallback_count' => ['int unsigned', false],
            'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);

        foreach (['request_count', 'success_count', 'failed_count', 'fallback_count'] as $counter) {
            $this->assertColumnDefault('ai_usage_logs', $counter, '0');
        }

        $this->assertForeignKey('ai_usage_logs', 'user_id', 'users', 'cascade');
        $this->assertIndex('ai_usage_logs', ['user_id', 'usage_date'], unique: true);
    }

    public function test_refactoring_targeting_a_finding_is_accepted(): void
    {
        $auditId = $this->insertAuditScan();

        $id = $this->insertRefactoring($auditId, ['scan_finding_id' => $this->insertFinding($auditId)]);

        $this->assertDatabaseHas('ai_refactorings', ['id' => $id, 'status' => 'pending', 'vulnerability_id' => null]);
    }

    public function test_refactoring_with_both_targets_is_rejected(): void
    {
        $auditId = $this->insertAuditScan();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('chk_ai_refactorings_single_target');

        $this->insertRefactoring($auditId, [
            'scan_finding_id' => $this->insertFinding($auditId),
            'vulnerability_id' => $this->insertVulnerability($auditId),
        ]);
    }

    public function test_refactoring_without_target_is_rejected(): void
    {
        $auditId = $this->insertAuditScan();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('chk_ai_refactorings_single_target');

        $this->insertRefactoring($auditId);
    }

    public function test_deleting_requesting_user_of_another_audit_keeps_refactoring_with_null_requester(): void
    {
        $guestAuditId = $this->insertAuditScan();
        $requesterId = $this->insertUser();
        $refactoringId = $this->insertRefactoring($guestAuditId, [
            'scan_finding_id' => $this->insertFinding($guestAuditId),
            'requested_by_user_id' => $requesterId,
        ]);

        DB::table('users')->where('id', $requesterId)->delete();

        $this->assertDatabaseHas('ai_refactorings', ['id' => $refactoringId, 'requested_by_user_id' => null]);
    }

    public function test_deleting_user_removes_their_audits_refactorings_and_usage_logs(): void
    {
        $userId = $this->insertUser();
        $auditId = $this->insertAuditScan(['user_id' => $userId, 'guest_token_hash' => null]);
        $this->insertRefactoring($auditId, [
            'scan_finding_id' => $this->insertFinding($auditId),
            'requested_by_user_id' => $userId,
        ]);
        DB::table('ai_usage_logs')->insert(['user_id' => $userId, 'usage_date' => '2026-11-14', 'request_count' => 1]);

        DB::table('users')->where('id', $userId)->delete();

        $this->assertDatabaseEmpty('audit_scans');
        $this->assertDatabaseEmpty('ai_refactorings');
        $this->assertDatabaseEmpty('ai_usage_logs');
    }

    public function test_one_usage_row_per_user_per_day(): void
    {
        $userId = $this->insertUser();
        DB::table('ai_usage_logs')->insert(['user_id' => $userId, 'usage_date' => '2026-11-14']);
        DB::table('ai_usage_logs')->insert(['user_id' => $userId, 'usage_date' => '2026-11-15']);

        $this->assertSame(0, DB::table('ai_usage_logs')->where('usage_date', '2026-11-14')->value('request_count'));

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('ai_usage_logs')->insert(['user_id' => $userId, 'usage_date' => '2026-11-14']);
    }

    private function insertRefactoring(int $auditId, array $overrides = []): int
    {
        return DB::table('ai_refactorings')->insertGetId([
            'audit_scan_id' => $auditId,
            'source' => 'llm',
            'prompt_version' => 'v1',
            ...$overrides,
        ]);
    }

    private function insertFinding(int $auditId): int
    {
        return DB::table('scan_findings')->insertGetId([
            'audit_scan_id' => $auditId,
            'tool' => 'eslint',
            'rule_id' => 'no-eval',
            'category' => 'security',
            'severity' => 'medium',
            'file_path' => 'src/index.ts',
            'message' => 'eval() tidak boleh dipakai.',
            'fingerprint' => hash('sha256', uniqid('', true)),
        ]);
    }

    private function insertVulnerability(int $auditId): int
    {
        $dependencyId = DB::table('dependencies')->insertGetId([
            'audit_scan_id' => $auditId,
            'name' => 'minimist',
            'version' => '1.2.5',
            'scope' => 'production',
            'relation' => 'transitive',
            'manifest_path' => 'package-lock.json',
            'source_file_type' => 'package_lock_v2',
            'version_status' => 'exact',
        ]);

        return DB::table('vulnerabilities')->insertGetId([
            'audit_scan_id' => $auditId,
            'dependency_id' => $dependencyId,
            'osv_id' => 'GHSA-xvch-5gv4-984h',
        ]);
    }
}
