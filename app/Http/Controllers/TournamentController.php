<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Contracts\View\View;

class TournamentController extends Controller
{
    public function show(Tournament $tournament): View
    {
        $tournament->load(['divisions']);

        return view('pages.tournaments.show', [
            'tournament' => $tournament,
        ]);
    }
}
