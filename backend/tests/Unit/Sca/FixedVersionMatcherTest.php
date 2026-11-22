<?php

namespace Tests\Unit\Sca;

use App\Enums\FixMatchReason;
use App\Enums\FixMatchStatus;
use App\Enums\VersionChange;
use App\Services\Sca\FixedVersionMatcher;
use App\Services\Sca\FixMatch;
use App\Services\Sca\OsvVulnerabilityParser;
use App\Services\Sca\SemverVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FixedVersionMatcherTest extends TestCase
{
    /**
     * Rentang untuk satu paket dari fixture OSV nyata, lewat parser yang sama dengan penyimpanan.
     *
     * @return list<array{type: string, events: list<array<string, string>>}>
     */
    private function fixtureRanges(string $file, string $id, string $package): array
    {
        $json = json_decode(file_get_contents(__DIR__.'/../../Fixtures/Sca/'.$file), true, 512, JSON_THROW_ON_ERROR);

        return OsvVulnerabilityParser::parse($json, $id)->affectedFor($package)['ranges'];
    }

    private function match(string $installed, ?array $ranges): FixMatch
    {
        return (new FixedVersionMatcher)->match($installed, $ranges);
    }

    /**
     * @param  list<array<string, string>>  $events
     * @return array{type: string, events: list<array<string, string>>}
     */
    private function range(array $events, string $type = 'SEMVER'): array
    {
        return ['type' => $type, 'events' => $events];
    }

    private function assertFix(FixMatch $match, string $fixed): void
    {
        $this->assertSame(FixMatchStatus::FixAvailable, $match->status);
        $this->assertSame($fixed, $match->fixedVersion);
        $this->assertNull($match->reason);
    }

    private function assertUndetermined(FixMatch $match, FixMatchReason $reason): void
    {
        $this->assertSame(FixMatchStatus::Undetermined, $match->status);
        $this->assertSame($reason, $match->reason);
        $this->assertNull($match->fixedVersion);
        $this->assertNull($match->versionChange);
    }

    // --- fixture nyata -------------------------------------------------------------------------------------------

    public function test_body_parser_2_2_2_gets_2_4_0_and_not_the_older_branch_fix(): void
    {
        $ranges = $this->fixtureRanges('osv-detail-body-parser.json', 'GHSA-27r3-jxmh-p828', 'body-parser');

        $this->assertCount(2, $ranges, 'kedua entri affected untuk paket yang sama harus ikut');
        $this->assertFix($this->match('2.2.2', $ranges), '2.4.0');
    }

    public function test_body_parser_1_20_0_gets_1_20_9(): void
    {
        $ranges = $this->fixtureRanges('osv-detail-body-parser.json', 'GHSA-27r3-jxmh-p828', 'body-parser');

        $this->assertFix($this->match('1.20.0', $ranges), '1.20.9');
    }

    public function test_body_parser_boundaries_and_gaps(): void
    {
        $ranges = $this->fixtureRanges('osv-detail-body-parser.json', 'GHSA-27r3-jxmh-p828', 'body-parser');

        $this->assertFix($this->match('2.0.0', $ranges), '2.4.0');
        $this->assertFix($this->match('0.0.1', $ranges), '1.20.9');
        $this->assertFix($this->match('1.20.8', $ranges), '1.20.9');
        $this->assertUndetermined($this->match('1.20.9', $ranges), FixMatchReason::NotInListedRanges);
        $this->assertUndetermined($this->match('1.21.0', $ranges), FixMatchReason::NotInListedRanges);
        $this->assertUndetermined($this->match('1.99.99', $ranges), FixMatchReason::NotInListedRanges);
        $this->assertUndetermined($this->match('2.4.0', $ranges), FixMatchReason::NotInListedRanges);
        $this->assertUndetermined($this->match('3.0.0', $ranges), FixMatchReason::NotInListedRanges);
    }

    public function test_proxy_addr_2_0_7_gets_2_0_8(): void
    {
        $ranges = $this->fixtureRanges('osv-detail-proxy-addr.json', 'GHSA-jqcg-44mw-7w3h', 'proxy-addr');

        $this->assertFix($this->match('2.0.7', $ranges), '2.0.8');
        $this->assertFix($this->match('1.1.0', $ranges), '2.0.8');
    }

    public function test_proxy_addr_below_introduced_is_undetermined(): void
    {
        $ranges = $this->fixtureRanges('osv-detail-proxy-addr.json', 'GHSA-jqcg-44mw-7w3h', 'proxy-addr');

        $this->assertUndetermined($this->match('1.0.0', $ranges), FixMatchReason::NotInListedRanges);
        $this->assertUndetermined($this->match('1.0.99', $ranges), FixMatchReason::NotInListedRanges);
    }

    public function test_proxy_addr_at_the_fixed_boundary_is_undetermined_because_fixed_is_exclusive(): void
    {
        $ranges = $this->fixtureRanges('osv-detail-proxy-addr.json', 'GHSA-jqcg-44mw-7w3h', 'proxy-addr');

        $this->assertUndetermined($this->match('2.0.8', $ranges), FixMatchReason::NotInListedRanges);
        $this->assertUndetermined($this->match('2.1.0', $ranges), FixMatchReason::NotInListedRanges);
    }

    public function test_real_lodash_fixture(): void
    {
        $lodash = $this->fixtureRanges('osv-detail-real.json', 'GHSA-35jh-r3h4-6jhm', 'lodash');
        $template = $this->fixtureRanges('osv-detail-real.json', 'GHSA-35jh-r3h4-6jhm', 'lodash.template');

        $this->assertFix($this->match('4.17.15', $lodash), '4.17.21');
        $this->assertUndetermined($this->match('4.17.21', $lodash), FixMatchReason::NotInListedRanges);

        // lodash.template hanya mencatat last_affected (inklusif), tanpa versi perbaikan.
        $this->assertSame(FixMatchStatus::NoFixRecorded, $this->match('4.5.0', $template)->status);
        $this->assertNull($this->match('4.5.0', $template)->fixedVersion);
        $this->assertNull($this->match('4.5.0', $template)->versionChange);
        $this->assertUndetermined($this->match('4.5.1', $template), FixMatchReason::NotInListedRanges);
    }

    // --- semantik interval ---------------------------------------------------------------------------------------

    public function test_candidates_are_chosen_per_interval(): void
    {
        $ranges = [$this->range([['introduced' => '1.0.0'], ['fixed' => '1.5.0'], ['introduced' => '1.6.0'], ['fixed' => '2.0.0']])];

        $this->assertFix($this->match('1.2.0', $ranges), '1.5.0');
        $this->assertFix($this->match('1.7.0', $ranges), '2.0.0');
        $this->assertUndetermined($this->match('1.5.5', $ranges), FixMatchReason::NotInListedRanges);
    }

    public function test_a_candidate_inside_another_still_affected_interval_is_skipped(): void
    {
        $ranges = [
            $this->range([['fixed' => '3.0.0'], ['introduced' => '1.5.0']]),
            $this->range([['introduced' => '0'], ['fixed' => '2.0.0']]),
        ];

        // 2.0.0 jatuh di [1.5.0, 3.0.0) yang masih terdampak, jadi dilewati.
        $this->assertFix($this->match('1.0.0', $ranges), '3.0.0');
        $this->assertFix($this->match('1.6.0', $ranges), '3.0.0');
        $this->assertFix($this->match('2.5.0', $ranges), '3.0.0');
    }

    public function test_overlapping_ranges_are_combined(): void
    {
        $ranges = [
            $this->range([['introduced' => '0'], ['fixed' => '2.0.0']]),
            $this->range([['introduced' => '1.0.0'], ['fixed' => '1.5.0']]),
        ];

        $this->assertFix($this->match('1.2.0', $ranges), '2.0.0');
        $this->assertFix($this->match('0.5.0', $ranges), '2.0.0');
    }

    public function test_contiguous_intervals_are_treated_as_one(): void
    {
        $ranges = [$this->range([['introduced' => '0'], ['fixed' => '1.5.0'], ['introduced' => '1.5.0'], ['fixed' => '2.0.0']])];

        // 1.5.0 sendiri masih terdampak oleh interval kedua, jadi bukan versi perbaikan untuk 1.0.0.
        $this->assertFix($this->match('1.0.0', $ranges), '2.0.0');
    }

    public function test_events_may_arrive_in_any_order(): void
    {
        $sorted = [$this->range([['introduced' => '1.0.0'], ['fixed' => '1.5.0'], ['introduced' => '1.6.0'], ['fixed' => '2.0.0']])];
        $shuffled = [$this->range([['fixed' => '2.0.0'], ['introduced' => '1.6.0'], ['fixed' => '1.5.0'], ['introduced' => '1.0.0']])];

        foreach (['1.0.0', '1.2.0', '1.5.0', '1.7.0', '2.0.0'] as $installed) {
            $this->assertEquals($this->match($installed, $sorted), $this->match($installed, $shuffled), $installed);
        }
    }

    public function test_introduced_zero_covers_the_lowest_versions(): void
    {
        $ranges = [$this->range([['introduced' => '0'], ['fixed' => '1.0.0']])];

        $this->assertFix($this->match('0.0.0', $ranges), '1.0.0');
        $this->assertFix($this->match('0.0.1', $ranges), '1.0.0');
        $this->assertFix($this->match('1.0.0-rc.1', $ranges), '1.0.0');
    }

    public function test_introduced_is_inclusive_and_fixed_is_exclusive(): void
    {
        $ranges = [$this->range([['introduced' => '1.0.0'], ['fixed' => '2.0.0']])];

        $this->assertFix($this->match('1.0.0', $ranges), '2.0.0');
        $this->assertFix($this->match('1.9.9', $ranges), '2.0.0');
        $this->assertUndetermined($this->match('2.0.0', $ranges), FixMatchReason::NotInListedRanges);
        $this->assertUndetermined($this->match('1.0.0-rc.1', $ranges), FixMatchReason::NotInListedRanges);
    }

    public function test_last_affected_is_inclusive_and_never_a_fix(): void
    {
        $ranges = [$this->range([['introduced' => '1.0.0'], ['last_affected' => '1.4.0']])];

        $this->assertSame(FixMatchStatus::NoFixRecorded, $this->match('1.4.0', $ranges)->status);
        $this->assertSame(FixMatchStatus::NoFixRecorded, $this->match('1.0.0', $ranges)->status);
        $this->assertUndetermined($this->match('1.4.1', $ranges), FixMatchReason::NotInListedRanges);
    }

    public function test_an_open_ended_range_has_no_recorded_fix(): void
    {
        $ranges = [$this->range([['introduced' => '1.0.0']])];

        $this->assertSame(FixMatchStatus::NoFixRecorded, $this->match('99.0.0', $ranges)->status);
        $this->assertUndetermined($this->match('0.9.0', $ranges), FixMatchReason::NotInListedRanges);
    }

    public function test_fixed_versions_not_greater_than_installed_do_not_count(): void
    {
        $ranges = [$this->range([['introduced' => '2.0.0']]), $this->range([['introduced' => '0'], ['fixed' => '1.0.0']])];

        $this->assertSame(FixMatchStatus::NoFixRecorded, $this->match('2.5.0', $ranges)->status);
    }

    public function test_equal_introduced_and_fixed_is_an_empty_interval(): void
    {
        $ranges = [$this->range([['introduced' => '1.0.0'], ['fixed' => '1.0.0']])];

        $this->assertUndetermined($this->match('1.0.0', $ranges), FixMatchReason::NotInListedRanges);
    }

    public function test_equal_introduced_and_last_affected_is_a_single_version(): void
    {
        $ranges = [$this->range([['introduced' => '1.0.0'], ['last_affected' => '1.0.0']])];

        $this->assertSame(FixMatchStatus::NoFixRecorded, $this->match('1.0.0', $ranges)->status);
        $this->assertUndetermined($this->match('1.0.1', $ranges), FixMatchReason::NotInListedRanges);
    }

    public function test_a_repeated_introduced_keeps_the_earliest(): void
    {
        $ranges = [$this->range([['introduced' => '1.0.0'], ['introduced' => '1.2.0'], ['fixed' => '2.0.0']])];

        $this->assertFix($this->match('1.1.0', $ranges), '2.0.0');
    }

    public function test_ecosystem_ranges_are_evaluated_like_semver_as_an_assumption(): void
    {
        $ranges = [$this->range([['introduced' => '0'], ['fixed' => '1.2.3']], 'ECOSYSTEM')];

        $this->assertFix($this->match('1.0.0', $ranges), '1.2.3');
    }

    public function test_git_and_unknown_range_types_are_ignored(): void
    {
        $git = $this->range([['introduced' => 'abc123def'], ['fixed' => 'def456abc']], 'GIT');
        $unknown = $this->range([['introduced' => '0'], ['fixed' => '9.9.9']], 'CUSTOM');
        $semver = $this->range([['introduced' => '0'], ['fixed' => '1.2.3']]);

        $this->assertFix($this->match('1.0.0', [$git, $unknown, $semver]), '1.2.3');
        $this->assertUndetermined($this->match('1.0.0', [$git]), FixMatchReason::NoRangeData);
        $this->assertUndetermined($this->match('1.0.0', [$git, $unknown]), FixMatchReason::NoRangeData);
    }

    // --- tidak dapat dipastikan ----------------------------------------------------------------------------------

    public function test_missing_detail_or_ranges(): void
    {
        $this->assertUndetermined($this->match('1.0.0', null), FixMatchReason::DetailUnavailable);
        $this->assertUndetermined($this->match('1.0.0', []), FixMatchReason::NoRangeData);
    }

    #[DataProvider('invalidInstalled')]
    public function test_invalid_installed_versions_are_undetermined(string $installed): void
    {
        $this->assertUndetermined($this->match($installed, [$this->range([['introduced' => '0'], ['fixed' => '2.0.0']])]), FixMatchReason::InvalidVersion);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidInstalled(): array
    {
        return array_map(fn ($v) => [$v], ['range' => '^1.2.3', 'empty' => '', 'two segments' => '1.2', 'tag' => 'latest', 'leading zero' => '01.2.3', 'prefix' => 'v1.2.3']);
    }

    public function test_installed_version_is_checked_before_range_data_but_after_missing_detail(): void
    {
        $this->assertUndetermined($this->match('bogus', null), FixMatchReason::DetailUnavailable);
        $this->assertUndetermined($this->match('bogus', []), FixMatchReason::InvalidVersion);
    }

    #[DataProvider('unevaluableRanges')]
    public function test_unevaluable_ranges_are_undetermined_and_never_guessed(array $events): void
    {
        $this->assertUndetermined($this->match('1.0.0', [$this->range($events)]), FixMatchReason::UnevaluableRange);
        $this->assertUndetermined($this->match('5.0.0', [$this->range($events)]), FixMatchReason::UnevaluableRange);
    }

    /**
     * @return array<string, array{list<array<string, string>>}>
     */
    public static function unevaluableRanges(): array
    {
        return [
            'limit event' => [[['introduced' => '0'], ['limit' => '3.0.0']]],
            'limit with fixed' => [[['introduced' => '0'], ['fixed' => '2.0.0'], ['limit' => '3.0.0']]],
            'fixed without introduced' => [[['fixed' => '2.0.0']]],
            'last_affected without introduced' => [[['last_affected' => '2.0.0']]],
            'second close without open' => [[['introduced' => '0'], ['fixed' => '1.0.0'], ['fixed' => '2.0.0']]],
            'non semver introduced' => [[['introduced' => 'abc'], ['fixed' => '2.0.0']]],
            'partial introduced' => [[['introduced' => '1.2'], ['fixed' => '2.0.0']]],
            'prefixed fixed' => [[['introduced' => '0'], ['fixed' => 'v2.0.0']]],
            'empty fixed' => [[['introduced' => '0'], ['fixed' => '']]],
            'unknown event key' => [[['introduced' => '0'], ['revoked' => '2.0.0']]],
            'empty event' => [[['introduced' => '0'], []]],
            'only unknown events' => [[['foo' => '1']]],
            'no events' => [[]],
        ];
    }

    public function test_an_unevaluable_range_blocks_a_confident_answer_even_when_another_range_matches(): void
    {
        $good = $this->range([['introduced' => '0'], ['fixed' => '2.0.0']]);
        $bad = $this->range([['introduced' => '0'], ['limit' => '9.0.0']]);

        $this->assertUndetermined($this->match('1.0.0', [$good, $bad]), FixMatchReason::UnevaluableRange);
        $this->assertUndetermined($this->match('5.0.0', [$good, $bad]), FixMatchReason::UnevaluableRange);
    }

    public function test_never_reports_not_affected(): void
    {
        $this->assertSame(['fix_available', 'no_fix_recorded', 'undetermined'], array_map(fn ($c) => $c->value, FixMatchStatus::cases()));
    }

    // --- hasil ---------------------------------------------------------------------------------------------------

    public function test_the_fixed_version_is_returned_exactly_as_written_in_osv(): void
    {
        $match = $this->match('1.0.0', [$this->range([['introduced' => '0'], ['fixed' => '2.4.0+build.5']])]);

        $this->assertFix($match, '2.4.0+build.5');
    }

    public function test_installed_version_is_echoed_as_given(): void
    {
        $this->assertSame('2.2.2', $this->match('2.2.2', [$this->range([['introduced' => '0'], ['fixed' => '3.0.0']])])->installedVersion);
        $this->assertSame('bogus', $this->match('bogus', [])->installedVersion);
    }

    #[DataProvider('versionChanges')]
    public function test_version_change_hint(string $installed, string $fixed, VersionChange $expected): void
    {
        $match = $this->match($installed, [$this->range([['introduced' => '0'], ['fixed' => $fixed]])]);

        $this->assertFix($match, $fixed);
        $this->assertSame($expected, $match->versionChange);
    }

    /**
     * @return array<string, array{string, string, VersionChange}>
     */
    public static function versionChanges(): array
    {
        return [
            'patch' => ['1.2.3', '1.2.4', VersionChange::Patch],
            'minor' => ['1.2.3', '1.3.0', VersionChange::Minor],
            'major' => ['1.2.3', '2.0.0', VersionChange::Major],
            'minor beats patch' => ['1.2.9', '1.4.0', VersionChange::Minor],
            'major beats minor' => ['1.9.9', '3.0.0', VersionChange::Major],
            'installed prerelease' => ['2.4.0-rc.1', '2.4.0', VersionChange::Prerelease],
            'fixed prerelease' => ['2.0.0', '2.4.0-rc.1', VersionChange::Prerelease],
            'both prerelease' => ['1.0.0-alpha', '1.0.0-beta', VersionChange::Prerelease],
            'build only does not count' => ['1.0.0', '1.0.1+build', VersionChange::Patch],
        ];
    }

    public function test_prerelease_installed_version_is_matched_by_semver_precedence(): void
    {
        $ranges = [$this->range([['introduced' => '2.0.0'], ['fixed' => '2.4.0']])];

        $this->assertFix($this->match('2.4.0-rc.1', $ranges), '2.4.0');
        $this->assertUndetermined($this->match('2.0.0-rc.1', $ranges), FixMatchReason::NotInListedRanges);
    }

    // --- propertinya dan batas biaya -------------------------------------------------------------------------------

    /**
     * @param  list<array{type: string, events: list<array<string, string>>}>  $ranges
     * @return array{0: FixMatchStatus|FixMatchReason, 1: string|null}
     */
    private function oracle(string $installed, array $ranges): array
    {
        $installedVersion = SemverVersion::tryParse($installed);
        $intervals = [];
        $fixed = [];

        foreach ($ranges as $range) {
            $events = $range['events'];
            usort($events, function ($a, $b) {
                $va = array_key_first($a) === 'introduced' && current($a) === '0' ? null : SemverVersion::tryParse((string) current($a));
                $vb = array_key_first($b) === 'introduced' && current($b) === '0' ? null : SemverVersion::tryParse((string) current($b));

                return match (true) {
                    $va === null && $vb === null => 0,
                    $va === null => -1,
                    $vb === null => 1,
                    default => $va->compareTo($vb),
                };
            });
            $open = false;
            $start = null;

            foreach ($events as $event) {
                $kind = array_key_first($event);
                $value = $kind === 'introduced' && $event[$kind] === '0' ? null : SemverVersion::tryParse($event[$kind]);

                if ($kind === 'introduced') {
                    if (! $open) {
                        [$open, $start] = [true, $value];
                    }

                    continue;
                }

                $intervals[] = [$start, $value, $kind === 'last_affected'];
                $open = false;

                if ($kind === 'fixed') {
                    $fixed[] = $value;
                }
            }

            if ($open) {
                $intervals[] = [$start, null, false];
            }
        }

        $contains = function (SemverVersion $v) use ($intervals): bool {
            foreach ($intervals as [$start, $end, $inclusive]) {
                $afterStart = $start === null || $v->compareTo($start) >= 0;
                $beforeEnd = $end === null || ($inclusive ? $v->compareTo($end) <= 0 : $v->compareTo($end) < 0);

                if ($afterStart && $beforeEnd) {
                    return true;
                }
            }

            return false;
        };

        if (! $contains($installedVersion)) {
            return [FixMatchReason::NotInListedRanges, null];
        }

        $candidates = array_filter($fixed, fn ($f) => $f->compareTo($installedVersion) > 0);
        usort($candidates, fn ($a, $b) => $a->compareTo($b));

        foreach ($candidates as $candidate) {
            if (! $contains($candidate)) {
                return [FixMatchStatus::FixAvailable, $candidate->original];
            }
        }

        return [FixMatchStatus::NoFixRecorded, null];
    }

    public function test_property_matches_a_naive_oracle_on_random_well_formed_ranges(): void
    {
        mt_srand(20261120);
        $version = fn () => mt_rand(0, 3).'.'.mt_rand(0, 3).'.'.mt_rand(0, 3).(mt_rand(0, 9) === 0 ? '-rc.'.mt_rand(0, 2) : '');
        $checked = 0;

        for ($case = 0; $case < 400; $case++) {
            $ranges = [];

            for ($r = mt_rand(1, 3); $r > 0; $r--) {
                $points = [];

                for ($k = mt_rand(1, 3) * 2; $k > 0; $k--) {
                    $candidate = $version();

                    // Titik berbeda: versi sama pada satu range hanya diuji di test berurutan di atas.
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

                usort($points, fn ($a, $b) => SemverVersion::tryParse($a)->compareTo(SemverVersion::tryParse($b)));
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
                $ranges[] = $this->range($events);
            }

            if ($ranges === []) {
                $case--;

                continue;
            }

            for ($probe = 0; $probe < 6; $probe++) {
                $installed = $version();
                $actual = $this->match($installed, $ranges);
                [$expectedKind, $expectedFixed] = $this->oracle($installed, $ranges);

                $this->assertSame($expectedKind, $actual->status === FixMatchStatus::Undetermined ? $actual->reason : $actual->status, "$installed ".json_encode($ranges));
                $this->assertSame($expectedFixed, $actual->fixedVersion, "$installed ".json_encode($ranges));

                if ($actual->status === FixMatchStatus::FixAvailable) {
                    $this->assertGreaterThan(0, SemverVersion::tryParse($actual->fixedVersion)->compareTo(SemverVersion::tryParse($installed)));
                    $this->assertContains($actual->fixedVersion, array_merge(...array_map(fn ($r) => array_map(fn ($e) => $e['fixed'] ?? '', $r['events']), $ranges)));
                }

                $checked++;
            }
        }

        $this->assertSame(2400, $checked);
    }

    public function test_maximum_size_input_is_handled_quickly_without_recursion(): void
    {
        $ranges = [];

        for ($r = 0; $r < 50; $r++) {
            $events = [];

            for ($k = 0; $k < 50; $k++) {
                $events[] = ['introduced' => ($r * 50 + $k).'.0.0'];
                $events[] = ['fixed' => ($r * 50 + $k).'.5.0'];
            }

            $ranges[] = $this->range($events);
        }

        $this->assertCount(100, $ranges[0]['events']);

        $start = microtime(true);
        $hit = $this->match('1234.2.0', $ranges);
        $miss = $this->match('1234.7.0', $ranges);
        $zero = $this->match('0.1.0', $ranges);
        $elapsed = microtime(true) - $start;

        $this->assertFix($hit, '1234.5.0');
        $this->assertUndetermined($miss, FixMatchReason::NotInListedRanges);
        $this->assertFix($zero, '0.5.0');
        $this->assertLessThan(2.0, $elapsed);
    }

    public function test_oversized_input_is_refused_instead_of_evaluated(): void
    {
        $tooManyRanges = array_fill(0, 101, $this->range([['introduced' => '0'], ['fixed' => '1.0.0']]));
        $tooManyEvents = [$this->range(array_map(fn ($i) => ['introduced' => "$i.0.0"], range(1, 201)))];

        $this->assertUndetermined($this->match('0.5.0', $tooManyRanges), FixMatchReason::UnevaluableRange);
        $this->assertUndetermined($this->match('0.5.0', $tooManyEvents), FixMatchReason::UnevaluableRange);
    }

    public function test_the_matcher_only_returns_values_written_as_fixed(): void
    {
        $lastAffected = $this->range([['introduced' => '0'], ['last_affected' => '1.4.0']]);
        $otherBranch = $this->range([['introduced' => '2.0.0'], ['fixed' => '2.5.0']]);

        // Hanya last_affected: tidak ada nilai `fixed` untuk dipilih, dan 1.4.1 tidak boleh disimpulkan.
        $alone = $this->match('1.0.0', [$lastAffected]);
        $this->assertSame(FixMatchStatus::NoFixRecorded, $alone->status);
        $this->assertNull($alone->fixedVersion);

        // Dengan cabang lain yang punya `fixed`: nilai itu tertulis di OSV, lebih besar, dan tidak terdampak;
        // lompatan ditandai major sebagai petunjuk.
        $withOtherBranch = $this->match('1.0.0', [$lastAffected, $otherBranch]);
        $this->assertFix($withOtherBranch, '2.5.0');
        $this->assertSame(VersionChange::Major, $withOtherBranch->versionChange);
        $this->assertFix($this->match('2.1.0', [$lastAffected, $otherBranch]), '2.5.0');
    }
}
