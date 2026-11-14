<?php

namespace Tests\Feature\Database;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithSchema;
use Tests\TestCase;

class SastTablesTest extends TestCase
{
    use InteractsWithSchema, RefreshDatabase;

    public function test_scan_tool_runs_table_matches_approved_schema(): void
    {
        $this->assertTableColumns('scan_tool_runs', [
            'id' => ['bigint unsigned', false],
            'audit_scan_id' => ['bigint unsigned', false],
            'tool' => ['varchar(20)', false],
            'status' => ['varchar(20)', false],
            'files_scanned' => ['int unsigned', true],
            'findings_count' => ['int unsigned', true],
            'error_message' => ['varchar(500)', true],
            'duration_ms' => ['int unsigned', true],
            'started_at' => ['timestamp', true],
            'finished_at' => ['timestamp', true],
            'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);

        $this->assertForeignKey('scan_tool_runs', 'audit_scan_id', 'audit_scans', 'cascade');
        $this->assertIndex('scan_tool_runs', ['audit_scan_id', 'tool'], unique: true);
    }

    public function test_scan_findings_table_matches_approved_schema(): void
    {
        $this->assertTableColumns('scan_findings', [
            'id' => ['bigint unsigned', false],
            'audit_scan_id' => ['bigint unsigned', false],
            'tool' => ['varchar(20)', false],
            'rule_id' => ['varchar(255)', false],
            'category' => ['varchar(30)', false],
            'severity' => ['varchar(10)', false],
            'file_path' => ['varchar(1024)', false],
            'line_start' => ['int unsigned', true],
            'line_end' => ['int unsigned', true],
            'column_start' => ['int unsigned', true],
            'code_snippet' => ['text', true],
            'message' => ['text', false],
            'cwe' => ['varchar(50)', true],
            'fingerprint' => ['char(64)', false],
            'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);

        $this->assertForeignKey('scan_findings', 'audit_scan_id', 'audit_scans', 'cascade');
        $this->assertIndex('scan_findings', ['audit_scan_id', 'fingerprint'], unique: true);
        $this->assertIndex('scan_findings', ['audit_scan_id', 'severity']);
        $this->assertIndex('scan_findings', ['audit_scan_id', 'tool']);
    }

    public function test_code_metrics_table_matches_approved_schema(): void
    {
        $this->assertTableColumns('code_metrics', [
            'id' => ['bigint unsigned', false],
            'audit_scan_id' => ['bigint unsigned', false],
            'scope' => ['varchar(10)', false],
            'language' => ['varchar(20)', false],
            'file_path' => ['varchar(1024)', false],
            'function_name' => ['varchar(255)', true],
            'line_start' => ['int unsigned', true],
            'loc' => ['int unsigned', true],
            'sloc' => ['int unsigned', true],
            'cyclomatic_complexity' => ['int unsigned', true],
            'halstead_volume' => ['decimal(12,2)', true],
            'halstead_difficulty' => ['decimal(10,2)', true],
            'halstead_effort' => ['decimal(14,2)', true],
            'maintainability_index' => ['decimal(5,2)', true],
            'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);

        $this->assertForeignKey('code_metrics', 'audit_scan_id', 'audit_scans', 'cascade');
        $this->assertIndex('code_metrics', ['audit_scan_id', 'scope']);
    }

    public function test_same_tool_cannot_run_twice_in_one_audit(): void
    {
        $auditId = $this->insertAuditScan();
        $this->insertToolRun($auditId, 'semgrep');
        $this->insertToolRun($auditId, 'eslint');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertToolRun($auditId, 'semgrep');
    }

    public function test_duplicate_finding_fingerprint_is_rejected_within_one_audit_only(): void
    {
        $fingerprint = hash('sha256', 'semgrep|rule|src/app.js|10|pesan');
        $firstAudit = $this->insertAuditScan();
        $secondAudit = $this->insertAuditScan();

        $this->insertFinding($firstAudit, $fingerprint);
        $this->insertFinding($secondAudit, $fingerprint);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertFinding($firstAudit, $fingerprint);
    }

    public function test_deleting_audit_deletes_its_sast_results(): void
    {
        $auditId = $this->insertAuditScan();
        $this->insertToolRun($auditId, 'semgrep');
        $this->insertFinding($auditId, hash('sha256', 'temuan'));
        DB::table('code_metrics')->insert([
            'audit_scan_id' => $auditId,
            'scope' => 'file',
            'language' => 'javascript',
            'file_path' => 'src/app.js',
            'maintainability_index' => 72.5,
        ]);

        DB::table('audit_scans')->where('id', $auditId)->delete();

        $this->assertDatabaseEmpty('scan_tool_runs');
        $this->assertDatabaseEmpty('scan_findings');
        $this->assertDatabaseEmpty('code_metrics');
    }

    private function insertToolRun(int $auditId, string $tool): void
    {
        DB::table('scan_tool_runs')->insert([
            'audit_scan_id' => $auditId,
            'tool' => $tool,
            'status' => 'completed',
        ]);
    }

    private function insertFinding(int $auditId, string $fingerprint): void
    {
        DB::table('scan_findings')->insert([
            'audit_scan_id' => $auditId,
            'tool' => 'semgrep',
            'rule_id' => 'javascript.lang.security.detect-eval',
            'category' => 'security',
            'severity' => 'high',
            'file_path' => 'src/app.js',
            'line_start' => 10,
            'message' => 'Penggunaan eval() berbahaya.',
            'fingerprint' => $fingerprint,
        ]);
    }
}
