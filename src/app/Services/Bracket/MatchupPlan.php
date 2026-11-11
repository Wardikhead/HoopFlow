<?php

namespace App\Services\Bracket;

final class MatchupPlan
{
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

    public function key(): string
    {
        return $this->stage.':'.$this->roundNumber.':'.$this->matchNumber;
    }
}
