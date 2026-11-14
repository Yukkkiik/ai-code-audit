<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UsersTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_exactly_the_approved_columns(): void
    {
        $this->assertEqualsCanonicalizing(
            ['id', 'google_id', 'name', 'email', 'avatar_url', 'last_login_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('users'),
        );
    }

    public function test_users_columns_have_approved_types_and_nullability(): void
    {
        $columns = collect(Schema::getColumns('users'))->keyBy('name');

        $this->assertSame('bigint unsigned', $columns['id']['type']);
        $this->assertSame('varchar(64)', $columns['google_id']['type']);
        $this->assertSame('varchar(255)', $columns['name']['type']);
        $this->assertSame('varchar(255)', $columns['email']['type']);
        $this->assertSame('varchar(2048)', $columns['avatar_url']['type']);
        $this->assertSame('timestamp', $columns['last_login_at']['type']);

        $this->assertFalse($columns['google_id']['nullable']);
        $this->assertFalse($columns['name']['nullable']);
        $this->assertFalse($columns['email']['nullable']);
        $this->assertTrue($columns['avatar_url']['nullable']);
        $this->assertTrue($columns['last_login_at']['nullable']);
    }

    public function test_google_id_and_email_have_unique_indexes(): void
    {
        $uniqueColumns = collect(Schema::getIndexes('users'))
            ->filter(fn (array $index) => $index['unique'] && ! $index['primary'])
            ->pluck('columns')
            ->all();

        $this->assertEqualsCanonicalizing([['google_id'], ['email']], $uniqueColumns);
    }

    public function test_duplicate_google_id_is_rejected(): void
    {
        User::factory()->create(['google_id' => '109876543210987654321']);

        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()->create(['google_id' => '109876543210987654321']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'mahasiswa@example.com']);

        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()->create(['email' => 'mahasiswa@example.com']);
    }

    public function test_factory_creates_valid_user_without_exposing_google_id(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->google_id);
        $this->assertNull($user->last_login_at);
        $this->assertArrayNotHasKey('google_id', $user->toArray());
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_only_approved_tables_exist_after_migration(): void
    {
        $database = DB::connection()->getDatabaseName();

        $tables = collect(Schema::getTables())
            ->where('schema', $database)
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing(['migrations', 'users'], $tables);
    }
}
