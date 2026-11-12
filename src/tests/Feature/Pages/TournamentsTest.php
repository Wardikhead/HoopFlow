<?php

use App\Livewire\Organizer\TournamentCreate;
use App\Livewire\Organizer\TournamentShow;
use App\Models\Tournament;
use App\Models\User;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('tournaments index loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->first();
    $this->actingAs($user)->get('/tournaments')->assertStatus(200);
});

it('tournaments index loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->first();
    $this->actingAs($user)->get('/tournaments')->assertStatus(200);
});

it('tournaments index loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->first();
    $this->actingAs($user)->get('/tournaments')->assertStatus(200);
});

it('tournament show loads', function () {
    $tournament = Tournament::first();
    $user = User::where('email', 'organizer@hoopflow.ph')->first();
    $this->actingAs($user)->get('/tournaments/'.$tournament->id)->assertStatus(200);
});

it('guest redirected from tournaments', function () {
    $this->get('/tournaments')->assertRedirect('/login');
});

it('non-organizer cannot access create', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->first();
    $this->actingAs($user)->get('/tournaments/create')->assertStatus(403);
});

it('create wizard starts with prototype defaults', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->assertSet('step', 0)
        ->assertSet('name', 'HoopFlow Metro Invitational')
        ->assertSet('division_name', 'Under 12 (U12)')
        ->assertSet('venue_name', 'San Antonio Central Sports Complex')
        ->assertSet('format', 'single-elimination')
        ->assertSet('bracketSlots', 8)
        ->assertSet('fee', 1500.0)
        ->assertSet('paymentMethod', 'GCash')
        ->assertSet('courts', ['Court 1', 'Court 2'])
        ->assertSet('slotMinutes', 30)
        ->assertSet('restMinutes', 15);
});

it('create wizard publishes a registration-open tournament', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $component = Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->call('nextStep')
        ->assertSet('step', 1)
        ->call('nextStep')
        ->assertSet('step', 2)
        ->call('save')
        ->assertHasNoErrors();

    $tournament = Tournament::where('name', 'HoopFlow Metro Invitational')->firstOrFail();

    $component->assertRedirect(route('tournamentShow', ['tournament' => $tournament->id]));

    expect($tournament->status)->toBe('registration-open');
    expect($tournament->description)->not->toBeNull();
    expect($tournament->entryFee())->toBe(1500.0);
    expect($tournament->slotCount())->toBe(8);
    expect($tournament->format())->toBe('single-elimination');
    expect($tournament->formatLabel())->toBe('Single elimination');
    expect($tournament->slotMinutes())->toBe(30);
    expect($tournament->restMinutes())->toBe(15);
    expect($tournament->venueName())->toBe('San Antonio Central Sports Complex');

    $division = $tournament->divisions()->firstOrFail();
    expect($division->name)->toBe('Under 12 (U12)');
    expect((int) $division->max_teams)->toBe(8);

    $this->assertDatabaseHas('game_rules', [
        'tournament_id' => $tournament->id,
        'division_id' => $division->id,
        'rest_minutes' => 15,
    ]);

    $this->assertDatabaseHas('courts', ['name' => 'Court 1']);
    $this->assertDatabaseHas('courts', ['name' => 'Court 2']);
});

it('tournament show renders the hero, team list, announcements, and organizer actions', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $response = $this->actingAs($organizer)->get('/tournaments/'.$tournament->id);

    $response->assertOk()
        ->assertSee('HoopFlow Summer Cup')
        ->assertSee('San Antonio Central Sports Complex')
        ->assertSee('Team list')
        ->assertSee('San Antonio Vipers')
        ->assertSee('Announcements')
        ->assertSee('Open bracket')
        ->assertSee('data-testid="show-create-announcement"', false);
});

it('coach with a registered team sees the my team action', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $this->actingAs($coach)
        ->get('/tournaments/'.$tournament->id)
        ->assertOk()
        ->assertSee('My team status')
        ->assertSee('data-testid="show-my-team"', false)
        ->assertDontSee('data-testid="show-register"', false);
});

it('coach without a registered team sees the register action', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    $this->actingAs($coach)
        ->get('/tournaments/'.$tournament->id)
        ->assertOk()
        ->assertSee('Register team')
        ->assertSee('data-testid="show-register"', false)
        ->assertDontSee('data-testid="show-my-team"', false);
});

it('fan only sees approved teams on the tournament desk', function () {
    $fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $this->actingAs($fan)
        ->get('/tournaments/'.$tournament->id)
        ->assertOk()
        ->assertSee('San Antonio Vipers')
        ->assertDontSee('Napico Nuggets');
});

it('organizer can generate a bracket from the tournament desk', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    expect($tournament->matches()->count())->toBe(0);

    Livewire::actingAs($organizer)
        ->test(TournamentShow::class, ['tournament' => $tournament->id])
        ->call('requestGenerateBracket')
        ->assertSet('showConfirm', true)
        ->assertSet('confirmAction', 'generate-bracket')
        ->call('confirm')
        ->assertSet('showConfirm', false);

    expect($tournament->matches()->count())->toBeGreaterThan(0);
});

it('organizer can preview and publish a court schedule from the tournament desk', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    app(BracketService::class)->generate($tournament, $tournament->format());

    $component = Livewire::actingAs($organizer)
        ->test(TournamentShow::class, ['tournament' => $tournament->id])
        ->call('requestGenerateSchedule')
        ->assertSet('showSchedulePreview', true)
        ->assertSee('Schedule preview');

    $component->call('publishSchedule')
        ->assertSet('showSchedulePreview', false)
        ->assertDispatched('hf-toast');

    expect($tournament->matchSlots()->count())->toBeGreaterThan(0);
});

it('stores round-robin legs and playoff team settings', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->call('nextStep')
        ->set('format', 'round-robin')
        ->set('bracketSlots', 8)
        ->set('roundRobinLegs', 3)
        ->set('playoffTeams', 4)
        ->call('nextStep')
        ->call('save')
        ->assertHasNoErrors();

    $tournament = Tournament::where('name', 'HoopFlow Metro Invitational')->firstOrFail();

    expect($tournament->rules_json['round_robin_legs'])->toBe(3)
        ->and($tournament->rules_json['playoff_teams'])->toBe(4)
        ->and($tournament->rules_json['format'])->toBe('round-robin');
});

it('stores best-of settings for playoff rounds', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->call('nextStep')
        ->set('bestOfQuarterfinal', 3)
        ->set('bestOfSemifinal', 5)
        ->set('bestOfFinal', 7)
        ->call('nextStep')
        ->call('save')
        ->assertHasNoErrors();

    $tournament = Tournament::where('name', 'HoopFlow Metro Invitational')->firstOrFail();

    expect($tournament->rules_json['best_of_quarterfinal'])->toBe(3)
        ->and($tournament->rules_json['best_of_semifinal'])->toBe(5)
        ->and($tournament->rules_json['best_of_final'])->toBe(7);
});

it('rejects even best-of lengths and out-of-range legs', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->call('nextStep')
        ->set('bestOfFinal', 4)
        ->set('roundRobinLegs', 11)
        ->call('nextStep')
        ->assertHasErrors(['bestOfFinal', 'roundRobinLegs']);
});
