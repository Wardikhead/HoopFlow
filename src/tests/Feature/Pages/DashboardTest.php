<?php

/** Tests for Dashboard test. */

use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\DashboardService;
use Database\Seeders\PrototypeSeeder;

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('guest is redirected to login', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('dashboard loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/dashboard')->assertOk();
});

it('dashboard loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/dashboard')->assertOk();
});

it('hides the register action for an open tournament the coach already joined', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();
    $joinedTeam = Team::factory()->create([
        'coach_user_id' => $coach->id,
        'organization_id' => $tournament->organization_id,
        'name' => 'Already Joined Ballers',
        'slug' => 'already-joined-ballers',
    ]);
    Registration::factory()->create([
        'tournament_id' => $tournament->id,
        'division_id' => $tournament->divisions()->firstOrFail()->id,
        'team_id' => $joinedTeam->id,
        'status' => 'submitted',
    ]);

    $this->actingAs($coach)
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('data-testid="coach-register-'.$tournament->id.'"', false)
        ->assertSee('data-testid="coach-my-team-'.$tournament->id.'"', false);
});

it('keeps the register action for open tournaments the coach has not joined', function () {
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();

    $this->actingAs($coach)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('data-testid="coach-register-'.$tournament->id.'"', false);
});

it('dashboard loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/dashboard')->assertOk();
});

it('organizer billing excludes rejected registrations', function () {
    $summerCup = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $service = app(DashboardService::class);

    expect($service->paymentSummary($summerCup))->toMatchArray([
        'collected' => 19500,
        'expected' => 22500,
        'outstanding' => 3000,
        'pendingCount' => 2,
    ]);
});

it('organizer dashboard kpis total every tournament', function () {
    $summerCup = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $service = app(DashboardService::class);

    expect($service->organizerKpis($summerCup))->toMatchArray([
        'tournaments' => 3,
        'pending' => 2,
        'approved' => 29,
        'registered' => 32,
        'collected' => 33500,
        'expected' => 42500,
        'uncollected' => 9000,
        'pendingCount' => 6,
    ]);
});

it('organizer dashboard shows all-tournament team and payment totals', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('₱9,000')
        ->assertSee('6 teams unpaid')
        ->assertDontSee('₱3,000');
});
