<?php

namespace App\Rules;

use App\Models\Division;
use App\Models\Tournament;
use App\Services\EligibilityService;
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

        if (! $this->division instanceof Division) {
            $fail('No division is assigned to this registration. Contact the organizer.');

            return;
        }

        $eligibility = app(EligibilityService::class);
        $reference = $this->tournament?->start_date !== null
            ? CarbonImmutable::parse($this->tournament->start_date)
            : $today;

        $age = (int) $birthdate->diff($reference)->y;
        $range = $eligibility->ageRange($this->division);

        if ($age < $range['min'] || $age > $range['max']) {
            $fail("Player must be {$range['min']}–{$range['max']} for {$this->division->name} at tournament start.");
        }
    }
}
