<?php

/** Database support for Division factory. */

namespace Database\Factories;

use App\Models\Division;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    /**
     * Definition.
     *
     * @return array<mixed> The result.
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'name' => fake()->words(2, true),
            'age_min' => fake()->numberBetween(8, 12),
            'age_max' => fake()->numberBetween(13, 18),
            'gender' => fake()->randomElement(['male', 'female', 'mixed']),
            'max_teams' => fake()->numberBetween(4, 16),
            'entry_fee' => fake()->randomFloat(2, 0, 500),
            'currency' => 'PHP',
        ];
    }
}
