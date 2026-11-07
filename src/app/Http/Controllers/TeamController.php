<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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

    public function myTeam(Request $request): View
    {
        $user = $request->user();

        $team = null;
        if ($user !== null) {
            $team = Team::where('coach_user_id', $user->id)->first();
        }

        if ($team !== null) {
            $team->load([
                'players',
                'coachUser',
                'registrations.tournament',
                'registrations.division',
                'registrations.payment',
            ]);
        }

        $registration = $team?->registrations?->first();

        return view('pages.teams.my-team', [
            'team' => $team,
            'registration' => $registration,
            'user' => $user,
        ]);
    }
}
