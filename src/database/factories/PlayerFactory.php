<?php

/** Database support for Player factory. */

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Definition.
     *
     * @return array<mixed> The result.
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birthdate' => fake()->date(),
            'jersey_number' => fake()->numberBetween(1, 99),
            'position' => fake()->randomElement(['PG', 'SG', 'SF', 'PF', 'C']),
            'photo_url' => null,
            'guardian_name' => fake()->name(),
            'guardian_phone' => fake()->phoneNumber(),
            'consent_at' => null,
            'consent_status' => 'pending',
            'consent_recorded_by' => null,
            'consent_recorded_at' => null,
            'consent_version' => null,
            'eligibility_status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
        ];
    }
}
