<?php

/** Application class App service provider. */

namespace App\Providers;

use App\Models\User;
use App\Services\Calendar\IcsCalendarWriter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\RequireLivewireHeaders;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(IcsCalendarWriter::class, fn (): IcsCalendarWriter => new IcsCalendarWriter(
            (string) config('hoopflow.timezone', 'Asia/Manila'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user) => $user->hasRole('organizer'));
        Gate::define('reset-demo', fn (User $user) => $user->hasRole('organizer'));
        Gate::define('view-audit', fn (User $user) => $user->hasRole('organizer'));

        $this->configureMailTransport();
        $this->configureRateLimiting();
        $this->configureLivewireUpdateRoute();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Register the Brevo HTTPS API transport.
     *
     * Railway blocks outbound SMTP on its Free, Trial, and Hobby plans, so
     * production sends through Brevo's API instead of the SMTP relay.
     */
    private function configureMailTransport(): void
    {
        Mail::extend('brevo', function (): BrevoApiTransport {
            $key = (string) config('services.brevo.key');

            if ($key === '') {
                throw new \RuntimeException('BREVO_API_KEY is not configured.');
            }

            return new BrevoApiTransport($key);
        });
    }

    /**
     * Register the named rate limiters used by the HTTP middleware.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('web', function (Request $request) {
            // API, Livewire update, and Livewire asset/upload routes have their
            // own limits. Skipping them here avoids double-counting requests.
            if ($request->is('api/*', 'livewire-*/update', 'livewire/update')) {
                return Limit::none();
            }

            return Limit::perMinute((int) config('rate_limits.web.max_attempts', 120))
                ->by($this->rateLimitKey($request));
        });

        RateLimiter::for('livewire', function (Request $request) {
            return Limit::perMinute((int) config('rate_limits.livewire.max_attempts', 240))
                ->by($this->rateLimitKey($request));
        });

        RateLimiter::for('register', function (Request $request) {
            $key = 'ip:'.$request->ip();

            return [
                Limit::perMinute((int) config('rate_limits.register.per_minute', 5))
                    ->by('minute|'.$key),
                Limit::perHour((int) config('rate_limits.register.per_hour', 60))
                    ->by('hour|'.$key),
            ];
        });

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

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('rate_limits.api.max_attempts', 120))
                ->by($this->rateLimitKey($request));
        });
    }

    /**
     * Keep Livewire's hash-based update path and add the dedicated throttle.
     */
    private function configureLivewireUpdateRoute(): void
    {
        Livewire::setUpdateRoute(function ($handle, $path) {
            return Route::post($path, $handle)
                ->middleware(['web', RequireLivewireHeaders::class, 'throttle:livewire'])
                ->name('livewire.update');
        });
    }

    /**
     * Rate limit key.
     *
     * @param  Request  $request  The request.
     * @return string The result.
     */
    private function rateLimitKey(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        return $userId !== null ? 'user:'.$userId : 'ip:'.$request->ip();
    }
}
