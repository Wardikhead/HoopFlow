<?php

namespace App\Rules;

use App\Models\Division;
use App\Models\Tournament;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

class EligibleBirthdate implements ValidationRule
{
    public function __construct(
        private readonly ?Division $division = null,
        private readonly ?Tournament $tournament = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Enter the player birthdate.');

            return;
        }

        $birthdate = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($birthdate === false || $birthdate->format('Y-m-d') !== $value) {
            $fail('Enter a valid birthdate.');

            return;
        }

        $today = CarbonImmutable::today();

        if ($birthdate > $today) {
            $fail('Birthdate cannot be in the future.');

            return;
        }

        $reference = $this->tournament?->start_date !== null
            ? CarbonImmutable::parse($this->tournament->start_date)
            : $today;

        $age = (int) $birthdate->diff($reference)->y;
        $min = $this->division?->age_min ?? 8;
        $max = $this->division?->age_max ?? 99;

        if ($age < $min || $age > $max) {
            $divisionName = $this->division?->name;

            $fail($divisionName !== null
                ? "Player must be {$min}–{$max} for {$divisionName} at tournament start."
                : "Player must be {$min}–{$max} at tournament start.");
        }
    }
}
