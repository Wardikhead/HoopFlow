<?php

use App\Models\User;
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
