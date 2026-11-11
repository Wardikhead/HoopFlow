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
        $format = $this->formatOf($target);

        if ($target instanceof Tournament) {
            $tournamentId = $target->id;
            $registrations = Registration::where('tournament_id', $tournamentId)
                ->where('status', 'approved')
                ->get();
            $matches = $this->standingsMatches($tournamentId, $format);
        } else {
            $tournamentId = $target->tournament_id;
            $registrations = Registration::where('division_id', $target->id)
                ->where('status', 'approved')
                ->get();
            $divisionTeamIds = $registrations->pluck('team_id')->all();
            $matches = $this->standingsMatches($tournamentId, $format);
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

                $winnerId = (int) ($match->game_winner_team_id ?? $match->winner_team_id);

                if ($winnerId === (int) $teamId) {
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

        $sorted = $rows
            ->sortByDesc('wins')
            ->values()
            ->groupBy('wins')
            ->flatMap(function (Collection $group) use ($matches): Collection {
                $headToHead = [];

                foreach ($group as $row) {
                    $headToHead[$row['team_id']] = 0;
                }

                foreach ($matches as $match) {
                    if (! in_array($match->status, ['final', 'forfeit'], true)) {
                        continue;
                    }

                    $homeId = $match->home_team_id !== null ? (int) $match->home_team_id : null;
                    $awayId = $match->away_team_id !== null ? (int) $match->away_team_id : null;

                    if ($homeId === null || $awayId === null
                        || ! array_key_exists($homeId, $headToHead)
                        || ! array_key_exists($awayId, $headToHead)) {
                        continue;
                    }

                    $winnerId = (int) ($match->game_winner_team_id ?? $match->winner_team_id);

                    if ($winnerId === $homeId) {
                        $headToHead[$homeId]++;
                    } elseif ($winnerId === $awayId) {
                        $headToHead[$awayId]++;
                    }
                }

                return $group->sort(function (array $a, array $b) use ($headToHead): int {
                    return [
                        $headToHead[$b['team_id']],
                        $b['point_diff'],
                        $b['points_for'],
                        $a['seed'] ?? PHP_INT_MAX,
                    ] <=> [
                        $headToHead[$a['team_id']],
                        $a['point_diff'],
                        $a['points_for'],
                        $b['seed'] ?? PHP_INT_MAX,
                    ];
                })->values();
            })
            ->values();

        return $sorted
            ->map(function (array $row, int $index) use ($cut): array {
                $row['rank'] = $index + 1;
                $row['qualified'] = $index < $cut;

                return $row;
            })
            ->values();
    }

    protected function formatOf(Tournament|Division $target): string
    {
        $tournament = $target instanceof Tournament ? $target : $target->tournament;
        $rules = $tournament?->rules_json;
        $format = is_array($rules) && isset($rules['format']) && is_string($rules['format'])
            ? $rules['format']
            : 'single-elimination';

        return in_array($format, ['single-elimination', 'double-elimination', 'round-robin'], true)
            ? $format
            : 'single-elimination';
    }

    /**
     * @return Collection<int, MatchModel>
     */
    protected function standingsMatches(int $tournamentId, string $format): Collection
    {
        $query = MatchModel::where('tournament_id', $tournamentId);

        if ($format === 'round-robin') {
            $query->where('stage', 'round_robin');
        }

        return $query->get();
    }

    public function playoffCut(Tournament|Division $target): int
    {
        $tournament = $target instanceof Tournament ? $target : $target->tournament;
        $rules = $tournament?->rules_json;
        $playoffTeams = is_array($rules) && isset($rules['playoff_teams']) && is_numeric($rules['playoff_teams'])
            ? (int) $rules['playoff_teams']
            : 0;

        if (in_array($playoffTeams, [2, 4, 8, 16], true)) {
            $approved = $target->registrations()->where('status', 'approved')->count();

            return min($playoffTeams, (int) $approved);
        }

        if ($target instanceof Tournament) {
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
