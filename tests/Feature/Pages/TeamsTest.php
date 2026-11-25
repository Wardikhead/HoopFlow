<?php

/** Tests for Teams test. */

use App\Livewire\Coach\MyTeam;
use App\Livewire\Organizer\TeamsIndex;
use App\Models\Division;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
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

it('points the teams register action at an open tournament the coach has not joined', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $summerCup = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $pasig = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    $summerCup->update(['status' => 'registration-open']);

    $this->actingAs($coach)
        ->get('/teams?tournament='.$summerCup->id)
        ->assertOk()
        ->assertSee(route('teamRegister', ['tournament' => $pasig->id]), false)
        ->assertDontSee(route('teamRegister', ['tournament' => $summerCup->id]), false);
});

it('hides the teams register action when the coach has joined every open tournament', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $summerCup = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $pasig = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    $summerCup->update(['status' => 'registration-open']);

    $joinedTeam = Team::factory()->create([
        'coach_user_id' => $coach->id,
        'organization_id' => $pasig->organization_id,
        'name' => 'All In Ballers',
        'slug' => 'all-in-ballers',
    ]);
    Registration::factory()->create([
        'tournament_id' => $pasig->id,
        'division_id' => $pasig->divisions()->firstOrFail()->id,
        'team_id' => $joinedTeam->id,
        'status' => 'submitted',
    ]);

    $this->actingAs($coach)
        ->get('/teams?tournament='.$summerCup->id)
        ->assertOk()
        ->assertDontSee('data-testid="teams-register"', false);
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

it('fans only see approved team profiles', function () {
    $fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $approved = Team::where('name', 'San Antonio Vipers')->firstOrFail();

    $this->actingAs($fan)
        ->get('/teams/'.$approved->id)
        ->assertOk()
        ->assertSee('San Antonio Vipers')
        ->assertSee('Public profile')
        ->assertDontSee('team-private', false)
        ->assertDontSee('GCASH-9823412');
});

it('fans get a friendly unavailable page for pending teams', function () {
    $fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $pending = Team::where('name', 'Bagong Ilog Bears')->firstOrFail();

    $this->actingAs($fan)
        ->get('/teams/'.$pending->id)
        ->assertStatus(403)
        ->assertSee('Team not available')
        ->assertSee('Pending and rejected registrations are visible only to the organizer and the team coach.')
        ->assertSee('Back to public teams')
        ->assertDontSee('Bagong Ilog Bears');
});

it('coach teams index auto-selects the only tournament they have teams in', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $summerCup = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $this->actingAs($coach);

    Livewire::test(TeamsIndex::class)
        ->assertSet('tournament', $summerCup->id)
        ->assertSee($summerCup->name);
});

it('team divisions only include divisions represented by visible teams', function () {
    $fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $hoopfest = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    Division::create([
        'tournament_id' => $hoopfest->id,
        'name' => 'Under 19 (U19)',
        'age_min' => null,
        'age_max' => 19,
        'gender' => null,
        'max_teams' => 8,
        'entry_fee' => 1500,
        'currency' => 'PHP',
    ]);

    $this->actingAs($fan);

    Livewire::withQueryParams(['tournament' => $hoopfest->id])
        ->test(TeamsIndex::class)
        ->assertSee('Under 17 (U17)')
        ->assertDontSee('Under 19 (U19)');
});

it('labels verified payments as paid and submitted registrations as pending', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $hoopfest = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();
    $pending = Team::where('name', 'Bagong Ilog Bears')->firstOrFail();
    $vipers = Team::where('name', 'San Antonio Vipers')->firstOrFail();
    $summerCup = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $this->actingAs($organizer);

    Livewire::withQueryParams(['tournament' => $hoopfest->id])
        ->test(TeamsIndex::class)
        ->assertSee('Paid');

    $this->actingAs($organizer)
        ->get('/teams/'.$pending->id.'?tournament='.$hoopfest->id)
        ->assertOk()
        ->assertSee('<h3 class="hf-section-title hf-mt-1">Pending</h3>', false);

    $this->actingAs($organizer)
        ->get('/teams/'.$vipers->id.'?tournament='.$summerCup->id)
        ->assertOk()
        ->assertSee('Paid');
});

