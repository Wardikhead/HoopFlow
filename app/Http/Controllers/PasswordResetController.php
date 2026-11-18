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
            try {
                $resetUser->notify(new PasswordChangedNotification);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. Sign in with your new password.');
    }
}
