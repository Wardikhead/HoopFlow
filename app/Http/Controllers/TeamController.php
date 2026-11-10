<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeamController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function show(Request $request, Team $team): View|Response
    {
        $team->load([
            'players' => fn ($query) => $query->orderBy('jersey_number')->orderBy('id'),
            'coachUser',
            'registrations.tournament.divisions',
            'registrations.division',
            'registrations.payment',
        ]);

        $user = $request->user();

        $role = 'fan';

        if ($user !== null && $user->hasRole('organizer')) {
            $role = 'organizer';
        } elseif ($user !== null && $user->hasRole('coach')) {
            $role = 'coach';
        }

        $isOwner = $user !== null && (int) $team->coach_user_id === (int) $user->id;
        $canSeePrivate = $role === 'organizer' || $isOwner;

        $registrations = $team->registrations->sortByDesc('id')->values();
        $requestedTournament = $request->query('tournament');
        $registration = null;

        if (is_numeric($requestedTournament)) {
            $registration = $registrations->firstWhere('tournament_id', (int) $requestedTournament);
        }

        if ($role === 'fan') {
            $approved = $registrations->firstWhere('status', 'approved');

            if (! $approved instanceof Registration) {
                return response()->view('pages.teams.unavailable', ['team' => $team], 403);
            }

            $registration = $approved;
        }

        $registration ??= $registrations->firstWhere('status', 'approved')
            ?? $registrations->first();

        if (! $registration instanceof Registration) {
            $registration = null;
        }

        $tournament = $registration?->tournament;
        $matches = $this->dashboard->teamMatches($team, $tournament?->id);

        $played = $matches
            ->whereIn('status', ['final', 'forfeit'])
            ->filter(fn (MatchModel $match): bool => ! $match->isByeGame())
            ->sortByDesc(fn (MatchModel $match): string => (string) ($match->completed_at ?? ''))
            ->values();

        $bracketMatch = $matches
            ->sortBy(fn (MatchModel $match): array => [(int) $match->round_number, (int) $match->match_number])
            ->first();

        return view('pages.teams.show', [
            'team' => $team,
            'registration' => $registration,
            'tournament' => $tournament,
            'role' => $role,
            'isOwner' => $isOwner,
            'canSeePrivate' => $canSeePrivate,
            'record' => $this->dashboard->teamRecord($team, $tournament?->id),
            'played' => $played,
            'nextMatch' => $this->dashboard->nextTeamMatch($team, $tournament?->id),
            'bracketMatch' => $bracketMatch,
        ]);
    }
}
