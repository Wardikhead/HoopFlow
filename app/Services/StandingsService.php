<?php

namespace App\Services;

use App\Models\Division;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Support\Collection;

class StandingsService
{
    /**
     * @return Collection<int, array{team_id: int, team_name: string|null, played: int, wins: int, losses: int, points: int, points_for: int, points_against: int, point_diff: int}>
     */
    public function compute(Tournament|Division $target): Collection
    {
        if ($target instanceof Tournament) {
            $tournamentId = $target->id;
            $registrations = Registration::where('tournament_id', $tournamentId)
                ->where('status', 'approved')
                ->get();
            $matches = MatchModel::where('tournament_id', $tournamentId)->get();
        } else {
            $tournamentId = $target->tournament_id;
            $registrations = Registration::where('division_id', $target->id)
                ->where('status', 'approved')
                ->get();
            $divisionTeamIds = $registrations->pluck('team_id')->all();
            $matches = MatchModel::where('tournament_id', $tournamentId)->get();
            if (! empty($divisionTeamIds)) {
                $matches = $matches->filter(
                    fn (MatchModel $match): bool => in_array($match->home_team_id, $divisionTeamIds, true)
                        || in_array($match->away_team_id, $divisionTeamIds, true)
                );
            }
        }

        $teamIds = $registrations->pluck('team_id')
            ->merge($matches->pluck('home_team_id'))
            ->merge($matches->pluck('away_team_id'))
            ->filter()
            ->unique()
            ->values();

        $teams = Team::whereIn('id', $teamIds)->get()->keyBy('id');

        $rows = $teamIds->map(function ($teamId) use ($teams, $matches): array {
            $team = $teams->get($teamId);

            $teamMatches = $matches->filter(
                fn (MatchModel $match): bool => in_array($match->status, ['final', 'forfeit'], true)
                    && $match->away_team_id !== null
                    && ($match->home_team_id === $teamId || $match->away_team_id === $teamId)
            );

            $wins = 0;
            $losses = 0;
            $pointsFor = 0;
            $pointsAgainst = 0;

            foreach ($teamMatches as $match) {
                $isHome = $match->home_team_id === $teamId;
                $own = $isHome ? $match->home_score : $match->away_score;
                $other = $isHome ? $match->away_score : $match->home_score;

                $pointsFor += (int) ($own ?? 0);
                $pointsAgainst += (int) ($other ?? 0);

                if ($match->winner_team_id === $teamId) {
                    $wins++;
                } else {
                    $losses++;
                }
            }

            return [
                'team_id' => (int) $teamId,
                'team_name' => $team?->name,
                'played' => $wins + $losses,
                'wins' => $wins,
                'losses' => $losses,
                'points' => $wins,
                'points_for' => $pointsFor,
                'points_against' => $pointsAgainst,
                'point_diff' => $pointsFor - $pointsAgainst,
            ];
        });

        return $rows->sort(function (array $a, array $b): int {
            return [$b['wins'], $b['point_diff'], $b['points_for']]
                <=> [$a['wins'], $a['point_diff'], $a['points_for']];
        })->values();
    }
}
