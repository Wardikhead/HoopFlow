<?php

/** Tests for Session security test. */

use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('ends a session when the browser fingerprint changes', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->withSession([
            'auth.fingerprint' => hash('sha256', 'original-browser'),
            'auth.last_activity' => time(),
        ])
        ->withHeader('User-Agent', 'different-browser')
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('ends an idle session after the configured timeout', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $timeout = (int) config('auth.session.idle_timeout');

    $this->actingAs($organizer)
        ->withSession([
            'auth.last_activity' => time() - ($timeout * 60) - 5,
        ])
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('keeps a recently active session alive', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->withSession([
            'auth.last_activity' => time() - 30,
        ])
        ->get('/dashboard')
        ->assertOk();
});

it('adds defensive security headers to responses', function () {
    $response = $this->get('/')->assertOk();

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'");

    expect($response->headers->get('Permissions-Policy'))
        ->toContain('camera=()')
        ->toContain('microphone=()')
        ->toContain('geolocation=()');
});

it('hides the demo quick-fill credentials when demo accounts are disabled', function () {
    config(['app.demo_accounts' => false]);

    $this->get('/login')
        ->assertOk()
        ->assertDontSee('Quick-fill accounts')
        ->assertDontSee('organizer@hoopflow.ph')
        ->assertDontSee('quick-login-organizer', false);
});

it('keeps CSRF protection in the web middleware group', function () {
    expect(app('router')->getMiddlewareGroups()['web'])
        ->toContain(PreventRequestForgery::class);
});

it('includes CSRF tokens in the authentication forms', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('name="_token"', false);

    $this->get('/register')
        ->assertOk()
        ->assertSee('name="_token"', false);
});

it('ships hardened session cookie settings', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(config('session.serialization'))->toBe('json');
});
