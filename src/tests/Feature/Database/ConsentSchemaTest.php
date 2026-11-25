<?php

/** Tests for Consent schema test. */

use App\Models\EligibilityDocument;
use App\Models\Player;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults new players to pending consent', function () {
    $player = Player::factory()->create();

    expect($player->fresh()->consent_status)->toBe('pending')
        ->and($player->fresh()->consent_at)->toBeNull();
});

it('allows a pending eligibility document without a file url', function () {
    $document = EligibilityDocument::factory()->create([
        'player_id' => Player::factory()->create()->id,
        'type' => 'birth_certificate',
        'file_url' => null,
        'status' => 'pending',
    ]);

    expect($document->fresh()->file_url)->toBeNull();
});

it('allows only one document per player and type', function () {
    $player = Player::factory()->create();

    EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
    ]);

    expect(fn () => EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
    ]))->toThrow(QueryException::class);
});
