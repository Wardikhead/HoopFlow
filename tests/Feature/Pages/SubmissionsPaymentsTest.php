<?php

use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('submissions loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/submissions')->assertStatus(200);
});

it('submissions forbidden for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/submissions')->assertStatus(403);
});

it('submissions forbidden for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/submissions')->assertStatus(403);
});

it('guest redirected from submissions', function () {
    $this->get('/submissions')->assertRedirect('/login');
});

it('payments loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/payments')->assertStatus(200);
});

it('payments forbidden for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/payments')->assertStatus(403);
});

it('payments forbidden for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/payments')->assertStatus(403);
});

it('guest redirected from payments', function () {
    $this->get('/payments')->assertRedirect('/login');
});
