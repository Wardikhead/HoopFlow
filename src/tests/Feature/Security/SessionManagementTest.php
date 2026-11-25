<?php

/** Tests for Session management test. */

use App\Models\User;
use App\Services\SessionRegistry;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function hfSessionRow(int $userId, string $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '203.0.113.24',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'payload' => '',
        'last_activity' => now()->subMinutes(4)->timestamp,
    ], $overrides);
}

it('lists active sessions with device and network details', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    DB::table('sessions')->insert(hfSessionRow($organizer->id, 'session-laptop'));

    $this->actingAs($organizer)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Active sessions')
        ->assertSee('Chrome on Windows')
        ->assertSee('203.0.113.24')
        ->assertSee('data-testid="profile-sessions"', false);
});

it('marks the current session in the registry', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $sessionId = str_repeat('a', 40);

    DB::table('sessions')->insert(hfSessionRow($organizer->id, $sessionId));

    $request = Request::create('/profile');
    $request->setLaravelSession(app('session')->driver());
    $request->session()->setId($sessionId);

    $sessions = app(SessionRegistry::class)->forUser($organizer, $request);

    expect($sessions->where('current', true))->toHaveCount(1)
        ->and($sessions->firstWhere('current', true)['device'])->toBe('Chrome on Windows');
});

it('revokes other sessions while keeping the current one and other users', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();

    DB::table('sessions')->insert([
        hfSessionRow($organizer->id, 'session-phone', ['user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1']),
        hfSessionRow($organizer->id, 'session-tablet'),
        hfSessionRow($coach->id, 'session-coach'),
    ]);

    $this->actingAs($organizer)
        ->delete('/profile/sessions')
        ->assertRedirect(route('profile'));

    $this->assertDatabaseMissing('sessions', ['id' => 'session-phone']);
    $this->assertDatabaseMissing('sessions', ['id' => 'session-tablet']);
    $this->assertDatabaseHas('sessions', ['id' => 'session-coach']);

    expect(session('status'))->toContain('Signed out');
});

it('requires authentication before revoking sessions', function () {
    $this->delete('/profile/sessions')->assertRedirect(route('login'));
});

it('includes a CSRF-protected form for revoking sessions', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    DB::table('sessions')->insert(hfSessionRow($organizer->id, 'session-phone'));

    $this->actingAs($organizer)
        ->get('/profile')
        ->assertOk()
        ->assertSee('name="_token"', false)
        ->assertSee('name="_method" value="DELETE"', false);
});
