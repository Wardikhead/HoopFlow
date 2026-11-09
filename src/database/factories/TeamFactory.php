<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'name' => fake()->city().' '.fake()->randomElement(['Bulldogs', 'Eagles', 'Tigers', 'Hawks']),
            'short_name' => strtoupper(fake()->unique()->lexify('???')),
            'avatar_color' => fake()->hexColor(),
            'city' => fake()->city(),
            'coach_name' => fake()->name(),
            'slug' => fake()->unique()->slug(),
            'logo_url' => null,
            'coach_user_id' => User::factory(),
        ];
    }
}
