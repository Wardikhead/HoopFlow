<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Contracts\View\View;

class TeamController extends Controller
{
    public function show(Team $team): View
    {
        $team->load([
            'players',
            'coachUser',
            'registrations.tournament',
            'registrations.division',
            'registrations.payment',
        ]);

        $registration = $team->registrations->first();

        return view('pages.teams.show', [
            'team' => $team,
            'registration' => $registration,
        ]);
    }
}
