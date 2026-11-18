# Forgot and Reset Password Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a production-ready forgot-password and reset-password flow for HoopFlow.

**Architecture:** Use Laravel's native password broker with a guest-only controller, two branded mail notifications, hardened rate limiting, session revocation, audit logging, and Blade pages that match the existing auth shell.

**Tech Stack:** Laravel 13, PHP 8.3+, Blade, Livewire 4/Alpine 3, Bootstrap 5.3, Pest, PostgreSQL/SQLite.

## Global Constraints

- Work in `src/`. Follow `src/AGENTS.md`.
- Run PHP, Artisan, and Composer commands from `src/`; run `git` commands from the repository root.
- No Tailwind, Vue, React, or Bootstrap JavaScript.
- Use `composer quality` from `src/` before claiming completion.
- Do not commit `.env` or real credentials.
- Keep the existing `NotCommonPassword`, `min:8`, and `max:255` password policy.
- Keep all password reset routes guest-only.
- Never expose reset tokens, passwords, or full reset URLs in logs.

---

### Task 1: Backend flow, notifications, and rate limits

**Files:**
- Modify: `src/config/auth.php`
- Modify: `src/config/rate_limits.php`
- Modify: `src/app/Providers/AppServiceProvider.php`
- Modify: `src/app/Services/SessionRegistry.php`
- Modify: `src/app/Models/User.php`
- Modify: `src/routes/web.php`
- Create: `src/app/Http/Controllers/PasswordResetController.php`
- Create: `src/app/Notifications/ResetPasswordNotification.php`
- Create: `src/app/Notifications/PasswordChangedNotification.php`
- Test: `src/tests/Feature/Auth/PasswordResetTest.php`

**Interfaces:**
- Consumes: `SessionRegistry`, `AuditLogger`, `User`, `NotCommonPassword`.
- Produces: `PasswordResetController::GENERIC_SENT_MESSAGE`, routes `password.request`, `password.email`, `password.reset`, `password.update`, `SessionRegistry::revokeAll(User): int`, notification classes.

- [ ] **Step 1: Write the failing backend tests**

Create `src/tests/Feature/Auth/PasswordResetTest.php` with:

```php
<?php

use App\Http\Controllers\PasswordResetController;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Services\SessionRegistry;
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
```

- [ ] **Step 2: Run the test and confirm it fails**

Run from `src/`:

```powershell
php artisan test tests/Feature/Auth/PasswordResetTest.php
```

Expected: failures for missing routes, controller, notifications, and `revokeAll`.

- [ ] **Step 3: Implement the configuration and services**

In `src/config/auth.php`, replace the `users` broker expiry and throttle:

```php
'users' => [
    'provider' => 'users',
    'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
    'expire' => (int) env('PASSWORD_RESET_EXPIRE', 60),
    'throttle' => (int) env('PASSWORD_RESET_THROTTLE', 60),
],
```

In `src/config/rate_limits.php`, add before `api`:

```php
'password_reset' => [
    'request_per_minute' => (int) env('RATE_LIMIT_PASSWORD_RESET_PER_MINUTE', 5),
    'request_per_hour' => (int) env('RATE_LIMIT_PASSWORD_RESET_PER_HOUR', 20),
    'request_per_email_per_minute' => (int) env('RATE_LIMIT_PASSWORD_RESET_PER_EMAIL_PER_MINUTE', 3),
    'submit_per_minute' => (int) env('RATE_LIMIT_PASSWORD_RESET_SUBMIT_PER_MINUTE', 10),
],
```

Add to `src/app/Services/SessionRegistry.php` after `revokeOthers`:

```php
public function revokeAll(User $user): int
{
    return DB::table($this->table())
        ->where('user_id', $user->id)
        ->delete();
}
```

In `src/app/Providers/AppServiceProvider.php`, import `Illuminate\Support\Str` and add these limiters inside `configureRateLimiting()` after the `register` limiter:

