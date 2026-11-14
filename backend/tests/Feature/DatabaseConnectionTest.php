<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_default_connection_is_mysql_and_reachable(): void
    {
        $this->assertSame('mysql', DB::getDefaultConnection());

        $this->assertSame(1, (int) DB::selectOne('SELECT 1 AS ok')->ok);
    }

    /**
     * Pengaman: test (terutama RefreshDatabase nanti) tidak boleh
     * menyentuh database development.
     */
    public function test_tests_run_against_dedicated_testing_database(): void
    {
        $database = DB::connection()->getDatabaseName();

        $this->assertStringEndsWith('_testing', $database);
        $this->assertSame($database, DB::selectOne('SELECT DATABASE() AS name')->name);
    }

    public function test_database_uses_utf8mb4_unicode_collation(): void
    {
        $schema = DB::selectOne(
            'SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
             FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()'
        );

        $this->assertSame('utf8mb4', $schema->charset);
        $this->assertSame('utf8mb4_unicode_ci', $schema->collation);
    }

    /**
     * CHECK constraint (dipakai ai_refactorings) baru ditegakkan sejak MySQL 8.0.16.
     */
    public function test_mysql_version_supports_check_constraints(): void
    {
        $version = DB::selectOne('SELECT VERSION() AS version')->version;

        $this->assertTrue(
            version_compare(strtok($version, '-'), '8.0.16', '>='),
            "MySQL {$version} tidak menegakkan CHECK constraint."
        );
    }
}
