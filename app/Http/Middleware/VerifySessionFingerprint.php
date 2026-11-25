<?php

/** Application class Verify session fingerprint. */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifySessionFingerprint
{
    /**
     * Handle.
     *
     * @param  Request  $request  The request.
     * @param  Closure  $next  The next.
     * @return Response The result.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $fingerprint = hash('sha256', (string) $request->userAgent());
        $stored = $request->session()->get('auth.fingerprint');

        if (! is_string($stored) || $stored === '') {
            $request->session()->put('auth.fingerprint', $fingerprint);

            return $next($request);
        }

        if (! hash_equals($stored, $fingerprint)) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->is('livewire/*')) {
                return response()->json(['message' => 'Your session ended for security reasons.'], 401);
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your session ended for security reasons. Sign in again.']);
        }

        return $next($request);
    }
}