```php
RateLimiter::for('password-email', function (Request $request) {
    $email = Str::lower(trim((string) $request->input('email')));
    $emailKey = $email !== '' ? hash('sha256', $email) : 'missing';

    return [
        Limit::perMinute((int) config('rate_limits.password_reset.request_per_minute', 5))
            ->by('password-email-minute|ip:'.$request->ip()),
        Limit::perHour((int) config('rate_limits.password_reset.request_per_hour', 20))
            ->by('password-email-hour|ip:'.$request->ip()),
        Limit::perMinute((int) config('rate_limits.password_reset.request_per_email_per_minute', 3))
            ->by('password-email-address|email:'.$emailKey),
    ];
});

RateLimiter::for('password-reset', function (Request $request) {
    return Limit::perMinute((int) config('rate_limits.password_reset.submit_per_minute', 10))
        ->by('password-reset|ip:'.$request->ip());
});
```

- [ ] **Step 4: Implement the notifications and User hook**

Create `src/app/Notifications/ResetPasswordNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $expires = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );

        return (new MailMessage)
            ->subject('Reset your HoopFlow password')
            ->greeting('Reset your password')
            ->line('We received a request to reset the password for your HoopFlow account.')
            ->action('Reset password', route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->line("This link expires in {$expires} minutes.")
            ->line('If you did not request this, you can safely ignore this email. Your password will not change.')
            ->salutation('— HoopFlow');
    }
}
```

Create `src/app/Notifications/PasswordChangedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your HoopFlow password was changed')
            ->greeting('Your password was changed')
            ->line('The password for your HoopFlow account was just reset.')
            ->line('All other sessions were signed out for your security.')
            ->line('If you did not make this change, reset your password immediately and contact support.')
            ->action('Sign in to HoopFlow', route('login'))
            ->salutation('— HoopFlow');
    }
}
```

In `src/app/Models/User.php`, import the notification and add after `casts()`:

```php
public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
{
    $this->notify(new \App\Notifications\ResetPasswordNotification($token));
}
```

- [ ] **Step 5: Implement the controller and routes**

Create `src/app/Http/Controllers/PasswordResetController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Rules\NotCommonPassword;
use App\Services\AuditLogger;
use App\Services\SessionRegistry;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public const GENERIC_SENT_MESSAGE = "If an account exists for that email, we've sent a password reset link. Check your inbox and spam folder.";

    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address.',
            'email.max' => 'Enter a valid email address.',
        ]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', self::GENERIC_SENT_MESSAGE);
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => Str::lower(trim((string) $request->query('email', ''))),
        ]);
    }

    public function reset(
        Request $request,
        SessionRegistry $sessions,
        AuditLogger $audit,
    ): RedirectResponse {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', new NotCommonPassword],
        ], [
            'token.required' => 'This reset link is invalid or has expired. Request a new one.',
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address.',
            'email.max' => 'Enter a valid email address.',
            'password.required' => 'Use at least 8 characters.',
            'password.min' => 'Use at least 8 characters.',
            'password.max' => 'Keep the password under 255 characters.',
            'password.confirmed' => 'The passwords do not match.',
        ]);

        $resetUser = null;

        $status = Password::reset(
            $data,
            function (User $user, string $password) use ($sessions, $audit, &$resetUser): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $sessions->revokeAll($user);

                $audit->log(
                    action: 'auth.password_reset',
                    entityType: User::class,
                    entityId: $user->id,
                    actorUserId: $user->id,
                );

                event(new PasswordReset($user));

                $resetUser = $user;
            },
        );

        if ($status !== Password::PasswordReset) {
            return back()
                ->withInput($request->only('email', 'token'))
                ->withErrors(['email' => 'This reset link is invalid or has expired. Request a new one.']);
        }

        if ($resetUser instanceof User) {
            $resetUser->notify(new PasswordChangedNotification);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. Sign in with your new password.');
    }
}
```

In `src/routes/web.php`, add after the logout route:

```php
Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])
    ->name('password.request')
    ->middleware('guest');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
    ->name('password.email')
    ->middleware(['guest', 'throttle:password-email']);
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
    ->name('password.reset')
    ->middleware('guest');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->name('password.update')
    ->middleware(['guest', 'throttle:password-reset']);
```

Add the import at the top of `src/routes/web.php`:

```php
use App\Http\Controllers\PasswordResetController;
```

- [ ] **Step 6: Run the backend tests and confirm they pass**

Run from `src/`:

```powershell
php artisan test tests/Feature/Auth/PasswordResetTest.php
```

Expected: all tests pass. The page-loading tests arrive in Task 2.

