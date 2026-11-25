<?php

/** Database support for Bracket factory. */

namespace Database\Factories;

use App\Models\Bracket;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bracket>
 */
class BracketFactory extends Factory
{
    /**
     * Definition.
     *
     * @return array<mixed> The result.
     */
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
