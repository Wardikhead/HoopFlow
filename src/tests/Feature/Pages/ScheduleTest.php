<?php

use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('schedule page loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->first();
    $this->actingAs($user)->get('/schedule')->assertStatus(200);
});

it('schedule page loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->first();
    $this->actingAs($user)->get('/schedule')->assertStatus(200);
});

it('schedule page loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->first();
    $this->actingAs($user)->get('/schedule')->assertStatus(200);
});

it('guest redirected from schedule', function () {
    $this->get('/schedule')->assertRedirect('/login');
});
