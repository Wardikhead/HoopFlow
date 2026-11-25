<?php

/** Configuration for Rate limits. */

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

    'password_reset' => [
        'request_per_minute' => (int) env('RATE_LIMIT_PASSWORD_RESET_PER_MINUTE', 5),
        'request_per_hour' => (int) env('RATE_LIMIT_PASSWORD_RESET_PER_HOUR', 20),
        'request_per_email_per_minute' => (int) env('RATE_LIMIT_PASSWORD_RESET_PER_EMAIL_PER_MINUTE', 3),
        'submit_per_minute' => (int) env('RATE_LIMIT_PASSWORD_RESET_SUBMIT_PER_MINUTE', 10),
    ],

    'api' => [
        'max_attempts' => (int) env('RATE_LIMIT_API_MAX_ATTEMPTS', 120),
    ],

];
