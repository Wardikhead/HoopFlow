<?php

namespace App\Http\Controllers;

use App\Services\SessionRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request, SessionRegistry $sessions): View
    {
        return view('pages.profile', [
            'activeSessions' => $sessions->forUser($request->user(), $request),
        ]);
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
}
