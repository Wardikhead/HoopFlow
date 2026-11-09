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
     * @return Collection<int, array{
     *     team_id: int,
     *     team_name: string|null,
     *     team: Team|null,
     *     played: int,
     *     wins: int,
     *     losses: int,
     *     points: int,
     *     points_for: int,
     *     points_against: int,
     *     point_diff: int,
     *     seed: int|null,
     *     rank: int,
     *     qualified: bool
     * }>
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
        $seeds = $registrations->pluck('seed', 'team_id');

        $rows = $teamIds->map(function ($teamId) use ($teams, $matches, $seeds): array {
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

            $seed = $seeds->get($teamId);

            return [
                'team_id' => (int) $teamId,
                'team_name' => $team instanceof Team ? $team->name : null,
                'team' => $team instanceof Team ? $team : null,
                'played' => $wins + $losses,
                'wins' => $wins,
                'losses' => $losses,
                'points' => $wins,
                'points_for' => $pointsFor,
                'points_against' => $pointsAgainst,
                'point_diff' => $pointsFor - $pointsAgainst,
                'seed' => $seed !== null ? (int) $seed : null,
                'rank' => 0,
                'qualified' => false,
            ];
        });

        $cut = $this->playoffCut($target);

        return $rows
            ->sort(function (array $a, array $b): int {
                $seedA = $a['seed'] ?? PHP_INT_MAX;
                $seedB = $b['seed'] ?? PHP_INT_MAX;

                return [$b['wins'], $b['point_diff'], $b['points_for'], $seedA]
                    <=> [$a['wins'], $a['point_diff'], $a['points_for'], $seedB];
            })
            ->values()
            ->map(function (array $row, int $index) use ($cut): array {
                $row['rank'] = $index + 1;
                $row['qualified'] = $index < $cut;

                return $row;
            })
            ->values();
    }

    public function playoffCut(Tournament|Division $target): int
    {
        if ($target instanceof Tournament) {
            $rules = $target->rules_json;
            $slots = is_array($rules) ? (int) ($rules['slots'] ?? 0) : 0;

            if ($slots <= 0) {
                $slots = (int) $target->divisions()->max('max_teams');
            }

            if ($slots <= 0) {
                $slots = (int) $target->registrations()->where('status', 'approved')->count();
            }
        } else {
            $slots = (int) ($target->max_teams ?? 0);

            if ($slots <= 0) {
                $slots = (int) $target->registrations()->where('status', 'approved')->count();
            }
        }

        return $slots > 0 ? (int) ceil($slots / 2) : 0;
    }
}
