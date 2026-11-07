<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\GameRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameRule>
 */
class GameRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'tournament_id' => fn (array $attributes) => Division::find($attributes['division_id'])?->tournament_id,
            'quarter_minutes' => 10,
            'overtime_minutes' => 5,
            'foul_limit' => 5,
            'rest_minutes' => 15,
            'max_games_per_day' => null,
            'tiebreakers_json' => null,
        ];
    }
}
