<?php

use App\Models\MatchModel;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('matches page loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/matches')->assertStatus(200);
});

it('matches page loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/matches')->assertStatus(200);
});

it('matches page loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/matches')->assertStatus(200);
});

it('guest redirected from matches', function () {
    $this->get('/matches')->assertRedirect('/login');
});

it('game stats page loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/game-stats')->assertStatus(200);
});

it('game stats page loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/game-stats')->assertStatus(200);
});

it('game stats page loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/game-stats')->assertStatus(200);
});

it('guest redirected from game stats', function () {
    $this->get('/game-stats')->assertRedirect('/login');
});

it('game stats page loads for a match', function () {
    $match = MatchModel::firstOrFail();
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/game-stats?match='.$match->id)->assertStatus(200);
});
