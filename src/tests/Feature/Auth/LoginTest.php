<?php

use Database\Seeders\PrototypeSeeder;

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('login page loads', function () {
    $this->get('/login')->assertOk();
});

it('valid login redirects to dashboard', function () {
    $this->post('/login', [
        'email' => 'organizer@hoopflow.ph',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

it('invalid credentials show error', function () {
    $this->post('/login', [
        'email' => 'organizer@hoopflow.ph',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

it('guest is redirected from dashboard to login', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('logout works', function () {
    $this->post('/login', [
        'email' => 'organizer@hoopflow.ph',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->post('/logout')->assertRedirect(route('login'));

    $this->get('/dashboard')->assertRedirect(route('login'));
});
