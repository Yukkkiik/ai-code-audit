<?php

namespace Tests\Unit\Sca;

use App\Enums\FixMatchReason;
use App\Enums\FixMatchStatus;
use App\Enums\VersionChange;
use App\Services\Sca\FixedVersionMatcher;
use App\Services\Sca\FixMatch;
use App\Services\Sca\OsvVulnerabilityParser;
use App\Services\Sca\SemverVersion;
use PHPUnit\Framework\TestCase;

class CombinedFixMatchTest extends TestCase
{
    /**
     * @param  list<?array>  $advisories
     */
    private function combined(string $installed, array $advisories): FixMatch
    {
        return (new FixedVersionMatcher)->matchCombined($installed, $advisories);
    }

    /**
     * @param  list<array<string, string>>  $events
     * @return list<array{type: string, events: list<array<string, string>>}>
     */
    private function ranges(array $events, string $type = 'SEMVER'): array
    {
        return [['type' => $type, 'events' => $events]];
    }

    /**
     * @return list<array{type: string, events: list<array<string, string>>}>
     */
    private function fixtureRanges(string $file, string $id, string $package): array
    {
        $json = json_decode(file_get_contents(__DIR__.'/../../Fixtures/Sca/'.$file), true, 512, JSON_THROW_ON_ERROR);

        return OsvVulnerabilityParser::parse($json, $id)->affectedFor($package)['ranges'];
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

    // --- gabungan beberapa advisori ------------------------------------------------------------------------------

    public function test_a_candidate_inside_another_advisorys_interval_is_skipped(): void
    {
        $a = $this->ranges([['introduced' => '0'], ['fixed' => '2.4.0']]);
        $b = $this->ranges([['introduced' => '2.0.0'], ['fixed' => '2.5.0']]);

        // Per advisori A memberi 2.4.0, tetapi 2.4.0 masih terdampak B.
        $this->assertFix($this->combined('2.2.2', [$a, $b]), '2.5.0');
        $this->assertFix((new FixedVersionMatcher)->match('2.2.2', $a), '2.4.0');
        $this->assertSame(VersionChange::Minor, $this->combined('2.2.2', [$a, $b])->versionChange);
    }

    public function test_no_candidate_outside_every_interval_is_no_fix_recorded(): void
    {
        $a = $this->ranges([['introduced' => '0'], ['fixed' => '2.4.0']]);
        $b = $this->ranges([['introduced' => '2.0.0']]);

        $match = $this->combined('2.2.2', [$a, $b]);

        $this->assertSame(FixMatchStatus::NoFixRecorded, $match->status);
        $this->assertNull($match->fixedVersion);
        $this->assertNull($match->reason);
    }

    public function test_the_task_example_where_the_second_advisory_does_not_contain_the_installed_version_is_undetermined(): void
    {
        // B terdampak [2.3.0, 2.5.0) tidak memuat 2.2.2: OSV tetap mengembalikannya, jadi tidak boleh diabaikan.
        $a = $this->ranges([['introduced' => '0'], ['fixed' => '2.4.0']]);
        $b = $this->ranges([['introduced' => '2.3.0'], ['fixed' => '2.5.0']]);

        $match = $this->combined('2.2.2', [$a, $b]);

        $this->assertUndetermined($match, FixMatchReason::NotInListedRanges);
        $this->assertNotSame('2.4.0', $match->fixedVersion);
    }

    public function test_a_mixed_set_never_becomes_fix_available_when_one_advisory_misses_the_installed_version(): void
    {
        $contains = $this->ranges([['introduced' => '0'], ['fixed' => '3.0.0']]);
        $misses = $this->ranges([['introduced' => '5.0.0'], ['fixed' => '6.0.0']]);

        $this->assertUndetermined($this->combined('1.0.0', [$contains, $misses]), FixMatchReason::NotInListedRanges);
    }

    public function test_the_advisory_order_does_not_change_the_result(): void
    {
        $advisories = [
            $this->ranges([['introduced' => '0'], ['fixed' => '2.4.0']]),
            $this->ranges([['introduced' => '2.0.0'], ['fixed' => '2.5.0']]),
            $this->ranges([['introduced' => '2.2.0'], ['fixed' => '2.6.0']]),
            $this->ranges([['introduced' => '1.0.0'], ['last_affected' => '2.2.2']]),
        ];

        foreach ($this->permutations($advisories) as $order) {
            $this->assertFix($this->combined('2.2.2', $order), '2.6.0');
        }

        $withMissing = [$advisories[0], null, $this->ranges([['introduced' => '9.0.0']])];

        foreach ($this->permutations($withMissing) as $order) {
            $this->assertUndetermined($this->combined('2.2.2', $order), FixMatchReason::DetailUnavailable);
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

    // --- satu advisori sama dengan match() -----------------------------------------------------------------------

    public function test_a_single_advisory_equals_match_on_the_real_fixtures(): void
    {
        $cases = [
            ['osv-detail-body-parser.json', 'GHSA-27r3-jxmh-p828', 'body-parser', ['2.2.2', '1.20.0', '2.4.0', '3.0.0']],
            ['osv-detail-proxy-addr.json', 'GHSA-jqcg-44mw-7w3h', 'proxy-addr', ['2.0.7', '1.0.0', '2.0.8']],
            ['osv-detail-real.json', 'GHSA-35jh-r3h4-6jhm', 'lodash', ['4.17.15', '4.17.21', '3.0.0']],
            ['osv-detail-real.json', 'GHSA-35jh-r3h4-6jhm', 'lodash.template', ['4.5.0']],
        ];
        $matcher = new FixedVersionMatcher;
        $checked = 0;

        foreach ($cases as [$file, $id, $package, $versions]) {
            $ranges = $this->fixtureRanges($file, $id, $package);

            foreach ($versions as $installed) {
                $this->assertEquals($matcher->match($installed, $ranges), $matcher->matchCombined($installed, [$ranges]), "$package $installed");
                $checked++;
            }
        }

        $this->assertSame(11, $checked);
    }

    public function test_a_single_advisory_equals_match_for_unusable_input(): void
    {
        $matcher = new FixedVersionMatcher;
        $inputs = [
            null,
            [],
            [['type' => 'GIT', 'events' => [['introduced' => '0']]]],
            $this->ranges([['limit' => '2.0.0']]),
            $this->ranges([['introduced' => 'x']]),
            FixedVersionMatcher::UNREADABLE_RANGES,
        ];

        foreach ($inputs as $i => $ranges) {
            foreach (['1.0.0', '01.0.0'] as $installed) {
                $this->assertEquals($matcher->match($installed, $ranges), $matcher->matchCombined($installed, [$ranges]), "input $i, $installed");
            }
        }
    }

    public function test_an_open_range_without_fixed_has_no_recorded_fix(): void
    {
        $this->assertSame(FixMatchStatus::NoFixRecorded, $this->combined('1.0.0', [$this->ranges([['introduced' => '0']])])->status);
    }

    // --- tidak dapat dipastikan ----------------------------------------------------------------------------------

    public function test_an_empty_list_is_undetermined_with_no_range_data(): void
    {
        $this->assertUndetermined($this->combined('1.0.0', []), FixMatchReason::NoRangeData);
    }

    public function test_an_advisory_without_detail_makes_everything_undetermined(): void
    {
        $fixable = $this->ranges([['introduced' => '0'], ['fixed' => '2.0.0']]);

        $this->assertUndetermined($this->combined('1.0.0', [$fixable, null]), FixMatchReason::DetailUnavailable);
    }

    public function test_an_advisory_with_only_git_ranges_has_no_range_data(): void
    {
        $git = [['type' => 'GIT', 'events' => [['introduced' => 'abc123']]]];
        $fixable = $this->ranges([['introduced' => '0'], ['fixed' => '2.0.0']]);

        $this->assertUndetermined($this->combined('1.0.0', [$git]), FixMatchReason::NoRangeData);
        $this->assertUndetermined($this->combined('1.0.0', [$fixable, $git]), FixMatchReason::NoRangeData);
    }

    public function test_reason_priority_matrix(): void
    {
        $fixable = $this->ranges([['introduced' => '0'], ['fixed' => '2.0.0']]);
        $reasons = [
            'detail_unavailable' => null,
            'unevaluable_range' => $this->ranges([['limit' => '2.0.0']]),
            'no_range_data' => [],
            'not_in_listed_ranges' => $this->ranges([['introduced' => '5.0.0'], ['fixed' => '6.0.0']]),
        ];
        $priority = ['detail_unavailable', 'invalid_version', 'unevaluable_range', 'no_range_data', 'not_in_listed_ranges'];

        // Setiap pasangan: yang berprioritas lebih tinggi menang, di kedua urutan.
        foreach ($reasons as $x => $xRanges) {
            foreach ($reasons as $y => $yRanges) {
                $expected = $priority[min(array_search($x, $priority, true), array_search($y, $priority, true))];

                $this->assertUndetermined($this->combined('1.0.0', [$xRanges, $fixable, $yRanges]), FixMatchReason::from($expected));
                $this->assertUndetermined($this->combined('1.0.0', [$yRanges, $fixable, $xRanges]), FixMatchReason::from($expected));
            }
        }

        // invalid_version: versi terpasang bukan semver ketat; kalah dari detail_unavailable, menang dari sisanya.
        $this->assertUndetermined($this->combined('01.0.0', [$fixable, $reasons['unevaluable_range']]), FixMatchReason::InvalidVersion);
        $this->assertUndetermined($this->combined('01.0.0', [$fixable, $reasons['no_range_data']]), FixMatchReason::InvalidVersion);
        $this->assertUndetermined($this->combined('01.0.0', [$fixable, null]), FixMatchReason::DetailUnavailable);
    }

    // --- batas ---------------------------------------------------------------------------------------------------

    public function test_the_interval_budget_is_a_named_constant_and_overrun_is_unevaluable(): void
    {
        $this->assertSame(20000, FixedVersionMatcher::MAX_COMBINED_INTERVALS);

        // Satu advisori = 1000 interval terpisah (20 range x 50 pasangan), tetap 1000 setelah digabung per advisori.
        $ranges = [];

        for ($r = 0; $r < 20; $r++) {
            $events = [];

            for ($k = 0; $k < 50; $k++) {
                $events[] = ['introduced' => (($r * 50 + $k) * 2).'.0.0'];
                $events[] = ['fixed' => (($r * 50 + $k) * 2 + 1).'.0.0'];
            }

            $ranges[] = ['type' => 'SEMVER', 'events' => $events];
        }

        // 21 x 1000 = 21.000 > 20.000: ditolak, apa pun urutannya.
        $this->assertUndetermined($this->combined('0.0.5', array_fill(0, 21, $ranges)), FixMatchReason::UnevaluableRange);
        $this->assertUndetermined($this->combined('0.0.5', [null, ...array_fill(0, 21, $ranges)]), FixMatchReason::DetailUnavailable);

        // 20 x 1000 = tepat di anggaran: masih dievaluasi.
        $this->assertFix($this->combined('0.0.5', array_fill(0, 20, $ranges)), '1.0.0');
    }

    public function test_the_advisory_count_cap_belongs_to_the_resolver_not_the_matcher(): void
    {
        // Matcher tidak membatasi jumlah advisori; resolver yang membatasi (dites di feature test).
        $advisories = array_fill(0, 250, $this->ranges([['introduced' => '0'], ['fixed' => '2.0.0']]));

        $this->assertFix($this->combined('1.0.0', $advisories), '2.0.0');
    }

    public function test_maximum_size_input_is_handled_quickly_without_recursion(): void
    {
        $advisories = $this->maximumInput();

        $start = microtime(true);
        $hit = $this->combined('10.2.0', $advisories);
        $elapsed = microtime(true) - $start;

        $this->assertFix($hit, '50.0.0');
        $this->assertLessThan(2.0, $elapsed);
    }

    /**
     * 200 advisori x 50 range x 100 event; setiap range menyambung 50 pasangan sehingga hasil gabungannya satu interval.
     *
     * @return list<list<array{type: string, events: list<array<string, string>>}>>
     */
    private function maximumInput(): array
    {
        $events = [];

        for ($k = 0; $k < 50; $k++) {
            $events[] = ['introduced' => "$k.0.0"];
            $events[] = ['fixed' => ($k + 1).'.0.0'];
        }

        $advisory = array_fill(0, 50, ['type' => 'SEMVER', 'events' => $events]);

        return array_fill(0, 200, $advisory);
    }

    // --- property test dengan oracle naif ------------------------------------------------------------------------

    /**
     * Oracle independen: keanggotaan versi dihitung dengan menelusuri event terurut, bukan dengan interval.
     *
     * @param  list<array{type: string, events: list<array<string, string>>}>  $ranges
     */
    private function affected(SemverVersion $version, array $ranges): bool
    {
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
     * @param  list<list<array{type: string, events: list<array<string, string>>}>>  $advisories
     * @return array{0: FixMatchStatus|FixMatchReason, 1: ?string}
     */
    private function oracle(string $installed, array $advisories): array
    {
        $installedVersion = SemverVersion::tryParse($installed);

        foreach ($advisories as $ranges) {
            if (! $this->affected($installedVersion, $ranges)) {
                return [FixMatchReason::NotInListedRanges, null];
            }
        }

        $fixed = [];

        foreach ($advisories as $ranges) {
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

            foreach ($advisories as $ranges) {
                $hit = $hit || $this->affected($candidate, $ranges);
            }

            if (! $hit) {
                return [FixMatchStatus::FixAvailable, $candidate->original];
            }
        }

        return [FixMatchStatus::NoFixRecorded, null];
    }

    public function test_property_matches_a_naive_oracle_on_random_well_formed_advisories(): void
    {
        mt_srand(20261123);
        $version = fn () => mt_rand(0, 3).'.'.mt_rand(0, 3).'.'.mt_rand(0, 3).(mt_rand(0, 9) === 0 ? '-rc.'.mt_rand(0, 2) : '');
        $checked = 0;
        $fixAvailable = 0;

        for ($case = 0; $case < 300; $case++) {
            $advisories = [];

            for ($a = mt_rand(1, 3); $a > 0; $a--) {
                $ranges = [];

                for ($r = mt_rand(1, 2); $r > 0; $r--) {
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

                if ($ranges !== []) {
                    $advisories[] = $ranges;
                }
            }

            if ($advisories === []) {
                $case--;

                continue;
            }

            for ($probe = 0; $probe < 8; $probe++) {
                $installed = $version();
                $actual = $this->combined($installed, $advisories);
                [$expectedKind, $expectedFixed] = $this->oracle($installed, $advisories);
                $label = "$installed ".json_encode($advisories);

                $this->assertSame($expectedKind, $actual->status === FixMatchStatus::Undetermined ? $actual->reason : $actual->status, $label);
                $this->assertSame($expectedFixed, $actual->fixedVersion, $label);

                if ($actual->status === FixMatchStatus::FixAvailable) {
                    $fixAvailable++;
                    $fixedVersion = SemverVersion::tryParse($actual->fixedVersion);
                    $this->assertGreaterThan(0, $fixedVersion->compareTo(SemverVersion::tryParse($installed)), $label);

                    foreach ($advisories as $ranges) {
                        $this->assertFalse($this->affected($fixedVersion, $ranges), $label);
                    }
                }

                $checked++;
            }
        }

        $this->assertSame(2400, $checked);
        $this->assertGreaterThan(0, $fixAvailable, 'seed harus menghasilkan kasus fix_available agar oracle bermakna');
    }

    public function test_the_matcher_only_returns_values_written_as_fixed(): void
    {
        $advisories = [
            $this->ranges([['introduced' => '0'], ['last_affected' => '1.4.0']]),
            $this->ranges([['introduced' => '1.0.0'], ['fixed' => '1.5.0']]),
        ];

        // 1.4.1 tidak pernah disimpulkan dari last_affected; hanya `fixed` tertulis yang boleh keluar.
        $match = $this->combined('1.2.0', $advisories);

        $this->assertFix($match, '1.5.0');
        $this->assertSame(VersionChange::Minor, $match->versionChange);
    }
}
