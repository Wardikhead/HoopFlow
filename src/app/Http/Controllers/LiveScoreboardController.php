<?php

/** HTTP controller for Live scoreboard controller. */

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\LiveScoreService;
use Illuminate\Contracts\View\View;

final class LiveScoreboardController extends Controller
{
    /**
     * Invoke the instance as a callable.
     *
     * @param  Tournament  $tournament  The tournament.
     * @param  LiveScoreService  $liveScores  The live scores.
     * @return View The result.
     */
    public function __invoke(Tournament $tournament, LiveScoreService $liveScores): View
    {
        return view('pages.live.show', [
            'tournament' => $tournament,
            'snapshot' => $liveScores->snapshot($tournament),
        ]);
    }
}
