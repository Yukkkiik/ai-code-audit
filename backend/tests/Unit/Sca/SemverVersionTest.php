<?php

namespace Tests\Unit\Sca;

use App\Services\Sca\SemverVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SemverVersionTest extends TestCase
{
    private function cmp(string $a, string $b): int
    {
        $left = SemverVersion::tryParse($a);
        $right = SemverVersion::tryParse($b);
        $this->assertNotNull($left, $a);
        $this->assertNotNull($right, $b);

        return $left->compareTo($right) <=> 0;
    }

    public function test_official_semver_org_precedence_is_strictly_increasing(): void
    {
        $ordered = ['1.0.0-alpha', '1.0.0-alpha.1', '1.0.0-alpha.beta', '1.0.0-beta', '1.0.0-beta.2', '1.0.0-beta.11', '1.0.0-rc.1', '1.0.0',
            '2.0.0', '2.1.0', '2.1.1'];

        for ($i = 0; $i < count($ordered) - 1; $i++) {
            $this->assertSame(-1, $this->cmp($ordered[$i], $ordered[$i + 1]), "{$ordered[$i]} < {$ordered[$i + 1]}");
            $this->assertSame(1, $this->cmp($ordered[$i + 1], $ordered[$i]));
        }

        $this->assertSame(-1, $this->cmp('1.0.0-alpha', '1.0.0'));
        $this->assertSame(-1, $this->cmp('1.0.0-alpha', '2.0.0'));
    }

    public function test_numeric_and_alphanumeric_prerelease_identifiers(): void
    {
        $this->assertSame(-1, $this->cmp('1.0.0-1', '1.0.0-a'), 'numerik < alfanumerik');
        $this->assertSame(-1, $this->cmp('1.0.0-2', '1.0.0-10'), 'numerik dibandingkan sebagai angka');
        $this->assertSame(-1, $this->cmp('1.0.0-a10', '1.0.0-a9'), 'alfanumerik dibandingkan leksikal ASCII');
        $this->assertSame(-1, $this->cmp('1.0.0-alpha', '1.0.0-alpha.0'), 'lebih sedikit identifier lebih kecil');
        $this->assertSame(-1, $this->cmp('1.0.0-alpha.1', '1.0.0-alpha.beta'));
        $this->assertSame(-1, $this->cmp('1.0.0-a-b', '1.0.0-a-c'));
        $this->assertSame(-1, $this->cmp('1.0.0-Z', '1.0.0-a'), 'huruf besar < huruf kecil (ASCII)');
        $this->assertSame(-1, $this->cmp('1.0.0-0', '1.0.0-1'));
        $this->assertSame(1, $this->cmp('1.0.0-99999999999999999999', '1.0.0-9999999999999999999'));
    }

    public function test_build_metadata_is_ignored_in_precedence(): void
    {
        $this->assertSame(0, $this->cmp('1.0.0+a', '1.0.0+b'));
        $this->assertSame(0, $this->cmp('1.0.0+build', '1.0.0'));
        $this->assertSame(0, $this->cmp('1.0.0-rc.1+x', '1.0.0-rc.1'));
        $this->assertSame(-1, $this->cmp('1.0.0-rc.1+zzz', '1.0.0+aaa'));
    }

    public function test_large_numbers_do_not_overflow(): void
    {
        $this->assertSame(1, $this->cmp('1.0.99999999999999999999999', '1.0.9999999999999999999999'));
        $this->assertSame(1, $this->cmp('10.0.0', '9.9.9'));
        $this->assertSame(-1, $this->cmp('9223372036854775807.0.0', '9223372036854775808.0.0'));
        $this->assertSame(0, $this->cmp('18446744073709551616.0.0', '18446744073709551616.0.0'));
    }

    #[DataProvider('invalidVersions')]
    public function test_invalid_versions_are_rejected(string $version): void
    {
        $this->assertNull(SemverVersion::tryParse($version), json_encode($version));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidVersions(): array
    {
        return array_map(fn ($v) => [$v], [
            'two segments' => '1.2', 'one segment' => '1', 'four segments' => '1.2.3.4', 'v prefix' => 'v1.2.3', 'equals prefix' => '=1.2.3',
            'leading space' => ' 1.2.3', 'trailing space' => '1.2.3 ', 'newline' => "1.2.3\n", 'leading zero major' => '01.2.3',
            'leading zero minor' => '1.02.3', 'leading zero patch' => '1.2.03', 'empty prerelease' => '1.2.3-', 'numeric prerelease leading zero' => '1.2.3-01',
            'empty identifier' => '1.2.3-a..b', 'leading dot identifier' => '1.2.3-.a', 'empty build' => '1.2.3+', 'empty prerelease with build' => '1.2.3-a+',
            'empty string' => '', 'tag' => 'latest', 'caret' => '^1.2.3', 'x range' => '1.2.x', 'negative' => '-1.2.3', 'unicode' => '1.2.3-ü',
            'wildcard' => '*', 'range' => '>=1.0.0', 'too long' => '1.0.0-'.str_repeat('a', 100), 'negative number' => '1.-2.3', 'plus sign' => '+1.2.3',
        ]);
    }

    #[DataProvider('validVersions')]
    public function test_valid_versions_are_accepted_and_kept_as_written(string $version): void
    {
        $parsed = SemverVersion::tryParse($version);

        $this->assertNotNull($parsed);
        $this->assertSame($version, $parsed->original);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validVersions(): array
    {
        return array_map(fn ($v) => [$v], ['zero' => '0.0.0', 'numeric prerelease zero' => '1.2.3-0', 'dotted zeros' => '1.2.3-0.0', 'hyphens' => '1.2.3-a-b.c-d',
            'build' => '1.2.3+build.5', 'prerelease and build' => '1.2.3-rc.1+build.5', 'big' => '999999999999.0.0', 'max length' => '1.0.0-'.str_repeat('a', 94)]);
    }

    public function test_prerelease_flag(): void
    {
        $this->assertTrue(SemverVersion::tryParse('1.0.0-rc.1')->isPrerelease());
        $this->assertFalse(SemverVersion::tryParse('1.0.0')->isPrerelease());
        $this->assertFalse(SemverVersion::tryParse('1.0.0+build')->isPrerelease());
    }

    public function test_exposes_major_minor_patch_as_strings(): void
    {
        $parsed = SemverVersion::tryParse('12.34.56-rc.1');

        $this->assertSame(['12', '34', '56'], [$parsed->major, $parsed->minor, $parsed->patch]);
    }

    /**
     * @return list<string>
     */
    private function sampleVersions(): array
    {
        $versions = [];

        foreach ([0, 1, 2, 10] as $major) {
            foreach ([0, 1, 9, 10] as $minor) {
                foreach ([0, 3] as $patch) {
                    $base = "$major.$minor.$patch";
                    $versions[] = $base;

                    foreach (['alpha', 'alpha.1', 'alpha.beta', 'beta.2', 'beta.11', 'rc.1', '1', '10', 'a-b', '0', '0.0'] as $pre) {
                        $versions[] = "$base-$pre";
                    }
                }
            }
        }

        return $versions;
    }

    public function test_property_compare_is_reflexive_and_antisymmetric_over_all_pairs(): void
    {
        $versions = $this->sampleVersions();

        foreach ($versions as $a) {
            $this->assertSame(0, $this->cmp($a, $a), $a);

            foreach ($versions as $b) {
                $this->assertSame($this->cmp($a, $b), -$this->cmp($b, $a), "$a vs $b");
            }
        }
    }

    public function test_property_compare_is_transitive(): void
    {
        $versions = $this->sampleVersions();
        mt_srand(7);
        shuffle($versions);
        $versions = array_slice($versions, 0, 45);

        foreach ($versions as $a) {
            foreach ($versions as $b) {
                foreach ($versions as $c) {
                    if ($this->cmp($a, $b) <= 0 && $this->cmp($b, $c) <= 0) {
                        $this->assertLessThanOrEqual(0, $this->cmp($a, $c), "$a <= $b <= $c");
                    }
                }
            }
        }
    }

    public function test_property_sorting_is_independent_of_the_initial_order(): void
    {
        $sort = function (array $versions): array {
            usort($versions, fn ($a, $b) => SemverVersion::tryParse($a)->compareTo(SemverVersion::tryParse($b)));

            return $versions;
        };
        $versions = $this->sampleVersions();
        $first = $sort($versions);

        foreach ([1, 2, 3] as $seed) {
            mt_srand($seed);
            shuffle($versions);
            $again = $sort($versions);

            for ($i = 0; $i < count($first); $i++) {
                $this->assertSame(0, $this->cmp($first[$i], $again[$i]), "posisi $i");
            }
        }

        for ($i = 0; $i < count($first) - 1; $i++) {
            $this->assertLessThanOrEqual(0, $this->cmp($first[$i], $first[$i + 1]));
        }
    }

    public function test_property_agrees_with_php_version_compare_for_release_versions(): void
    {
        $releases = array_values(array_filter($this->sampleVersions(), fn ($v) => ! str_contains($v, '-')));

        foreach ($releases as $a) {
            foreach ($releases as $b) {
                $this->assertSame(version_compare($a, $b) <=> 0, $this->cmp($a, $b), "$a vs $b");
            }
        }
    }
}
