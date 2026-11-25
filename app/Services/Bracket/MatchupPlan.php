<?php

/** Domain service for Matchup plan. */

namespace App\Services\Bracket;

final class MatchupPlan
{
    /**
     * Create a matchup plan for the given teams.
     *
     * @param  string  $stage  The stage.
     * @param  int  $roundNumber  The round number.
     * @param  int  $matchNumber  The match number.
     * @param  SlotSource  $home  The home.
     * @param  SlotSource  $away  The away.
     * @param  int  $bestOf  The best of.
     */
    public function __construct(
        public readonly string $stage,
        public readonly int $roundNumber,
        public readonly int $matchNumber,
        public readonly SlotSource $home,
        public readonly SlotSource $away,
        public readonly int $bestOf = 1,
    ) {
        if (! in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            throw new \InvalidArgumentException('Best-of must be 1, 3, 5, 7, or 9.');
        }
    }

    /**
     * Return the stable identifier for this matchup.
     *
     * @return string The result.
     */
    public function key(): string
    {
        return $this->stage.':'.$this->roundNumber.':'.$this->matchNumber;
    }
}
