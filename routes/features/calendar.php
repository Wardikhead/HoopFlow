<?php

use App\Http\Controllers\ScheduleCalendarController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/calendar/tournaments/{tournament}.ics', [ScheduleCalendarController::class, 'tournament'])
        ->whereNumber('tournament')
        ->name('calendar.tournament');

    Route::get('/calendar/tournaments/{tournament}/teams/{team}.ics', [ScheduleCalendarController::class, 'team'])
        ->whereNumber('tournament')
        ->whereNumber('team')
        ->name('calendar.team');
});
