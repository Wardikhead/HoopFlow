<?php

/** HTTP controller for Tournament controller. */

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Contracts\View\View;

class TournamentController extends Controller
{
    /**
     * Show.
     *
     * @param  Tournament  $tournament  The tournament.
     * @return View The result.
     */
    public function show(Tournament $tournament): View
    {
        $tournament->load(['divisions']);

        return view('pages.tournaments.show', [
            'tournament' => $tournament,
        ]);
    }
}
