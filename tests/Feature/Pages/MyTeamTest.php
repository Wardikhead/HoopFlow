<?php

/** Tests for My team test. */

use App\Livewire\Coach\MyTeam;
use App\Models\Division;
use App\Models\EligibilityDocument;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('updates player fields without touching consent or document status', function () {
    $coach = User::factory()->coach()->create();
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => null,
        'age_max' => 15,
    ]);
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    Registration::factory()->create([
        'tournament_id' => $tournament->id,
        'division_id' => $division->id,
        'team_id' => $team->id,
    ]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'birthdate' => '2012-01-01',
        'consent_status' => 'verified',
        'consent_at' => now(),
        'eligibility_status' => 'verified',
        'verified_at' => now(),
    ]);
    $document = EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
    ]);

    Livewire::actingAs($coach)
        ->test(MyTeam::class)
        ->call('openPlayerDialog', $player->id)
        ->set('playerName', 'Updated Player')
        ->set('playerNumber', '9')
        ->set('playerPosition', MyTeam::POSITIONS[0])
        ->set('playerBirthdate', '2012-01-01')
        ->call('savePlayer')
        ->assertHasNoErrors();

    expect($player->fresh()->consent_status)->toBe('verified')
        ->and($document->fresh()->status)->toBe('approved');
});

it('resets document review when the coach changes the birthdate', function () {
    $coach = User::factory()->coach()->create();
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 15 (U15)',
        'age_min' => null,
        'age_max' => 15,
    ]);
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    Registration::factory()->create([
        'tournament_id' => $tournament->id,
        'division_id' => $division->id,
        'team_id' => $team->id,
    ]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'birthdate' => '2012-01-01',
        'consent_status' => 'verified',
        'consent_at' => now(),
        'eligibility_status' => 'verified',
        'verified_at' => now(),
    ]);
    $document = EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
        'reviewed_by' => $coach->id,
        'reviewed_at' => now(),
    ]);

    Livewire::actingAs($coach)
        ->test(MyTeam::class)
        ->call('openPlayerDialog', $player->id)
        ->set('playerBirthdate', '2013-01-01')
        ->call('savePlayer')
        ->assertHasNoErrors();

    expect($document->fresh()->status)->toBe('pending')
        ->and($document->fresh()->reviewed_by)->toBeNull()
        ->and($player->fresh()->eligibility_status)->toBe('pending')
        ->and($player->fresh()->consent_status)->toBe('verified');
});

it('rejects a birthdate outside the allowed range', function () {
    $coach = User::factory()->coach()->create();
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'birthdate' => '2012-01-01',
    ]);

    Livewire::actingAs($coach)
        ->test(MyTeam::class)
        ->call('openPlayerDialog', $player->id)
        ->set('playerBirthdate', '2020-01-01')
        ->call('savePlayer')
        ->assertHasErrors(['playerBirthdate']);
});

it('rejects an over-age birthdate when the roster has no registration loaded', function () {
    $coach = User::factory()->coach()->create();
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 17 (U17)',
        'age_min' => null,
        'age_max' => 17,
    ]);
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'birthdate' => '2010-01-01',
    ]);

    Livewire::actingAs($coach)
        ->test(MyTeam::class, ['teamId' => $team->id, 'tournamentId' => $tournament->id])
        ->call('openPlayerDialog', $player->id)
        ->set('playerBirthdate', '1996-01-01')
        ->call('savePlayer')
        ->assertHasErrors(['playerBirthdate']);

    expect($player->fresh()->birthdate?->format('Y-m-d'))->toBe('2010-01-01');
});

it('rejects an over-age birthdate against the registered division', function () {
    $coach = User::factory()->coach()->create();
    $tournament = Tournament::factory()->create(['start_date' => '2026-06-01']);
    $division = Division::factory()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Under 17 (U17)',
        'age_min' => null,
        'age_max' => 17,
    ]);
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    Registration::factory()->create([
        'tournament_id' => $tournament->id,
        'division_id' => $division->id,
        'team_id' => $team->id,
    ]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'birthdate' => '2010-01-01',
    ]);

    Livewire::actingAs($coach)
        ->test(MyTeam::class, ['teamId' => $team->id, 'tournamentId' => $tournament->id])
        ->call('openPlayerDialog', $player->id)
        ->set('playerBirthdate', '1996-01-01')
        ->call('savePlayer')
        ->assertHasErrors(['playerBirthdate']);

    expect($player->fresh()->birthdate?->format('Y-m-d'))->toBe('2010-01-01');
});

it('does not expose consent or document editing in the player dialog', function () {
    $coach = User::factory()->coach()->create();
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'birthdate' => '2012-01-01',
    ]);

    Livewire::actingAs($coach)
        ->test(MyTeam::class)
        ->call('openPlayerDialog', $player->id)
        ->assertSeeHtml('data-testid="my-player-birthdate"')
        ->assertDontSeeHtml('data-testid="my-player-consent"')
        ->assertDontSeeHtml('data-testid="my-player-document"');
});
