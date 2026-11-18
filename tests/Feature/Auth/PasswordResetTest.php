<?php

use App\Http\Controllers\PasswordResetController;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Services\SessionRegistry;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Notification::fake();
});

it('sends a reset link and returns the same status for a known email', function () {
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'RESET@EXAMPLE.COM'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', PasswordResetController::GENERIC_SENT_MESSAGE);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
    $this->assertDatabaseHas('password_reset_tokens', ['email' => 'reset@example.com']);
});

it('returns the same status for an unknown email and sends nothing', function () {
    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'missing@example.com'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', PasswordResetController::GENERIC_SENT_MESSAGE);

    Notification::assertNothingSent();
});

it('validates the email before requesting a link', function () {
    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');

    Notification::assertNothingSent();
});

it('rate limits link requests per ip', function () {
    config([
        'rate_limits.password_reset.request_per_minute' => 2,
        'rate_limits.password_reset.request_per_hour' => 100,
        'rate_limits.password_reset.request_per_email_per_minute' => 100,
    ]);

    foreach (range(1, 2) as $index) {
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => "missing{$index}@example.com"])
            ->assertRedirect(route('password.request'));
    }

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'missing3@example.com'])
        ->assertStatus(429);
});

it('resets the password, revokes sessions, audits, and notifies the owner', function () {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => 'old-password',
        'remember_token' => 'old-remember-token',
    ]);

    DB::table('sessions')->insert([
        'id' => 'existing-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'RESET@EXAMPLE.COM',
        'password' => 'New-secret-123',
        'password_confirmation' => 'New-secret-123',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your password has been reset. Sign in with your new password.');

    $user->refresh();

    expect(Hash::check('New-secret-123', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('old-remember-token');

    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'reset@example.com']);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'auth.password_reset',
        'entity_type' => User::class,
        'entity_id' => $user->id,
        'actor_user_id' => $user->id,
    ]);

    Notification::assertSentTo($user, PasswordChangedNotification::class);
});

it('still completes the reset when the confirmation email fails', function () {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => 'old-password',
    ]);

    $token = Password::broker()->createToken($user);

    app()->instance(Dispatcher::class, new class implements Dispatcher
    {
        public function send($notifiables, $notification)
        {
            throw new RuntimeException('SMTP unavailable');
        }

        public function sendNow($notifiables, $notification, ?array $channels = null)
        {
            throw new RuntimeException('SMTP unavailable');
        }
    });

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'New-secret-123',
        'password_confirmation' => 'New-secret-123',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your password has been reset. Sign in with your new password.');

    expect(Hash::check('New-secret-123', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset']);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'reset@example.com']);
});

it('rejects the old password and accepts the new password', function () {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => 'old-password',
    ]);

    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'New-secret-123',
        'password_confirmation' => 'New-secret-123',
    ])->assertRedirect(route('login'));

    $this->post(route('login'), [
        'email' => 'reset@example.com',
        'password' => 'old-password',
    ])->assertSessionHasErrors('email');

    $this->post(route('login'), [
        'email' => 'reset@example.com',
        'password' => 'New-secret-123',
    ])->assertRedirect(route('dashboard'));
});

it('rejects weak, common, and mismatched passwords', function (string $password, string $confirmation) {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => 'old-password',
    ]);

    $token = Password::broker()->createToken($user);

    $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
})->with([
    'too short' => ['short', 'short'],
    'too common' => ['password', 'password'],
    'mismatched' => ['New-secret-123', 'Different-secret-123'],
]);

it('rejects an invalid token', function () {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => 'old-password',
    ]);

    $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
        ->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'New-secret-123',
            'password_confirmation' => 'New-secret-123',
        ])
        ->assertSessionHasErrors([
            'email' => 'This reset link is invalid or has expired. Request a new one.',
        ]);

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('rejects an expired token', function () {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => 'old-password',
    ]);

    $token = Password::broker()->createToken($user);

    $this->travel(61)->minutes();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'New-secret-123',
        'password_confirmation' => 'New-secret-123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('rejects a reused token', function () {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => 'old-password',
    ]);

    $token = Password::broker()->createToken($user);
    $payload = [
        'token' => $token,
        'email' => $user->email,
        'password' => 'New-secret-123',
        'password_confirmation' => 'New-secret-123',
    ];

    $this->post(route('password.update'), $payload)->assertRedirect(route('login'));
    $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
});

it('redirects authenticated users away from the password reset routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('password.request'))->assertRedirect(route('dashboard'));
    $this->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('dashboard'));
    $this->get(route('password.reset', ['token' => 'token', 'email' => $user->email]))
        ->assertRedirect(route('dashboard'));
    $this->post(route('password.update'), [])->assertRedirect(route('dashboard'));
});

it('builds a reset email with the correct action url', function () {
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $mail = (new ResetPasswordNotification('token-123'))->toMail($user);

    expect($mail->subject)->toBe('Reset your HoopFlow password')
        ->and($mail->actionText)->toBe('Reset password')
        ->and($mail->actionUrl)->toBe(
            route('password.reset', ['token' => 'token-123', 'email' => 'reset@example.com'])
        );
});

it('routes reset notifications through the custom notification', function () {
    $user = User::factory()->create();

    $user->sendPasswordResetNotification('token-123');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('builds a password changed confirmation', function () {
    $user = User::factory()->create();

    $mail = (new PasswordChangedNotification)->toMail($user);

    expect($mail->subject)->toBe('Your HoopFlow password was changed')
        ->and($mail->actionUrl)->toBe(route('login'));
});

it('revokes every session for a user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $session = fn (string $id, int $userId): array => [
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ];

    DB::table('sessions')->insert([
        $session('one', $user->id),
        $session('two', $user->id),
        $session('three', $other->id),
    ]);

    $removed = app(SessionRegistry::class)->revokeAll($user);

    expect($removed)->toBe(2);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
});

it('shows the forgot password page', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Forgot password')
        ->assertSee('forgot-password-email', false)
        ->assertSee(route('login'), false);
});

it('shows the reset password form with the token, email, and strength meter', function () {
    $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'reset@example.com']))
        ->assertOk()
        ->assertSee('test-token', false)
        ->assertSee('reset-password-email', false)
        ->assertSee('reset-password-password-toggle', false)
        ->assertSee('Password requirements')
        ->assertSee('aria-valuemax="4"', false);
});

it('shows the forgot password link and reset status on the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('login-forgot-password', false)
        ->assertSee(route('password.request'), false);

    $this->withSession(['status' => 'Your password has been reset. Sign in with your new password.'])
        ->get(route('login'))
        ->assertSee('Your password has been reset. Sign in with your new password.');
});
