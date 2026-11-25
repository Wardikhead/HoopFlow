<?php

/** Database support for Court factory. */

namespace Database\Factories;

use App\Models\Court;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    /**
     * Definition.
     *
     * @return array<mixed> The result.
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'name' => 'Court '.fake()->numberBetween(1, 4),
            'notes' => null,
        ];
    }
}
