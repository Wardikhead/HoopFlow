<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentController;
use App\Livewire\Coach\TeamRegister;
use App\Livewire\Organizer\BracketShow;
use App\Livewire\Organizer\TeamsIndex;
use App\Livewire\Organizer\TournamentCreate;
use App\Livewire\Organizer\TournamentsIndex;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::view('/', 'pages.home')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/tournaments', TournamentsIndex::class)->name('tournaments');
    Route::get('/tournaments/create', TournamentCreate::class)->name('tournamentCreate')->middleware('role:organizer');
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournamentShow');

    Route::get('/teams', TeamsIndex::class)->name('teams');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teamShow');
    Route::get('/my-team', [TeamController::class, 'myTeam'])->name('myTeam')->middleware('role:coach');
    Route::get('/team-register', TeamRegister::class)->name('teamRegister')->middleware('role:coach');

    Route::get('/bracket', BracketShow::class)->name('bracket');

    foreach ([
        'schedule' => '/schedule',
        'standings' => '/standings',
        'announcements' => '/announcements',
        'submissions' => '/submissions',
        'payments' => '/payments',
        'audit' => '/audit',
        'gameStats' => '/game-stats',
        'profile' => '/profile',
    ] as $name => $uri) {
        Route::get($uri, fn () => view('pages.dashboard'))->name($name);
    }
});
