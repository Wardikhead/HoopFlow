<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\MatchModel;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(protected StandingsService $standings) {}

    /**
     * @return array<string, mixed>
     */
    public function organizerKpis(Tournament $tournament): array
    {
        $registrations = Registration::where('tournament_id', $tournament->id)->get();
        $pendingCount = $registrations->where('status', 'submitted')->count();
        $approved = $registrations->where('status', 'approved')->count();
        $registered = $registrations->count();

        $payments = Payment::whereIn('registration_id', $registrations->pluck('id'))->get();
        $collected = (int) $payments->where('status', 'verified')->sum('amount');
        $expected = (int) $payments->sum('amount');
        $outstanding = $expected - $collected;
        $unpaidCount = $payments->where('status', '!=', 'verified')->count();

        $matches = MatchModel::where('tournament_id', $tournament->id)->get();
        $matchesPlayed = $matches->whereIn('status', ['final', 'forfeit'])->count();
        $matchesScheduled = $matches->where('status', 'scheduled')->count();
        $totalMatches = $matches->count();

        return [
            'tournaments' => Tournament::count(),
            'pending' => $pendingCount,
            'uncollected' => $outstanding,
            'pendingCount' => $unpaidCount,
            'collected' => $collected,
            'expected' => $expected,
            'approved' => $approved,
            'registered' => $registered,
            'matchesPlayed' => $matchesPlayed,
            'matchesScheduled' => $matchesScheduled,
            'totalMatches' => $totalMatches,
            'progress' => $totalMatches > 0 ? (int) round(($matchesPlayed / $totalMatches) * 100) : 0,
        ];
    }

    /**
     * @return Collection<int, Team>
     */
    public function coachTeams(User $user): Collection
    {
        return Team::where('coach_user_id', $user->id)->get();
    }

    /**
     * @return array{wins: int, losses: int}
     */
    public function teamRecord(Team $team): array
    {
        // fallback: compute from matches directly
        $matches = MatchModel::where(function ($q) use ($team) {
            $q->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id);
        })->whereIn('status', ['final', 'forfeit'])->get();

        $wins = $matches->where('winner_team_id', $team->id)->count();
        $losses = $matches->count() - $wins;

        return ['wins' => $wins, 'losses' => $losses];
    }

    /**
     * @return Collection<int, MatchModel>
     */
    public function upcomingMatches(Tournament $tournament, int $limit = 4): Collection
    {
        return MatchModel::where('tournament_id', $tournament->id)
            ->where('status', 'scheduled')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, MatchModel>
     */
    public function recentResults(Tournament $tournament, int $limit = 4): Collection
    {
        return MatchModel::where('tournament_id', $tournament->id)
            ->whereIn('status', ['final', 'forfeit'])
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Announcement>
     */
    public function announcements(Tournament $tournament, int $limit = 3): Collection
    {
        return Announcement::where('tournament_id', $tournament->id)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{collected: int, expected: int, outstanding: int, pendingCount: int}
     */
    public function paymentSummary(Tournament $tournament): array
    {
        $registrations = Registration::where('tournament_id', $tournament->id)->get();
        $payments = Payment::whereIn('registration_id', $registrations->pluck('id'))->get();
        $collected = (int) $payments->where('status', 'verified')->sum('amount');
        $expected = (int) $payments->sum('amount');

        return [
            'collected' => $collected,
            'expected' => $expected,
            'outstanding' => $expected - $collected,
            'pendingCount' => $payments->where('status', '!=', 'verified')->count(),
        ];
    }
}
