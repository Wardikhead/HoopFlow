<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->is('livewire/*')) {
                return response()->json(['message' => 'Your account is inactive.'], 403);
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your account is inactive. Contact an organizer.']);
        }

        return $next($request);
    }
}
