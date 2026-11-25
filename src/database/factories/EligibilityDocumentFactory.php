<?php

/** Database support for Eligibility document factory. */

namespace Database\Factories;

use App\Models\EligibilityDocument;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EligibilityDocument>
 */
class EligibilityDocumentFactory extends Factory
{
    /**
     * Definition.
     *
     * @return array<mixed> The result.
     */
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'type' => fake()->randomElement(['birth_certificate', 'consent_form']),
            'file_url' => fake()->url(),
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'notes' => null,
        ];
    }
}