- [ ] **Step 7: Commit**

```powershell
git add src/config/auth.php src/config/rate_limits.php src/app/Providers/AppServiceProvider.php src/app/Services/SessionRegistry.php src/app/Models/User.php src/routes/web.php src/app/Http/Controllers/PasswordResetController.php src/app/Notifications src/tests/Feature/Auth/PasswordResetTest.php
git commit -m "feat(auth): add password reset backend and notifications"
```

---

### Task 2: Forgot and reset pages, login integration

**Files:**
- Create: `src/resources/views/components/password-meter.blade.php`
- Create: `src/resources/views/auth/forgot-password.blade.php`
- Create: `src/resources/views/auth/reset-password.blade.php`
- Modify: `src/resources/views/auth/login.blade.php`
- Modify: `src/resources/views/auth/register.blade.php`
- Modify: `src/resources/css/hf/_components.scss`
- Test: `src/tests/Feature/Auth/PasswordResetTest.php`

**Interfaces:**
- Consumes: routes from Task 1.
- Produces: `auth.forgot-password`, `auth.reset-password`, `x-password-meter`.

- [ ] **Step 1: Write the failing page tests**

Append to `src/tests/Feature/Auth/PasswordResetTest.php`:

```php
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
```

- [ ] **Step 2: Run the page tests and confirm they fail**

Run from `src/`:

```powershell
php artisan test tests/Feature/Auth/PasswordResetTest.php --filter="forgot password page|reset password form|forgot password link"
```

Expected: failures for missing views, component, and login link.

- [ ] **Step 3: Extract the password meter component**

Create `src/resources/views/components/password-meter.blade.php`:

```blade
@props(['model' => 'password'])

<div class="hf-password-meter" :class="'hf-score-'+evaluation.score" x-show="focused || {{ $model }}.length > 0" x-cloak>
    <div
        class="hf-password-bars"
        role="meter"
        aria-label="Password strength"
        aria-valuemin="0"
        aria-valuemax="4"
        :aria-valuenow="evaluation.score"
        :aria-valuetext="evaluation.label || 'Empty'">
        <template x-for="index in 4" :key="index">
            <span class="hf-password-bar" :class="{ 'is-filled': index <= evaluation.score }"></span>
        </template>
    </div>
    <p class="hf-password-hint" role="status" aria-live="polite" x-text="evaluation.hint"></p>
    <ul class="hf-password-checks" aria-label="Password requirements">
        @foreach ([
            'length' => 'At least 8 characters',
            'lowercase' => 'A lowercase letter',
            'uppercase' => 'An uppercase letter',
            'number' => 'A number',
            'symbol' => 'A symbol',
        ] as $key => $label)
            <li class="hf-password-check" :class="{ 'is-met': evaluation.checks['{{ $key }}'] }">
                <span class="hf-sr-only" x-text="evaluation.checks['{{ $key }}'] ? 'Met: ' : 'Not met: '"></span>
                <i class="bi" :class="evaluation.checks['{{ $key }}'] ? 'bi-check-circle-fill' : 'bi-circle'" aria-hidden="true"></i>
                <span>{{ $label }}</span>
            </li>
        @endforeach
    </ul>
</div>
```

In `src/resources/views/auth/register.blade.php`, replace the existing `<div class="hf-password-meter" ...>...</div>` block with:

```blade
<x-password-meter />
```

- [ ] **Step 4: Create the forgot-password page**

Create `src/resources/views/auth/forgot-password.blade.php`:

