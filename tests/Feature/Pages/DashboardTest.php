<?php

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

    expect($service->organizerKpis($summerCup))->toMatchArray([
        'registered' => 16,
        'approved' => 15,
        'collected' => 19500,
        'expected' => 22500,
        'uncollected' => 3000,
        'pendingCount' => 2,
    ]);
});

it('organizer dashboard shows the prototype uncollected total', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('₱3,000')
        ->assertSee('2 teams unpaid')
        ->assertDontSee('₱4,500');
});
