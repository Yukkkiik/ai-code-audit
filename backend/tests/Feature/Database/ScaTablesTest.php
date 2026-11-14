<?php

namespace Tests\Feature\Database;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithSchema;
use Tests\TestCase;

class ScaTablesTest extends TestCase
{
    use InteractsWithSchema, RefreshDatabase;

    public function test_dependencies_table_matches_approved_schema(): void
    {
        $this->assertTableColumns('dependencies', [
            'id' => ['bigint unsigned', false],
            'audit_scan_id' => ['bigint unsigned', false],
            'ecosystem' => ['varchar(20)', false],
            'name' => ['varchar(214)', false],
            'version' => ['varchar(100)', true],
            'declared_spec' => ['varchar(1024)', true],
            'scope' => ['varchar(15)', false],
            'relation' => ['varchar(15)', false],
            'manifest_path' => ['varchar(1024)', false],
            'source_file_type' => ['varchar(20)', false],
            'lock_path' => ['varchar(1024)', true],
            'version_status' => ['varchar(20)', false],
            'unresolved_reason' => ['varchar(30)', true],
            'osv_status' => ['varchar(15)', false],
            'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);

        $this->assertColumnDefault('dependencies', 'ecosystem', 'npm');
        $this->assertColumnDefault('dependencies', 'osv_status', 'not_queried');
        $this->assertForeignKey('dependencies', 'audit_scan_id', 'audit_scans', 'cascade');
        $this->assertIndex('dependencies', ['audit_scan_id', 'name']);
        $this->assertIndex('dependencies', ['audit_scan_id', 'version_status']);
    }

    public function test_vulnerabilities_table_matches_approved_schema(): void
    {
        $this->assertTableColumns('vulnerabilities', [
            'id' => ['bigint unsigned', false],
            'audit_scan_id' => ['bigint unsigned', false],
            'dependency_id' => ['bigint unsigned', false],
            'osv_id' => ['varchar(100)', false],
            'aliases' => ['json', true],
            'summary' => ['varchar(1000)', true],
            'details' => ['mediumtext', true],
            'severity_label' => ['varchar(10)', true],
            'severity_vectors' => ['json', true],
            'affected_ranges' => ['json', true],
            'fixed_versions' => ['json', true],
            'reference_urls' => ['json', true],
            'published_at' => ['timestamp', true],
            'modified_at' => ['timestamp', true],
            'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);

        $this->assertForeignKey('vulnerabilities', 'audit_scan_id', 'audit_scans', 'cascade');
        $this->assertForeignKey('vulnerabilities', 'dependency_id', 'dependencies', 'cascade');
        $this->assertIndex('vulnerabilities', ['dependency_id', 'osv_id'], unique: true);
        $this->assertIndex('vulnerabilities', ['audit_scan_id', 'severity_label']);
    }

    public function test_dependency_accepts_long_git_spec_and_scoped_package_name(): void
    {
        $auditId = $this->insertAuditScan();
        $gitSpec = 'git+https://github.com/organisasi/'.str_repeat('nama-repo-panjang-', 30).'.git#'.str_repeat('a', 40);

        $dependencyId = $this->insertDependency($auditId, [
            'name' => '@scope/'.str_repeat('p', 207),
            'version' => null,
            'declared_spec' => $gitSpec,
            'version_status' => 'unresolved',
            'unresolved_reason' => 'non_registry_source',
        ]);

        $this->assertGreaterThan(255, strlen($gitSpec));
        $this->assertDatabaseHas('dependencies', ['id' => $dependencyId, 'declared_spec' => $gitSpec, 'osv_status' => 'not_queried']);
    }

    public function test_same_advisory_cannot_be_recorded_twice_for_one_dependency(): void
    {
        $auditId = $this->insertAuditScan();
        $lodash = $this->insertDependency($auditId, ['name' => 'lodash']);
        $minimist = $this->insertDependency($auditId, ['name' => 'minimist']);

        $this->insertVulnerability($auditId, $lodash, 'GHSA-p6mc-m468-83gw');
        $this->insertVulnerability($auditId, $minimist, 'GHSA-p6mc-m468-83gw');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertVulnerability($auditId, $lodash, 'GHSA-p6mc-m468-83gw');
    }

    public function test_vulnerability_json_columns_round_trip(): void
    {
        $auditId = $this->insertAuditScan();
        $dependencyId = $this->insertDependency($auditId);
        $this->insertVulnerability($auditId, $dependencyId, 'GHSA-35jh-r3h4-6jhm', [
            'aliases' => json_encode(['CVE-2021-23337']),
            'fixed_versions' => json_encode(['4.17.21']),
            'reference_urls' => json_encode(['https://github.com/advisories/GHSA-35jh-r3h4-6jhm']),
        ]);

        $row = DB::table('vulnerabilities')->first();

        $this->assertSame(['CVE-2021-23337'], json_decode($row->aliases, true));
        $this->assertSame(['4.17.21'], json_decode($row->fixed_versions, true));
    }

    public function test_deleting_dependency_or_audit_deletes_vulnerabilities(): void
    {
        $auditId = $this->insertAuditScan();
        $first = $this->insertDependency($auditId, ['name' => 'lodash']);
        $second = $this->insertDependency($auditId, ['name' => 'minimist']);
        $this->insertVulnerability($auditId, $first, 'GHSA-1111-1111-1111');
        $this->insertVulnerability($auditId, $second, 'GHSA-2222-2222-2222');

        DB::table('dependencies')->where('id', $first)->delete();
        $this->assertDatabaseCount('vulnerabilities', 1);

        DB::table('audit_scans')->where('id', $auditId)->delete();
        $this->assertDatabaseEmpty('dependencies');
        $this->assertDatabaseEmpty('vulnerabilities');
    }

    private function insertDependency(int $auditId, array $overrides = []): int
    {
        return DB::table('dependencies')->insertGetId([
            'audit_scan_id' => $auditId,
            'name' => 'lodash',
            'version' => '4.17.20',
            'declared_spec' => '^4.17.20',
            'scope' => 'production',
            'relation' => 'direct',
            'manifest_path' => 'package-lock.json',
            'source_file_type' => 'package_lock_v3',
            'lock_path' => 'node_modules/lodash',
            'version_status' => 'exact',
            ...$overrides,
        ]);
    }

    private function insertVulnerability(int $auditId, int $dependencyId, string $osvId, array $overrides = []): void
    {
        DB::table('vulnerabilities')->insert([
            'audit_scan_id' => $auditId,
            'dependency_id' => $dependencyId,
            'osv_id' => $osvId,
            'summary' => 'Prototype pollution',
            'severity_label' => 'high',
            ...$overrides,
        ]);
    }
}
