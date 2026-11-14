<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\InteractsWithSchema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use InteractsWithSchema, RefreshDatabase;

    public function test_only_tables_from_database_md_exist_after_migration(): void
    {
        $tables = collect(Schema::getTables())
            ->where('schema', DB::connection()->getDatabaseName())
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing([
            'migrations',
            'users',
            'audit_scans',
            'scan_tool_runs',
            'scan_findings',
            'code_metrics',
            'dependencies',
            'vulnerabilities',
            'ai_refactorings',
            'ai_usage_logs',
            'failed_jobs',
        ], $tables);
    }

    public function test_all_tables_use_innodb_and_utf8mb4_unicode_collation(): void
    {
        $tables = collect(Schema::getTables())->where('schema', DB::connection()->getDatabaseName());

        foreach ($tables as $table) {
            $this->assertSame('InnoDB', $table['engine'], "Engine tabel {$table['name']}");
            $this->assertSame('utf8mb4_unicode_ci', $table['collation'], "Collation tabel {$table['name']}");
        }
    }

    public function test_failed_jobs_table_matches_laravel_structure(): void
    {
        $this->assertTableColumns('failed_jobs', [
            'id' => ['bigint unsigned', false],
            'uuid' => ['varchar(255)', false],
            'connection' => ['varchar(255)', false],
            'queue' => ['varchar(255)', false],
            'payload' => ['longtext', false],
            'exception' => ['longtext', false],
            'failed_at' => ['timestamp', false],
        ]);

        $this->assertIndex('failed_jobs', ['uuid'], unique: true);
        $this->assertIndex('failed_jobs', ['connection', 'queue', 'failed_at']);
    }
}
