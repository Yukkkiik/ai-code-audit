<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Helper untuk menguji struktur tabel terhadap DATABASE.md dan
 * menyisipkan baris induk minimal tanpa bergantung pada model Eloquent.
 */
trait InteractsWithSchema
{
    /**
     * @param  array<string, array{0: string, 1: bool}>  $expected  kolom => [tipe MySQL, nullable]
     */
    protected function assertTableColumns(string $table, array $expected): void
    {
        $columns = collect(Schema::getColumns($table))->keyBy('name');

        $this->assertEqualsCanonicalizing(
            array_keys($expected),
            $columns->keys()->all(),
            "Kolom tabel {$table} tidak sesuai DATABASE.md.",
        );

        foreach ($expected as $name => [$type, $nullable]) {
            $this->assertSame($type, $columns[$name]['type'], "Tipe kolom {$table}.{$name}");
            $this->assertSame($nullable, $columns[$name]['nullable'], "Nullable kolom {$table}.{$name}");
        }
    }

    protected function assertColumnDefault(string $table, string $column, string $expected): void
    {
        $default = collect(Schema::getColumns($table))->firstWhere('name', $column)['default'];

        $this->assertSame($expected, trim((string) $default, "'"), "Default kolom {$table}.{$column}");
    }

    /**
     * @param  list<string>  $columns
     */
    protected function assertIndex(string $table, array $columns, bool $unique = false): void
    {
        $found = collect(Schema::getIndexes($table))->contains(
            fn (array $index) => $index['columns'] === $columns
                && $index['unique'] === $unique
                && ! $index['primary'],
        );

        $label = $unique ? 'UNIQUE' : 'INDEX';
        $this->assertTrue($found, "{$label}(".implode(', ', $columns).") tidak ditemukan di tabel {$table}.");
    }

    protected function assertForeignKey(string $table, string $column, string $foreignTable, string $onDelete): void
    {
        $foreignKey = collect(Schema::getForeignKeys($table))
            ->first(fn (array $fk) => $fk['columns'] === [$column]);

        $this->assertNotNull($foreignKey, "Foreign key {$table}.{$column} tidak ditemukan.");
        $this->assertSame($foreignTable, $foreignKey['foreign_table']);
        $this->assertSame(['id'], $foreignKey['foreign_columns']);
        $this->assertSame($onDelete, $foreignKey['on_delete'], "ON DELETE {$table}.{$column}");
    }

    protected function assertCheckConstraint(string $table, string $name): void
    {
        $constraint = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->first();

        $this->assertNotNull($constraint, "CHECK constraint {$name} tidak ditemukan di tabel {$table}.");
        $this->assertSame('YES', $constraint->ENFORCED, "CHECK constraint {$name} tidak ditegakkan.");
    }

    protected function insertUser(array $overrides = []): int
    {
        return DB::table('users')->insertGetId([
            'google_id' => (string) random_int(10 ** 15, 10 ** 16),
            'name' => 'Pengguna Uji',
            'email' => Str::uuid().'@example.com',
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }

    protected function insertAuditScan(array $overrides = []): int
    {
        return DB::table('audit_scans')->insertGetId([
            'public_id' => strtolower((string) Str::ulid()),
            'guest_token_hash' => hash('sha256', Str::random(40)),
            'source_type' => 'zip',
            'repository_name' => 'contoh-repo',
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }
}
