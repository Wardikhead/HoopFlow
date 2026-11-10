<?php

use App\Livewire\Coach\MyTeam;
use App\Models\EligibilityDocument;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('syncs a coach document status change to the eligibility document', function () {
    $coach = User::factory()->create();
    $team = Team::factory()->create(['coach_user_id' => $coach->id]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'eligibility_status' => 'verified',
        'verified_at' => now(),
        'consent_at' => now(),
        'birthdate' => now()->subYears(14),
    ]);
    $document = EligibilityDocument::factory()->create([
        'player_id' => $player->id,
        'type' => 'birth_certificate',
        'status' => 'approved',
    ]);

    Livewire::actingAs($coach)
        ->test(MyTeam::class)
        ->call('openPlayerDialog', $player->id)
        ->set('playerDocumentStatus', 'missing')
        ->call('savePlayer')
        ->assertHasNoErrors();

    expect($document->fresh()->status)->toBe('missing')
        ->and($player->fresh()->eligibility_status)->toBe('missing');
});
