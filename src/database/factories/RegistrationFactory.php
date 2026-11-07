<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'tournament_id' => fn (array $attributes) => Division::find($attributes['division_id'])->tournament_id ?? Tournament::factory(),
            'team_id' => Team::factory(),
            'status' => 'submitted',
            'submitted_at' => now(),
            'decided_by' => null,
            'decided_at' => null,
            'rejection_reason' => null,
            'seed' => null,
            'roster_locked_at' => null,
        ];
    }
}
