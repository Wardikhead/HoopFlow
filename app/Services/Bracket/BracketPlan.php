<?php

namespace App\Services\Bracket;

final class BracketPlan
{
    /**
     * @param  list<MatchupPlan>  $matchups
     */
    public function __construct(public readonly array $matchups)
    {
    }

    /**
     * @return array<string, MatchupPlan>
     */
    public function keyed(): array
    {
        $keyed = [];

        foreach ($this->matchups as $matchup) {
            $keyed[$matchup->key()] = $matchup;
        }

        return $keyed;
    }
}
