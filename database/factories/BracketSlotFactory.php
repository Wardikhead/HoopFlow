<?php

/** Database support for Bracket slot factory. */

namespace Database\Factories;

use App\Models\Bracket;
use App\Models\BracketSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BracketSlot>
 */
class BracketSlotFactory extends Factory
{
    /**
     * Definition.
     *
     * @return array<mixed> The result.
     */
    public function definition(): array
    {
        return [
            'bracket_id' => Bracket::factory(),
            'stage' => 'winners',
            'round_number' => 1,
            'match_number' => 1,
            'slot' => 'A',
            'source_type' => 'seed',
            'source_match_id' => null,
            'source_outcome' => null,
            'team_id' => null,
        ];
    }
}