```blade
@extends('layouts.public')

@php($resetExpiry = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60))

@section('title', 'Forgot password')
@section('meta-description', 'Request a secure HoopFlow password reset link for your account.')
@section('body-class', 'hf-auth')
@section('main-class', 'hf-auth-main')

@section('content')
    <div class="hf-auth-shell">
        <aside class="hf-auth-aside" aria-hidden="true">
            <x-brand size="lg" on-dark tagline="Tournament ops" />
            <p class="hf-eyebrow hf-eyebrow--on-dark">Account recovery</p>
            <h2 class="hf-auth-aside__title">Get back to the sideline without missing a tip-off.</h2>
            <ul class="hf-auth-points">
                <li><i class="bi bi-shield-lock" aria-hidden="true"></i> Reset links expire after {{ $resetExpiry }} minutes</li>
                <li><i class="bi bi-envelope-check" aria-hidden="true"></i> We only send to the address on your account</li>
                <li><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Signing back in takes one more step</li>
            </ul>
        </aside>

        <div class="hf-auth-card" data-testid="forgot-password-card">
            <span class="hf-eyebrow">Account recovery</span>
            <h1 class="hf-auth-title">Forgot password</h1>
            <p class="hf-auth-sub">Enter your account email and we'll send a secure reset link.</p>

            @if (session('status'))
                <x-callout tone="success" class="hf-mb-2">{{ session('status') }}</x-callout>
            @endif

            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf

                <x-form-errors :targets="['email' => 'forgotEmail']" />
                <x-required-note class="hf-mb-2" />

                <div class="hf-field">
                    <label class="hf-label" for="forgotEmail">Email</label>
                    <input
                        class="hf-input"
                        id="forgotEmail"
                        name="email"
                        type="email"
                        autocomplete="email"
                        value="{{ old('email') }}"
                        data-testid="forgot-password-email"
                        required
                        @error('email') aria-invalid="true" aria-describedby="forgotEmail-error" @enderror
                    >
                    @error('email')
                        <div class="hf-field-error" id="forgotEmail-error">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="hf-btn hf-btn--primary hf-full hf-mt-3" data-testid="forgot-password-submit">
                    Send reset link
                </button>
            </form>

            <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
                Remembered it? <a href="{{ route('login') }}">Sign in</a>.
            </p>
        </div>
    </div>
@endsection
```

- [ ] **Step 5: Create the reset-password page**

Create `src/resources/views/auth/reset-password.blade.php`:

```blade
@extends('layouts.public')

@php($resetExpiry = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60))

@section('title', 'Reset password')
@section('meta-description', 'Choose a new password for your HoopFlow account.')
@section('body-class', 'hf-auth')
@section('main-class', 'hf-auth-main')

@section('content')
    <div class="hf-auth-shell">
        <aside class="hf-auth-aside" aria-hidden="true">
            <x-brand size="lg" on-dark tagline="Tournament ops" />
            <p class="hf-eyebrow hf-eyebrow--on-dark">Account recovery</p>
            <h2 class="hf-auth-aside__title">One new password, then back to the bracket.</h2>
            <ul class="hf-auth-points">
                <li><i class="bi bi-shield-check" aria-hidden="true"></i> Links expire after {{ $resetExpiry }} minutes</li>
                <li><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Every other session is signed out</li>
                <li><i class="bi bi-envelope-check" aria-hidden="true"></i> We email a confirmation when it is done</li>
            </ul>
        </aside>

        <div class="hf-auth-card" data-testid="reset-password-card" x-data="hfPasswordMeter">
            <span class="hf-eyebrow">Account recovery</span>
            <h1 class="hf-auth-title">New password</h1>
            <p class="hf-auth-sub">Choose a strong password. Resetting signs out every other session.</p>

            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ old('token', $token) }}">

                <x-form-errors :targets="[
                    'email' => 'resetEmail',
                    'password' => 'resetPassword',
                    'token' => 'resetEmail',
                ]" />
                <x-required-note class="hf-mb-2" />

                <div class="hf-field">
                    <label class="hf-label" for="resetEmail">Email</label>
                    <input
                        class="hf-input"
                        id="resetEmail"
                        name="email"
                        type="email"
                        autocomplete="email"
                        value="{{ old('email', $email) }}"
                        data-testid="reset-password-email"
                        required
                        @error('email') aria-invalid="true" aria-describedby="resetEmail-error" @enderror
                    >
                    @error('email')
                        <div class="hf-field-error" id="resetEmail-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="hf-field hf-mt-2">
                    <label class="hf-label" for="resetPassword">New password</label>
                    <div class="hf-inline-form">
                        <input
                            class="hf-input"
                            id="resetPassword"
                            name="password"
                            type="password"
                            x-bind:type="showPassword ? 'text' : 'password'"
                            x-model="password"
                            x-init="$el.value && (password = $el.value)"
                            x-on:focus="focused = true"
                            x-on:blur="focused = false"
                            autocomplete="new-password"
                            data-testid="reset-password-password"
                            required
                            @error('password') aria-invalid="true" aria-describedby="resetPassword-error" @enderror
                        >
                        <button
                            type="button"
                            class="hf-btn hf-btn--sm"
                            x-on:click="toggle()"
                            x-bind:aria-pressed="showPassword ? 'true' : 'false'"
                            x-text="showPassword ? 'Hide' : 'Show'"
                            data-testid="reset-password-password-toggle"
                        >Show</button>
                    </div>
                    @error('password')
                        <div class="hf-field-error" id="resetPassword-error">{{ $message }}</div>
                    @enderror
                    <x-password-meter />
                </div>

                <div class="hf-field hf-mt-2" x-data="{ showConfirmation: false }">
                    <label class="hf-label" for="resetPasswordConfirmation">Confirm new password</label>
                    <div class="hf-inline-form">
                        <input
                            class="hf-input"
                            id="resetPasswordConfirmation"
                            name="password_confirmation"
                            type="password"
                            x-bind:type="showConfirmation ? 'text' : 'password'"
                            autocomplete="new-password"
                            data-testid="reset-password-confirmation"
                            required
                        >
                        <button
                            type="button"
                            class="hf-btn hf-btn--sm"
                            x-on:click="showConfirmation = ! showConfirmation"
                            x-bind:aria-pressed="showConfirmation ? 'true' : 'false'"
                            x-text="showConfirmation ? 'Hide' : 'Show'"
                            data-testid="reset-password-confirmation-toggle"
                        >Show</button>
                    </div>
                </div>

                <button type="submit" class="hf-btn hf-btn--primary hf-full hf-mt-3" data-testid="reset-password-submit">
                    Reset password
                </button>
            </form>

            <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
                Link expired? <a href="{{ route('password.request') }}">Request a new one</a>.
            </p>
        </div>
    </div>
@endsection
```

