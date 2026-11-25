<?php

/** Database support for Payment factory. */

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Definition.
     *
     * @return array<mixed> The result.
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'amount' => fake()->randomFloat(2, 100, 1000),
            'currency' => 'PHP',
            'method' => fake()->randomElement(['cash', 'gcash', 'bank']),
            'reference_no' => fake()->uuid(),
            'receipt_no' => fake()->uuid(),
            'status' => 'pending',
            'paid_at' => null,
            'recorded_by' => User::factory(),
            'remarks' => null,
            'refunded_amount' => 0,
            'refunded_at' => null,
        ];
    }
}
