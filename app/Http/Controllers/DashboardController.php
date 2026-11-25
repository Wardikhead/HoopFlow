<?php

/** HTTP controller for Dashboard controller. */

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\DashboardService;
use App\Services\StandingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Initialize the instance.
     *
     * @param  DashboardService  $dashboard  The dashboard.
     * @param  StandingsService  $standings  The standings.
     */
    public function __construct(
        protected DashboardService $dashboard,
        protected StandingsService $standings,
    ) {}

    /**
     * Index.
     *
     * @param  Request  $request  The request.
     * @return View The result.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $role = 'fan';

        if ($user !== null && $user->hasRole('organizer')) {
            $role = 'organizer';
        } elseif ($user !== null && $user->hasRole('coach')) {
            $role = 'coach';
        }

        $tournament = Tournament::query()
            ->whereNull('archived_at')
            ->where('status', 'active')
            ->orderBy('start_date')
            ->first()
            ?? Tournament::query()->whereNull('archived_at')->orderBy('start_date')->first();

        $data = [
            'tournament' => $tournament,
            'user' => $user,
            'role' => $role,
            'kpis' => [],
            'paymentSummary' => [],
            'recentResults' => collect(),
            'announcements' => collect(),
            'upcomingMatches' => collect(),
            'standings' => collect(),
            'nextMatch' => null,
            'coachTeams' => collect(),
            'coachTeam' => null,
            'coachRegistration' => null,
            'coachTournament' => null,
            'coachRecord' => ['wins' => 0, 'losses' => 0, 'pointsFor' => 0, 'pointsAgainst' => 0, 'diff' => 0],
            'coachNextMatch' => null,
            'coachRosterReady' => 0,
            'coachRosterTotal' => 0,
            'openTournaments' => collect(),
        ];

        if ($role === 'organizer') {
            $data['kpis'] = $tournament instanceof Tournament ? $this->dashboard->organizerKpis($tournament) : [];
            $data['paymentSummary'] = $tournament instanceof Tournament ? $this->dashboard->paymentSummary($tournament) : [];
            $data['recentResults'] = $tournament instanceof Tournament ? $this->dashboard->recentResults($tournament, 3) : collect();
            $data['announcements'] = $tournament instanceof Tournament ? $this->dashboard->announcements($tournament, 2) : collect();
            $data['standings'] = $tournament instanceof Tournament
                ? $this->standings->compute($tournament)->take(4)
                : collect();
            $data['nextMatch'] = $tournament instanceof Tournament ? $this->dashboard->nextMatch($tournament) : null;
        } elseif ($role === 'coach') {
            $coachTeams = $this->dashboard->coachTeams($user);
            $coachTeam = $coachTeams->first();

            $coachRegistration = null;
            $coachTournament = null;

            if ($coachTeam instanceof Team) {
                $coachTeam->load('players');

                $coachRegistration = $coachTeam->registrations
                    ->sortByDesc(fn (Registration $registration): int => ($registration->status === 'approved' ? 1000 : 0) + (int) $registration->id)
                    ->first();

                $coachTournament = $coachRegistration?->tournament;
            }

            $data['coachTeams'] = $coachTeams;
            $data['coachTeam'] = $coachTeam;
            $data['coachRegistration'] = $coachRegistration;
            $data['coachTournament'] = $coachTournament;
            $data['coachRecord'] = $coachTeam instanceof Team
                ? $this->dashboard->teamRecord($coachTeam, $coachTournament?->id)
                : ['wins' => 0, 'losses' => 0, 'pointsFor' => 0, 'pointsAgainst' => 0, 'diff' => 0];
            $data['coachNextMatch'] = $coachTeam instanceof Team
                ? $this->dashboard->nextTeamMatch($coachTeam, $coachTournament?->id)
                : null;
            $data['coachRosterTotal'] = $coachTeam instanceof Team ? $coachTeam->players->count() : 0;
            $data['coachRosterReady'] = $coachTeam instanceof Team
                ? $coachTeam->players->filter(fn ($player): bool => $player->consent_at !== null)->count()
                : 0;
            $data['openTournaments'] = Tournament::query()
                ->whereNull('archived_at')
                ->where('status', 'registration-open')
                ->with([
                    'divisions',
                    'registrations' => fn ($query) => $query
                        ->whereHas('team', fn ($teamQuery) => $teamQuery->where('coach_user_id', $user->id))
                        ->with('team'),
                ])
                ->orderBy('start_date')
                ->get();
        } else {
            $data['upcomingMatches'] = $tournament instanceof Tournament ? $this->dashboard->upcomingMatches($tournament, 4) : collect();
            $data['recentResults'] = $tournament instanceof Tournament ? $this->dashboard->recentResults($tournament, 4) : collect();
            $data['announcements'] = $tournament instanceof Tournament ? $this->dashboard->announcements($tournament, 10) : collect();
            $data['standings'] = $tournament instanceof Tournament
                ? $this->standings->compute($tournament)->take(5)
                : collect();
        }

        return view('pages.dashboard', $data);
    }
}
