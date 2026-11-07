<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::view('/', 'pages.home')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => view('pages.dashboard'))->name('dashboard');

    // Shell navigation destinations (full pages land in later phases).
    foreach ([
        'tournaments' => '/tournaments',
        'teams' => '/teams',
        'bracket' => '/bracket',
        'schedule' => '/schedule',
        'standings' => '/standings',
        'announcements' => '/announcements',
        'submissions' => '/submissions',
        'payments' => '/payments',
        'audit' => '/audit',
        'myTeam' => '/my-team',
        'teamRegister' => '/team-register',
        'gameStats' => '/game-stats',
        'tournamentCreate' => '/tournaments/create',
        'profile' => '/profile',
    ] as $name => $uri) {
        Route::get($uri, fn () => view('pages.dashboard'))->name($name);
    }
});
