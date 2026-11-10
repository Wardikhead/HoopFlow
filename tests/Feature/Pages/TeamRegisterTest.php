<?php

use App\Livewire\Coach\TeamRegister;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\EligibilityService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('persists team identity fields when a coach registers', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    $players = [];
    for ($number = 1; $number <= 8; $number++) {
        $players[] = [
            'name' => 'Test Player '.$number,
            'number' => (string) $number,
            'position' => 'Point Guard (PG)',
            'age' => '14',
            'guardianConsent' => true,
            'documentStatus' => 'pending',
        ];
    }

    $component = Livewire::actingAs($coach)
        ->test(TeamRegister::class, ['tournament_id' => $tournament->id])
        ->set('name', 'Testville Titans')
        ->set('shortName', 'TVT')
        ->set('city', 'Testville')
        ->set('coach', 'Coach Test')
        ->set('avatarColor', '#123456')
        ->set('players', $players)
        ->set('paymentMethod', 'GCash')
        ->set('paymentRef', 'TESTPAY123')
        ->call('save')
        ->assertHasNoErrors();

    $team = Team::where('name', 'Testville Titans')->firstOrFail();

    $component->assertRedirect(route('myTeam', [
        'team' => $team->id,
        'tournament' => $tournament->id,
    ]));

    expect($team->short_name)->toBe('TVT');
    expect($team->avatar_color)->toBe('#123456');
    expect($team->city)->toBe('Testville');
    expect($team->coach_name)->toBe('Coach Test');
    expect($team->shortLabel())->toBe('TVT');
    expect($team->displayColor())->toBe('#123456');
});

it('persists coach document statuses for organizer review', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    $players = [];
    for ($number = 1; $number <= 8; $number++) {
        $players[] = [
            'name' => 'Document Player '.$number,
            'number' => (string) $number,
            'position' => 'Point Guard (PG)',
            'age' => '14',
            'guardianConsent' => true,
            'documentStatus' => 'verified',
        ];
    }

    Livewire::actingAs($coach)
        ->test(TeamRegister::class, ['tournament_id' => $tournament->id])
        ->set('name', 'Documentville Titans')
        ->set('shortName', 'DVT')
        ->set('city', 'Documentville')
        ->set('coach', 'Coach Documents')
        ->set('avatarColor', '#123456')
        ->set('players', $players)
        ->set('paymentMethod', 'GCash')
        ->set('paymentRef', 'DOCPAY123')
        ->call('save')
        ->assertHasNoErrors();

    $team = Team::where('name', 'Documentville Titans')->firstOrFail();

    expect($team->players()->count())->toBe(8)
        ->and($team->players()
            ->whereHas('eligibilityDocuments', fn ($query) => $query
                ->where('type', 'birth_certificate')
                ->where('status', 'approved'))
            ->count())->toBe(8)
        ->and(app(EligibilityService::class)->status($team)['documentsComplete'])->toBeTrue();
});
