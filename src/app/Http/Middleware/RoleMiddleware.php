<?php

/** Application class Role middleware. */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle.
     *
     * @param  Request  $request  The request.
     * @param  Closure  $next  The next.
     * @param  string...  $roles  The roles.
     * @return Response The result.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            abort(403);
        }

        if ($user->hasAnyRole(...$roles)) {
            return $next($request);
        }

        abort(403);
    }
}
