<?php

namespace Tests\Unit\Sca;

use App\Enums\FixMatchReason;
use App\Enums\FixMatchStatus;
use App\Enums\VersionChange;
use App\Services\Sca\AdvisoryAffects;
use App\Services\Sca\FixedVersionMatcher;
use App\Services\Sca\FixMatch;
use App\Services\Sca\OsvVulnerabilityDetail;
use App\Services\Sca\OsvVulnerabilityParser;
use App\Services\Sca\SemverVersion;
use PHPUnit\Framework\TestCase;

/**
 * Item 7.10: daftar `versions` eksplisit OSV pada parser dan pencocokan versi perbaikan.
 *
 * Fixture `osv-detail-versions-handwritten.json` DITULIS TANGAN. Bentuk `versions` pada paket npm di sana belum
 * terbukti pada respons OSV nyata; fixture nyata (osv-detail-real.json, body-parser, proxy-addr) tidak memuat
 * `versions` untuk npm, hanya entri RubyGems.
 */
class ExplicitVersionsMatchTest extends TestCase
{
    // --- pembantu ------------------------------------------------------------------------------------------------

    private function handwritten(): OsvVulnerabilityDetail
    {
        $json = json_decode(file_get_contents(__DIR__.'/../../Fixtures/Sca/osv-detail-versions-handwritten.json'), true, 512, JSON_THROW_ON_ERROR);

        return OsvVulnerabilityParser::parse($json, 'HANDWRITTEN-VERSIONS-0001');
    }

