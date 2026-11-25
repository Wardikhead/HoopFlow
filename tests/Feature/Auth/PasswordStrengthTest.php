<?php

/** Tests for Password strength test. */

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('rejects passwords shorter than eight characters', function () {
    $this->post('/register', [
        'name' => 'Short Password',
        'email' => 'short@example.com',
        'password' => 'short',
        'role' => 'coach',
    ])->assertSessionHasErrors([
        'password' => 'Use at least 8 characters.',
    ]);

    expect(User::where('email', 'short@example.com')->exists())->toBeFalse();
});

it('rejects a common password', function () {
    $this->post('/register', [
        'name' => 'Common Password',
        'email' => 'common@example.com',
        'password' => 'password',
        'role' => 'coach',
    ])->assertSessionHasErrors([
        'password' => 'That password is too common. Add a word only you would use.',
    ]);
});

it('rejects a common password with a numeric suffix', function () {
    $this->post('/register', [
        'name' => 'Common Suffix',
        'email' => 'common-suffix@example.com',
        'password' => 'qwerty123',
        'role' => 'coach',
    ])->assertSessionHasErrors('password');
});

it('accepts the existing secret123 fixture', function () {
    $this->post('/register', [
        'name' => 'Secret Fixture',
        'email' => 'secret-fixture@example.com',
        'password' => 'secret123',
        'role' => 'coach',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    expect(User::where('email', 'secret-fixture@example.com')->exists())->toBeTrue();
});

it('renders the password meter, checklist, and show toggle', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('hfPasswordMeter', false)
        ->assertSee('register-password-toggle', false)
        ->assertSee('Password requirements')
        ->assertSee('role="meter"', false)
        ->assertSee('aria-valuemax="4"', false);
});
