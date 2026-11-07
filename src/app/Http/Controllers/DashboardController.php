<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index(Request $request): View
    {
        $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->first()
            ?? Tournament::first();

        $user = $request->user();

        $data = [
            'tournament' => $tournament,
            'user' => $user,
            'kpis' => $tournament ? $this->dashboard->organizerKpis($tournament) : [],
            'paymentSummary' => $tournament ? $this->dashboard->paymentSummary($tournament) : [],
            'recentResults' => $tournament ? $this->dashboard->recentResults($tournament, 3) : collect(),
            'announcements' => $tournament ? $this->dashboard->announcements($tournament, 2) : collect(),
            'upcomingMatches' => $tournament ? $this->dashboard->upcomingMatches($tournament, 4) : collect(),
            'coachTeams' => $user ? $this->dashboard->coachTeams($user) : collect(),
        ];

        return view('pages.dashboard', $data);
    }
}
