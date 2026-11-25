<?php

/** Tests for Parity test. */

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

$roles = [
    'organizer' => 'organizer@hoopflow.ph',
    'coach' => 'coach.marcus@hoopflow.ph',
    'fan' => 'fan.juan@hoopflow.ph',
];

it('dashboard loads for each role', function () use ($roles) {
    foreach ($roles as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
});

it('guest is redirected from dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('tournaments index loads for each role', function () use ($roles) {
    foreach ($roles as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/tournaments')->assertOk();
    }
});

it('tournament show loads', function () {
    $tournament = Tournament::firstOrFail();
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/tournaments/'.$tournament->id)->assertOk();
});

it('tournaments create loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/tournaments/create')->assertOk();
});

it('non-organizer cannot access tournament create', function () {
    foreach (['coach.marcus@hoopflow.ph', 'fan.juan@hoopflow.ph'] as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/tournaments/create')->assertStatus(403);
    }
});

it('guest is redirected from tournaments', function () {
    $this->get('/tournaments')->assertRedirect('/login');
});

it('teams index loads for each role', function () use ($roles) {
    foreach ($roles as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/teams')->assertOk();
    }
});

it('team show loads', function () {
    $team = Team::firstOrFail();
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/teams/'.$team->id)->assertOk();
});

it('guest is redirected from teams', function () {
    $this->get('/teams')->assertRedirect('/login');
});

it('bracket page loads for each role', function () use ($roles) {
    foreach ($roles as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/bracket')->assertOk();
    }
});

it('guest is redirected from bracket', function () {
    $this->get('/bracket')->assertRedirect('/login');
});

it('schedule page loads for each role', function () use ($roles) {
    foreach ($roles as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/schedule')->assertOk();
    }
});

it('guest is redirected from schedule', function () {
    $this->get('/schedule')->assertRedirect('/login');
});

it('matches page loads for each role', function () use ($roles) {
    foreach ($roles as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/matches')->assertOk();
    }
});

it('game stats page loads for each role', function () use ($roles) {
    foreach ($roles as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/game-stats')->assertOk();
    }
});

it('game stats page loads for a match', function () {
    $match = MatchModel::firstOrFail();
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/game-stats?match='.$match->id)->assertOk();
});

it('guest is redirected from game stats', function () {
    $this->get('/game-stats')->assertRedirect('/login');
});

it('announcements load for each role', function () use ($roles) {
    foreach ($roles as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/announcements')->assertOk();
    }
});

it('announcement create loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/announcements/create')->assertOk();
});

it('non-organizer cannot access announcement create', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/announcements/create')->assertStatus(403);
});

it('guest is redirected from announcements', function () {
    $this->get('/announcements')->assertRedirect('/login');
});

it('profile loads for each role', function () use ($roles) {
    foreach ($roles as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/profile')->assertOk();
    }
});

it('guest is redirected from profile', function () {
    $this->get('/profile')->assertRedirect('/login');
});

it('audit loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/audit')->assertOk();
});

it('audit forbidden for coach and fan', function () {
    foreach (['coach.marcus@hoopflow.ph', 'fan.juan@hoopflow.ph'] as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/audit')->assertStatus(403);
    }
});

it('guest is redirected from audit', function () {
    $this->get('/audit')->assertRedirect('/login');
});

it('submissions load for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/submissions')->assertOk();
});

it('submissions forbidden for coach and fan', function () {
    foreach (['coach.marcus@hoopflow.ph', 'fan.juan@hoopflow.ph'] as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/submissions')->assertStatus(403);
    }
});

it('guest is redirected from submissions', function () {
    $this->get('/submissions')->assertRedirect('/login');
});

it('payments load for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/payments')->assertOk();
});

it('payments forbidden for coach and fan', function () {
    foreach (['coach.marcus@hoopflow.ph', 'fan.juan@hoopflow.ph'] as $email) {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->get('/payments')->assertStatus(403);
    }
});

it('guest is redirected from payments', function () {
    $this->get('/payments')->assertRedirect('/login');
});

it('standings page loads', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/standings')->assertOk();
});

it('team register loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/team-register')->assertOk();
});

it('my team loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/my-team')->assertOk();
});

it('my team forbidden for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/my-team')->assertStatus(403);
});

it('seed data matches prototype expectations', function () {
    expect(Tournament::count())->toBe(3);

    $active = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $open = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();
    $cdo = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    $activeRegs = Registration::where('tournament_id', $active->id)->get();
    expect($activeRegs->count())->toBe(16);
    expect($activeRegs->where('status', 'approved')->count())->toBe(15);
    expect($activeRegs->where('status', 'rejected')->count())->toBe(1);

    $activeMatches = MatchModel::where('tournament_id', $active->id)->get();
    expect($activeMatches->count())->toBe(15);
    expect($activeMatches->where('status', 'forfeit')->count())->toBe(1);

    expect(MatchModel::where('tournament_id', $active->id)->where('round_number', 16)->whereNull('away_team_id')->count())->toBe(1);

    $openRegs = Registration::where('tournament_id', $open->id)->get();
    expect($openRegs->where('status', 'pending')->count() + $openRegs->where('status', 'submitted')->count())->toBe(2);
    expect(MatchModel::where('tournament_id', $open->id)->count())->toBe(0);

    $cdoRegs = Registration::where('tournament_id', $cdo->id)->get();
    expect($cdoRegs->count())->toBe(8);
    expect($cdoRegs->where('status', 'approved')->count())->toBe(8);
    expect(MatchModel::where('tournament_id', $cdo->id)->count())->toBe(0);

    expect(Announcement::count())->toBeGreaterThanOrEqual(4);
    expect(AuditLog::count())->toBeGreaterThanOrEqual(6);
    expect(User::count())->toBeGreaterThanOrEqual(3);
});

it('seed reset is idempotent', function () {
    $before = Tournament::count();
    $this->seed(PrototypeSeeder::class);
    expect(Tournament::count())->toBe($before);
});
