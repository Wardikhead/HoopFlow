<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | These values define the named limiters registered in AppServiceProvider.
    | Requests are keyed by the authenticated user when available, falling
    | back to the client IP address for guests. Values are attempts allowed
    | per window.
    |
    */

    'web' => [
        'max_attempts' => (int) env('RATE_LIMIT_WEB_MAX_ATTEMPTS', 120),
    ],

    'livewire' => [
        'max_attempts' => (int) env('RATE_LIMIT_LIVEWIRE_MAX_ATTEMPTS', 240),
    ],

    'register' => [
        'per_minute' => (int) env('RATE_LIMIT_REGISTER_PER_MINUTE', 5),
        'per_hour' => (int) env('RATE_LIMIT_REGISTER_PER_HOUR', 60),
    ],

    'api' => [
        'max_attempts' => (int) env('RATE_LIMIT_API_MAX_ATTEMPTS', 120),
    ],

];
