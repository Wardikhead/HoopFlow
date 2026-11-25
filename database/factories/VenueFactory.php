<?php

/** Database support for Venue factory. */

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
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
            'name' => fake()->company().' Court',
            'address' => fake()->address(),
            'timezone' => 'UTC',
        ];
    }
}
