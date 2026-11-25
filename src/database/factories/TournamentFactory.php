<?php

/** Database support for Tournament factory. */

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    /**
     * Definition.
     *
     * @return array<mixed> The result.
     */
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'name' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'start_date' => fake()->date(),
            'end_date' => fake()->date(),
            'status' => 'draft',
            'payment_method' => 'GCash',
            'payment_account' => '09489666136',
            'rules_json' => null,
            'created_by' => User::factory(),
        ];
    }
}