    /**
     * @param  list<array<string, mixed>>  $affected
     */
    private function parse(array $affected): OsvVulnerabilityDetail
    {
        return OsvVulnerabilityParser::parse(['id' => 'X', 'affected' => $affected], 'X');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function npm(string $name, array $extra): array
    {
        return ['package' => ['ecosystem' => 'npm', 'name' => $name]] + $extra;
    }

    /**
     * @param  list<array<string, string>>  $events
     * @return list<array{type: string, events: list<array<string, string>>}>
     */
    private function ranges(array $events): array
    {
        return [['type' => 'SEMVER', 'events' => $events]];
    }

    /**
     * @return list<array{type: string, events: list<array<string, string>>}>
     */
    private function below(string $fixed, string $introduced = '0'): array
    {
        return $this->ranges([['introduced' => $introduced], ['fixed' => $fixed]]);
    }

    /**
     * @param  list<array{type: string, events: list<array<string, string>>}>|null  $ranges
     * @param  list<string>|null  $versions
     */
    private function one(string $installed, ?array $ranges, ?array $versions): FixMatch
    {
        return (new FixedVersionMatcher)->match($installed, AdvisoryAffects::of($ranges, $versions));
    }

    /**
     * @param  list<mixed>  $advisories  AdvisoryAffects, array rentang lama, atau null
     */
    private function combined(string $installed, array $advisories): FixMatch
    {
        return (new FixedVersionMatcher)->matchCombined($installed, $advisories);
    }

    private function assertFix(FixMatch $match, string $fixed): void
    {
        $this->assertSame(FixMatchStatus::FixAvailable, $match->status);
        $this->assertSame($fixed, $match->fixedVersion);
        $this->assertNull($match->reason);
    }

    private function assertNoFix(FixMatch $match): void
    {
        $this->assertSame(FixMatchStatus::NoFixRecorded, $match->status);
        $this->assertNull($match->fixedVersion);
        $this->assertNull($match->reason);
    }

    private function assertUndetermined(FixMatch $match, FixMatchReason $reason): void
    {
        $this->assertSame(FixMatchStatus::Undetermined, $match->status);
        $this->assertSame($reason, $match->reason);
        $this->assertNull($match->fixedVersion);
        $this->assertNull($match->versionChange);
    }

    // --- parser --------------------------------------------------------------------------------------------------

    public function test_the_parser_reads_versions_per_npm_package_and_leaves_ranges_and_fixed_unchanged(): void
    {
        $both = $this->handwritten()->affectedFor('hand-both');

        $this->assertSame(['1.0.0', '1.0.1', '2.1.0+build'], $both['versions']);
        $this->assertSame([['type' => 'SEMVER', 'events' => [['introduced' => '0'], ['fixed' => '2.0.0']]]], $both['ranges']);
        $this->assertSame(['2.0.0'], $both['fixed']);

        $only = $this->handwritten()->affectedFor('hand-versions-only');
        $this->assertSame(['3.0.0', '3.0.1'], $only['versions']);
        $this->assertSame([], $only['ranges']);
        $this->assertSame([], $only['fixed']);
    }

    public function test_a_missing_versions_key_is_an_empty_list_and_real_npm_fixtures_have_none(): void
    {
        $this->assertSame([], $this->parse([$this->npm('a', ['ranges' => $this->below('1.0.0')])])->affectedFor('a')['versions']);

        foreach ([['osv-detail-real.json', 'GHSA-35jh-r3h4-6jhm', 'lodash'], ['osv-detail-body-parser.json', 'GHSA-27r3-jxmh-p828', 'body-parser'], ['osv-detail-proxy-addr.json', 'GHSA-jqcg-44mw-7w3h', 'proxy-addr']] as [$file, $id, $package]) {
            $json = json_decode(file_get_contents(__DIR__.'/../../Fixtures/Sca/'.$file), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame([], OsvVulnerabilityParser::parse($json, $id)->affectedFor($package)['versions'], $file);
        }
    }

    public function test_versions_of_other_ecosystems_are_ignored_and_unknown_packages_default_to_empty(): void
    {
        $detail = $this->handwritten();

        // RubyGems "hand-ruby" membawa 2 versi, tetapi bukan npm.
        $this->assertSame(['ranges' => [], 'fixed' => [], 'versions' => []], $detail->affectedFor('hand-ruby'));
        $this->assertSame(['ranges' => [], 'fixed' => [], 'versions' => []], $detail->affectedFor('unknown-package'));
    }

    public function test_versions_that_are_not_a_list_make_the_whole_list_unknown(): void
    {
        $detail = $this->handwritten();

        $this->assertNull($detail->affectedFor('hand-not-a-list')['versions']);
        $this->assertCount(1, $detail->affectedFor('hand-not-a-list')['ranges']);
    }

    public function test_invalid_entries_are_ignored_and_duplicates_removed(): void
    {
        $this->assertSame(['ok', '1.2.3'], $this->handwritten()->affectedFor('hand-junk')['versions']);

        $long = str_repeat('1', 101);
        $parsed = $this->parse([$this->npm('a', ['versions' => [$long, str_repeat('2', 100), '1.0.0', '1.0.0']])]);

        $this->assertSame([str_repeat('2', 100), '1.0.0'], $parsed->affectedFor('a')['versions']);
    }

    public function test_numeric_looking_entries_stay_strings(): void
    {
        $this->assertSame(['10', '010', '1.0.0'], $this->parse([$this->npm('a', ['versions' => ['10', '010', '10', '1.0.0']])])->affectedFor('a')['versions']);
    }

    public function test_several_affected_entries_for_one_package_are_merged_and_any_unknown_makes_it_unknown(): void
    {
        $detail = $this->handwritten();

        $this->assertSame(['1.0.0', '1.0.1', '1.0.2'], $detail->affectedFor('hand-two-entries')['versions']);
        $this->assertNull($detail->affectedFor('hand-one-null-entry')['versions']);
    }

    public function test_exactly_one_thousand_distinct_versions_are_accepted_and_one_more_is_unknown(): void
    {
        $versions = fn (int $count) => array_map(fn (int $i) => "1.0.$i", range(1, $count));

        $this->assertCount(1000, $this->parse([$this->npm('a', ['versions' => $versions(1000)])])->affectedFor('a')['versions']);
        $this->assertNull($this->parse([$this->npm('a', ['versions' => $versions(1001)])])->affectedFor('a')['versions']);

        // Duplikat tidak dihitung sebagai distinct.
        $this->assertCount(1000, $this->parse([$this->npm('a', ['versions' => [...$versions(1000), ...$versions(1000)]])])->affectedFor('a')['versions']);

        // Gabungan dua entri yang melewati batas juga tidak lengkap, bukan dipotong.
        $merged = $this->parse([$this->npm('a', ['versions' => $versions(600)]), $this->npm('a', ['versions' => array_map(fn (int $i) => "2.0.$i", range(1, 600))])]);
        $this->assertNull($merged->affectedFor('a')['versions']);
    }

    // --- matcher per advisori: langkah 1-7 -----------------------------------------------------------------------

    public function test_step_1_missing_detail_wins_over_everything(): void
    {
        $this->assertUndetermined($this->one('01.0.0', null, null), FixMatchReason::DetailUnavailable);
        $this->assertUndetermined($this->one('1.0.0', null, ['1.0.0']), FixMatchReason::DetailUnavailable);
    }

    public function test_step_2_an_invalid_installed_version_comes_before_unknown_versions(): void
    {
        $this->assertUndetermined($this->one('01.0.0', $this->below('2.0.0'), null), FixMatchReason::InvalidVersion);
    }

    public function test_step_3_unknown_versions_block_even_with_good_ranges(): void
    {
        $this->assertUndetermined($this->one('1.0.0', $this->below('2.0.0'), null), FixMatchReason::UnevaluableRange);
    }

    public function test_step_4_an_unevaluable_range_blocks_even_when_the_installed_version_is_listed(): void
    {
        $this->assertUndetermined($this->one('1.0.0', $this->ranges([['limit' => '2.0.0']]), ['1.0.0']), FixMatchReason::UnevaluableRange);
        $this->assertUndetermined($this->one('1.0.0', array_fill(0, 101, $this->below('2.0.0')[0]), ['1.0.0']), FixMatchReason::UnevaluableRange);
    }

    public function test_step_5_no_ranges_and_no_versions_is_no_range_data(): void
    {
        $this->assertUndetermined($this->one('1.0.0', [], []), FixMatchReason::NoRangeData);
        $this->assertUndetermined($this->one('1.0.0', [['type' => 'GIT', 'events' => [['introduced' => 'abc']]]], []), FixMatchReason::NoRangeData);
    }

    public function test_step_6_versions_only_installed_listed_is_affected_without_a_recorded_fix(): void
    {
        $match = $this->one('3.0.0', [], ['3.0.0', '3.0.1']);

        $this->assertNoFix($match);
        $this->assertSame('3.0.0', $match->installedVersion);
    }

    public function test_step_6_versions_only_installed_not_listed_is_not_in_listed_ranges(): void
    {
        $this->assertUndetermined($this->one('3.0.2', [], ['3.0.0', '3.0.1']), FixMatchReason::NotInListedRanges);
        $this->assertUndetermined($this->one('3.0.2', [['type' => 'GIT', 'events' => [['introduced' => 'abc']]]], ['3.0.0']), FixMatchReason::NotInListedRanges);
    }

    public function test_step_6_listed_in_versions_but_outside_the_ranges_is_affected_and_gets_the_fix_value(): void
    {
        // 1.0.0 tidak termuat range [5.0.0, 6.0.0), tetapi tertulis di versions: terdampak; nilai fixed tertulis 6.0.0.
        $this->assertFix($this->one('1.0.0', $this->below('6.0.0', '5.0.0'), ['1.0.0']), '6.0.0');
    }

    public function test_step_6_in_neither_ranges_nor_versions_is_not_in_listed_ranges(): void
    {
        $this->assertUndetermined($this->one('1.0.0', $this->below('6.0.0', '5.0.0'), ['2.0.0']), FixMatchReason::NotInListedRanges);
    }

    public function test_step_7_a_candidate_written_in_versions_is_skipped(): void
    {
        $ranges = $this->ranges([['introduced' => '0'], ['fixed' => '2.0.0'], ['introduced' => '5.0.0'], ['fixed' => '6.0.0']]);

        $this->assertFix($this->one('1.0.0', $ranges, []), '2.0.0');
        $this->assertFix($this->one('1.0.0', $ranges, ['2.0.0']), '6.0.0');
        $this->assertNoFix($this->one('1.0.0', $ranges, ['2.0.0', '6.0.0']));
        $this->assertSame(VersionChange::Major, $this->one('1.0.0', $ranges, ['2.0.0'])->versionChange);
    }

    public function test_matching_ignores_build_metadata_on_both_sides(): void
    {
        $this->assertNoFix($this->one('1.2.3+x', [], ['1.2.3']));
        $this->assertNoFix($this->one('1.2.3', [], ['1.2.3+y']));

        // Kandidat "2.5.0" dikecualikan oleh versions "2.5.0+y".
        $ranges = $this->ranges([['introduced' => '0'], ['fixed' => '2.5.0'], ['introduced' => '3.0.0'], ['fixed' => '4.0.0']]);
        $this->assertFix($this->one('1.0.0', $ranges, ['2.5.0+y']), '4.0.0');
    }

    public function test_entries_that_are_not_valid_semver_never_match(): void
    {
        $this->assertUndetermined($this->one('1.2.3', [], ['latest', '1.2', 'v1.2.3', '1.2.3-01', '']), FixMatchReason::NotInListedRanges);

        // Kandidat 2.0.0 tidak dikecualikan oleh entri sampah.
        $this->assertFix($this->one('1.0.0', $this->below('2.0.0'), ['latest', '2', 'x.y.z']), '2.0.0');
    }

    public function test_an_empty_versions_list_behaves_exactly_like_the_old_matcher(): void
    {
        $matcher = new FixedVersionMatcher;
        $cases = [$this->below('2.0.0'), $this->ranges([['introduced' => '0']]), [], null, $this->ranges([['limit' => '1.0.0']])];

        foreach ($cases as $i => $ranges) {
            foreach (['1.0.0', '2.0.0', '01.0.0'] as $installed) {
                $this->assertEquals($matcher->match($installed, $ranges), $matcher->match($installed, AdvisoryAffects::fromRanges($ranges)), "$i $installed");
                $this->assertEquals($matcher->match($installed, $ranges), $matcher->match($installed, AdvisoryAffects::of($ranges, [])), "$i $installed");
                $this->assertEquals($matcher->matchCombined($installed, [$ranges]), $matcher->matchCombined($installed, [AdvisoryAffects::fromRanges($ranges)]), "$i $installed");
            }
        }
    }

    // --- pencocokan gabungan -------------------------------------------------------------------------------------

    public function test_combined_versions_of_other_advisories_exclude_a_candidate(): void
    {
        $a = AdvisoryAffects::of($this->below('2.4.0'), []);
        $b = AdvisoryAffects::of($this->below('2.0.0'), ['2.4.0']);

        // Per advisori A memberi 2.4.0; B menulis 2.4.0 di versions, jadi gabungan tidak punya kandidat.
        $this->assertFix($this->one('1.0.0', $this->below('2.4.0'), []), '2.4.0');
        $this->assertNoFix($this->combined('1.0.0', [$a, $b]));
        $this->assertNoFix($this->combined('1.0.0', [$b, $a]));
    }

    public function test_combined_a_versions_only_advisory_can_make_an_installed_version_affected(): void
    {
        $a = AdvisoryAffects::of($this->below('2.0.0'), []);
        $b = AdvisoryAffects::of([], ['1.0.0']);

        $this->assertFix($this->combined('1.0.0', [$a, $b]), '2.0.0');
        $this->assertUndetermined($this->combined('1.5.0', [$a, $b]), FixMatchReason::NotInListedRanges);
    }

    public function test_combined_one_advisory_with_unknown_versions_blocks_everything(): void
    {
        $good = AdvisoryAffects::of($this->below('2.0.0'), []);
        $unknown = AdvisoryAffects::of($this->below('3.0.0'), null);

        $this->assertUndetermined($this->combined('1.0.0', [$good, $unknown]), FixMatchReason::UnevaluableRange);
        $this->assertUndetermined($this->combined('1.0.0', [$unknown, $good]), FixMatchReason::UnevaluableRange);
        $this->assertUndetermined($this->combined('1.0.0', [$unknown, $good, null]), FixMatchReason::DetailUnavailable);
    }

    public function test_combined_the_old_array_input_equals_from_ranges(): void
    {
        $a = $this->below('2.4.0');
        $b = $this->below('2.5.0', '2.0.0');

        $this->assertEquals($this->combined('2.2.2', [$a, $b]), $this->combined('2.2.2', [AdvisoryAffects::fromRanges($a), AdvisoryAffects::fromRanges($b)]));
        $this->assertFix($this->combined('2.2.2', [$a, $b]), '2.5.0');
    }

    public function test_combined_the_budget_counts_distinct_version_keys(): void
    {
        $this->assertSame(20000, FixedVersionMatcher::MAX_COMBINED_VERSIONS);

        $advisory = fn (int $seed, int $count = 1000) => AdvisoryAffects::of($this->below('9999.0.0'), array_map(fn (int $i) => "$seed.0.$i", range(1, $count)));

        // 20 x 1000 kunci berbeda = tepat 20.000: diproses. 21 x 1000 = 21.000: ditolak, apa pun urutannya.
        $twenty = array_map($advisory, range(1, 20));
        $twentyOne = array_map($advisory, range(1, 21));

        $this->assertFix($this->combined('0.0.5', $twenty), '9999.0.0');
        $this->assertUndetermined($this->combined('0.0.5', $twentyOne), FixMatchReason::UnevaluableRange);
        $this->assertUndetermined($this->combined('0.0.5', array_reverse($twentyOne)), FixMatchReason::UnevaluableRange);
        $this->assertUndetermined($this->combined('0.0.5', [null, ...$twentyOne]), FixMatchReason::DetailUnavailable);

        // Kunci yang sama pada 30 advisori hanya dihitung sekali.
        $this->assertFix($this->combined('0.0.5', array_fill(0, 30, $advisory(1))), '9999.0.0');

        // Versi yang hanya berbeda metadata build adalah kunci yang sama.
        $withBuild = AdvisoryAffects::of($this->below('9999.0.0'), array_map(fn (int $i) => "1.0.$i+b", range(1, 1000)));
        $this->assertFix($this->combined('0.0.5', [...array_map($advisory, range(1, 20)), $withBuild]), '9999.0.0');
    }

    public function test_combined_the_advisory_order_never_changes_the_result(): void
    {
        $advisories = [
            AdvisoryAffects::of($this->below('2.4.0'), []),
            AdvisoryAffects::of($this->below('2.0.0'), ['2.4.0', '2.5.0']),
            AdvisoryAffects::of([], ['1.0.0']),
            AdvisoryAffects::of($this->below('2.6.0', '0.5.0'), ['2.2.2']),
        ];

        $expected = $this->combined('1.0.0', $advisories);
        $this->assertNotNull($expected->status);

        foreach ($this->permutations($advisories) as $order) {
            $this->assertEquals($expected, $this->combined('1.0.0', $order));
        }

        $withBlocker = [...$advisories, AdvisoryAffects::of($this->below('9.0.0'), null)];

        foreach ($this->permutations($withBlocker) as $order) {
            $this->assertUndetermined($this->combined('1.0.0', $order), FixMatchReason::UnevaluableRange);
        }
    }

    /**
     * @param  list<mixed>  $items
     * @return list<list<mixed>>
     */
    private function permutations(array $items): array
    {
        if (count($items) <= 1) {
            return [$items];
        }

        $result = [];

        foreach ($items as $i => $item) {
            $rest = $items;
            unset($rest[$i]);

            foreach ($this->permutations(array_values($rest)) as $tail) {
                $result[] = [$item, ...$tail];
            }
        }

        return $result;
    }

    // --- property test dengan oracle naif ------------------------------------------------------------------------

    /**
     * Oracle independen: keanggotaan pada range dihitung dengan menelusuri event terurut; keanggotaan pada versions
     * dengan membandingkan string tanpa metadata build.
     *
     * @param  list<array{type: string, events: list<array<string, string>>}>  $ranges
     * @param  list<string>  $versions
     */
    private function affected(SemverVersion $version, array $ranges, array $versions): bool
    {
        foreach ($versions as $listed) {
            if (strtok($listed, '+') === strtok($version->original, '+')) {
                return true;
            }
        }

        foreach ($ranges as $range) {
            $events = $range['events'];
            usort($events, function (array $a, array $b): int {
                $x = reset($a) === '0' ? null : SemverVersion::tryParse(reset($a));
                $y = reset($b) === '0' ? null : SemverVersion::tryParse(reset($b));

                return $x === null || $y === null ? ($x === null ? 0 : 1) <=> ($y === null ? 0 : 1) : $x->compareTo($y);
            });
            $inside = false;

            foreach ($events as $event) {
                $kind = key($event);
                $point = $event[$kind] === '0' ? null : SemverVersion::tryParse($event[$kind]);
                $order = $point === null ? -1 : $point->compareTo($version);

                if ($kind === 'introduced' && $order <= 0) {
                    $inside = true;
                } elseif ($kind === 'fixed' && $order <= 0) {
                    $inside = false;
                } elseif ($kind === 'last_affected' && $order < 0) {
                    $inside = false;
                }
            }

            if ($inside) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{0: list<array{type: string, events: list<array<string, string>>}>, 1: list<string>}>  $advisories
     * @return array{0: FixMatchStatus|FixMatchReason, 1: ?string}
     */
    private function oracle(string $installed, array $advisories): array
    {
        $installedVersion = SemverVersion::tryParse($installed);
        $reasons = [];

        foreach ($advisories as [$ranges, $versions]) {
            if ($ranges === [] && $versions === []) {
                $reasons[] = FixMatchReason::NoRangeData;
            } elseif (! $this->affected($installedVersion, $ranges, $versions)) {
                $reasons[] = FixMatchReason::NotInListedRanges;
            }
        }

        foreach ([FixMatchReason::NoRangeData, FixMatchReason::NotInListedRanges] as $reason) {
            if (in_array($reason, $reasons, true)) {
                return [$reason, null];
            }
        }

        $fixed = [];

        foreach ($advisories as [$ranges]) {
            foreach ($ranges as $range) {
                foreach ($range['events'] as $event) {
                    if (isset($event['fixed'])) {
                        $fixed[] = SemverVersion::tryParse($event['fixed']);
                    }
                }
            }
        }

        usort($fixed, fn ($a, $b) => $a->compareTo($b));

        foreach ($fixed as $candidate) {
            if ($candidate->compareTo($installedVersion) <= 0) {
                continue;
            }

            $hit = false;

            foreach ($advisories as [$ranges, $versions]) {
                $hit = $hit || $this->affected($candidate, $ranges, $versions);
            }

            if (! $hit) {
                return [FixMatchStatus::FixAvailable, $candidate->original];
            }
        }

        return [FixMatchStatus::NoFixRecorded, null];
    }

    public function test_property_matches_a_naive_oracle_on_random_advisories_with_explicit_versions(): void
    {
        mt_srand(20261123);
        $version = fn () => mt_rand(0, 3).'.'.mt_rand(0, 3).'.'.mt_rand(0, 3).(mt_rand(0, 9) === 0 ? '-rc.'.mt_rand(0, 2) : '');
        $checked = 0;
        $fixAvailable = 0;
        $viaVersions = 0;

        for ($case = 0; $case < 300; $case++) {
            $advisories = [];

            for ($a = mt_rand(1, 3); $a > 0; $a--) {
                $ranges = [];

                // Satu dari enam advisori tidak punya range (hanya versions, atau kosong sama sekali).
                for ($r = mt_rand(0, 5) === 0 ? 0 : mt_rand(1, 2); $r > 0; $r--) {
                    $points = [];

                    for ($k = mt_rand(1, 3) * 2; $k > 0; $k--) {
                        $candidate = $version();

                        if (! in_array($candidate, $points, true)) {
                            $points[] = $candidate;
                        }
                    }

                    if (count($points) % 2 === 1) {
                        array_pop($points);
                    }

                    if ($points === []) {
                        continue;
                    }

                    usort($points, fn ($x, $y) => SemverVersion::tryParse($x)->compareTo(SemverVersion::tryParse($y)));
                    $events = [];

                    foreach ($points as $i => $point) {
                        $events[] = $i % 2 === 0
                            ? ['introduced' => ($i === 0 && mt_rand(0, 2) === 0) ? '0' : $point]
                            : [(mt_rand(0, 4) === 0 ? 'last_affected' : 'fixed') => $point];
                    }

                    if (mt_rand(0, 5) === 0) {
                        array_pop($events);
                    }

                    shuffle($events);
                    $ranges[] = ['type' => 'SEMVER', 'events' => $events];
                }

                $versions = [];

                for ($v = mt_rand(0, 3); $v > 0; $v--) {
                    $versions[] = $version().(mt_rand(0, 4) === 0 ? '+b' : '');
                }

                $advisories[] = [$ranges, array_values(array_unique($versions))];
            }

            for ($probe = 0; $probe < 8; $probe++) {
                $installed = $version().(mt_rand(0, 6) === 0 ? '+i' : '');
                $input = array_map(fn (array $advisory) => AdvisoryAffects::of($advisory[0], $advisory[1]), $advisories);
                $actual = $this->combined($installed, $input);
                [$expectedKind, $expectedFixed] = $this->oracle($installed, $advisories);
                $label = "$installed ".json_encode($advisories);

                $this->assertSame($expectedKind, $actual->status === FixMatchStatus::Undetermined ? $actual->reason : $actual->status, $label);
                $this->assertSame($expectedFixed, $actual->fixedVersion, $label);

                if (count($advisories) === 1) {
                    $this->assertEquals($actual, $this->one($installed, $advisories[0][0], $advisories[0][1]), "single $label");
                }

                if ($actual->status === FixMatchStatus::FixAvailable) {
                    $fixAvailable++;
                    $fixedVersion = SemverVersion::tryParse($actual->fixedVersion);
                    $this->assertGreaterThan(0, $fixedVersion->compareTo(SemverVersion::tryParse($installed)), $label);

                    foreach ($advisories as [$ranges, $versions]) {
                        $this->assertFalse($this->affected($fixedVersion, $ranges, $versions), $label);
                    }
                }

                if ($actual->status !== FixMatchStatus::Undetermined && array_filter($advisories, fn ($adv) => $adv[1] !== [])) {
                    $viaVersions++;
                }

                $checked++;
            }
        }

        $this->assertSame(2400, $checked);
        $this->assertGreaterThan(0, $fixAvailable, 'seed harus menghasilkan kasus fix_available agar oracle bermakna');
        $this->assertGreaterThan(0, $viaVersions, 'seed harus menghasilkan kasus terdampak dengan versions');
    }

    // --- data maksimum -------------------------------------------------------------------------------------------

    public function test_maximum_size_input_is_handled_quickly_without_recursion(): void
    {
        $start = microtime(true);
        $hit = $this->combined('10.2.0', $this->maximumInput());
        $elapsed = microtime(true) - $start;

        $this->assertFix($hit, '50.0.0');
        $this->assertLessThan(2.0, $elapsed);
    }

    /**
     * 200 advisori x (50 range x 100 event + 1000 versi). Setiap range menyambung 50 pasangan (satu interval gabungan);
     * 1000 versi sama pada semua advisori (1000 kunci distinct, di bawah anggaran) dan di luar interval.
     *
     * @return list<AdvisoryAffects>
     */
    private function maximumInput(): array
    {
        $events = [];

        for ($k = 0; $k < 50; $k++) {
            $events[] = ['introduced' => "$k.0.0"];
            $events[] = ['fixed' => ($k + 1).'.0.0'];
        }

        $ranges = array_fill(0, 50, ['type' => 'SEMVER', 'events' => $events]);
        $versions = array_map(fn (int $i) => "100.0.$i", range(1, 1000));

        return array_fill(0, 200, AdvisoryAffects::of($ranges, $versions));
    }
}