- [ ] **Step 6: Update the login page**

In `src/resources/views/auth/login.blade.php`, add after the intro paragraph:

```blade
@if (session('status'))
    <x-callout tone="success" class="hf-mb-2">{{ session('status') }}</x-callout>
@endif
```

Replace the password label line:

```blade
<label class="hf-label" for="loginPassword">Password</label>
```

with:

```blade
<div class="hf-between">
    <label class="hf-label" for="loginPassword">Password</label>
    <a class="hf-small" href="{{ route('password.request') }}" data-testid="login-forgot-password">Forgot password?</a>
</div>
```

In `src/resources/css/hf/_components.scss`, extend the required marker selector:

```scss
.hf-field:has([required]) > .hf-label::after,
.hf-field:has([required]) > .hf-between > .hf-label::after {
  content: ' *';
  color: var(--hf-danger-ink);
  font-weight: 700;
}
```

- [ ] **Step 7: Run the auth tests**

Run from `src/`:

```powershell
php artisan test tests/Feature/Auth
```

Expected: all auth tests pass, including the existing registration and login tests.

- [ ] **Step 8: Commit**

```powershell
git add src/resources/views/components/password-meter.blade.php src/resources/views/auth/forgot-password.blade.php src/resources/views/auth/reset-password.blade.php src/resources/views/auth/login.blade.php src/resources/views/auth/register.blade.php src/resources/css/hf/_components.scss src/tests/Feature/Auth/PasswordResetTest.php
git commit -m "feat(auth): add forgot and reset password pages"
```

---

### Task 3: Mailtrap production email setup

**Files:**
- Modify: `src/.env.example`
- Modify: `src/.env` (local only, never commit)
- Create: `docs/email-setup.md`

**Interfaces:**
- Consumes: `MAIL_*` variables read by `src/config/mail.php`.
- Produces: documented Mailtrap Email Sending configuration and env contract.

- [ ] **Step 1: Add the reset and rate limit variables to `.env.example`**

Under `# Authentication hardening` add:

```ini
PASSWORD_RESET_EXPIRE=60
PASSWORD_RESET_THROTTLE=60
```

Under `# Rate limiting` add:

```ini
RATE_LIMIT_PASSWORD_RESET_PER_MINUTE=5
RATE_LIMIT_PASSWORD_RESET_PER_HOUR=20
RATE_LIMIT_PASSWORD_RESET_PER_EMAIL_PER_MINUTE=3
RATE_LIMIT_PASSWORD_RESET_SUBMIT_PER_MINUTE=10
```