it('fans see the requested tournament when a team is approved in more than one', function () {
    $fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $summerCup = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $hoopfest = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();
    $team = Team::where('name', 'San Antonio Vipers')->firstOrFail();

    Registration::create([
        'tournament_id' => $hoopfest->id,
        'division_id' => $hoopfest->divisions()->firstOrFail()->id,
        'team_id' => $team->id,
        'status' => 'approved',
        'submitted_at' => now(),
    ]);

    $this->actingAs($fan)
        ->get('/teams/'.$team->id.'?tournament='.$summerCup->id)
        ->assertOk()
        ->assertSee($summerCup->name)
        ->assertDontSee($hoopfest->name);
});

it('fans cannot use an approved registration from another tournament to view a pending one', function () {
    $fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $summerCup = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $hoopfest = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();
    $team = Team::where('name', 'San Antonio Vipers')->firstOrFail();

    Registration::create([
        'tournament_id' => $hoopfest->id,
        'division_id' => $hoopfest->divisions()->firstOrFail()->id,
        'team_id' => $team->id,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $this->actingAs($fan)
        ->get('/teams/'.$team->id.'?tournament='.$hoopfest->id)
        ->assertStatus(403)
        ->assertSee('Team not available');

    $this->actingAs($fan)
        ->get('/teams/'.$team->id.'?tournament='.$summerCup->id)
        ->assertOk()
        ->assertSee($summerCup->name);
});

it('tournament team links carry the tournament context', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $summerCup = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $team = Team::where('name', 'San Antonio Vipers')->firstOrFail();

    $this->actingAs($organizer)
        ->get('/tournaments/'.$summerCup->id)
        ->assertOk()
        ->assertSee(route('teamShow', ['team' => $team->id, 'tournament' => $summerCup->id]), false);
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

it('my-team scopes the team and registration to the requested tournament', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $summerCup = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $hoopfest = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();
    $coachTeam = Team::where('coach_user_id', $coach->id)->firstOrFail();

    $otherTeam = Team::create([
        'organization_id' => $coachTeam->organization_id,
        'name' => 'Apex Test',
        'short_name' => 'APX',
        'slug' => 'apex-test',
        'avatar_color' => '#111827',
        'city' => 'Pasig City',
        'coach_name' => 'Marcus Rivera',
        'coach_user_id' => $coach->id,
    ]);

    Registration::create([
        'tournament_id' => $hoopfest->id,
        'division_id' => $hoopfest->divisions()->firstOrFail()->id,
        'team_id' => $otherTeam->id,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $this->actingAs($coach);

    Livewire::withQueryParams(['tournament' => $summerCup->id])
        ->test(MyTeam::class)
        ->assertSet('teamId', $coachTeam->id)
        ->assertSet('tournamentId', $summerCup->id)
        ->assertSee($summerCup->name)
        ->assertDontSee($hoopfest->name);

    Livewire::withQueryParams(['team' => $otherTeam->id, 'tournament' => $hoopfest->id])
        ->test(MyTeam::class)
        ->assertSet('teamId', $otherTeam->id)
        ->assertSet('tournamentId', $hoopfest->id)
        ->assertSee($hoopfest->name)
        ->assertDontSee($summerCup->name);

    Livewire::withQueryParams(['team' => $otherTeam->id])
        ->test(MyTeam::class)
        ->assertSet('teamId', $otherTeam->id)
        ->assertSet('tournamentId', null)
        ->assertSee($hoopfest->name);
});

it('tournament pages link my team with the tournament registration context', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $summerCup = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $coachTeam = Team::where('coach_user_id', $coach->id)->firstOrFail();
    $myTeamUrl = route('myTeam', ['team' => $coachTeam->id, 'tournament' => $summerCup->id]);

    $this->actingAs($coach)
        ->get('/tournaments')
        ->assertOk()
        ->assertSee($myTeamUrl);

    $this->actingAs($coach)
        ->get('/tournaments/'.$summerCup->id)
        ->assertOk()
        ->assertSee($myTeamUrl);
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
        ->set('playerBirthdate', '2012-01-01')
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
