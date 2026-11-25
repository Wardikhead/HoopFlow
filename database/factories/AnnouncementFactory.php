<?php

/** Database support for Announcement factory. */

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
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
            'tournament_id' => Tournament::factory(),
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'audience' => 'all',
            'status' => 'draft',
            'published_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
