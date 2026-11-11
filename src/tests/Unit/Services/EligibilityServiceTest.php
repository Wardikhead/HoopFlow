<?php

use App\Models\Division;
use App\Models\EligibilityDocument;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Services\EligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('initializes a player with pending consent and a pending birth certificate', function () {
    $team = Team::factory()->create();
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);

    (new EligibilityService)->initializePlayer($player);

    $player->refresh();

    expect($player->consent_status)->toBe('pending')
        ->and($player->consent_at)->toBeNull()
        ->and($player->eligibility_status)->toBe('pending')
        ->and($player->eligibilityDocuments()
            ->where('type', 'birth_certificate')
            ->where('status', 'pending')
            ->count())->toBe(1);
});

it('records verified consent with actor metadata', function () {
    $organizer = User::factory()->create();
    $player = Player::factory()->create([
        'consent_status' => 'pending',
        'consent_at' => null,
    ]);

    (new EligibilityService)->recordConsent($player, 'verified', $organizer);

    $player->refresh();

    expect($player->consent_status)->toBe('verified')
        ->and($player->consent_at)->not->toBeNull()
        ->and((int) $player->consent_recorded_by)->toBe((int) $organizer->id)
        ->and($player->consent_recorded_at)->not->toBeNull();
});

it('records missing consent and clears the consent timestamp', function () {
    $organizer = User::factory()->create();
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);

    (new EligibilityService)->recordConsent($player, 'missing', $organizer);

    expect($player->fresh()->consent_status)->toBe('missing')
        ->and($player->fresh()->consent_at)->toBeNull()
        ->and($player->fresh()->eligibility_status)->toBe('missing');
});

it('reviews a document as approved and records the reviewer', function () {
    $organizer = User::factory()->create();
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);

    (new EligibilityService)->reviewDocument($player, 'approved', $organizer);

    $document = $player->eligibilityDocuments()
        ->where('type', 'birth_certificate')
        ->firstOrFail();

    expect($document->status)->toBe('approved')
        ->and((int) $document->reviewed_by)->toBe((int) $organizer->id)
        ->and($document->reviewed_at)->not->toBeNull()
        ->and($player->fresh()->eligibility_status)->toBe('verified');
});

it('resets document review to pending and clears reviewer metadata', function () {
    $organizer = User::factory()->create();
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);
    $document = EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
        'reviewed_by' => $organizer->id,
        'reviewed_at' => now(),
    ]);

    (new EligibilityService)->resetDocumentReview($player);

    $document->refresh();

    expect($document->status)->toBe('pending')
        ->and($document->reviewed_by)->toBeNull()
        ->and($document->reviewed_at)->toBeNull()
        ->and($player->fresh()->eligibility_status)->toBe('pending');
});

it('recalculates verified only when consent and the document are both approved', function () {
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
    ]);

    (new EligibilityService)->recalculate($player);

    expect($player->fresh()->eligibility_status)->toBe('verified')
        ->and($player->fresh()->verified_at)->not->toBeNull();
});

it('recalculates pending when consent is still pending', function () {
    $player = Player::factory()->create([
        'consent_status' => 'pending',
        'consent_at' => null,
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
    ]);

    (new EligibilityService)->recalculate($player);

    expect($player->fresh()->eligibility_status)->toBe('pending')
        ->and($player->fresh()->verified_at)->toBeNull();
});

it('recalculates missing when the document is missing', function () {
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'missing',
    ]);

    (new EligibilityService)->recalculate($player);

    expect($player->fresh()->eligibility_status)->toBe('missing');
});

it('status reads consent and document state from the new columns', function () {
    $team = Team::factory()->create();
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'consent_status' => 'verified',
        'consent_at' => now(),
        'eligibility_status' => 'verified',
        'verified_at' => now(),
    ]);
    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
    ]);

    $result = (new EligibilityService)->status($team);

    expect($result['documentsComplete'])->toBeTrue()
        ->and($result['consentComplete'])->toBeTrue()
        ->and($result['verifiedAt'])->not->toBeNull();
});

it('treats a player without a document row as pending', function () {
    $player = Player::factory()->create([
        'consent_status' => 'verified',
        'consent_at' => now(),
        'eligibility_status' => 'verified',
    ]);

    expect((new EligibilityService)->documentStatusForPlayer($player))->toBe('pending');
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
