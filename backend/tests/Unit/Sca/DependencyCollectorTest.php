<?php

namespace Tests\Unit\Sca;

use App\Enums\DependencyRelation;
use App\Enums\DependencyScope;
use App\Enums\ManifestType;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;
use App\Services\Ingestion\RepositoryFileWalker;
use App\Services\Sca\CollectedDependencies;
use App\Services\Sca\DependencyCollector;
use App\Services\Sca\ManifestFinder;
use App\Services\Sca\ManifestProject;
use App\Services\Sca\PackageJsonParser;
use App\Services\Sca\PackageLockParser;
use App\Services\Sca\ParsedDependency;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DependencyCollectorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/collector-'.bin2hex(random_bytes(5));
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    private function write(string $path, array|string $content): void
    {
        $full = $this->root.'/'.$path;
        is_dir(dirname($full)) || mkdir(dirname($full), 0755, true);
        file_put_contents($full, is_string($content) ? $content : json_encode($content));
    }

    /**
     * @param  array<string, mixed>  $packages
     * @param  array<string, mixed>  $rootEntry
     */
    private function lock(string $dir, array $packages, array $rootEntry = [], int $version = 3): void
    {
        $this->write(($dir === '' ? '' : $dir.'/').'package-lock.json', [
            'lockfileVersion' => $version,
            'packages' => ['' => (object) $rootEntry] + $packages,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reg(string $name, string $version, array $extra = []): array
    {
        $bare = substr(strrchr('/'.$name, '/'), 1);

        return ['version' => $version, 'resolved' => "https://registry.npmjs.org/$name/-/$bare-$version.tgz"] + $extra;
    }

    private function collect(int $lockMax = 1_000_000): CollectedDependencies
    {
        return (new DependencyCollector(
            new ManifestFinder(new RepositoryFileWalker(['node_modules', 'vendor', '.git'])),
            new PackageJsonParser(1_000_000),
            new PackageLockParser($lockMax),
        ))->collect($this->root);
    }

    /**
     * @param  list<ParsedDependency>  $deps
     * @return list<string>
     */
    private function names(array $deps): array
    {
        return array_map(fn ($d) => $d->name, $deps);
    }

    /**
     * @param  list<ParsedDependency>  $deps
     */
    private function find(array $deps, string $name, ?string $manifestPath = null): ParsedDependency
    {
        foreach ($deps as $dep) {
            if ($dep->name === $name && ($manifestPath === null || $dep->manifestPath === $manifestPath)) {
                return $dep;
            }
        }

        $this->fail("dependensi $name tidak ditemukan");
    }

    public function test_no_manifest_means_no_projects_found(): void
    {
        $this->write('src/index.js', 'x');
        $this->write('package-lock.json', '{}');

        $result = $this->collect();

        $this->assertFalse($result->foundProjects);
        $this->assertSame([], $result->dependencies);
        $this->assertSame([], $result->failures);
    }

    public function test_package_json_only_classifies_exact_and_ranges(): void
    {
        $this->write('package.json', ['dependencies' => ['a' => '1.2.3', 'b' => '^2.0.0', 'c' => 'github:x/y'], 'devDependencies' => ['d' => '~1.0.0']]);

        $result = $this->collect();

        $this->assertTrue($result->foundProjects);
        $this->assertSame([], $result->failures);
        $this->assertSame(VersionStatus::Exact, $this->find($result->dependencies, 'a')->versionStatus);
        $this->assertSame(UnresolvedReason::RangeWithoutLockfile, $this->find($result->dependencies, 'b')->unresolvedReason);
        $this->assertSame(UnresolvedReason::NonRegistrySource, $this->find($result->dependencies, 'c')->unresolvedReason);
        $this->assertSame(DependencyScope::Development, $this->find($result->dependencies, 'd')->scope);
        $this->assertSame(ManifestType::PackageJson, $this->find($result->dependencies, 'a')->sourceFileType);
    }

    public function test_lockfile_is_preferred_and_includes_transitive(): void
    {
        $this->write('package.json', ['dependencies' => ['a' => '^1.0.0']]);
        $this->lock('', ['node_modules/a' => $this->reg('a', '1.2.0'), 'node_modules/b' => $this->reg('b', '2.0.0')], ['dependencies' => ['a' => '^1.0.0']]);

        $result = $this->collect();

        $this->assertCount(2, $result->dependencies);
        $this->assertSame([], $result->failures);

        foreach ($result->dependencies as $dep) {
            $this->assertSame(ManifestType::PackageLockV3, $dep->sourceFileType);
            $this->assertSame('package-lock.json', $dep->manifestPath);
        }

        $this->assertSame('1.2.0', $this->find($result->dependencies, 'a')->version);
        $this->assertSame(DependencyRelation::Transitive, $this->find($result->dependencies, 'b')->relation);
    }

    public function test_unsupported_lockfile_falls_back_with_unsupported_reason_for_ranges(): void
    {
        $this->write('package.json', ['dependencies' => ['a' => '^1', 'b' => '2.0.0', 'c' => 'github:x/y']]);
        $this->write('package-lock.json', ['lockfileVersion' => 1, 'dependencies' => ['a' => ['version' => '1.0.0']]]);

        $result = $this->collect();

        $this->assertSame([], $result->failures);
        $this->assertSame(UnresolvedReason::UnsupportedLockfile, $this->find($result->dependencies, 'a')->unresolvedReason);
        $this->assertSame(VersionStatus::Exact, $this->find($result->dependencies, 'b')->versionStatus);
        $this->assertSame(UnresolvedReason::NonRegistrySource, $this->find($result->dependencies, 'c')->unresolvedReason);
    }

    public function test_corrupt_lockfile_falls_back_and_records_failure(): void
    {
        $this->write('package.json', ['dependencies' => ['a' => '^1.0.0']]);
        $this->write('package-lock.json', '{"lockfileVersion":3,');

        $result = $this->collect();

        $this->assertCount(1, $result->failures);
        $this->assertSame('package-lock.json', $result->failures[0]->path);
        $this->assertSame(UnresolvedReason::LockfileUnreadable, $this->find($result->dependencies, 'a')->unresolvedReason);
        $this->assertSame(ManifestType::PackageJson, $this->find($result->dependencies, 'a')->sourceFileType);
    }

    public function test_unreadable_lockfile_changes_only_the_reason_of_ranges(): void
    {
        $this->write('package.json', ['dependencies' => ['a' => '^1.0.0', 'b' => '2.0.0', 'c' => 'github:x/y', 'd' => 'latest']]);
        $this->write('package-lock.json', '[');

        $deps = $this->collect()->dependencies;

        $this->assertSame(UnresolvedReason::LockfileUnreadable, $this->find($deps, 'a')->unresolvedReason);
        $this->assertSame(UnresolvedReason::LockfileUnreadable, $this->find($deps, 'd')->unresolvedReason);
        $this->assertSame(VersionStatus::Exact, $this->find($deps, 'b')->versionStatus);
        $this->assertSame(UnresolvedReason::NonRegistrySource, $this->find($deps, 'c')->unresolvedReason);
    }

    public function test_project_without_lockfile_still_uses_range_without_lockfile(): void
    {
        $this->write('package.json', ['dependencies' => ['a' => '^1.0.0']]);

        $this->assertSame(UnresolvedReason::RangeWithoutLockfile, $this->collect()->dependencies[0]->unresolvedReason);
    }

    public function test_oversized_lockfile_falls_back_and_records_failure(): void
    {
        $this->write('package.json', ['dependencies' => ['a' => '^1.0.0']]);
        $this->lock('', ['node_modules/a' => $this->reg('a', '1.0.0')]);

        $result = $this->collect(lockMax: 10);

        $this->assertCount(1, $result->failures);
        $this->assertSame(ManifestType::PackageJson, $this->find($result->dependencies, 'a')->sourceFileType);
        $this->assertSame(UnresolvedReason::LockfileUnreadable, $this->find($result->dependencies, 'a')->unresolvedReason);
    }

    public function test_broken_package_json_with_good_lockfile_uses_lockfile_and_records_failure(): void
    {
        $this->write('package.json', '{"dependencies":');
        $this->lock('', ['node_modules/a' => $this->reg('a', '1.0.0')], ['dependencies' => ['a' => '^1.0.0']]);

        $result = $this->collect();

        $this->assertSame(['a'], $this->names($result->dependencies));
        $this->assertSame('1.0.0', $result->dependencies[0]->version);
        $this->assertCount(1, $result->failures);
        $this->assertSame('package.json', $result->failures[0]->path);
    }

    public function test_both_broken_records_two_failures_and_no_dependencies(): void
    {
        $this->write('package.json', '{');
        $this->write('package-lock.json', '[');

        $result = $this->collect();

        $this->assertTrue($result->foundProjects);
        $this->assertSame([], $result->dependencies);
        $this->assertSame(['package-lock.json', 'package.json'], array_map(fn ($f) => $f->path, $result->failures));
    }

    public function test_monorepo_projects_keep_their_own_manifest_path(): void
    {
        foreach (['a', 'b'] as $dir) {
            $this->write("$dir/package.json", ['dependencies' => ['x' => '^1.0.0']]);
            $this->lock($dir, ['node_modules/x' => $this->reg('x', '1.0.0')], ['dependencies' => ['x' => '^1.0.0']]);
        }

        $result = $this->collect();

        $this->assertSame(['a/package-lock.json', 'b/package-lock.json'], array_map(fn ($d) => $d->manifestPath, $result->dependencies));
    }

    public function test_one_broken_project_does_not_stop_the_other(): void
    {
        $this->write('a/package.json', '{');
        $this->write('b/package.json', ['dependencies' => ['x' => '1.0.0']]);

        $result = $this->collect();

        $this->assertSame(['x'], $this->names($result->dependencies));
        $this->assertSame('a/package.json', $result->failures[0]->path);
    }

    public function test_direct_dependency_missing_from_lockfile_is_filled_from_package_json(): void
    {
        $this->write('package.json', ['dependencies' => ['x' => '^1.0.0', 'y' => '2.0.0', 'z' => '^3.0.0']]);
        $this->lock('', ['node_modules/x' => $this->reg('x', '1.0.0')], ['dependencies' => ['x' => '^1.0.0']]);

        $result = $this->collect();

        $this->assertSame(['x', 'y', 'z'], $this->names($result->dependencies));
        $this->assertSame(ManifestType::PackageLockV3, $this->find($result->dependencies, 'x')->sourceFileType);
        $y = $this->find($result->dependencies, 'y');
        $this->assertSame(ManifestType::PackageJson, $y->sourceFileType);
        $this->assertSame(VersionStatus::Exact, $y->versionStatus);
        $this->assertSame('package.json', $y->manifestPath);
        $this->assertSame(UnresolvedReason::RangeWithoutLockfile, $this->find($result->dependencies, 'z')->unresolvedReason);
    }

    public function test_package_present_only_nested_in_lockfile_is_still_filled(): void
    {
        $this->write('package.json', ['dependencies' => ['y' => '^2.0.0']]);
        $this->lock('', ['node_modules/z' => $this->reg('z', '1.0.0'), 'node_modules/z/node_modules/y' => $this->reg('y', '2.1.0')], ['dependencies' => ['z' => '^1.0.0']]);

        $result = $this->collect();

        $this->assertCount(3, $result->dependencies);
        $this->assertSame(ManifestType::PackageJson, $this->find($result->dependencies, 'y', 'package.json')->sourceFileType);
        $this->assertSame(UnresolvedReason::RangeWithoutLockfile, $this->find($result->dependencies, 'y', 'package.json')->unresolvedReason);
        $this->assertSame('2.1.0', $this->find($result->dependencies, 'y', 'package-lock.json')->version);
    }

    public function test_peer_dependency_missing_from_lockfile_is_not_filled(): void
    {
        $this->write('package.json', ['dependencies' => ['x' => '^1.0.0'], 'peerDependencies' => ['react' => '^18.0.0']]);
        $this->lock('', ['node_modules/x' => $this->reg('x', '1.0.0')], ['dependencies' => ['x' => '^1.0.0']]);

        $this->assertSame(['x'], $this->names($this->collect()->dependencies));
    }

    public function test_alias_present_at_top_level_in_lockfile_is_not_filled(): void
    {
        $this->write('package.json', ['dependencies' => ['alias' => 'npm:real@1.0.0']]);
        $this->lock('', ['node_modules/alias' => $this->reg('real', '1.0.0', ['name' => 'real'])], ['dependencies' => ['alias' => 'npm:real@1.0.0']]);

        $result = $this->collect();

        $this->assertCount(1, $result->dependencies);
        $this->assertSame(ManifestType::PackageLockV3, $result->dependencies[0]->sourceFileType);
    }

    public function test_package_in_dependencies_and_dev_dependencies_takes_production_scope(): void
    {
        $this->write('package.json', ['devDependencies' => ['a' => '1.0.0'], 'dependencies' => ['a' => '1.0.0'], 'peerDependencies' => ['a' => '1.0.0']]);

        $result = $this->collect();

        $this->assertCount(1, $result->dependencies);
        $this->assertSame(DependencyScope::Production, $result->dependencies[0]->scope);
    }

    public function test_scope_priority_is_production_then_optional_then_development_then_peer(): void
    {
        $this->write('package.json', [
            'devDependencies' => ['a' => '1.0.0', 'b' => '1.0.0'],
            'peerDependencies' => ['a' => '1.0.0', 'b' => '1.0.0', 'c' => '1.0.0'],
            'optionalDependencies' => ['b' => '1.0.0', 'c' => '1.0.0'],
        ]);

        $result = $this->collect();

        $this->assertSame(DependencyScope::Development, $this->find($result->dependencies, 'a')->scope);
        $this->assertSame(DependencyScope::Optional, $this->find($result->dependencies, 'b')->scope);
        $this->assertSame(DependencyScope::Optional, $this->find($result->dependencies, 'c')->scope);
    }

    public function test_different_versions_of_same_name_are_kept(): void
    {
        $this->write('package.json', ['dependencies' => ['a' => '1.0.0'], 'devDependencies' => ['a' => '2.0.0']]);

        $this->assertSame(['1.0.0', '2.0.0'], array_map(fn ($d) => $d->version, $this->collect()->dependencies));
    }

    public function test_ignores_node_modules_vendor_and_yarn_pnpm_lockfiles(): void
    {
        $this->write('package.json', ['dependencies' => ['a' => '^1.0.0']]);
        $this->write('node_modules/x/package.json', ['dependencies' => ['bad' => '1.0.0']]);
        $this->write('vendor/y/package.json', ['dependencies' => ['bad' => '1.0.0']]);
        $this->write('yarn.lock', 'x');
        $this->write('pnpm-lock.yaml', 'x');

        $result = $this->collect();

        $this->assertSame(['a'], $this->names($result->dependencies));
        $this->assertSame(UnresolvedReason::RangeWithoutLockfile, $result->dependencies[0]->unresolvedReason);
        $this->assertSame([], $result->failures);
    }

    public function test_overlong_or_invalid_utf8_manifest_path_is_skipped_and_recorded(): void
    {
        foreach ([str_repeat('a/', 600).'package.json', "app/\xFF/package.json"] as $path) {
            $finder = $this->createStub(ManifestFinder::class);
            $finder->method('find')->willReturn([new ManifestProject(dirname($path), $path, null)]);

            $result = (new DependencyCollector($finder, new PackageJsonParser(1000), new PackageLockParser(1000)))->collect($this->root);

            $this->assertTrue($result->foundProjects);
            $this->assertSame([], $result->dependencies);
            $this->assertCount(1, $result->failures);
            $this->assertLessThanOrEqual(300, mb_strlen($result->failures[0]->message));
        }
    }

    public function test_overlong_lockfile_path_is_skipped_and_recorded(): void
    {
        $finder = $this->createStub(ManifestFinder::class);
        $finder->method('find')->willReturn([new ManifestProject('d', 'd/package.json', str_repeat('a/', 600).'package-lock.json')]);

        $result = (new DependencyCollector($finder, new PackageJsonParser(1000), new PackageLockParser(1000)))->collect($this->root);

        $this->assertSame([], $result->dependencies);
        $this->assertCount(1, $result->failures);
    }

    public function test_failure_messages_contain_no_server_path(): void
    {
        $this->write('a/package.json', '{');
        $this->write('b/package.json', ['dependencies' => ['x' => '1.0.0']]);
        $this->write('b/package-lock.json', '[');

        foreach ($this->collect()->failures as $failure) {
            $this->assertStringNotContainsString($this->root, $failure->message);
            $this->assertStringNotContainsString(sys_get_temp_dir(), $failure->message);
        }
    }

    #[DataProvider('ranges')]
    public function test_ranges_are_never_exact(string $spec): void
    {
        $this->write('package.json', ['dependencies' => ['a' => $spec]]);
        $this->write('x/package.json', ['dependencies' => ['a' => $spec]]);
        $this->write('x/package-lock.json', ['lockfileVersion' => 1]);

        foreach ($this->collect()->dependencies as $dep) {
            $this->assertSame(VersionStatus::Unresolved, $dep->versionStatus);
            $this->assertNull($dep->version);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ranges(): array
    {
        return array_map(fn ($s) => [$s], ['^1.2.3', '~1.2.3', '*', 'latest', '>=1', '1.x', '']);
    }

    public function test_workspaces_covered_by_root_lockfile_do_not_duplicate_lockfile_entries(): void
    {
        $this->write('package.json', ['workspaces' => ['packages/*']]);
        $this->write('packages/web/package.json', ['dependencies' => ['lodash' => '^4.17.0', 'ms' => '2.0.0']]);
        $this->write('packages/api/package.json', ['devDependencies' => ['jest' => '^29.0.0']]);
        $this->lock('', [
            'node_modules/web' => ['resolved' => 'packages/web', 'link' => true],
            'node_modules/api' => ['resolved' => 'packages/api', 'link' => true],
            'packages/web' => ['name' => 'web', 'dependencies' => ['lodash' => '^4.17.0', 'ms' => '2.0.0']],
            'packages/api' => ['name' => 'api', 'devDependencies' => ['jest' => '^29.0.0']],
            'node_modules/lodash' => $this->reg('lodash', '4.17.21'),
            'node_modules/ms' => $this->reg('ms', '2.0.0'),
            'node_modules/jest' => $this->reg('jest', '29.7.0', ['dev' => true]),
        ], ['workspaces' => ['packages/*']]);

        $result = $this->collect();

        $this->assertSame([], $result->failures);

        foreach ($result->dependencies as $dep) {
            $this->assertSame('package-lock.json', $dep->manifestPath);
            $this->assertNotSame(UnresolvedReason::RangeWithoutLockfile, $dep->unresolvedReason);
        }

        $this->assertSame(['web', 'api', 'lodash', 'ms', 'jest'], $this->names($result->dependencies));
        $this->assertSame(DependencyRelation::Direct, $this->find($result->dependencies, 'lodash')->relation);
    }

    public function test_project_outside_lockfile_workspaces_is_still_processed(): void
    {
        $this->write('package.json', ['workspaces' => ['packages/*']]);
        $this->write('packages/web/package.json', ['dependencies' => ['lodash' => '^4.17.0']]);
        $this->write('examples/demo/package.json', ['dependencies' => ['left-pad' => '^1.0.0']]);
        $this->lock('', [
            'packages/web' => ['name' => 'web', 'dependencies' => ['lodash' => '^4.17.0']],
            'node_modules/lodash' => $this->reg('lodash', '4.17.21'),
        ]);

        $result = $this->collect();

        $this->assertSame(['lodash', 'left-pad'], $this->names($result->dependencies));
        $this->assertSame('examples/demo/package.json', $this->find($result->dependencies, 'left-pad')->manifestPath);
        $this->assertSame(UnresolvedReason::RangeWithoutLockfile, $this->find($result->dependencies, 'left-pad')->unresolvedReason);
    }

    public function test_workspace_with_its_own_lockfile_is_its_own_project(): void
    {
        $this->write('package.json', ['dependencies' => ['a' => '1.0.0']]);
        $this->lock('', ['packages/web' => ['name' => 'web'], 'node_modules/a' => $this->reg('a', '1.0.0')], ['dependencies' => ['a' => '1.0.0']]);
        $this->write('packages/web/package.json', ['dependencies' => ['b' => '2.0.0']]);
        $this->lock('packages/web', ['node_modules/b' => $this->reg('b', '2.0.0')], ['dependencies' => ['b' => '2.0.0']]);

        $result = $this->collect();

        $this->assertSame(['a', 'b'], $this->names($result->dependencies));
        $this->assertSame('packages/web/package-lock.json', $this->find($result->dependencies, 'b')->manifestPath);
    }

    public function test_workspace_is_processed_normally_when_root_lockfile_is_unusable(): void
    {
        $this->write('package.json', ['workspaces' => ['packages/*']]);
        $this->write('package-lock.json', '{');
        $this->write('packages/web/package.json', ['dependencies' => ['lodash' => '^4.17.0']]);

        $result = $this->collect();

        $this->assertSame(['lodash'], $this->names($result->dependencies));
        $this->assertSame('packages/web/package.json', $result->dependencies[0]->manifestPath);
        $this->assertCount(1, $result->failures);
    }

    public function test_nested_project_under_a_subdirectory_lockfile_uses_relative_workspace_paths(): void
    {
        $this->write('app/package.json', ['workspaces' => ['packages/*']]);
        $this->write('app/packages/web/package.json', ['dependencies' => ['lodash' => '^4.17.0']]);
        $this->lock('app', ['packages/web' => ['name' => 'web'], 'node_modules/lodash' => $this->reg('lodash', '4.17.21')]);

        $result = $this->collect();

        $this->assertSame(['lodash'], $this->names($result->dependencies));
        $this->assertSame('app/package-lock.json', $result->dependencies[0]->manifestPath);
    }

    public function test_very_long_declared_spec_is_cut_on_a_character_boundary(): void
    {
        $spec = 'git+https://example.test/'.str_repeat('€', 400);
        $this->write('package.json', ['dependencies' => ['a' => $spec]]);
        $this->write('x/package.json', ['dependencies' => ['a' => 'a/b#'.str_repeat('€', 400)]]);

        foreach ($this->collect()->dependencies as $dep) {
            $this->assertLessThanOrEqual(1024, strlen($dep->declaredSpec));
            $this->assertTrue(mb_check_encoding($dep->declaredSpec, 'UTF-8'));
            $this->assertSame(UnresolvedReason::NonRegistrySource, $dep->unresolvedReason);
        }
    }

    public function test_version_longer_than_column_is_marked_unresolved(): void
    {
        $long = '1.0.0-'.str_repeat('a', 120);
        $this->write('a/package.json', ['dependencies' => ['x' => $long]]);
        $this->write('b/package.json', ['dependencies' => ['y' => '^1']]);
        $this->lock('b', ['node_modules/y' => $this->reg('y', $long)], ['dependencies' => ['y' => '^1']]);

        $result = $this->collect();

        foreach ($result->dependencies as $dep) {
            $this->assertSame(VersionStatus::Unresolved, $dep->versionStatus);
            $this->assertNull($dep->version);
            $this->assertSame(UnresolvedReason::MissingVersion, $dep->unresolvedReason);
        }

        $this->assertCount(2, $result->dependencies);
    }

    public function test_version_at_column_limit_stays_exact(): void
    {
        $version = '1.0.0-'.str_repeat('a', 94);
        $this->write('package.json', ['dependencies' => ['x' => $version]]);

        $this->assertSame(100, strlen($version));
        $this->assertSame($version, $this->collect()->dependencies[0]->version);
    }
}
