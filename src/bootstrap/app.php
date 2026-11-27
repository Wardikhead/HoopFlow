<?php

/** Application bootstrap for App. */

use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifySessionFingerprint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // API routes share the web session so the same Blade pages can call
            // them with fetch() and the CSRF meta tag. Public reads stay open.
            Route::middleware(['web', 'throttle:api'])
                ->prefix('api')
                ->name('api.')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'session.fingerprint' => VerifySessionFingerprint::class,
            'session.timeout' => EnforceSessionTimeout::class,
        ]);

        $middleware->web(
            append: [
                'throttle:web',
                EnsureUserIsActive::class,
                VerifySessionFingerprint::class,
                EnforceSessionTimeout::class,
            ],
            prepend: [
                SecurityHeaders::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
