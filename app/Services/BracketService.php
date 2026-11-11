<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Registration;
use App\Models\Tournament;
use App\Services\Bracket\BracketPlan;
use App\Services\Bracket\DoubleEliminationBuilder;
use App\Services\Bracket\RoundRobinBuilder;
use App\Services\Bracket\SingleEliminationBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BracketService
{
    /**
     * @return array{matches: int, games: int, byes: int}
     */
    public function generate(Tournament $tournament, string $format = 'single-elimination'): array
    {
        if (! in_array($format, ['single-elimination', 'round-robin', 'double-elimination'], true)) {
            throw new \InvalidArgumentException('Invalid bracket format.');
        }

        $registrations = Registration::where('tournament_id', $tournament->id)
            ->where('status', 'approved')
            ->get();

        if ($registrations->count() < 2) {
            throw new \InvalidArgumentException('At least two approved teams are required.');
        }

        $rules = is_array($tournament->rules_json) ? $tournament->rules_json : [];
        $slots = isset($rules['slots']) && is_numeric($rules['slots']) ? (int) $rules['slots'] : null;

        if ($slots !== null && $registrations->count() > $slots) {
            throw new \InvalidArgumentException('Approved teams exceed the configured slots.');
        }

        $division = $tournament->divisions()->first();

        if (! $division instanceof Division) {
            $division = Division::create([
                'tournament_id' => $tournament->id,
                'name' => 'Default Division',
            ]);
        }

        return DB::transaction(function () use ($tournament, $format, $registrations, $division, $rules, $slots): array {
            $this->deleteBracketData($tournament, $format);

            $ordered = $registrations
                ->sortBy(fn (Registration $registration): int => $registration->seed ?? 999)
                ->values()
                ->pluck('team_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $bracket = Bracket::create([
                'division_id' => $division->id,
                'name' => $tournament->name.' Bracket',
                'format' => match ($format) {
                    'single-elimination' => 'single_elim',
                    'double-elimination' => 'double_elim',
                    default => 'round_robin',
                },
                'phase' => $format === 'round-robin' ? 'group' : 'main',
                'seeding_method' => 'manual',
                'status' => 'generated',
                'generated_at' => now(),
            ]);

            $bestOf = [
                0 => $this->bestOfRule($rules, 'best_of_final'),
                1 => $this->bestOfRule($rules, 'best_of_semifinal'),
                2 => $this->bestOfRule($rules, 'best_of_quarterfinal'),
            ];

            $plan = match ($format) {
                'single-elimination' => (new SingleEliminationBuilder)->build(
                    $this->bracketSize($slots, count($ordered), [2, 4, 8, 16, 32]),
                    $ordered,
                    $bestOf,
                ),
                'double-elimination' => (new DoubleEliminationBuilder)->build(
                    $this->bracketSize($slots, count($ordered), [4, 8, 16, 32]),
                    $ordered,
                    $bestOf,
                ),
                default => (new RoundRobinBuilder)->build(
                    $ordered,
                    $this->roundRobinLegs($rules),
                ),
            };

            $result = $this->persistPlan($tournament, $bracket, $plan);

            app(AuditLogger::class)->log(
                'bracket.generate',
                'tournament',
                $tournament->id,
                null,
                [
                    'teams' => $registrations->count(),
                    'byes' => $result['byes'],
                    'matches' => $result['matches'],
                    'games' => $result['games'],
                ],
                $tournament->organization_id,
            );

            return $result;
        });
    }

    protected function nextMatchId(): int
    {
        if (DB::getDriverName() === 'pgsql') {
            return (int) DB::selectOne("select nextval('matches_id_seq') as v")->v;
        }

        return (int) (MatchModel::max('id') ?? 0) + 1;
    }

    /**
     * @param  list<int>  $allowed
     */
    protected function bracketSize(?int $slots, int $teamCount, array $allowed): int
    {
        if ($slots !== null) {
            if (! in_array($slots, $allowed, true)) {
                throw new \InvalidArgumentException('Bracket size must be '.implode(', ', $allowed).'.');
            }

            return $slots;
        }

        $size = 2;

        while ($size < $teamCount) {
            $size *= 2;
        }

        if (! in_array($size, $allowed, true)) {
            throw new \InvalidArgumentException('Bracket size must be '.implode(', ', $allowed).'.');
        }

        return $size;
    }

    /**
     * Infer the bracket size from the rows that are already persisted.
     *
     * Single elimination numbers rounds by bracket size (16, 8, 4, 2), while
     * double elimination numbers winners rounds 1..k. Counting the first winners
     * round works for both, and the max round number is the legacy fallback.
     */
    protected function existingBracketSize(Bracket $bracket): int
    {
        $firstRoundCount = MatchModel::where('bracket_id', $bracket->id)
            ->where('stage', 'winners')
            ->whereNull('series_id')
            ->where('round_number', 1)
            ->count();

        if ($firstRoundCount > 0) {
            return $firstRoundCount * 2;
        }

        return (int) MatchModel::where('bracket_id', $bracket->id)
            ->where('stage', 'winners')
            ->whereNull('series_id')
            ->max('round_number');
    }

    /**
     * Keep the existing bracket size when editing slots so a 16-slot bracket does
     * not shrink to 8 slots just because only five teams are approved.
     *
     * Call this before deleting the old matches; it reads the persisted rows.
     *
     * @param  list<int>  $allowed
     */
    protected function updateBracketSize(Bracket $bracket, int $teamCount, array $allowed): int
    {
        $existing = $this->existingBracketSize($bracket);

        $size = in_array($existing, $allowed, true)
            ? $existing
            : $this->bracketSize(null, $teamCount, $allowed);

        if ($teamCount > $size) {
            throw new \InvalidArgumentException('Approved teams exceed the configured slots.');
        }

        return $size;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    protected function bestOfRule(array $rules, string $key): int
    {
        $value = $rules[$key] ?? 1;

        return is_numeric($value) && in_array((int) $value, [1, 3, 5, 7, 9], true)
            ? (int) $value
            : 1;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    protected function roundRobinLegs(array $rules): int
    {
        $value = $rules['round_robin_legs'] ?? 1;

        return is_numeric($value) && (int) $value >= 1 && (int) $value <= 10
            ? (int) $value
            : 1;
    }

    /**
     * @return array{matches: int, games: int, byes: int}
     */
    protected function persistPlan(Tournament $tournament, Bracket $bracket, BracketPlan $plan): array
    {
        $anchors = [];
        $games = 0;

        $makeMatch = function (array $attributes): MatchModel {
            $match = new MatchModel($attributes);
            $match->id = $this->nextMatchId();
            $match->save();

            return $match;
        };

        foreach ($plan->matchups as $matchup) {
            $anchor = $makeMatch([
                'bracket_id' => $bracket->id,
                'tournament_id' => $tournament->id,
                'stage' => $matchup->stage,
                'round_number' => $matchup->roundNumber,
                'match_number' => $matchup->matchNumber,
                'series_id' => null,
                'game_number' => 1,
                'best_of' => $matchup->bestOf,
                'series_home_wins' => 0,
                'series_away_wins' => 0,
                'series_winner_team_id' => null,
                'game_winner_team_id' => null,
                'home_team_id' => null,
                'away_team_id' => null,
                'home_score' => null,
                'away_score' => null,
                'winner_team_id' => null,
                'status' => $matchup->stage === 'reset' ? 'conditional' : 'scheduled',
                'completed_at' => null,
                'forfeit_reason' => null,
            ]);

            $anchors[$matchup->key()] = $anchor;
            $games++;

            for ($gameNumber = 2; $gameNumber <= $matchup->bestOf; $gameNumber++) {
                $makeMatch([
                    'bracket_id' => $bracket->id,
                    'tournament_id' => $tournament->id,
                    'stage' => $matchup->stage,
                    'round_number' => $matchup->roundNumber,
                    'match_number' => $matchup->matchNumber,
                    'series_id' => $anchor->id,
                    'game_number' => $gameNumber,
                    'best_of' => $matchup->bestOf,
                    'series_home_wins' => 0,
                    'series_away_wins' => 0,
                    'series_winner_team_id' => null,
                    'game_winner_team_id' => null,
                    'home_team_id' => null,
                    'away_team_id' => null,
                    'home_score' => null,
                    'away_score' => null,
                    'winner_team_id' => null,
                    'status' => 'conditional',
                    'completed_at' => null,
                    'forfeit_reason' => null,
                ]);
                $games++;
            }
        }

        foreach ($plan->matchups as $matchup) {
            $anchor = $anchors[$matchup->key()];

            foreach (['home' => $matchup->home, 'away' => $matchup->away] as $slot => $source) {
                $sourceMatchId = null;
                $outcome = null;

                if ($source->type !== 'seed') {
                    $sourceMatchId = $anchors[(string) $source->key()]->id;
                    $outcome = $source->type;
                }

                BracketSlot::create([
                    'bracket_id' => $bracket->id,
                    'stage' => $matchup->stage,
                    'round_number' => $matchup->roundNumber,
                    'match_number' => $matchup->matchNumber,
                    'slot' => $slot,
                    'source_type' => $source->type,
                    'source_match_id' => $sourceMatchId,
                    'source_outcome' => $outcome,
                    'team_id' => $source->type === 'seed' ? $source->teamId : null,
                ]);
            }

            if ($matchup->home->type === 'seed' && $matchup->home->teamId !== null) {
                $anchor->home_team_id = $matchup->home->teamId;
            }

            if ($matchup->away->type === 'seed' && $matchup->away->teamId !== null) {
                $anchor->away_team_id = $matchup->away->teamId;
            }

            if ($anchor->isDirty()) {
                $anchor->save();
            }

            $this->syncSeriesTeams($anchor);
        }

        $this->resolveStructuralByes($bracket);

        $anchorCount = MatchModel::where('bracket_id', $bracket->id)->whereNull('series_id')->count();
        $byeCount = MatchModel::where('bracket_id', $bracket->id)
            ->whereNull('series_id')
            ->where('status', 'final')
            ->whereNull('home_score')
            ->whereNull('away_score')
            ->count();

        return [
            'matches' => $anchorCount,
            'games' => MatchModel::where('bracket_id', $bracket->id)->count(),
            'byes' => $byeCount,
        ];
    }

    public function syncSeriesTeams(MatchModel $anchor): void
    {
        if ($anchor->best_of <= 1 || $anchor->series_id !== null) {
            return;
        }

        MatchModel::where('series_id', $anchor->id)
            ->whereIn('status', ['conditional', 'scheduled'])
            ->update([
                'home_team_id' => $anchor->home_team_id,
                'away_team_id' => $anchor->away_team_id,
            ]);
    }

    public function resolveStructuralByes(Bracket $bracket): void
    {
        $anchorCount = MatchModel::where('bracket_id', $bracket->id)->whereNull('series_id')->count();
        $limit = max(20, $anchorCount * 3);

        for ($iteration = 0; $iteration < $limit; $iteration++) {
            $changed = false;

            $matches = MatchModel::where('bracket_id', $bracket->id)
                ->whereNull('series_id')
                ->whereNotIn('status', ['final', 'forfeit', 'in_progress', 'cancelled', 'conditional'])
                ->get();

            foreach ($matches as $match) {
                $slots = BracketSlot::where('bracket_id', $match->bracket_id)
                    ->where('stage', $match->stage)
                    ->where('round_number', $match->round_number)
                    ->where('match_number', $match->match_number)
                    ->with('sourceMatch')
                    ->get()
                    ->keyBy('slot');
                $home = $slots->get('home');
                $away = $slots->get('away');

                if (! $home instanceof BracketSlot || ! $away instanceof BracketSlot) {
                    continue;
                }

                $homeState = $this->slotState($home);
                $awayState = $this->slotState($away);

                if ($homeState === 'impossible' && $awayState === 'impossible') {
                    $this->cancelMatch($match);
                    $changed = true;
                    continue;
                }

                if ($match->home_team_id !== null && $awayState === 'impossible') {
                    $this->applyBye($match, (int) $match->home_team_id);
                    $changed = true;
                    continue;
                }

                if ($match->away_team_id !== null && $homeState === 'impossible') {
                    $this->applyBye($match, (int) $match->away_team_id);
                    $changed = true;
                }
            }

            if (! $changed) {
                break;
            }
        }
    }

    protected function slotState(BracketSlot $slot): string
    {
        if ($slot->team_id !== null) {
            return 'known';
        }

        if ($slot->source_type === 'seed') {
            return 'impossible';
        }

        $source = $slot->sourceMatch;

        if (! $source instanceof MatchModel || $source->status === 'cancelled') {
            return 'impossible';
        }

        if (! in_array($source->status, ['final', 'forfeit'], true)) {
            return 'pending';
        }

        if ($slot->source_outcome === 'loser') {
            return $source->home_team_id !== null && $source->away_team_id !== null
                ? 'known'
                : 'impossible';
        }

        return $source->winner_team_id !== null ? 'known' : 'pending';
    }

    protected function applyBye(MatchModel $match, int $winnerId): void
    {
        if ($match->best_of > 1) {
            MatchModel::where('series_id', $match->id)
                ->whereIn('status', ['conditional', 'scheduled'])
                ->update(['status' => 'cancelled']);
            $match->best_of = 1;
        }

        $match->home_score = null;
        $match->away_score = null;
        $match->winner_team_id = $winnerId;
        $match->series_winner_team_id = $winnerId;
        $match->game_winner_team_id = $winnerId;
        $match->series_home_wins = (int) $match->home_team_id === $winnerId ? 1 : 0;
        $match->series_away_wins = (int) $match->away_team_id === $winnerId ? 1 : 0;
        $match->status = 'final';
        $match->completed_at = null;
        $match->save();

        $this->propagateMatchResult($match, $winnerId);
    }

    protected function cancelMatch(MatchModel $match): void
    {
        if ($match->best_of > 1) {
            MatchModel::where('series_id', $match->id)
                ->whereIn('status', ['conditional', 'scheduled'])
                ->update(['status' => 'cancelled']);
            $match->best_of = 1;
        }

        $match->home_team_id = null;
        $match->away_team_id = null;
        $match->winner_team_id = null;
        $match->series_winner_team_id = null;
        $match->game_winner_team_id = null;
        $match->status = 'cancelled';
        $match->save();
    }

    protected function deleteBracketData(Tournament $tournament, string $format): void
    {
        $divisionIds = $tournament->divisions()->pluck('id');

        if ($format === 'round-robin') {
            $playoffBrackets = Bracket::whereIn('division_id', $divisionIds)
                ->where('phase', 'playoffs')
                ->get();

            foreach ($playoffBrackets as $playoffBracket) {
                $hasResults = MatchModel::where('bracket_id', $playoffBracket->id)
                    ->whereIn('status', ['final', 'forfeit', 'in_progress'])
                    ->exists();

                if ($hasResults) {
                    throw new \InvalidArgumentException('Playoffs already have results; the group stage cannot be regenerated.');
                }
            }

            $bracketIds = Bracket::whereIn('division_id', $divisionIds)->pluck('id');
            $this->deleteMatchesAndSlots($bracketIds);
            Bracket::whereIn('id', $bracketIds)->delete();

            return;
        }

        $bracketIds = Bracket::whereIn('division_id', $divisionIds)->pluck('id');
        $this->deleteMatchesAndSlots($bracketIds);
        Bracket::whereIn('id', $bracketIds)->delete();
    }

    /**
     * @param  Collection<int, int>  $bracketIds
     */
    protected function deleteMatchesAndSlots(Collection $bracketIds): void
    {
        $matchIds = MatchModel::whereIn('bracket_id', $bracketIds)->pluck('id');

        MatchSlot::whereIn('match_id', $matchIds)->delete();
        MatchModel::whereIn('bracket_id', $bracketIds)->delete();
        BracketSlot::whereIn('bracket_id', $bracketIds)->delete();
    }

    /**
     * @param  list<int|null>  $orderedTeamIds
     * @return array{matches: int, games: int, byes: int}
     */
    public function updateSlots(Bracket $bracket, array $orderedTeamIds): array
    {
        $tournament = $bracket->division?->tournament;

        if (! $tournament instanceof Tournament) {
            throw new \InvalidArgumentException('Bracket is not attached to a tournament.');
        }

        $hasResults = MatchModel::where('bracket_id', $bracket->id)
            ->whereIn('status', ['final', 'forfeit', 'in_progress'])
            ->exists();

        if ($hasResults) {
            throw new \InvalidArgumentException('Cannot reassign slots after results are recorded.');
        }

        return DB::transaction(function () use ($bracket, $tournament, $orderedTeamIds): array {
            $rules = is_array($tournament->rules_json) ? $tournament->rules_json : [];
            $bestOf = [
                0 => $this->bestOfRule($rules, 'best_of_final'),
                1 => $this->bestOfRule($rules, 'best_of_semifinal'),
                2 => $this->bestOfRule($rules, 'best_of_quarterfinal'),
            ];
            $teamCount = count(array_filter($orderedTeamIds, fn ($id): bool => $id !== null));

            // Resolve the size before deleting rows: updateBracketSize reads the
            // persisted winners rounds to keep the configured bracket width.
            $size = match ($bracket->format) {
                'single_elim' => $this->updateBracketSize($bracket, $teamCount, [2, 4, 8, 16, 32]),
                'double_elim' => $this->updateBracketSize($bracket, $teamCount, [4, 8, 16, 32]),
                default => null,
            };

            $this->deleteMatchesAndSlots(collect([$bracket->id]));

            $plan = match ($bracket->format) {
                'single_elim' => (new SingleEliminationBuilder)->build(
                    (int) $size,
                    $orderedTeamIds,
                    $bestOf,
                ),
                'double_elim' => (new DoubleEliminationBuilder)->build(
                    (int) $size,
                    $orderedTeamIds,
                    $bestOf,
                ),
                default => (new RoundRobinBuilder)->build(
                    array_values(array_filter($orderedTeamIds, fn ($id): bool => $id !== null)),
                    $this->roundRobinLegs($rules),
                ),
            };

            return $this->persistPlan($tournament, $bracket, $plan);
        });
    }

    public function correctScore(MatchModel $match, int $score1, int $score2, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Correction reason is required.');
        }
        if ($score1 < 0 || $score2 < 0) {
            throw new \InvalidArgumentException('Scores must be zero or positive whole numbers.');
        }
        if ($score1 === $score2) {
            throw new \InvalidArgumentException('Elimination games cannot end in a tie.');
        }

        DB::transaction(function () use ($match, $score1, $score2, $reason): void {
            $match->refresh();

            $previousScore1 = $match->home_score;
            $previousScore2 = $match->away_score;
            $previousWinnerId = $match->winner_team_id;

            $team1Id = $match->home_team_id;
            $team2Id = $match->away_team_id;

            if ($team1Id === null && $team2Id === null) {
                throw new \InvalidArgumentException('Match has no teams.');
            }

            if ($team1Id === null) {
                $winnerId = $team2Id;
            } elseif ($team2Id === null) {
                $winnerId = $team1Id;
            } else {
                $winnerId = $score1 > $score2 ? $team1Id : $team2Id;
            }

            $match->home_score = $score1;
            $match->away_score = $score2;
            $match->winner_team_id = $winnerId;
            $match->status = 'final';
            $match->forfeit_reason = null;
            if ($match->completed_at === null) {
                $match->completed_at = now();
            }
            $match->save();

            if ($previousWinnerId !== $winnerId) {
                $this->propagateMatchResult($match, $winnerId);
            }

            $this->writeAudit($match, 'score.correct', [
                'home_score' => $previousScore1,
                'away_score' => $previousScore2,
                'winner_team_id' => $previousWinnerId,
            ], [
                'home_score' => $score1,
                'away_score' => $score2,
                'winner_team_id' => $winnerId,
                'reason' => $reason,
            ]);
        });
    }

    public function recordScore(MatchModel $match, int $score1, int $score2): void
    {
        if ($score1 < 0 || $score2 < 0) {
            throw new \InvalidArgumentException('Scores must be zero or positive whole numbers.');
        }
        if ($score1 === $score2) {
            throw new \InvalidArgumentException('Elimination games cannot end in a tie.');
        }

        DB::transaction(function () use ($match, $score1, $score2): void {
            $match->refresh();

            if (in_array($match->status, ['final', 'forfeit'], true)) {
                throw new \InvalidArgumentException('Use correction to change a recorded result.');
            }

            if ($match->home_team_id === null || $match->away_team_id === null) {
                throw new \InvalidArgumentException('Both teams must be known before entering a score.');
            }

            $homeTeamId = (int) $match->home_team_id;
            $awayTeamId = (int) $match->away_team_id;
            $winnerId = $score1 > $score2 ? $homeTeamId : $awayTeamId;

            $previousHomeScore = $match->home_score;
            $previousAwayScore = $match->away_score;
            $previousWinnerId = $match->winner_team_id;

            $match->home_score = $score1;
            $match->away_score = $score2;
            $match->winner_team_id = $winnerId;
            $match->status = 'final';
            $match->completed_at = now();
            $match->forfeit_reason = null;
            $match->save();

            $this->propagateMatchResult($match, $winnerId);

            $this->writeAudit($match, 'score.save', [
                'home_score' => $previousHomeScore,
                'away_score' => $previousAwayScore,
                'winner_team_id' => $previousWinnerId,
            ], [
                'home_score' => $score1,
                'away_score' => $score2,
                'winner_team_id' => $winnerId,
            ]);
        });
    }

    public function recordForfeit(MatchModel $match, int $forfeitSide): void
    {
        if (! in_array($forfeitSide, [1, 2], true)) {
            throw new \InvalidArgumentException('Choose which team forfeited.');
        }

        DB::transaction(function () use ($match, $forfeitSide): void {
            $match->refresh();

            if (in_array($match->status, ['final', 'forfeit'], true)) {
                throw new \InvalidArgumentException('Only a scheduled game can be recorded as a forfeit.');
            }

            if ($match->home_team_id === null || $match->away_team_id === null) {
                throw new \InvalidArgumentException('Both teams must be known before recording a forfeit.');
            }

            $homeTeamId = (int) $match->home_team_id;
            $awayTeamId = (int) $match->away_team_id;
            $winnerId = $forfeitSide === 1 ? $awayTeamId : $homeTeamId;
            $score1 = $forfeitSide === 1 ? 0 : 20;
            $score2 = $forfeitSide === 2 ? 0 : 20;

            $previousHomeScore = $match->home_score;
            $previousAwayScore = $match->away_score;
            $previousWinnerId = $match->winner_team_id;

            $match->home_score = $score1;
            $match->away_score = $score2;
            $match->winner_team_id = $winnerId;
            $match->status = 'forfeit';
            $match->completed_at = now();
            $match->forfeit_reason = 'Forfeit recorded';
            $match->save();

            $this->propagateMatchResult($match, $winnerId);

            $this->writeAudit($match, 'score.forfeit', [
                'home_score' => $previousHomeScore,
                'away_score' => $previousAwayScore,
                'winner_team_id' => $previousWinnerId,
            ], [
                'home_score' => $score1,
                'away_score' => $score2,
                'winner_team_id' => $winnerId,
                'forfeit_side' => $forfeitSide,
            ]);
        });
    }

    public function finalizeMatch(MatchModel $match): void
    {
        DB::transaction(function () use ($match): void {
            $match->refresh();

            if ($match->home_score === null || $match->away_score === null) {
                return;
            }

            $team1Id = $match->home_team_id;
            $team2Id = $match->away_team_id;

            $winnerId = $match->winner_team_id;

            if ($winnerId === null) {
                if ($team1Id === null && $team2Id === null) {
                    return;
                }
                if ($team1Id === null) {
                    $winnerId = $team2Id;
                } elseif ($team2Id === null) {
                    $winnerId = $team1Id;
                } else {
                    $winnerId = $match->home_score > $match->away_score ? $team1Id : $team2Id;
                }
            }

            $match->winner_team_id = $winnerId;
            $match->status = 'final';
            if ($match->completed_at === null) {
                $match->completed_at = now();
            }
            $match->save();

            $this->propagateMatchResult($match, $winnerId);
        });
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    protected function writeAudit(MatchModel $match, string $action, array $before, array $after): void
    {
        $match->loadMissing('tournament');

        $auditId = (int) (AuditLog::max('id') ?? 0) + 1;

        AuditLog::create([
            'id' => $auditId,
            'organization_id' => $match->tournament?->organization_id,
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => 'match',
            'entity_id' => $match->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip' => null,
            'user_agent' => null,
            'created_at' => now(),
        ]);
    }

    protected function propagateMatchResult(MatchModel $match, ?int $winnerId): void
    {
        if ($match->series_id !== null) {
            return;
        }

        if ($match->best_of > 1 && $match->status === 'in_progress') {
            return;
        }

        $loserId = null;

        if ($match->home_team_id !== null && $match->away_team_id !== null && $winnerId !== null) {
            $loserId = (int) $match->home_team_id === $winnerId
                ? (int) $match->away_team_id
                : (int) $match->home_team_id;
        }

        $slots = BracketSlot::where('source_match_id', $match->id)->get();

        if ($slots->isEmpty()) {
            $this->propagateLegacySingleElimination($match, $winnerId);

            return;
        }

        foreach ($slots as $slot) {
            $value = $slot->source_outcome === 'loser' ? $loserId : $winnerId;

            $target = MatchModel::where('bracket_id', $slot->bracket_id)
                ->where('stage', $slot->stage)
                ->where('round_number', $slot->round_number)
                ->where('match_number', $slot->match_number)
                ->whereNull('series_id')
                ->first();

            if (! $target instanceof MatchModel) {
                continue;
            }

            if (in_array($target->status, ['final', 'forfeit', 'in_progress'], true)) {
                continue;
            }

            $slot->team_id = $value;
            $slot->save();

            $column = $slot->slot === 'home' ? 'home_team_id' : 'away_team_id';
            $target->{$column} = $value;
            $target->save();

            $this->syncSeriesTeams($target);
        }
    }

    protected function propagateLegacySingleElimination(MatchModel $match, ?int $winnerId): void
    {
        if ($winnerId === null) {
            return;
        }

        $format = $match->bracket?->format;

        if ($format !== 'single_elim' && $format !== null) {
            return;
        }

        $nextRound = (int) ($match->round_number / 2);

        if ($nextRound < 2) {
            return;
        }

        $nextMatchNumber = (int) ceil($match->match_number / 2);
        $target = MatchModel::where('bracket_id', $match->bracket_id)
            ->where('round_number', $nextRound)
            ->where('match_number', $nextMatchNumber)
            ->first();

        if ($target !== null && ! in_array($target->status, ['final', 'forfeit'], true)) {
            if ($match->match_number % 2 !== 0) {
                $target->home_team_id = $winnerId;
            } else {
                $target->away_team_id = $winnerId;
            }
            $target->save();
        }
    }
}
