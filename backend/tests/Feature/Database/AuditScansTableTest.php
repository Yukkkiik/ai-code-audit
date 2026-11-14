<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithSchema;
use Tests\TestCase;

class AuditScansTableTest extends TestCase
{
    use InteractsWithSchema, RefreshDatabase;

    public function test_audit_scans_table_matches_approved_schema(): void
    {
        $this->assertTableColumns('audit_scans', [
            'id' => ['bigint unsigned', false],
            'public_id' => ['char(26)', false],
            'user_id' => ['bigint unsigned', true],
            'guest_token_hash' => ['char(64)', true],
            'source_type' => ['varchar(10)', false],
            'repository_name' => ['varchar(255)', false],
            'repository_url' => ['varchar(2048)', true],
            'original_filename' => ['varchar(255)', true],
            'source_size_bytes' => ['bigint unsigned', true],
            'commit_sha' => ['char(40)', true],
            'status' => ['varchar(20)', false],
            'sast_status' => ['varchar(20)', false],
            'sca_status' => ['varchar(20)', false],
            'sast_error' => ['varchar(500)', true],
            'sca_error' => ['varchar(500)', true],
            'error_code' => ['varchar(50)', true],
            'error_message' => ['varchar(500)', true],
            'health_score' => ['tinyint unsigned', true],
            'health_category' => ['varchar(20)', true],
            'queued_at' => ['timestamp', true],
            'started_at' => ['timestamp', true],
            'finished_at' => ['timestamp', true],
            'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);
    }

    public function test_status_columns_have_initial_defaults(): void
    {
        $this->assertColumnDefault('audit_scans', 'status', 'queued');
        $this->assertColumnDefault('audit_scans', 'sast_status', 'pending');
        $this->assertColumnDefault('audit_scans', 'sca_status', 'pending');
    }

    public function test_audit_scans_indexes_and_foreign_key(): void
    {
        $this->assertIndex('audit_scans', ['public_id'], unique: true);
        $this->assertIndex('audit_scans', ['user_id', 'created_at']);
        $this->assertIndex('audit_scans', ['status']);
        $this->assertForeignKey('audit_scans', 'user_id', 'users', 'cascade');
        $this->assertCheckConstraint('audit_scans', 'chk_audit_scans_owner');
    }

    public function test_guest_audit_and_user_audit_are_accepted(): void
    {
        $guestAuditId = $this->insertAuditScan();
        $userAuditId = $this->insertAuditScan(['user_id' => $this->insertUser(), 'guest_token_hash' => null]);

        $this->assertDatabaseHas('audit_scans', ['id' => $guestAuditId, 'user_id' => null, 'status' => 'queued']);
        $this->assertDatabaseHas('audit_scans', ['id' => $userAuditId, 'guest_token_hash' => null]);
    }

    public function test_audit_without_owner_is_rejected_by_check_constraint(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('chk_audit_scans_owner');

        $this->insertAuditScan(['user_id' => null, 'guest_token_hash' => null]);
    }

    public function test_public_id_must_be_unique(): void
    {
        $this->insertAuditScan(['public_id' => '01jd3x5w8q2g7b9n4k6m1r0t2y']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertAuditScan(['public_id' => '01jd3x5w8q2g7b9n4k6m1r0t2y']);
    }

    public function test_deleting_user_deletes_their_audits(): void
    {
        $userId = $this->insertUser();
        $auditId = $this->insertAuditScan(['user_id' => $userId, 'guest_token_hash' => null]);

        DB::table('users')->where('id', $userId)->delete();

        $this->assertDatabaseMissing('audit_scans', ['id' => $auditId]);
    }
}
