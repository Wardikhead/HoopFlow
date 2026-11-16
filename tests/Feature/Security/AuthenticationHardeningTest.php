<?php

use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('throttles repeated failed login attempts', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post('/login', [
            'email' => 'organizer@hoopflow.ph',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $response = $this->post('/login', [
        'email' => 'organizer@hoopflow.ph',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))
        ->toContain('Too many login attempts')
        ->toContain('seconds');

    $this->assertGuest();
});

it('counts throttled attempts against the normalized email address', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post('/login', [
            'email' => 'ORGANIZER@HOOPFLOW.PH',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post('/login', [
        'email' => 'organizer@hoopflow.ph',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('clears the login throttle after a successful sign in', function () {
    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $this->post('/login', [
            'email' => 'organizer@hoopflow.ph',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post('/login', [
        'email' => 'organizer@hoopflow.ph',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->post('/logout')->assertRedirect(route('login'));

    $this->post('/login', [
        'email' => 'organizer@hoopflow.ph',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->post('/login', [
        'email' => 'organizer@hoopflow.ph',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

it('rejects inactive accounts with the generic invalid credentials message', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $organizer->update(['status' => 'suspended']);

    $this->post('/login', [
        'email' => 'organizer@hoopflow.ph',
        'password' => 'password',
    ])->assertSessionHasErrors([
        'email' => 'Invalid credentials.',
    ]);

    $this->assertGuest();
});

it('ends an existing session when the account is no longer active', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $organizer->update(['status' => 'suspended']);

    $this->actingAs($organizer)
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('regenerates the session id when signing in', function () {
    $this->get('/login')->assertOk();

    $before = session()->getId();

    $this->post('/login', [
        'email' => 'organizer@hoopflow.ph',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    expect(session()->getId())->not->toBe($before);
});

it('stores registered passwords as one-way hashes', function () {
    $this->post('/register', [
        'name' => 'Hash Check',
        'email' => 'hash-check@example.com',
        'password' => 'secret123',
        'role' => 'coach',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'hash-check@example.com')->firstOrFail();

    expect($user->password)
        ->not->toBe('secret123')
        ->and(Hash::check('secret123', $user->password))->toBeTrue()
        ->and(Hash::needsRehash($user->password))->toBeFalse();
});
