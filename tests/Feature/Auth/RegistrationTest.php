<?php

/** Tests for Registration test. */

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('registration page loads', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('Create account')
        ->assertSee('register-name', false)
        ->assertSee('register-role', false)
        ->assertSee('register-organization', false);
});

it('registers a coach with an organization and signs them in', function () {
    $response = $this->post('/register', [
        'name' => 'Juan Dela Cruz',
        'email' => 'JUAN@example.com',
        'password' => 'secret123',
        'role' => 'coach',
        'organization' => 'Barangay San Antonio Youth Club',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = User::where('email', 'juan@example.com')->firstOrFail();
    $organization = Organization::where('slug', 'barangay-san-antonio-youth-club')->firstOrFail();
    $coachRole = Role::where('key', 'coach')->firstOrFail();

    expect($user->name)->toBe('Juan Dela Cruz')
        ->and($user->hasRole('coach'))->toBeTrue()
        ->and($organization->name)->toBe('Barangay San Antonio Youth Club');

    $this->assertDatabaseHas('user_roles', [
        'user_id' => $user->id,
        'role_id' => $coachRole->id,
        'organization_id' => $organization->id,
    ]);
});

it('registers a fan without an organization', function () {
    $response = $this->post('/register', [
        'name' => 'Maria Santos',
        'email' => 'maria@example.com',
        'password' => 'secret123',
        'role' => 'fan',
        'organization' => '',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = User::where('email', 'maria@example.com')->firstOrFail();

    expect($user->hasRole('fan'))->toBeTrue();

    $this->assertDatabaseHas('user_roles', [
        'user_id' => $user->id,
        'organization_id' => null,
    ]);
});

it('rejects duplicate email addresses', function () {
    User::create([
        'first_name' => 'Existing',
        'last_name' => 'User',
        'email' => 'existing@example.com',
        'password' => 'secret123',
    ]);

    $this->post('/register', [
        'name' => 'Juan Dela Cruz',
        'email' => 'existing@example.com',
        'password' => 'secret123',
        'role' => 'coach',
    ])->assertSessionHasErrors('email');

    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
});

it('validates the registration fields', function () {
    $this->post('/register', [])
        ->assertSessionHasErrors(['name', 'email', 'password', 'role']);

    $this->post('/register', [
        'name' => 'J',
        'email' => 'not-an-email',
        'password' => 'short',
        'role' => 'organizer',
    ])->assertSessionHasErrors(['name', 'email', 'password', 'role']);
});
