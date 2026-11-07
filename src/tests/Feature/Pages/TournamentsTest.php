<?php

use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
