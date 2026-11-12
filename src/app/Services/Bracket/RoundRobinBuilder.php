<?php

namespace App\Services\Bracket;

final class RoundRobinBuilder
{
    /**
     * @param  list<int>  $teamIds
     */
    public function build(array $teamIds, int $legs = 1): BracketPlan
    {
        $teamCount = count($teamIds);

        if ($teamCount < 2 || $teamCount > 16) {
            throw new \InvalidArgumentException('Round robin requires 2 to 16 teams.');
        }

        if ($legs < 1 || $legs > 10) {
            throw new \InvalidArgumentException('Meetings per pair must be between 1 and 10.');
        }

        $rotation = $teamIds;

        if ($teamCount % 2 !== 0) {
            $rotation[] = null;
        }

        $rotationCount = count($rotation);
        $baseRounds = $rotationCount - 1;
        $matchups = [];

        for ($leg = 1; $leg <= $legs; $leg++) {
            $rotation = $teamIds;

            if ($teamCount % 2 !== 0) {
                $rotation[] = null;
            }

            for ($roundIndex = 0; $roundIndex < $baseRounds; $roundIndex++) {
                $matchday = ($leg - 1) * $baseRounds + $roundIndex + 1;
                $matchNumber = 0;

                for ($i = 0; $i < intdiv($rotationCount, 2); $i++) {
                    $home = $rotation[$i];
                    $away = $rotation[$rotationCount - 1 - $i];

                    if ($home === null || $away === null) {
                        continue;
                    }

                    if ($leg % 2 === 0) {
                        [$home, $away] = [$away, $home];
                    }

                    $matchNumber++;
                    $matchups[] = new MatchupPlan(
                        'round_robin',
                        $matchday,
                        $matchNumber,
                        SlotSource::seed($home),
                        SlotSource::seed($away),
                        1,
                    );
                }

                $last = array_pop($rotation);
                array_splice($rotation, 1, 0, [$last]);
            }
        }

        return new BracketPlan($matchups);
    }
}
