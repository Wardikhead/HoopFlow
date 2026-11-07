<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', fn (User $user) => $user->hasRole('organizer'));
        Gate::define('reset-demo', fn (User $user) => $user->hasRole('organizer'));
        Gate::define('view-audit', fn (User $user) => $user->hasRole('organizer'));
    }
}
