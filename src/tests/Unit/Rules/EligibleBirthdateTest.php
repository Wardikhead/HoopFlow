<?php

/** Tests for Eligible birthdate test. */

use App\Models\Division;
use App\Models\Tournament;
use App\Rules\EligibleBirthdate;
use Illuminate\Support\Facades\Validator;

/**
 * Birthdate errors.
 *
 * @param  string  $birthdate  The birthdate.
 * @param  Division  $division  The division.
 * @param  Tournament  $tournament  The tournament.
 * @return array<mixed> The result.
 */
function birthdateErrors(string $birthdate, Division $division, Tournament $tournament): array
{
    return Validator::make(
        ['birthdate' => $birthdate],
        ['birthdate' => [new EligibleBirthdate($division, $tournament)]],
    )->errors()->all();
}

it('accepts a player whose age at tournament start is inside the range', function () {
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => 13,
        'age_max' => 15,
    ]);

    expect(birthdateErrors('2012-01-01', $division, $tournament))->toBeEmpty();
});

it('rejects a player who is too old at tournament start', function () {
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => 13,
        'age_max' => 15,
    ]);

    expect(birthdateErrors('2008-01-01', $division, $tournament))
        ->toContain('Player must be 13–15 for Under 15 (U15) at tournament start.');
});

it('rejects a player who is too young at tournament start', function () {
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => 13,
        'age_max' => 15,
    ]);

    expect(birthdateErrors('2014-01-01', $division, $tournament))
        ->toContain('Player must be 13–15 for Under 15 (U15) at tournament start.');
});

it('rejects a future birthdate', function () {
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => 13,
        'age_max' => 15,
    ]);

    expect(birthdateErrors(now()->addYear()->format('Y-m-d'), $division, $tournament))
        ->toContain('Birthdate cannot be in the future.');
});

it('rejects an invalid date string', function () {
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => 13,
        'age_max' => 15,
    ]);

    expect(birthdateErrors('not-a-date', $division, $tournament))
        ->toContain('Enter a valid birthdate.');
});
