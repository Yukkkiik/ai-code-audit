<?php

namespace Tests\Feature\Sca;

use App\Enums\DependencyRelation;
use App\Enums\DependencyScope;
use App\Enums\ManifestType;
use App\Enums\OsvQueryStatus;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;
use App\Models\AuditScan;
use App\Models\Dependency;
use App\Models\Vulnerability;
use App\Services\Sca\DependencyStore;
use App\Services\Sca\ParsedDependency;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DependencyStoreTest extends TestCase
{
    use RefreshDatabase;

    private function dep(string $name, array $override = []): ParsedDependency
    {
        return new ParsedDependency(...($override + [
            'name' => $name,
            'declaredSpec' => '^1.0.0',
            'scope' => DependencyScope::Production,
            'versionStatus' => VersionStatus::Exact,
            'version' => '1.2.3',
            'unresolvedReason' => null,
            'manifestPath' => 'package-lock.json',
            'sourceFileType' => ManifestType::PackageLockV3,
            'relation' => DependencyRelation::Direct,
            'lockPath' => 'node_modules/'.$name,
        ]));
    }

    private function store(AuditScan $audit, array $deps): int
    {
        return $this->app->make(DependencyStore::class)->store($audit, $deps);
    }

    public function test_stores_all_columns_with_enum_values_and_defaults(): void
    {
        $audit = AuditScan::factory()->create();

        $this->assertSame(1, $this->store($audit, [$this->dep('express')]));

        $row = Dependency::query()->where('audit_scan_id', $audit->id)->sole();
        $this->assertSame('express', $row->name);
        $this->assertSame('1.2.3', $row->version);
        $this->assertSame('^1.0.0', $row->declared_spec);
        $this->assertSame(DependencyScope::Production, $row->scope);
        $this->assertSame(DependencyRelation::Direct, $row->relation);
        $this->assertSame('package-lock.json', $row->manifest_path);
        $this->assertSame(ManifestType::PackageLockV3, $row->source_file_type);
        $this->assertSame('node_modules/express', $row->lock_path);
        $this->assertSame(VersionStatus::Exact, $row->version_status);
        $this->assertNull($row->unresolved_reason);
        $this->assertSame(OsvQueryStatus::NotQueried, $row->osv_status);
        $this->assertSame('npm', $row->ecosystem->value);
        $this->assertNotNull($row->created_at);
        $this->assertNotNull($row->updated_at);
    }

    public function test_stores_unresolved_rows_with_null_version_and_reason(): void
    {
        $audit = AuditScan::factory()->create();

        $this->store($audit, [$this->dep('a', [
            'versionStatus' => VersionStatus::Unresolved, 'version' => null,
            'unresolvedReason' => UnresolvedReason::RangeWithoutLockfile,
            'manifestPath' => 'package.json', 'sourceFileType' => ManifestType::PackageJson, 'lockPath' => null,
        ])]);

        $row = Dependency::query()->sole();
        $this->assertSame(VersionStatus::Unresolved, $row->version_status);
        $this->assertNull($row->version);
        $this->assertSame(UnresolvedReason::RangeWithoutLockfile, $row->unresolved_reason);
        $this->assertSame(OsvQueryStatus::NotQueried, $row->osv_status);
    }

    public function test_null_spec_and_lock_path_are_stored_as_null(): void
    {
        $audit = AuditScan::factory()->create();

        $this->store($audit, [$this->dep('a', ['declaredSpec' => null, 'lockPath' => null])]);

        $row = Dependency::query()->sole();
        $this->assertNull($row->declared_spec);
        $this->assertNull($row->lock_path);
    }

    public function test_stores_more_than_one_chunk(): void
    {
        $audit = AuditScan::factory()->create();

        $count = $this->store($audit, array_map(fn ($i) => $this->dep("pkg-$i"), range(1, 1200)));

        $this->assertSame(1200, $count);
        $this->assertSame(1200, Dependency::query()->where('audit_scan_id', $audit->id)->count());
    }

    public function test_empty_list_stores_nothing(): void
    {
        $audit = AuditScan::factory()->create();

        $this->assertSame(0, $this->store($audit, []));
        $this->assertSame(0, Dependency::query()->count());
    }

    public function test_rolls_back_everything_when_one_row_is_invalid(): void
    {
        $audit = AuditScan::factory()->create();
        Dependency::factory()->count(2)->create(['audit_scan_id' => $audit->id]);

        try {
            $this->store($audit, [$this->dep('ok'), $this->dep(str_repeat('a', 300))]);
            $this->fail('QueryException diharapkan');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(2, Dependency::query()->where('audit_scan_id', $audit->id)->count());
        $this->assertSame(0, Dependency::query()->where('name', 'ok')->count());
    }

    public function test_store_twice_for_same_audit_does_not_duplicate(): void
    {
        $audit = AuditScan::factory()->create();
        $deps = [$this->dep('a'), $this->dep('b')];

        $this->store($audit, $deps);
        $this->store($audit, $deps);

        $this->assertSame(2, Dependency::query()->where('audit_scan_id', $audit->id)->count());
    }

    public function test_store_replaces_previous_rows_for_the_audit(): void
    {
        $audit = AuditScan::factory()->create();
        $this->store($audit, [$this->dep('old')]);

        $this->store($audit, [$this->dep('new')]);

        $this->assertSame(['new'], Dependency::query()->where('audit_scan_id', $audit->id)->pluck('name')->all());
    }

    public function test_does_not_touch_other_audits(): void
    {
        $audit = AuditScan::factory()->create();
        $other = AuditScan::factory()->create();
        Dependency::factory()->count(3)->create(['audit_scan_id' => $other->id]);

        $this->store($audit, [$this->dep('a')]);
        $this->store($audit, []);

        $this->assertSame(3, Dependency::query()->where('audit_scan_id', $other->id)->count());
        $this->assertSame(0, Dependency::query()->where('audit_scan_id', $audit->id)->count());
    }

    public function test_replacing_rows_also_removes_their_vulnerabilities_by_cascade(): void
    {
        $audit = AuditScan::factory()->create();
        $dependency = Dependency::factory()->create(['audit_scan_id' => $audit->id]);
        Vulnerability::factory()->create(['audit_scan_id' => $audit->id, 'dependency_id' => $dependency->id]);

        $this->store($audit, [$this->dep('a')]);

        $this->assertSame(0, Vulnerability::query()->count());
    }

    public function test_special_characters_in_names_and_specs_are_stored_safely(): void
    {
        $audit = AuditScan::factory()->create();
        $name = "x'; DROP TABLE dependencies; --";

        $this->store($audit, [$this->dep($name, ['declaredSpec' => "git+https://x/y.git#\"\\'é€"])]);

        $row = Dependency::query()->sole();
        $this->assertSame($name, $row->name);
        $this->assertSame("git+https://x/y.git#\"\\'é€", $row->declared_spec);
    }

    public function test_values_at_column_limits_are_accepted(): void
    {
        $audit = AuditScan::factory()->create();

        $this->store($audit, [$this->dep(str_repeat('a', 214), [
            'version' => '1.0.0-'.str_repeat('a', 94),
            'declaredSpec' => str_repeat('a', 1024),
            'manifestPath' => str_repeat('a', 1024),
            'lockPath' => str_repeat('a', 1024),
        ])]);

        $this->assertSame(1, Dependency::query()->count());
    }
}
