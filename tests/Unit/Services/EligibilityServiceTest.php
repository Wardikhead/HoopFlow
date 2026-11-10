<?php

use App\Models\Division;
use App\Models\EligibilityDocument;
use App\Models\Player;
use App\Models\Team;
use App\Services\EligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refresh marks players verified when docs approved and consent present', function () {
    $team = Team::factory()->create();
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'consent_at' => now(),
        'eligibility_status' => 'pending',
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'status' => 'approved',
    ]);

    $service = new EligibilityService;
    $service->refresh($team);

    expect($player->fresh()->eligibility_status)->toBe('verified');
});

it('refresh marks players pending when consent missing', function () {
    $team = Team::factory()->create();
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'consent_at' => null,
        'eligibility_status' => 'verified',
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'status' => 'approved',
    ]);

    $service = new EligibilityService;
    $service->refresh($team);

    expect($player->fresh()->eligibility_status)->toBe('pending');
});

it('status returns documentsComplete/consentComplete/verifiedAt', function () {
    $team = Team::factory()->create();
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'consent_at' => now(),
        'eligibility_status' => 'verified',
        'verified_at' => now(),
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'status' => 'approved',
    ]);

    $service = new EligibilityService;
    $result = $service->status($team);

    expect($result['documentsComplete'])->toBeTrue();
    expect($result['consentComplete'])->toBeTrue();
    expect($result['verifiedAt'])->not->toBeNull();
});

it('status trusts a coach-verified player when no document rows exist', function () {
    $team = Team::factory()->create();
    Player::factory()->create([
        'team_id' => $team->id,
        'consent_at' => now(),
        'eligibility_status' => 'verified',
        'verified_at' => now(),
    ]);

    $service = new EligibilityService;
    $result = $service->status($team);

    expect($result['documentsComplete'])->toBeTrue();
});

it('status trusts a coach-verified player when existing document rows are approved', function () {
    $team = Team::factory()->create();
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'consent_at' => now(),
        'eligibility_status' => 'verified',
        'verified_at' => now(),
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'status' => 'approved',
    ]);

    $service = new EligibilityService;
    $result = $service->status($team);

    expect($result['documentsComplete'])->toBeTrue();
});

it('status does not trust a verified player when an existing document row is not approved', function () {
    $team = Team::factory()->create();
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'consent_at' => now(),
        'eligibility_status' => 'verified',
        'verified_at' => now(),
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'status' => 'pending',
    ]);

    $service = new EligibilityService;
    $result = $service->status($team);

    expect($result['documentsComplete'])->toBeFalse();
});

it('resolves a player document chip from the document rows', function () {
    $team = Team::factory()->create();
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'eligibility_status' => 'verified',
    ]);

    $service = new EligibilityService;

    expect($service->documentStatusForPlayer($player))->toBe('verified');

    $document = EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'status' => 'pending',
    ]);
    $player->unsetRelation('eligibilityDocuments');
    expect($service->documentStatusForPlayer($player))->toBe('pending');

    $document->update(['status' => 'missing']);
    $player->unsetRelation('eligibilityDocuments');
    expect($service->documentStatusForPlayer($player))->toBe('missing');

    $document->update(['status' => 'approved']);
    $player->unsetRelation('eligibilityDocuments');
    expect($service->documentStatusForPlayer($player))->toBe('verified');
});

it('validatePlayer returns errors for invalid input', function () {
    $division = Division::factory()->create(['age_min' => 8, 'age_max' => 15, 'name' => 'U15']);
    $team = Team::factory()->create();
    Player::factory()->create(['team_id' => $team->id, 'jersey_number' => 5]);

    $service = new EligibilityService;
    $errors = $service->validatePlayer([
        'name' => 'A',
        'birthdate' => '2020-01-01',
        'jersey_number' => 5,
        'position' => 'Goalkeeper',
        'team_id' => $team->id,
    ], $division, null);

    expect($errors)->toHaveKey('name');
    expect($errors)->toHaveKey('birthdate');
    expect($errors)->toHaveKey('jersey_number');
    expect($errors)->toHaveKey('position');
});

it('validatePlayer returns empty for valid input', function () {
    $division = Division::factory()->create(['age_min' => 8, 'age_max' => 15, 'name' => 'U15']);
    $team = Team::factory()->create();

    $service = new EligibilityService;
    $errors = $service->validatePlayer([
        'name' => 'Valid Player',
        'birthdate' => now()->subYears(12)->format('Y-m-d'),
        'jersey_number' => 7,
        'position' => 'PG',
        'team_id' => $team->id,
    ], $division, null);

    expect($errors)->toBeEmpty();
});
