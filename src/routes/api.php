<?php

/** Route definitions for Api. */

use App\Http\Controllers\Api\MatchScoreController;
use App\Http\Controllers\Api\PublicLiveScoreController;
use Illuminate\Support\Facades\Route;

Route::get('/public/tournaments/{slug}/live', PublicLiveScoreController::class)
    ->name('public.tournaments.live');

Route::middleware('auth')->group(function () {
    Route::patch('/matches/{match}/score', MatchScoreController::class)
        ->name('matches.score');
});
