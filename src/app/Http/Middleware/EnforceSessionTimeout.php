<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $timeout = max(1, (int) config('auth.session.idle_timeout', 60)) * 60;
        $lastActivity = $request->session()->get('auth.last_activity');

        if (! is_numeric($lastActivity)) {
            $request->session()->put('auth.last_activity', time());

            return $next($request);
        }

        if (time() - (int) $lastActivity >= $timeout) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->is('livewire/*')) {
                return response()->json(['message' => 'Your session expired due to inactivity.'], 401);
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your session expired due to inactivity. Sign in again.']);
        }

        $request->session()->put('auth.last_activity', time());

        return $next($request);
    }
}
