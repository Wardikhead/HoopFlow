<?php

use App\Livewire\Coach\TeamRegister;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('persists team identity fields when a coach registers', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();

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

    Livewire::actingAs($coach)
        ->test(TeamRegister::class, ['tournament_id' => 2])
        ->set('name', 'Testville Titans')
        ->set('shortName', 'TVT')
        ->set('city', 'Testville')
        ->set('coach', 'Coach Test')
        ->set('avatarColor', '#123456')
        ->set('players', $players)
        ->set('paymentMethod', 'GCash')
        ->set('paymentRef', 'TESTPAY123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('myTeam'));

    $team = Team::where('name', 'Testville Titans')->firstOrFail();

    expect($team->short_name)->toBe('TVT');
    expect($team->avatar_color)->toBe('#123456');
    expect($team->city)->toBe('Testville');
    expect($team->coach_name)->toBe('Coach Test');
    expect($team->shortLabel())->toBe('TVT');
    expect($team->displayColor())->toBe('#123456');
});
