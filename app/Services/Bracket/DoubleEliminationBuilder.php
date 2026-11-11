<?php

namespace App\Services\Bracket;

final class DoubleEliminationBuilder
{
    /**
     * @param  list<int|null>  $orderedTeamIds  Seed order: index 0 is seed 1.
     * @param  array<int, int>  $bestOfByRound  Keys: 0 final, 1 semifinal, 2 quarterfinal.
     */
    public function build(int $size, array $orderedTeamIds, array $bestOfByRound = []): BracketPlan
    {
        if (! in_array($size, [4, 8, 16, 32], true)) {
            throw new \InvalidArgumentException('Double elimination requires 4, 8, 16, or 32 slots.');
        }

        $seedOrder = SeedOrder::forSize($size);
        $rounds = (int) log($size, 2);
        $matchups = [];

        // Winners bracket: round 1..k, round k is the winners final.
        for ($round = 1; $round <= $rounds; $round++) {
            $count = intdiv($size, 2 ** $round);
            $fromFinal = $rounds - $round;
            $bestOf = $bestOfByRound[$fromFinal] ?? 1;

            for ($match = 1; $match <= $count; $match++) {
                if ($round === 1) {
                    $homeSeed = $seedOrder[($match - 1) * 2];
                    $awaySeed = $seedOrder[($match - 1) * 2 + 1];
                    $home = SlotSource::seed($orderedTeamIds[$homeSeed - 1] ?? null);
                    $away = SlotSource::seed($orderedTeamIds[$awaySeed - 1] ?? null);
                } else {
                    $home = SlotSource::winner('winners', $round - 1, $match * 2 - 1);
                    $away = SlotSource::winner('winners', $round - 1, $match * 2);
                }

                $matchups[] = new MatchupPlan('winners', $round, $match, $home, $away, $bestOf);
            }
        }

        // Losers round 1 receives the losers of winners round 1, paired adjacent.
        $firstLosersCount = intdiv($size, 4);
        for ($match = 1; $match <= $firstLosersCount; $match++) {
            $matchups[] = new MatchupPlan(
                'losers',
                1,
                $match,
                SlotSource::loser('winners', 1, $match * 2 - 1),
                SlotSource::loser('winners', 1, $match * 2),
                1,
            );
        }

        // For each winners round r >= 2:
        //   drop round 2r-2: winners of the previous losers round vs losers of winners round r.
        //   minor round 2r-1: winners of the drop round pair adjacent.
        for ($round = 2; $round <= $rounds; $round++) {
            $dropRound = 2 * $round - 2;
            $count = intdiv($size, 2 ** $round);

            for ($match = 1; $match <= $count; $match++) {
                $matchups[] = new MatchupPlan(
                    'losers',
                    $dropRound,
                    $match,
                    SlotSource::winner('losers', $dropRound - 1, $match),
                    SlotSource::loser('winners', $round, $match),
                    1,
                );
            }

            if ($round < $rounds) {
                $minorRound = 2 * $round - 1;
                $minorCount = intdiv($size, 2 ** ($round + 1));

                for ($match = 1; $match <= $minorCount; $match++) {
                    $matchups[] = new MatchupPlan(
                        'losers',
                        $minorRound,
                        $match,
                        SlotSource::winner('losers', $dropRound, $match * 2 - 1),
                        SlotSource::winner('losers', $dropRound, $match * 2),
                        1,
                    );
                }
            }
        }

        $lastLosersRound = 2 * ($rounds - 1);
        $grandFinalBestOf = $bestOfByRound[0] ?? 1;

        $matchups[] = new MatchupPlan(
            'grand_final',
            1,
            1,
            SlotSource::winner('winners', $rounds, 1),
            SlotSource::winner('losers', $lastLosersRound, 1),
            $grandFinalBestOf,
        );

        if ($grandFinalBestOf === 1) {
            $matchups[] = new MatchupPlan(
                'reset',
                1,
                1,
                SlotSource::winner('winners', $rounds, 1),
                SlotSource::winner('losers', $lastLosersRound, 1),
                1,
            );
        }

        return new BracketPlan($matchups);
    }
}
