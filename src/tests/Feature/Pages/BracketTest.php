<?php

use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('bracket page loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->first();
    $this->actingAs($user)->get('/bracket')->assertStatus(200);
});

it('bracket page loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->first();
    $this->actingAs($user)->get('/bracket')->assertStatus(200);
});

it('bracket page loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->first();
    $this->actingAs($user)->get('/bracket')->assertStatus(200);
});

it('guest redirected from bracket', function () {
    $this->get('/bracket')->assertRedirect('/login');
});
