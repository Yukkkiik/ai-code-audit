<?php

namespace Tests\Unit\Sca;

use App\Enums\DependencyRelation;
use App\Enums\DependencyScope;
use App\Enums\ManifestType;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;
use App\Exceptions\ManifestParseException;
use App\Services\Sca\ExactVersion;
use App\Services\Sca\PackageLockParser;
use App\Services\Sca\ParsedDependency;
use App\Services\Sca\ParsedLockfile;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PackageLockParserTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/pkglock-'.bin2hex(random_bytes(5));
        mkdir($this->root.'/app', 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    private function parseRaw(string $json, int $maxBytes = 1_000_000): ParsedLockfile
    {
        file_put_contents($this->root.'/app/package-lock.json', $json);

        return (new PackageLockParser($maxBytes))->parse($this->root, 'app/package-lock.json');
    }

    private function fixture(string $name): ParsedLockfile
    {
        return $this->parseRaw(file_get_contents(__DIR__.'/../../Fixtures/Sca/'.$name));
    }

    /**
     * @param  array<string, mixed>  $packages
     * @param  array<string, mixed>  $root  isi entri root ""
     * @return list<ParsedDependency>
     */
    private function deps(array $packages, array $root = [], int $version = 3): array
    {
        $result = $this->parseRaw(json_encode([
            'lockfileVersion' => $version,
            'packages' => ['' => (object) $root] + $packages,
        ]));

        $this->assertTrue($result->supported);

        return $result->dependencies;
    }

    /**
     * @return array<string, mixed>
     */
    private function reg(string $name, string $version, array $extra = []): array
    {
        $bare = substr(strrchr('/'.$name, '/'), 1);

        return ['version' => $version, 'resolved' => "https://registry.npmjs.org/$name/-/$bare-$version.tgz"] + $extra;
    }

    /**
     * @param  list<ParsedDependency>  $deps
     */
    private function byPath(array $deps, string $lockPath): ParsedDependency
    {
        foreach ($deps as $dep) {
            if ($dep->lockPath === $lockPath) {
                return $dep;
            }
        }

        $this->fail("lock_path $lockPath tidak ditemukan");
    }

    /**
     * @param  list<ParsedDependency>  $deps
     * @return array<string, int>
     */
    private function tally(array $deps): array
    {
        $c = ['direct' => 0, 'transitive' => 0, 'development' => 0, 'optional' => 0, 'production' => 0, 'peer' => 0];

        foreach ($deps as $d) {
            $c[$d->relation->value]++;
            $c[$d->scope->value]++;
        }

        return $c;
    }

    public function test_v3_fixture_counts_direct_transitive_and_dev_correctly(): void
    {
        $result = $this->fixture('package-lock-v3.json');

        $this->assertTrue($result->supported);
        $this->assertSame(ManifestType::PackageLockV3, $result->type);
        $this->assertCount(9, $result->dependencies);
        $this->assertSame(
            ['direct' => 4, 'transitive' => 5, 'development' => 3, 'optional' => 1, 'production' => 5, 'peer' => 0],
            $this->tally($result->dependencies),
        );

        foreach ($result->dependencies as $dep) {
            $this->assertSame(VersionStatus::Exact, $dep->versionStatus);
            $this->assertSame(ManifestType::PackageLockV3, $dep->sourceFileType);
            $this->assertSame('app/package-lock.json', $dep->manifestPath);
        }
    }

    public function test_real_npm_lockfile_parses_without_marking_non_semver_as_exact(): void
    {
        $result = $this->fixture('package-lock-real.json');

        $this->assertTrue($result->supported);
        $this->assertSame(ManifestType::PackageLockV3, $result->type);
        $this->assertGreaterThan(0, count($result->dependencies));

        foreach ($result->dependencies as $dep) {
            if ($dep->versionStatus === VersionStatus::Exact) {
                $this->assertSame($dep->version, ExactVersion::parse((string) $dep->version), $dep->lockPath);
            } else {
                $this->assertNull($dep->version, $dep->lockPath);
            }
        }
    }

    public function test_v2_fixture_is_parsed_from_packages_and_ignores_legacy_dependencies(): void
    {
        $result = $this->fixture('package-lock-v2.json');

        $this->assertSame(ManifestType::PackageLockV2, $result->type);
        $this->assertCount(9, $result->dependencies);
        $this->assertNotContains('legacy-only', array_map(fn ($d) => $d->name, $result->dependencies));
        $this->assertSame(ManifestType::PackageLockV2, $result->dependencies[0]->sourceFileType);
    }

    public function test_root_entry_is_not_a_dependency(): void
    {
        $this->assertSame([], $this->deps([], ['name' => 'app', 'version' => '1.0.0']));
    }

    public function test_nested_node_modules_paths_are_separate_entries_with_lock_path(): void
    {
        $deps = $this->deps([
            'node_modules/qs' => $this->reg('qs', '6.11.0'),
            'node_modules/a/node_modules/qs' => $this->reg('qs', '6.5.0'),
        ]);

        $this->assertSame(['node_modules/qs', 'node_modules/a/node_modules/qs'], array_map(fn ($d) => $d->lockPath, $deps));
        $this->assertSame('6.5.0', $this->byPath($deps, 'node_modules/a/node_modules/qs')->version);
        $this->assertSame(DependencyRelation::Transitive, $this->byPath($deps, 'node_modules/a/node_modules/qs')->relation);
    }

    public function test_scoped_package_name_is_extracted(): void
    {
        $deps = $this->deps([
            'node_modules/@scope/pkg' => $this->reg('@scope/pkg', '1.0.0'),
            'node_modules/x/node_modules/@scope/inner' => $this->reg('@scope/inner', '2.0.0'),
        ]);

        $this->assertSame('@scope/pkg', $deps[0]->name);
        $this->assertSame('@scope/inner', $deps[1]->name);
    }

    public function test_name_field_overrides_key_for_alias_but_direct_match_uses_key(): void
    {
        $deps = $this->deps(
            ['node_modules/alias' => $this->reg('real-pkg', '1.2.3', ['name' => 'real-pkg'])],
            ['dependencies' => ['alias' => 'npm:real-pkg@1.2.3']],
        );

        $this->assertSame('real-pkg', $deps[0]->name);
        $this->assertSame(DependencyRelation::Direct, $deps[0]->relation);
        $this->assertSame(VersionStatus::Exact, $deps[0]->versionStatus);
    }

    #[DataProvider('rootSections')]
    public function test_direct_is_decided_by_root_dependency_sections(string $section, DependencyScope $scope, array $flags): void
    {
        $deps = $this->deps(
            ['node_modules/x' => $this->reg('x', '1.0.0', $flags), 'node_modules/y' => $this->reg('y', '1.0.0', $flags)],
            [$section => ['x' => '^1.0.0']],
        );

        $this->assertSame(DependencyRelation::Direct, $deps[0]->relation);
        $this->assertSame(DependencyRelation::Transitive, $deps[1]->relation);
        $this->assertSame($scope, $deps[0]->scope);
    }

    /**
     * @return array<string, array{string, DependencyScope, array<string, bool>}>
     */
    public static function rootSections(): array
    {
        return [
            'dependencies' => ['dependencies', DependencyScope::Production, []],
            'devDependencies' => ['devDependencies', DependencyScope::Development, ['dev' => true]],
            'optionalDependencies' => ['optionalDependencies', DependencyScope::Optional, ['optional' => true]],
            'peerDependencies' => ['peerDependencies', DependencyScope::Peer, ['peer' => true]],
        ];
    }

    public function test_root_with_only_peer_dependencies_marks_them_direct(): void
    {
        $deps = $this->deps(['node_modules/react' => $this->reg('react', '18.2.0', ['peer' => true])], ['peerDependencies' => ['react' => '^18.0.0']]);

        $this->assertSame(DependencyRelation::Direct, $deps[0]->relation);
        $this->assertSame(DependencyScope::Peer, $deps[0]->scope);
        $this->assertSame('^18.0.0', $deps[0]->declaredSpec);
    }

    #[DataProvider('scopeFlags')]
    public function test_scope_mapping_follows_flag_order(array $flags, DependencyScope $scope): void
    {
        $this->assertSame($scope, $this->deps(['node_modules/x' => $this->reg('x', '1.0.0', $flags)])[0]->scope);
    }

    /**
     * @return array<string, array{array<string, bool>, DependencyScope}>
     */
    public static function scopeFlags(): array
    {
        return [
            'none' => [[], DependencyScope::Production],
            'dev' => [['dev' => true], DependencyScope::Development],
            'optional' => [['optional' => true], DependencyScope::Optional],
            'devOptional' => [['devOptional' => true], DependencyScope::Development],
            'peer' => [['peer' => true], DependencyScope::Peer],
            'dev and optional -> dev wins' => [['dev' => true, 'optional' => true], DependencyScope::Development],
            'optional and peer -> optional wins' => [['optional' => true, 'peer' => true], DependencyScope::Optional],
            'false flags' => [['dev' => false, 'optional' => false], DependencyScope::Production],
        ];
    }

    public function test_workspace_dependencies_count_as_direct(): void
    {
        $result = $this->deps([
            'node_modules/web' => ['resolved' => 'packages/web', 'link' => true],
            'packages/web' => ['name' => 'web', 'version' => '1.0.0', 'dependencies' => ['lodash' => '^4.17.0']],
            'packages/web/node_modules/zod' => $this->reg('zod', '3.0.0'),
            'node_modules/lodash' => $this->reg('lodash', '4.17.21'),
            'node_modules/ms' => $this->reg('ms', '2.0.0'),
        ], ['workspaces' => ['packages/*']]);

        $this->assertSame(DependencyRelation::Direct, $this->byPath($result, 'node_modules/lodash')->relation);
        $this->assertSame('^4.17.0', $this->byPath($result, 'node_modules/lodash')->declaredSpec);
        $this->assertSame(DependencyRelation::Transitive, $this->byPath($result, 'node_modules/ms')->relation);
        $this->assertNull($this->byPath($result, 'node_modules/ms')->declaredSpec);
        $this->assertNotContains('packages/web', array_map(fn ($d) => $d->lockPath, $result));
    }

    public function test_key_with_node_modules_only_as_substring_is_skipped_as_project_code(): void
    {
        $deps = $this->deps([
            'packages/my_node_modules/x' => ['version' => '1.0.0'],
            'node_modules/real' => $this->reg('real', '1.0.0'),
        ]);

        $this->assertSame(['node_modules/real'], array_map(fn ($d) => $d->lockPath, $deps));
    }

    public function test_root_spec_wins_over_workspace_spec_for_declared_spec(): void
    {
        $deps = $this->deps([
            'packages/web' => ['dependencies' => ['lodash' => '^3.0.0']],
            'node_modules/lodash' => $this->reg('lodash', '4.17.21'),
        ], ['dependencies' => ['lodash' => '^4.0.0']]);

        $this->assertSame('^4.0.0', $deps[0]->declaredSpec);
    }

    public function test_link_entry_is_non_registry_source(): void
    {
        $dep = $this->deps(['node_modules/web' => ['resolved' => 'packages/web', 'link' => true]])[0];

        $this->assertSame(VersionStatus::Unresolved, $dep->versionStatus);
        $this->assertSame(UnresolvedReason::NonRegistrySource, $dep->unresolvedReason);
        $this->assertNull($dep->version);
    }

    #[DataProvider('foreignResolved')]
    public function test_git_file_and_foreign_sources_are_non_registry_source(string $resolved): void
    {
        $dep = $this->deps(['node_modules/x' => ['version' => '1.0.0', 'resolved' => $resolved]])[0];

        $this->assertSame(VersionStatus::Unresolved, $dep->versionStatus);
        $this->assertSame(UnresolvedReason::NonRegistrySource, $dep->unresolvedReason);
        $this->assertNull($dep->version);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function foreignResolved(): array
    {
        return array_map(fn ($s) => [$s], [
            'git+ssh' => 'git+ssh://git@github.com/a/x.git#abc123', 'git+https' => 'git+https://github.com/a/x.git#abc',
            'git:' => 'git://github.com/a/x.git', 'github:' => 'github:a/x#abc', 'file:' => 'file:../x',
            'arbitrary tarball' => 'https://example.test/downloads/x.tgz',
            'tarball without registry layout' => 'https://example.test/x/x-1.0.0.tgz',
            'name in url differs' => 'https://registry.npmjs.org/other/-/other-1.0.0.tgz',
            'file name wrong version' => 'https://registry.npmjs.org/x/-/x-9.9.9.tgz',
            'file name wrong package' => 'https://registry.npmjs.org/x/-/y-1.0.0.tgz',
            'relative path' => 'packages/x',
        ]);
    }

    public function test_scoped_tarball_with_matching_name_and_version_is_exact(): void
    {
        $dep = $this->deps(['node_modules/@s/p' => $this->reg('@s/p', '1.0.0')])[0];

        $this->assertSame(VersionStatus::Exact, $dep->versionStatus);
        $this->assertSame('1.0.0', $dep->version);
    }

    public function test_scoped_tarball_with_wrong_scope_is_non_registry(): void
    {
        $dep = $this->deps(['node_modules/@s/p' => ['version' => '1.0.0', 'resolved' => 'https://registry.npmjs.org/@t/p/-/p-1.0.0.tgz']])[0];

        $this->assertSame(UnresolvedReason::NonRegistrySource, $dep->unresolvedReason);
    }

    public function test_private_registry_with_standard_layout_is_exact(): void
    {
        $dep = $this->deps(['node_modules/x' => ['version' => '1.0.0', 'resolved' => 'https://npm.corp.test/repo/x/-/x-1.0.0.tgz']])[0];

        $this->assertSame(VersionStatus::Exact, $dep->versionStatus);
    }

    public function test_alias_tarball_must_match_real_package_name(): void
    {
        $ok = $this->deps(['node_modules/alias' => $this->reg('real', '1.0.0', ['name' => 'real'])])[0];
        $bad = $this->deps(['node_modules/alias' => $this->reg('alias', '1.0.0', ['name' => 'real'])])[0];

        $this->assertSame(VersionStatus::Exact, $ok->versionStatus);
        $this->assertSame(UnresolvedReason::NonRegistrySource, $bad->unresolvedReason);
    }

    public function test_bundled_dependency_without_resolved_is_exact(): void
    {
        $dep = $this->deps(['node_modules/a/node_modules/b' => ['version' => '1.0.0', 'inBundle' => true]])[0];

        $this->assertSame(VersionStatus::Exact, $dep->versionStatus);
        $this->assertSame('1.0.0', $dep->version);
    }

    #[DataProvider('badVersions')]
    public function test_missing_or_invalid_version_is_missing_version(array $entry): void
    {
        $dep = $this->deps(['node_modules/x' => $entry + ['resolved' => 'https://registry.npmjs.org/x/-/x-1.0.0.tgz']])[0];

        $this->assertSame(VersionStatus::Unresolved, $dep->versionStatus);
        $this->assertSame(UnresolvedReason::MissingVersion, $dep->unresolvedReason);
        $this->assertNull($dep->version);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function badVersions(): array
    {
        return [
            'absent' => [[]], 'null' => [['version' => null]], 'int' => [['version' => 1]], 'empty' => [['version' => '']],
            'range' => [['version' => '^1.0.0']], 'tag' => [['version' => 'latest']], 'partial' => [['version' => '1.0']],
            'url' => [['version' => 'file:../x']], 'garbage' => [['version' => 'abc']],
        ];
    }

    public function test_never_marks_non_semver_as_exact(): void
    {
        foreach (['^1.0.0', '~1.0.0', '*', 'latest', '1.x', '1', '>=1.0.0', 'git+https://x/y.git', '01.2.3'] as $version) {
            $deps = $this->deps(['node_modules/x' => ['version' => $version, 'resolved' => 'https://registry.npmjs.org/x/-/x-1.0.0.tgz']]);

            $this->assertNotSame(VersionStatus::Exact, $deps[0]->versionStatus, $version);
        }
    }

    public function test_workspace_source_directories_are_skipped(): void
    {
        $deps = $this->deps(['packages/web' => ['version' => '1.0.0'], 'packages/api' => ['version' => '1.0.0']]);

        $this->assertSame([], $deps);
    }

    #[DataProvider('unsupportedVersions')]
    public function test_unsupported_lockfile_versions_return_unsupported(string $json): void
    {
        $result = $this->parseRaw($json);

        $this->assertFalse($result->supported);
        $this->assertNull($result->type);
        $this->assertSame([], $result->dependencies);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedVersions(): array
    {
        return [
            'v1' => ['{"lockfileVersion":1,"dependencies":{"a":{"version":"1.0.0"}}}'],
            'absent' => ['{"name":"x"}'],
            'v4' => ['{"lockfileVersion":4,"packages":{"":{}}}'],
            'v0' => ['{"lockfileVersion":0,"packages":{"":{}}}'],
            'string' => ['{"lockfileVersion":"3","packages":{"":{}}}'],
            'null' => ['{"lockfileVersion":null}'],
            'float' => ['{"lockfileVersion":3.5}'],
        ];
    }

    public function test_lockfile_without_dependencies_returns_empty_supported_result(): void
    {
        $result = $this->parseRaw('{"lockfileVersion":3,"packages":{"":{"name":"x"}}}');

        $this->assertTrue($result->supported);
        $this->assertSame([], $result->dependencies);
    }

    #[DataProvider('brokenLockfiles')]
    public function test_malformed_lockfiles_throw(string $json): void
    {
        $this->expectException(ManifestParseException::class);

        $this->parseRaw($json);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function brokenLockfiles(): array
    {
        return [
            'broken json' => ['{"lockfileVersion":3,'], 'empty' => [''], 'root is list' => ['[1,2]'], 'root scalar' => ['"x"'],
            'v3 without packages' => ['{"lockfileVersion":3}'], 'v2 without packages' => ['{"lockfileVersion":2,"dependencies":{}}'],
            'packages is list' => ['{"lockfileVersion":3,"packages":[{"version":"1.0.0"}]}'],
            'packages is string' => ['{"lockfileVersion":3,"packages":"x"}'],
            'dependency entry not object' => ['{"lockfileVersion":3,"packages":{"node_modules/a":"1.0.0"}}'],
            'empty name after node_modules' => ['{"lockfileVersion":3,"packages":{"node_modules/":{"version":"1.0.0"}}}'],
            'path inside package' => ['{"lockfileVersion":3,"packages":{"node_modules/a/lib":{"version":"1.0.0"}}}'],
            'scope without name' => ['{"lockfileVersion":3,"packages":{"node_modules/@s":{"version":"1.0.0"}}}'],
        ];
    }

    public function test_oversized_file_is_rejected(): void
    {
        $this->expectException(ManifestParseException::class);

        $this->parseRaw('{"lockfileVersion":3,"packages":{"":{}}}', maxBytes: 10);
    }

    public function test_deeply_nested_json_is_rejected(): void
    {
        $this->expectException(ManifestParseException::class);

        $this->parseRaw(str_repeat('[', 100).str_repeat(']', 100));
    }

    public function test_symlinked_lockfile_is_rejected(): void
    {
        file_put_contents($this->root.'/outside.json', '{"lockfileVersion":3,"packages":{"":{}}}');
        symlink($this->root.'/outside.json', $this->root.'/app/package-lock.json');

        $this->expectException(ManifestParseException::class);

        (new PackageLockParser(1_000_000))->parse($this->root, 'app/package-lock.json');
    }

    public function test_lockfile_behind_a_symlinked_directory_outside_root_is_rejected(): void
    {
        $outside = sys_get_temp_dir().'/pkglock-out-'.bin2hex(random_bytes(5));
        mkdir($outside);
        file_put_contents($outside.'/package-lock.json', '{"lockfileVersion":3,"packages":{"":{}}}');
        symlink($outside, $this->root.'/linked');

        try {
            $this->expectException(ManifestParseException::class);
            (new PackageLockParser(1_000_000))->parse($this->root, 'linked/package-lock.json');
        } finally {
            (new Filesystem)->deleteDirectory($outside);
        }
    }

    public function test_missing_file_throws(): void
    {
        $this->expectException(ManifestParseException::class);

        (new PackageLockParser(1_000_000))->parse($this->root, 'app/package-lock.json');
    }

    public function test_overlong_name_or_lock_path_throws(): void
    {
        foreach ([
            ['node_modules/'.str_repeat('a', 215) => ['version' => '1.0.0']],
            ['node_modules/x' => ['name' => str_repeat('a', 215), 'version' => '1.0.0']],
            [str_repeat('a/', 600).'node_modules/x' => ['version' => '1.0.0']],
        ] as $packages) {
            try {
                $this->deps($packages);
                $this->fail('Exception diharapkan');
            } catch (ManifestParseException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_long_declared_spec_is_truncated(): void
    {
        $deps = $this->deps(['node_modules/x' => $this->reg('x', '1.0.0')], ['dependencies' => ['x' => str_repeat('a', 1100)]]);

        $this->assertSame(1024, strlen($deps[0]->declaredSpec));
    }

    public function test_exception_message_does_not_contain_server_path(): void
    {
        foreach (['{"x":', '[1]', '{"lockfileVersion":3}'] as $json) {
            try {
                $this->parseRaw($json);
                $this->fail('Exception diharapkan');
            } catch (ManifestParseException $e) {
                $this->assertStringNotContainsString($this->root, $e->getMessage());
                $this->assertStringNotContainsString('package-lock', $e->getMessage());
            }
        }

        try {
            (new PackageLockParser(1_000_000))->parse($this->root, 'app/missing.json');
            $this->fail('Exception diharapkan');
        } catch (ManifestParseException $e) {
            $this->assertStringNotContainsString($this->root, $e->getMessage());
        }
    }

    public function test_numeric_looking_dependency_names_do_not_break_direct_detection(): void
    {
        $deps = $this->deps(['node_modules/123' => $this->reg('123', '1.0.0')], ['dependencies' => ['123' => '^1.0.0']]);

        $this->assertSame(DependencyRelation::Direct, $deps[0]->relation);
    }
}