Append at the end of `.env.example`:

```ini
# Production email (Mailtrap Email Sending)
# 1. Verify the sending domain in Mailtrap.
# 2. Replace the token and sender address, then uncomment.
# MAIL_MAILER=smtp
# MAIL_HOST=live.smtp.mailtrap.io
# MAIL_PORT=587
# MAIL_SCHEME=null
# MAIL_USERNAME=api
# MAIL_PASSWORD=your-mailtrap-api-token
# MAIL_FROM_ADDRESS=no-reply@your-verified-domain.com
# MAIL_FROM_NAME="${APP_NAME}"
```

- [ ] **Step 2: Add the same block to the local `.env`**

Add the same active reset/rate variables and the commented Mailtrap block to `src/.env`. Do not commit this file.

- [ ] **Step 3: Write the email setup guide**

Create `docs/email-setup.md`:

```markdown
# Production email setup (Mailtrap Email Sending)

HoopFlow sends password reset and password changed messages through Laravel's
mailer. Local development defaults to `MAIL_MAILER=log`; production uses
Mailtrap Email Sending over SMTP.

## 1. Verify the sending domain

1. Sign in to Mailtrap and open **Email Sending → Sending Domains**.
2. Add the domain HoopFlow will send from, for example `hoopflow.ph`.
3. Add the SPF, DKIM, and DMARC records Mailtrap provides to your DNS.
4. Wait for the domain status to show as verified.

Mail must be sent from an address on this verified domain. `MAIL_FROM_ADDRESS`
must match it.

## 2. Get SMTP credentials

Open **Email Sending → Sending Setup**, choose the verified domain, select the
**Transactional** stream, and switch the view to **SMTP**.

Use the credentials shown there:

| Variable | Value |
|---|---|
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | `live.smtp.mailtrap.io` |
| `MAIL_PORT` | `587` |
| `MAIL_SCHEME` | `null` |
| `MAIL_USERNAME` | `api` |
| `MAIL_PASSWORD` | your Mailtrap API token |

Mailtrap creates a separate token for each sending domain. Creating a new token
invalidates the old one.

## 3. Configure the application

```ini
APP_URL=https://your-production-domain.example
MAIL_MAILER=smtp
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=api
MAIL_PASSWORD=your-mailtrap-api-token
MAIL_FROM_ADDRESS=no-reply@your-verified-domain.com
MAIL_FROM_NAME="HoopFlow"
```

Clear the cached config after changing values:

```powershell
php artisan config:clear
```

## 4. Verify a send

From the application, open `/forgot-password`, submit a real account email, and
open the message in Mailtrap **Email Logs**. The reset button must point at
`APP_URL`, not `localhost`.

Without the API token or a verified domain, delivery fails. The application
keeps the failure in `storage/logs/laravel.log`; it does not silently discard it.

## Local development

Keep `MAIL_MAILER=log` for local work. Reset emails are written to
`storage/logs/laravel.log` and no external account is needed.

To inspect real messages locally without a verified domain, use a Mailtrap Email
Sandbox inbox instead of Email Sending: create a sandbox, copy its SMTP
credentials, and keep the token in local `.env` only.

## Never commit secrets

`.env` is ignored by git. Keep API tokens out of `.env.example`, source files,
tests, and chat transcripts.
```

- [ ] **Step 4: Commit the tracked configuration and documentation**

```powershell
git add docs/email-setup.md src/.env.example
git commit -m "docs: add Mailtrap production email setup"
```

---

### Task 4: Quality gate

- [ ] **Step 1: Run Pint, Larastan, and Pest**

From `src/`:

```powershell
composer quality
```

Expected: Pint passes, Larastan reports no errors, Pest passes.

- [ ] **Step 2: Fix any real failures**

Run `composer format` if Pint reports style issues, then rerun `composer quality`.
Do not lower the password policy or remove security assertions to make a test pass.

- [ ] **Step 3: Report the actual output**

Report the exact Pint, Larastan, and Pest summary lines from the final run. If a
live Mailtrap delivery was not tested because credentials or a verified domain
were unavailable, say so explicitly.
