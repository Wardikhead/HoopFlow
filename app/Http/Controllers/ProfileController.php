<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Rules\NotCommonPassword;
use App\Services\AuditLogger;
use App\Services\SessionRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show(Request $request, SessionRegistry $sessions): View
    {
        return view('pages.profile', [
            'activeSessions' => $sessions->forUser($request->user(), $request),
        ]);
    }

    public function showPasswordForm(Request $request): View
    {
        return view('pages.profile-password');
    }

    public function destroyOtherSessions(Request $request, SessionRegistry $sessions): RedirectResponse
    {
        $count = $sessions->revokeOthers($request->user(), $request);

        return redirect()
            ->route('profile')
            ->with('status', match ($count) {
                0 => 'No other sessions were signed in.',
                1 => 'Signed out 1 other session.',
                default => "Signed out {$count} other sessions.",
            });
    }

    public function updatePassword(
        Request $request,
        SessionRegistry $sessions,
        AuditLogger $audit,
    ): RedirectResponse {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', new NotCommonPassword],
        ], [
            'current_password.required' => 'Enter your current password.',
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.required' => 'Use at least 8 characters.',
            'password.min' => 'Use at least 8 characters.',
            'password.max' => 'Keep the password under 255 characters.',
            'password.confirmed' => 'The passwords do not match.',
        ]);

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        $sessions->revokeOthers($user, $request);

        $audit->log(
            action: 'auth.password_change',
            entityType: User::class,
            entityId: $user->id,
            actorUserId: $user->id,
        );

        try {
            $user->notify(new PasswordChangedNotification);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()
            ->route('profile')
            ->with('status', 'Your password has been changed. Other sessions were signed out.');
    }
}
