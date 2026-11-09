<?php

use App\Livewire\Coach\MyTeam;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('teams index loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/teams')->assertStatus(200);
});

it('teams index loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/teams')->assertStatus(200);
});

it('teams index loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/teams')->assertStatus(200);
});

it('team show loads', function () {
    $team = Team::first();
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/teams/'.$team->id)->assertStatus(200);
});

it('my-team loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/my-team')->assertStatus(200);
});

it('my-team is forbidden for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/my-team')->assertStatus(403);
});

it('team-register loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/team-register')->assertStatus(200);
});

it('my-team renders the prototype roster, timeline, and payment cards', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $playerCount = $team->players()->count();

    $this->actingAs($coach)
        ->get('/my-team')
        ->assertOk()
        ->assertSee($team->name)
        ->assertSee('Roster ('.$playerCount.')')
        ->assertSee('Registration timeline')
        ->assertSee('Send to:')
        ->assertDontSee('Update payment reference')
        ->assertSee('my-team-roster', false)
        ->assertSee('my-team-timeline', false)
        ->assertSee('my-team-payment', false);
});

it('coach can add a player and the change is audited', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $registration = $team->registrations()->firstOrFail();
    $registration->update(['status' => 'submitted', 'roster_locked_at' => null]);

    $this->actingAs($coach);

    Livewire::test(MyTeam::class)
        ->call('openPlayerDialog')
        ->assertSet('showPlayerDialog', true)
        ->set('playerName', 'Test Player')
        ->set('playerNumber', '88')
        ->set('playerPosition', MyTeam::POSITIONS[0])
        ->set('playerAge', '14')
        ->set('playerConsent', true)
        ->set('playerDocumentStatus', 'pending')
        ->call('savePlayer')
        ->assertHasNoErrors()
        ->assertSet('showPlayerDialog', false)
        ->assertDispatched('hf-toast');

    $this->assertDatabaseHas('players', [
        'team_id' => $team->id,
        'first_name' => 'Test',
        'last_name' => 'Player',
        'jersey_number' => 88,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'team.roster.add',
        'entity_type' => 'team',
        'entity_id' => $team->id,
    ]);
});

it('coach can update and remove a player', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $registration = $team->registrations()->firstOrFail();
    $registration->update(['status' => 'submitted', 'roster_locked_at' => null]);
    $player = $team->players()->firstOrFail();

    $this->actingAs($coach);

    Livewire::test(MyTeam::class)
        ->call('openPlayerDialog', $player->id)
        ->assertSet('showPlayerDialog', true)
        ->set('playerName', 'Updated Player')
        ->call('savePlayer')
        ->assertHasNoErrors()
        ->assertSet('showPlayerDialog', false);

    $this->assertDatabaseHas('players', [
        'id' => $player->id,
        'first_name' => 'Updated',
        'last_name' => 'Player',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'team.roster.update',
        'entity_id' => $team->id,
    ]);

    Livewire::test(MyTeam::class)
        ->call('confirmRemovePlayer', $player->id)
        ->assertSet('showRemoveDialog', true)
        ->call('removePlayer')
        ->assertSet('showRemoveDialog', false);

    $this->assertDatabaseMissing('players', ['id' => $player->id]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'team.roster.remove',
        'entity_id' => $team->id,
    ]);
});

it('a locked roster rejects player dialog changes', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $player = $team->players()->firstOrFail();

    $this->actingAs($coach);

    Livewire::test(MyTeam::class)
        ->call('openPlayerDialog', $player->id)
        ->assertSet('showPlayerDialog', false)
        ->assertDispatched('hf-toast')
        ->assertDontSee('my-team-add-player', false);
});

it('coach can submit a payment reference and the change is audited', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $payment = $team->registrations()->firstOrFail()->payment()->firstOrFail();
    $payment->update(['status' => 'pending', 'reference_no' => null]);

    $this->actingAs($coach);

    Livewire::test(MyTeam::class)
        ->call('openPaymentDialog')
        ->assertSet('showPaymentDialog', true)
        ->set('paymentMethod', 'Maya')
        ->set('paymentReference', 'MAYA-123456')
        ->call('savePayment')
        ->assertHasNoErrors()
        ->assertSet('showPaymentDialog', false);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'method' => 'Maya',
        'reference_no' => 'MAYA-123456',
        'status' => 'pending',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'payment.submit',
        'entity_type' => 'team',
        'entity_id' => $team->id,
    ]);
});

it('player validation rejects an empty name', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $team = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $team->registrations()->firstOrFail()->update(['status' => 'submitted', 'roster_locked_at' => null]);

    $this->actingAs($coach);

    Livewire::test(MyTeam::class)
        ->call('openPlayerDialog')
        ->set('playerName', '')
        ->call('savePlayer')
        ->assertHasErrors(['playerName'])
        ->assertSet('showPlayerDialog', true);
});
