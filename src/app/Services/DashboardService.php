<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(protected StandingsService $standings) {}

    /**
     * Team and payment figures cover every tournament; match figures follow the
     * active tournament shown on the dashboard.
     *
     * @return array<string, mixed>
     */
    public function organizerKpis(Tournament $tournament): array
    {
        $registrations = Registration::query()
            ->with(['division', 'payment', 'tournament.divisions'])
            ->get();
        $pendingCount = $registrations->where('status', 'submitted')->count();
        $approved = $registrations->where('status', 'approved')->count();
        $registered = $registrations->count();

        $billable = $registrations->where('status', '!=', 'rejected');
        $paid = $billable->filter(
            fn (Registration $registration): bool => $registration->payment?->status === 'verified'
        );
        $collected = (int) $paid->sum(
            fn (Registration $registration): float => (float) $registration->payment->amount
        );
        $expected = (int) $billable->sum(function (Registration $registration): float {
            $fee = $registration->division?->entry_fee;

            return $fee !== null
                ? (float) $fee
                : (float) ($registration->tournament?->entryFee() ?? 0);
        });
        $outstanding = max(0, $expected - $collected);
        $unpaidCount = $billable->filter(
            fn (Registration $registration): bool => $registration->payment?->status !== 'verified'
        )->count();

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
        return Team::query()
            ->where('coach_user_id', $user->id)
            ->with(['registrations.tournament.divisions', 'registrations.division', 'registrations.payment'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{wins: int, losses: int, pointsFor: int, pointsAgainst: int, diff: int}
     */
    public function teamRecord(Team $team, ?int $tournamentId = null): array
    {
        $query = MatchModel::query()
            ->where(function ($builder) use ($team): void {
                $builder
                    ->where('home_team_id', $team->id)
                    ->orWhere('away_team_id', $team->id);
            })
            ->whereIn('status', ['final', 'forfeit']);

        if ($tournamentId !== null) {
            $query->where('tournament_id', $tournamentId);
        }

        $matches = $query->get()->filter(
            fn (MatchModel $match): bool => $match->home_team_id !== null
                && $match->away_team_id !== null
                && ! $match->isByeGame()
        );

        $wins = 0;
        $losses = 0;
        $pointsFor = 0;
        $pointsAgainst = 0;

        foreach ($matches as $match) {
            $isHome = (int) $match->home_team_id === (int) $team->id;

            $pointsFor += (int) ($isHome ? $match->home_score : $match->away_score);
            $pointsAgainst += (int) ($isHome ? $match->away_score : $match->home_score);

            if ((int) $match->winner_team_id === (int) $team->id) {
                $wins++;
            } else {
                $losses++;
            }
        }

        return [
            'wins' => $wins,
            'losses' => $losses,
            'pointsFor' => $pointsFor,
            'pointsAgainst' => $pointsAgainst,
            'diff' => $pointsFor - $pointsAgainst,
        ];
    }

    /**
     * @return Collection<int, MatchModel>
     */
    public function upcomingMatches(Tournament $tournament, int $limit = 4): Collection
    {
        $matches = $this->matchQuery()
            ->where('tournament_id', $tournament->id)
            ->where('status', 'scheduled')
            ->get();

        return $this->sortBySlot($matches)->take($limit)->values();
    }

    /**
     * @return Collection<int, MatchModel>
     */
    public function recentResults(Tournament $tournament, int $limit = 4): Collection
    {
        return $this->matchQuery()
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', ['final', 'forfeit'])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Announcement>
     */
    public function announcements(Tournament $tournament, int $limit = 3): Collection
    {
        return Announcement::query()
            ->with(['createdBy', 'tournament'])
            ->where('tournament_id', $tournament->id)
            ->where('status', 'published')
            ->orderByDesc('pinned')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{collected: int, expected: int, outstanding: int, pendingCount: int}
     */
    public function paymentSummary(Tournament $tournament): array
    {
        $registrations = Registration::query()
            ->where('tournament_id', $tournament->id)
            ->with(['division', 'payment'])
            ->get();

        $tournament->loadMissing('divisions');
        $fallbackFee = $tournament->entryFee();

        $billable = $registrations->where('status', '!=', 'rejected');
        $paid = $billable->filter(
            fn (Registration $registration): bool => $registration->payment?->status === 'verified'
        );
        $collected = (int) $paid->sum(
            fn (Registration $registration): float => (float) $registration->payment->amount
        );
        $expected = (int) $billable->sum(
            fn (Registration $registration): float => (float) ($registration->division->entry_fee ?? $fallbackFee)
        );

        return [
            'collected' => $collected,
            'expected' => $expected,
            'outstanding' => max(0, $expected - $collected),
            'pendingCount' => $billable->filter(
                fn (Registration $registration): bool => $registration->payment?->status === 'pending'
            )->count(),
        ];
    }

    public function nextMatch(Tournament $tournament): ?MatchModel
    {
        return $this->sortBySlot(
            $this->matchQuery()
                ->where('tournament_id', $tournament->id)
                ->where('status', 'scheduled')
                ->get()
        )->first();
    }

    public function nextTeamMatch(Team $team, ?int $tournamentId = null): ?MatchModel
    {
        $query = $this->matchQuery()
            ->where(function ($builder) use ($team): void {
                $builder
                    ->where('home_team_id', $team->id)
                    ->orWhere('away_team_id', $team->id);
            })
            ->where('status', 'scheduled');

        if ($tournamentId !== null) {
            $query->where('tournament_id', $tournamentId);
        }

        return $this->sortBySlot($query->get())->first();
    }

    /**
     * @return Collection<int, MatchModel>
     */
    public function teamMatches(Team $team, ?int $tournamentId = null): Collection
    {
        $query = $this->matchQuery()
            ->where(function ($builder) use ($team): void {
                $builder
                    ->where('home_team_id', $team->id)
                    ->orWhere('away_team_id', $team->id);
            });

        if ($tournamentId !== null) {
            $query->where('tournament_id', $tournamentId);
        }

        return $query->get();
    }

    /**
     * @return Builder<MatchModel>
     */
    protected function matchQuery()
    {
        return MatchModel::query()->with([
            'homeTeam',
            'awayTeam',
            'tournament',
            'matchSlots.court',
        ]);
    }

    /**
     * @param  Collection<int, MatchModel>  $matches
     * @return Collection<int, MatchModel>
     */
    protected function sortBySlot(Collection $matches): Collection
    {
        return $matches
            ->sort(function (MatchModel $a, MatchModel $b): int {
                $aStart = $a->matchSlots->first()?->start_at?->getTimestamp();
                $bStart = $b->matchSlots->first()?->start_at?->getTimestamp();

                return [$aStart ?? PHP_INT_MAX, (int) $a->id]
                    <=> [$bStart ?? PHP_INT_MAX, (int) $b->id];
            })
            ->values();
    }
}
