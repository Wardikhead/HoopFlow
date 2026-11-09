<?php

use Database\Seeders\PrototypeSeeder;

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('login page loads', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Quick-fill accounts')
        ->assertSee('login-password-toggle', false)
        ->assertSee('quick-login-organizer', false)
        ->assertSee(route('register'), false);
});

it('login normalizes the email address', function () {
    $this->post('/login', [
        'email' => 'ORGANIZER@HOOPFLOW.PH',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
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
