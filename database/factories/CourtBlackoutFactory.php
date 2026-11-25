<?php

/** Database support for Court blackout factory. */

namespace Database\Factories;

use App\Models\Court;
use App\Models\CourtBlackout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourtBlackout>
 */
class CourtBlackoutFactory extends Factory
{
    /**
     * Definition.
     *
     * @return array<mixed> The result.
     */
    public function definition(): array
    {
        return [
            'court_id' => Court::factory(),
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'reason' => fake()->sentence(),
        ];
    }
}
