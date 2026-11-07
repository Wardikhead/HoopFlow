<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birthdate' => fake()->date(),
            'jersey_number' => fake()->numberBetween(0, 99),
            'position' => fake()->randomElement(['PG', 'SG', 'SF', 'PF', 'C']),
            'photo_url' => null,
            'guardian_name' => fake()->name(),
            'guardian_phone' => fake()->phoneNumber(),
            'consent_at' => now(),
            'consent_version' => '1.0',
            'eligibility_status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
        ];
    }
}
