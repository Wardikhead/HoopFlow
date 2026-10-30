<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home')->name('home');

/*
|--------------------------------------------------------------------------
| Feature routes
|--------------------------------------------------------------------------
|
| Keep this file as a table of contents. Each feature registers its own
| routes from routes/features/{feature}.php, loaded below.
|
*/

// Route::middleware('auth')->group(base_path('routes/features/dashboard.php'));
