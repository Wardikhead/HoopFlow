<?php

use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('profile loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/profile')->assertStatus(200);
});

it('profile loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/profile')->assertStatus(200);
});

it('profile loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/profile')->assertStatus(200);
});

it('guest redirected from profile', function () {
    $this->get('/profile')->assertRedirect('/login');
});

it('audit loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/audit')->assertStatus(200);
});

it('audit forbidden for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/audit')->assertStatus(403);
});

it('audit forbidden for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();
    $this->actingAs($user)->get('/audit')->assertStatus(403);
});

it('guest redirected from audit', function () {
    $this->get('/audit')->assertRedirect('/login');
});
