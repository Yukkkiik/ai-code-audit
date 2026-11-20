<?php

namespace Tests\Unit\Sca;

use App\Enums\DependencyScope;
use App\Enums\ManifestType;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;
use App\Exceptions\ManifestParseException;
use App\Services\Sca\PackageJsonParser;
use App\Services\Sca\ParsedDependency;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PackageJsonParserTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/pkgjson-'.bin2hex(random_bytes(5));
        mkdir($this->root.'/app', 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>|string  $content
     * @return list<ParsedDependency>
     */
    private function parse(array|string $content, int $maxBytes = 1_000_000, string $path = 'app/package.json'): array
    {
        file_put_contents($this->root.'/'.$path, is_string($content) ? $content : json_encode($content));

        return (new PackageJsonParser($maxBytes))->parse($this->root, $path);
    }

    private function single(string $spec): ParsedDependency
    {
        $result = $this->parse(['dependencies' => ['pkg' => $spec]]);
        $this->assertCount(1, $result);

        return $result[0];
    }

    #[DataProvider('exactSpecs')]
    public function test_exact_versions_are_resolved_with_prefix_stripped(string $spec, string $version): void
    {
        $dep = $this->single($spec);

        $this->assertSame(VersionStatus::Exact, $dep->versionStatus);
        $this->assertSame($version, $dep->version);
        $this->assertNull($dep->unresolvedReason);
        $this->assertSame($spec, $dep->declaredSpec);
        $this->assertSame('pkg', $dep->name);
        $this->assertSame('app/package.json', $dep->manifestPath);
        $this->assertSame(ManifestType::PackageJson, $dep->sourceFileType);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function exactSpecs(): array
    {
        return [
            'plain' => ['1.2.3', '1.2.3'],
            'equals prefix' => ['=1.2.3', '1.2.3'],
            'v prefix' => ['v1.2.3', '1.2.3'],
            'prerelease' => ['1.2.3-beta.1', '1.2.3-beta.1'],
            'build metadata' => ['1.2.3+build.5', '1.2.3+build.5'],
        ];
    }

    #[DataProvider('rangeSpecs')]
    public function test_ranges_are_unresolved_range_without_lockfile(string $spec): void
    {
        $dep = $this->single($spec);

        $this->assertSame(VersionStatus::Unresolved, $dep->versionStatus);
        $this->assertNull($dep->version);
        $this->assertSame(UnresolvedReason::RangeWithoutLockfile, $dep->unresolvedReason);
        $this->assertSame($spec, $dep->declaredSpec);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rangeSpecs(): array
    {
        return array_map(fn (string $s) => [$s], [
            'caret' => '^1.2.3', 'tilde' => '~1.2.3', 'gte' => '>=1.2.3', 'lt' => '<2.0.0',
            'star' => '*', 'x' => 'x', 'minor x' => '1.x', 'major only' => '1', 'partial' => '1.2',
            'or' => '^1.0.0 || ^2.0.0', 'space range' => '1.2.3 - 2.3.4', 'latest' => 'latest',
            'tag' => 'next', 'empty' => '',
        ]);
    }

    #[DataProvider('nonRegistrySpecs')]
    public function test_non_registry_sources_are_unresolved(string $spec): void
    {
        $dep = $this->single($spec);

        $this->assertSame(VersionStatus::Unresolved, $dep->versionStatus);
        $this->assertNull($dep->version);
        $this->assertSame(UnresolvedReason::NonRegistrySource, $dep->unresolvedReason);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonRegistrySpecs(): array
    {
        return array_map(fn (string $s) => [$s], [
            'git+https' => 'git+https://github.com/a/b.git#v1.0.0', 'git+ssh' => 'git+ssh://git@github.com/a/b.git',
            'git://' => 'git://github.com/a/b.git', 'github:' => 'github:a/b', 'gitlab:' => 'gitlab:a/b',
            'bitbucket:' => 'bitbucket:a/b', 'gist:' => 'gist:abc123',
            'shorthand' => 'a/b', 'shorthand ref' => 'a/b#main', 'file:' => 'file:../local',
            'link:' => 'link:../local', 'workspace:' => 'workspace:*', 'http tarball' => 'http://x.test/p-1.0.0.tgz',
            'https tarball' => 'https://x.test/p-1.0.0.tgz',
        ]);
    }

    public function test_npm_alias_with_exact_version_uses_real_package_name(): void
    {
        $result = $this->parse(['dependencies' => ['alias' => 'npm:real-pkg@1.2.3', 'sc' => 'npm:@scope/real@2.0.0']]);

        $this->assertSame('real-pkg', $result[0]->name);
        $this->assertSame('1.2.3', $result[0]->version);
        $this->assertSame(VersionStatus::Exact, $result[0]->versionStatus);
        $this->assertSame('npm:real-pkg@1.2.3', $result[0]->declaredSpec);
        $this->assertSame('@scope/real', $result[1]->name);
        $this->assertSame('2.0.0', $result[1]->version);
    }

    #[DataProvider('nonExactAliases')]
    public function test_npm_alias_without_exact_version_is_non_registry(string $spec): void
    {
        $result = $this->parse(['dependencies' => ['alias' => $spec]]);

        $this->assertSame('alias', $result[0]->name);
        $this->assertSame(VersionStatus::Unresolved, $result[0]->versionStatus);
        $this->assertSame(UnresolvedReason::NonRegistrySource, $result[0]->unresolvedReason);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonExactAliases(): array
    {
        return ['range' => ['npm:real@^1.2.3'], 'no version' => ['npm:real'], 'empty' => ['npm:']];
    }

    public function test_scopes_are_mapped_from_each_section(): void
    {
        $result = $this->parse([
            'dependencies' => ['a' => '1.0.0'],
            'devDependencies' => ['b' => '1.0.0'],
            'optionalDependencies' => ['c' => '1.0.0'],
            'peerDependencies' => ['d' => '1.0.0'],
        ]);

        $this->assertSame(
            ['a' => DependencyScope::Production, 'b' => DependencyScope::Development, 'c' => DependencyScope::Optional, 'd' => DependencyScope::Peer],
            array_column(array_map(fn ($d) => ['n' => $d->name, 's' => $d->scope], $result), 's', 'n'),
        );
    }

    public function test_same_name_in_two_sections_returns_both_entries(): void
    {
        $result = $this->parse(['devDependencies' => ['react' => '18.2.0'], 'peerDependencies' => ['react' => '^18.0.0']]);

        $this->assertCount(2, $result);
        $this->assertSame([DependencyScope::Development, DependencyScope::Peer], [$result[0]->scope, $result[1]->scope]);
    }

    public function test_non_string_spec_is_unresolved_without_declared_spec(): void
    {
        $result = $this->parse(['dependencies' => ['a' => 1, 'b' => null, 'c' => ['x']]]);

        $this->assertCount(3, $result);

        foreach ($result as $dep) {
            $this->assertSame(VersionStatus::Unresolved, $dep->versionStatus);
            $this->assertSame(UnresolvedReason::RangeWithoutLockfile, $dep->unresolvedReason);
            $this->assertNull($dep->declaredSpec);
        }
    }

    public function test_long_declared_spec_is_truncated_but_classified_in_full(): void
    {
        $spec = 'git+https://example.test/'.str_repeat('a', 1100).'.git';

        $dep = $this->single($spec);

        $this->assertSame(1024, strlen($dep->declaredSpec));
        $this->assertSame(UnresolvedReason::NonRegistrySource, $dep->unresolvedReason);
    }

    public function test_package_without_dependency_sections_returns_empty_list(): void
    {
        $this->assertSame([], $this->parse(['name' => 'x', 'scripts' => ['postinstall' => 'rm -rf /']]));
        $this->assertSame([], $this->parse(['dependencies' => new \stdClass]));
    }

    #[DataProvider('brokenManifests')]
    public function test_malformed_manifests_throw(string $json): void
    {
        $this->expectException(ManifestParseException::class);

        $this->parse($json);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function brokenManifests(): array
    {
        return [
            'broken json' => ['{"dependencies": {'],
            'empty file' => [''],
            'root is list' => ['[1,2]'],
            'root is scalar' => ['"x"'],
            'section is list' => ['{"dependencies": ["a"]}'],
            'section is string' => ['{"devDependencies": "a"}'],
        ];
    }

    public function test_oversized_file_is_rejected_before_reading(): void
    {
        $this->expectException(ManifestParseException::class);

        $this->parse(['dependencies' => ['a' => '1.0.0']], maxBytes: 10);
    }

    public function test_deeply_nested_json_is_rejected(): void
    {
        $this->expectException(ManifestParseException::class);

        $this->parse(str_repeat('[', 100).str_repeat(']', 100));
    }

    public function test_symlinked_package_json_is_rejected(): void
    {
        $outside = $this->root.'/outside.json';
        file_put_contents($outside, '{"dependencies":{"a":"1.0.0"}}');
        symlink($outside, $this->root.'/app/package.json');

        $this->expectException(ManifestParseException::class);

        (new PackageJsonParser(1_000_000))->parse($this->root, 'app/package.json');
    }

    public function test_missing_file_throws(): void
    {
        $this->expectException(ManifestParseException::class);

        (new PackageJsonParser(1_000_000))->parse($this->root, 'app/package.json');
    }

    public function test_overlong_package_name_throws(): void
    {
        $this->expectException(ManifestParseException::class);

        $this->parse(['dependencies' => [str_repeat('a', 215) => '1.0.0']]);
    }

    public function test_exception_message_does_not_contain_server_path(): void
    {
        foreach (['{"x":', '[1]'] as $json) {
            try {
                $this->parse($json);
                $this->fail('Exception diharapkan');
            } catch (ManifestParseException $e) {
                $this->assertStringNotContainsString($this->root, $e->getMessage());
                $this->assertStringNotContainsString('package.json', $e->getMessage());
            }
        }

        try {
            (new PackageJsonParser(1_000_000))->parse($this->root, 'app/missing.json');
            $this->fail('Exception diharapkan');
        } catch (ManifestParseException $e) {
            $this->assertStringNotContainsString($this->root, $e->getMessage());
        }
    }

    #[DataProvider('rangeSpecs')]
    public function test_never_marks_a_range_as_exact(string $spec): void
    {
        $this->assertNotSame(VersionStatus::Exact, $this->single($spec)->versionStatus);
    }
}
