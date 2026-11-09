<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TeamController;
use App\Livewire\Coach\MyTeam;
use App\Livewire\Coach\TeamRegister;
use App\Livewire\Organizer\AnnouncementCreate;
use App\Livewire\Organizer\AnnouncementsIndex;
use App\Livewire\Organizer\AuditLogIndex;
use App\Livewire\Organizer\BracketShow;
use App\Livewire\Organizer\GameStatsShow;
use App\Livewire\Organizer\MatchesIndex;
use App\Livewire\Organizer\PaymentsIndex;
use App\Livewire\Organizer\ScheduleShow;
use App\Livewire\Organizer\SubmissionsIndex;
use App\Livewire\Organizer\TeamsIndex;
use App\Livewire\Organizer\TournamentCreate;
use App\Livewire\Organizer\TournamentShow;
use App\Livewire\Organizer\TournamentsIndex;
use App\Livewire\Public\StandingsShow;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->name('login.store')->middleware('guest');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register')->middleware('guest');
Route::post('/register', [AuthController::class, 'register'])->name('register.store')->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::view('/', 'pages.home')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/tournaments', TournamentsIndex::class)->name('tournaments');
    Route::get('/tournaments/create', TournamentCreate::class)->name('tournamentCreate')->middleware('role:organizer');
    Route::get('/tournaments/{tournament}', TournamentShow::class)->name('tournamentShow');

    Route::get('/teams', TeamsIndex::class)->name('teams');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teamShow');
    Route::get('/my-team', MyTeam::class)->name('myTeam')->middleware('role:coach');
    Route::get('/team-register', TeamRegister::class)->name('teamRegister')->middleware('role:coach');

    Route::get('/bracket', BracketShow::class)->name('bracket');

    Route::get('/matches', MatchesIndex::class)->name('matches');

    Route::get('/schedule', ScheduleShow::class)->name('schedule');

    Route::get('/game-stats', GameStatsShow::class)->name('gameStats');

    Route::get('/announcements', AnnouncementsIndex::class)->name('announcements');
    Route::get('/announcements/create', AnnouncementCreate::class)->name('announcementCreate')->middleware('role:organizer');

    Route::view('/profile', 'pages.profile')->name('profile');

    Route::get('/audit', AuditLogIndex::class)->name('audit')->middleware('role:organizer');

    Route::get('/payments', PaymentsIndex::class)->name('payments')->middleware('role:organizer');

    Route::get('/submissions', SubmissionsIndex::class)->name('submissions')->middleware('role:organizer');

    Route::get('/standings', StandingsShow::class)->name('standings');
});
