<?php

/** Domain service for Single elimination builder. */

namespace App\Services\Bracket;

final class SingleEliminationBuilder
{
    /**
     * @param  list<int|null>  $orderedTeamIds  Seed order: index 0 is seed 1.
     * @param  array<int, int>  $bestOfByRound  Keys: 0 final, 1 semifinal, 2 quarterfinal.
     */
    public function build(int $size, array $orderedTeamIds, array $bestOfByRound = []): BracketPlan
    {
        if (! in_array($size, [2, 4, 8, 16, 32], true)) {
            throw new \InvalidArgumentException('Bracket size must be 2, 4, 8, 16, or 32.');
        }

        $seedOrder = SeedOrder::forSize($size);
        $rounds = (int) log($size, 2);
        $matchups = [];
        $previousRoundSize = null;

        for ($roundIndex = 0; $roundIndex < $rounds; $roundIndex++) {
            $roundSize = intdiv($size, 2 ** $roundIndex);
            $count = intdiv($roundSize, 2);
            $fromFinal = $rounds - $roundIndex - 1;
            $bestOf = $bestOfByRound[$fromFinal] ?? 1;

            for ($match = 1; $match <= $count; $match++) {
                if ($roundIndex === 0) {
                    $homeSeed = $seedOrder[($match - 1) * 2];
                    $awaySeed = $seedOrder[($match - 1) * 2 + 1];
                    $home = SlotSource::seed($orderedTeamIds[$homeSeed - 1] ?? null);
                    $away = SlotSource::seed($orderedTeamIds[$awaySeed - 1] ?? null);
                } else {
                    $home = SlotSource::winner('winners', (int) $previousRoundSize, $match * 2 - 1);
                    $away = SlotSource::winner('winners', (int) $previousRoundSize, $match * 2);
                }

                $matchups[] = new MatchupPlan('winners', $roundSize, $match, $home, $away, $bestOf);
            }

            $previousRoundSize = $roundSize;
        }

        return new BracketPlan($matchups);
    }
}
