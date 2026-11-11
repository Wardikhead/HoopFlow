<?php

namespace Database\Factories;

use App\Models\Bracket;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bracket>
 */
class BracketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'name' => fake()->words(2, true),
            'format' => 'single_elim',
            'seeding_method' => 'random',
            'status' => 'draft',
            'generated_at' => null,
            'phase' => 'main',
        ];
    }
}
