<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Trait yang menghapus atau mengosongkan tabel sebelum test.
     */
    private const DESTRUCTIVE_DATABASE_TRAITS = [
        RefreshDatabase::class,
        DatabaseMigrations::class,
        DatabaseTruncation::class,
    ];

    /**
     * Pengaman dipasang di sini (bukan di hook beforeRefreshingDatabase) karena
     * method milik trait RefreshDatabase akan menimpa hook yang didefinisikan di kelas induk.
     */
    protected function setUpTraits()
    {
        $uses = class_uses_recursive(static::class);

        if (array_intersect(self::DESTRUCTIVE_DATABASE_TRAITS, $uses) !== []) {
            $this->ensureTestingDatabase();
        }

        return parent::setUpTraits();
    }

    private function ensureTestingDatabase(): void
    {
        $database = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Test dihentikan: database [{$database}] bukan database testing (*_testing). Periksa DB_DATABASE di phpunit.xml."
            );
        }
    }
}
