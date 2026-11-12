<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Registration;
use App\Models\Team;
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
    public function generate(
        Tournament $tournament,
        string $format = 'single-elimination',
        bool $shuffle = false,
    ): array {
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

        return DB::transaction(function () use ($tournament, $format, $registrations, $division, $rules, $slots, $shuffle): array {
            $this->deleteBracketData($tournament, $format);

            $ordered = $shuffle
                ? $registrations->shuffle()->pluck('team_id')->map(fn ($id): int => (int) $id)->all()
                : $registrations
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
                'seeding_method' => $shuffle ? 'random' : 'manual',
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
                    'shuffled' => $shuffle,
                    'seedingMethod' => $shuffle ? 'random' : 'manual',
                ],
                $tournament->organization_id,
            );

            return $result;
        });
    }

    /**
     * @return array{matches: int, games: int, byes: int}
     */
    public function generatePlayoffs(Tournament $tournament): array
    {
        if ($tournament->format() !== 'round-robin') {
            throw new \InvalidArgumentException('Playoffs are only available for round-robin tournaments.');
        }

        $rules = is_array($tournament->rules_json) ? $tournament->rules_json : [];
        $playoffTeams = isset($rules['playoff_teams']) && is_numeric($rules['playoff_teams'])
            ? (int) $rules['playoff_teams']
            : 0;

        if (! in_array($playoffTeams, [2, 4, 8, 16], true)) {
            throw new \InvalidArgumentException('Choose 2, 4, 8, or 16 playoff teams.');
        }

        $divisionIds = $tournament->divisions()->pluck('id');
        $group = Bracket::whereIn('division_id', $divisionIds)
            ->where('phase', 'group')
            ->first();

        if (! $group instanceof Bracket) {
            throw new \InvalidArgumentException('Generate the round-robin group stage first.');
        }

        $pending = MatchModel::where('bracket_id', $group->id)
            ->whereNotIn('status', ['final', 'forfeit', 'cancelled'])
            ->exists();

        if ($pending) {
            throw new \InvalidArgumentException('All group games must be final before generating playoffs.');
        }

        $standings = app(StandingsService::class)->compute($tournament)->take($playoffTeams)->values();

        if ($standings->count() < $playoffTeams) {
            throw new \InvalidArgumentException('Not enough ranked teams to fill the playoff bracket.');
        }

        $orderedTeamIds = $standings
            ->pluck('team_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $division = $tournament->divisions()->first();

        if (! $division instanceof Division) {
            throw new \InvalidArgumentException('The tournament has no division.');
        }

        return DB::transaction(function () use ($tournament, $divisionIds, $division, $orderedTeamIds, $playoffTeams, $rules): array {
            $existing = Bracket::whereIn('division_id', $divisionIds)
                ->where('phase', 'playoffs')
                ->get();

            foreach ($existing as $bracket) {
                $hasResults = MatchModel::where('bracket_id', $bracket->id)
                    ->whereIn('status', ['final', 'forfeit', 'in_progress'])
                    ->exists();

                if ($hasResults) {
                    throw new \InvalidArgumentException('Playoffs already have results; regenerate is not allowed.');
                }

                $this->deleteMatchesAndSlots(Bracket::whereKey($bracket->id)->pluck('id'));
                $bracket->delete();
            }

            $bracket = Bracket::create([
                'division_id' => $division->id,
                'name' => $tournament->name.' Playoffs',
                'format' => 'single_elim',
                'phase' => 'playoffs',
                'seeding_method' => 'standings',
                'status' => 'generated',
                'generated_at' => now(),
            ]);

            $bestOf = [
                0 => $this->bestOfRule($rules, 'best_of_final'),
                1 => $this->bestOfRule($rules, 'best_of_semifinal'),
                2 => $this->bestOfRule($rules, 'best_of_quarterfinal'),
            ];

            $plan = (new SingleEliminationBuilder)->build($playoffTeams, $orderedTeamIds, $bestOf);
            $result = $this->persistPlan($tournament, $bracket, $plan);

            app(AuditLogger::class)->log(
                'bracket.playoffs',
                'tournament',
                $tournament->id,
                null,
                [
                    'teams' => $playoffTeams,
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
            $childIds = MatchModel::where('series_id', $match->id)
                ->whereIn('status', ['conditional', 'scheduled'])
                ->pluck('id');

            $this->releaseSlots($childIds);
            MatchModel::whereIn('id', $childIds)->update(['status' => 'cancelled']);
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
            $childIds = MatchModel::where('series_id', $match->id)
                ->whereIn('status', ['conditional', 'scheduled'])
                ->pluck('id');

            $this->releaseSlots($childIds);
            MatchModel::whereIn('id', $childIds)->update(['status' => 'cancelled']);
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

            $this->deleteMatchesAndSlots(Bracket::whereKey($bracket->id)->pluck('id'));

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

            $result = $this->persistPlan($tournament, $bracket, $plan);

            $assignments = [];

            foreach ($orderedTeamIds as $index => $teamId) {
                $assignments[] = [
                    'seed' => $index + 1,
                    'team_id' => $teamId,
                ];
            }

            app(AuditLogger::class)->log(
                'bracket.slots',
                'tournament',
                $tournament->id,
                null,
                ['assignments' => $assignments],
                $tournament->organization_id,
            );

            return $result;
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

            if (! in_array($match->status, ['final', 'forfeit', 'in_progress'], true)) {
                throw new \InvalidArgumentException('Only a recorded result can be corrected.');
            }

            $anchor = $match->series_id !== null
                ? MatchModel::find($match->series_id)
                : $match;

            if (! $anchor instanceof MatchModel) {
                throw new \InvalidArgumentException('Series anchor not found.');
            }

            $previousHomeScore = $match->home_score;
            $previousAwayScore = $match->away_score;
            $previousWinnerId = $match->winner_team_id;
            $previousGameWinnerId = $match->game_winner_team_id;

            $newGameWinnerId = $score1 > $score2
                ? (int) $match->home_team_id
                : (int) $match->away_team_id;

            $winnerChanged = (int) $previousGameWinnerId !== $newGameWinnerId;

            if ($winnerChanged && $this->downstreamHasResults($anchor)) {
                throw new \InvalidArgumentException('Cannot change this result because a later matchup already has a result.');
            }

            if ($winnerChanged) {
                $this->clearDownstream($anchor);
            }

            $match->home_score = $score1;
            $match->away_score = $score2;
            $match->game_winner_team_id = $newGameWinnerId;
            $match->winner_team_id = $match->series_id === null && $match->best_of > 1
                ? null
                : $newGameWinnerId;
            $match->status = 'final';
            $match->forfeit_reason = null;

            if ($match->completed_at === null) {
                $match->completed_at = now();
            }

            $match->save();

            if ($anchor->id === $match->id && $anchor->best_of <= 1) {
                $anchor->series_winner_team_id = $newGameWinnerId;
                $anchor->series_home_wins = $newGameWinnerId === (int) $anchor->home_team_id ? 1 : 0;
                $anchor->series_away_wins = $newGameWinnerId === (int) $anchor->away_team_id ? 1 : 0;
                $anchor->save();

                if ($winnerChanged) {
                    $this->propagateMatchResult($anchor, $newGameWinnerId);
                }
            } else {
                $this->syncSeries($anchor);
            }

            $this->syncGrandFinalReset($anchor);

            $bracket = $anchor->bracket;

            if ($bracket instanceof Bracket) {
                $this->resolveStructuralByes($bracket);
            }

            $this->writeAudit($match, 'score.correct', [
                'home_score' => $previousHomeScore,
                'away_score' => $previousAwayScore,
                'winner_team_id' => $previousWinnerId,
            ], [
                'home_score' => $score1,
                'away_score' => $score2,
                'winner_team_id' => $newGameWinnerId,
                'reason' => $reason,
            ]);
        });
    }

    public function syncGrandFinalReset(MatchModel $grandFinal): void
    {
        if ($grandFinal->stage !== 'grand_final' || $grandFinal->best_of > 1) {
            return;
        }

        $reset = MatchModel::where('bracket_id', $grandFinal->bracket_id)
            ->where('stage', 'reset')
            ->first();

        if (! $reset instanceof MatchModel) {
            return;
        }

        if (! in_array($grandFinal->status, ['final', 'forfeit'], true)) {
            $reset->status = 'conditional';
            $reset->home_team_id = $grandFinal->home_team_id;
            $reset->away_team_id = $grandFinal->away_team_id;
            $reset->save();

            return;
        }

        $winnersFinal = MatchModel::where('bracket_id', $grandFinal->bracket_id)
            ->where('stage', 'winners')
            ->whereNull('series_id')
            ->orderByDesc('round_number')
            ->first();

        $winnersChampionId = $winnersFinal?->winner_team_id;

        if ($winnersChampionId !== null && (int) $grandFinal->winner_team_id === (int) $winnersChampionId) {
            if (! in_array($reset->status, ['final', 'forfeit'], true)) {
                $reset->status = 'cancelled';
                $reset->save();
            }

            return;
        }

        if (in_array($reset->status, ['conditional', 'cancelled'], true)) {
            $reset->status = 'scheduled';
            $reset->home_team_id = $grandFinal->home_team_id;
            $reset->away_team_id = $grandFinal->away_team_id;
            $reset->save();
        }
    }

    public function champion(Tournament $tournament): ?Team
    {
        $format = $tournament->format();
        $divisionIds = $tournament->divisions()->pluck('id');

        if ($format === 'round-robin') {
            $playoff = Bracket::whereIn('division_id', $divisionIds)
                ->where('phase', 'playoffs')
                ->first();

            return $playoff instanceof Bracket
                ? $this->singleEliminationChampion($playoff)
                : null;
        }

        $bracket = Bracket::whereIn('division_id', $divisionIds)
            ->where('phase', 'main')
            ->first();

        if (! $bracket instanceof Bracket) {
            return null;
        }

        return $format === 'double-elimination'
            ? $this->doubleEliminationChampion($bracket)
            : $this->singleEliminationChampion($bracket);
    }

    protected function singleEliminationChampion(Bracket $bracket): ?Team
    {
        $final = MatchModel::where('bracket_id', $bracket->id)
            ->whereNull('series_id')
            ->where('stage', 'winners')
            ->orderByDesc('round_number')
            ->first();

        if (! $final instanceof MatchModel || ! in_array($final->status, ['final', 'forfeit'], true)) {
            return null;
        }

        return $final->winnerTeam instanceof Team ? $final->winnerTeam : null;
    }

    protected function doubleEliminationChampion(Bracket $bracket): ?Team
    {
        $grandFinal = MatchModel::where('bracket_id', $bracket->id)
            ->where('stage', 'grand_final')
            ->first();

        if (! $grandFinal instanceof MatchModel || ! in_array($grandFinal->status, ['final', 'forfeit'], true)) {
            return null;
        }

        if ($grandFinal->best_of > 1) {
            return $grandFinal->winnerTeam instanceof Team ? $grandFinal->winnerTeam : null;
        }

        $reset = MatchModel::where('bracket_id', $bracket->id)
            ->where('stage', 'reset')
            ->first();

        if ($reset instanceof MatchModel && in_array($reset->status, ['final', 'forfeit'], true)) {
            return $reset->winnerTeam instanceof Team ? $reset->winnerTeam : null;
        }

        $winnersFinal = MatchModel::where('bracket_id', $bracket->id)
            ->where('stage', 'winners')
            ->whereNull('series_id')
            ->orderByDesc('round_number')
            ->first();

        if ($winnersFinal instanceof MatchModel
            && $winnersFinal->winner_team_id !== null
            && (int) $grandFinal->winner_team_id === (int) $winnersFinal->winner_team_id) {
            return $grandFinal->winnerTeam instanceof Team ? $grandFinal->winnerTeam : null;
        }

        return null;
    }

    protected function downstreamHasResults(MatchModel $anchor): bool
    {
        $slots = BracketSlot::where('source_match_id', $anchor->id)->get();

        foreach ($slots as $slot) {
            if ($slot->stage === 'reset') {
                continue;
            }

            $target = MatchModel::where('bracket_id', $slot->bracket_id)
                ->where('stage', $slot->stage)
                ->where('round_number', $slot->round_number)
                ->where('match_number', $slot->match_number)
                ->whereNull('series_id')
                ->first();

            if (! $target instanceof MatchModel) {
                continue;
            }

            if ($target->status === 'final' && $target->isByeGame()) {
                continue;
            }

            if (in_array($target->status, ['final', 'forfeit', 'in_progress'], true)) {
                return true;
            }
        }

        if ($anchor->stage === 'grand_final') {
            $reset = MatchModel::where('bracket_id', $anchor->bracket_id)
                ->where('stage', 'reset')
                ->first();

            if ($reset instanceof MatchModel && in_array($reset->status, ['final', 'forfeit', 'in_progress'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function clearDownstream(MatchModel $source): void
    {
        $slots = BracketSlot::where('source_match_id', $source->id)->get();

        foreach ($slots as $slot) {
            if ($slot->stage === 'reset') {
                continue;
            }

            $target = MatchModel::where('bracket_id', $slot->bracket_id)
                ->where('stage', $slot->stage)
                ->where('round_number', $slot->round_number)
                ->where('match_number', $slot->match_number)
                ->whereNull('series_id')
                ->first();

            if (! $target instanceof MatchModel) {
                continue;
            }

            if (in_array($target->status, ['final', 'forfeit', 'in_progress'], true) && ! $target->isByeGame()) {
                throw new \InvalidArgumentException('Cannot change this result because a later matchup already has a result.');
            }

            $slot->team_id = null;
            $slot->save();

            $column = $slot->slot === 'home' ? 'home_team_id' : 'away_team_id';
            $target->{$column} = null;

            if (($target->status === 'final' && $target->isByeGame()) || $target->status === 'cancelled') {
                $target->status = 'scheduled';
                $target->winner_team_id = null;
                $target->series_winner_team_id = null;
                $target->game_winner_team_id = null;
                $target->save();
                $this->clearDownstream($target);
            } else {
                $target->save();
            }

            $this->syncSeriesTeams($target);
        }
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

            if (in_array($match->status, ['final', 'forfeit', 'cancelled', 'conditional'], true)) {
                throw new \InvalidArgumentException('Use correction to change a recorded result.');
            }

            if ($match->series_id === null && $match->best_of > 1 && $match->status === 'in_progress') {
                throw new \InvalidArgumentException('Game 1 is already recorded. Score the active game.');
            }

            $this->applyGameResult($match, $score1, $score2, 'final');

            $this->writeAudit($match, 'score.save', [
                'home_score' => null,
                'away_score' => null,
                'winner_team_id' => null,
            ], [
                'home_score' => $score1,
                'away_score' => $score2,
                'winner_team_id' => $match->game_winner_team_id,
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

            if (in_array($match->status, ['final', 'forfeit', 'cancelled', 'conditional'], true)) {
                throw new \InvalidArgumentException('Only a scheduled game can be recorded as a forfeit.');
            }

            if ($match->series_id === null && $match->best_of > 1 && $match->status === 'in_progress') {
                throw new \InvalidArgumentException('Game 1 is already recorded. Score the active game.');
            }

            $score1 = $forfeitSide === 1 ? 0 : 20;
            $score2 = $forfeitSide === 2 ? 0 : 20;

            $this->applyGameResult($match, $score1, $score2, 'forfeit', 'Forfeit recorded');

            $this->writeAudit($match, 'score.forfeit', [
                'home_score' => null,
                'away_score' => null,
                'winner_team_id' => null,
            ], [
                'home_score' => $score1,
                'away_score' => $score2,
                'winner_team_id' => $match->game_winner_team_id,
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

            if ($match->home_team_id === null || $match->away_team_id === null) {
                return;
            }

            if (in_array($match->status, ['final', 'forfeit', 'in_progress'], true)) {
                return;
            }

            $this->applyGameResult(
                $match,
                (int) $match->home_score,
                (int) $match->away_score,
                'final',
            );
        });
    }

    /**
     * @return array{winnerId: int, loserId: int|null}
     */
    protected function applyGameResult(MatchModel $game, int $score1, int $score2, string $status, ?string $forfeitReason = null): array
    {
        $homeTeamId = $game->home_team_id !== null ? (int) $game->home_team_id : null;
        $awayTeamId = $game->away_team_id !== null ? (int) $game->away_team_id : null;

        if ($homeTeamId === null || $awayTeamId === null) {
            throw new \InvalidArgumentException('Both teams must be known before entering a score.');
        }

        $winnerId = $score1 > $score2 ? $homeTeamId : $awayTeamId;
        $loserId = $winnerId === $homeTeamId ? $awayTeamId : $homeTeamId;

        $game->home_score = $score1;
        $game->away_score = $score2;
        $game->game_winner_team_id = $winnerId;
        $game->winner_team_id = $game->series_id === null && $game->best_of > 1
            ? null
            : $winnerId;
        $game->status = $status;
        $game->forfeit_reason = $forfeitReason;
        $game->completed_at = now();
        $game->save();

        if ($game->series_id === null) {
            if ($game->best_of > 1) {
                $this->syncSeries($game);
            } else {
                $game->series_winner_team_id = $winnerId;
                $game->series_home_wins = $winnerId === $homeTeamId ? 1 : 0;
                $game->series_away_wins = $winnerId === $awayTeamId ? 1 : 0;
                $game->save();
                $this->propagateMatchResult($game, $winnerId);
            }
        } else {
            $anchor = MatchModel::find($game->series_id);

            if ($anchor instanceof MatchModel) {
                $this->syncSeries($anchor);
            }
        }

        if ($game->bracket instanceof Bracket) {
            $this->resolveStructuralByes($game->bracket);
        }

        if ($game->series_id === null && $game->stage === 'grand_final') {
            $this->syncGrandFinalReset($game);
        }

        return ['winnerId' => $winnerId, 'loserId' => $loserId];
    }

    public function seriesWinsNeeded(int $bestOf): int
    {
        return intdiv($bestOf, 2) + 1;
    }

    public function syncSeries(MatchModel $anchor): void
    {
        if ($anchor->series_id !== null) {
            return;
        }

        if ($anchor->best_of <= 1) {
            $anchor->series_winner_team_id = $anchor->winner_team_id;
            $anchor->series_home_wins = $anchor->winner_team_id !== null && (int) $anchor->winner_team_id === (int) $anchor->home_team_id ? 1 : 0;
            $anchor->series_away_wins = $anchor->winner_team_id !== null && (int) $anchor->winner_team_id === (int) $anchor->away_team_id ? 1 : 0;
            $anchor->save();

            return;
        }

        $games = MatchModel::where('series_id', $anchor->id)
            ->orderBy('game_number')
            ->get();

        $allGames = $games->prepend($anchor);
        $homeWins = 0;
        $awayWins = 0;
        $lastDecidingGame = null;
        $needed = $this->seriesWinsNeeded($anchor->best_of);

        foreach ($allGames as $game) {
            $isCompleted = $game->is($anchor)
                ? $game->game_winner_team_id !== null
                : in_array($game->status, ['final', 'forfeit'], true);

            if (! $isCompleted || $game->game_winner_team_id === null) {
                continue;
            }

            if ((int) $game->game_winner_team_id === (int) $anchor->home_team_id) {
                $homeWins++;
                if ($homeWins === $needed) {
                    $lastDecidingGame = $game;
                }
            } elseif ((int) $game->game_winner_team_id === (int) $anchor->away_team_id) {
                $awayWins++;
                if ($awayWins === $needed) {
                    $lastDecidingGame = $game;
                }
            }
        }

        $anchor->series_home_wins = $homeWins;
        $anchor->series_away_wins = $awayWins;

        if ($homeWins >= $needed || $awayWins >= $needed) {
            $winnerId = $homeWins >= $needed ? $anchor->home_team_id : $anchor->away_team_id;
            $anchor->winner_team_id = $winnerId;
            $anchor->series_winner_team_id = $winnerId;
            $anchor->status = $lastDecidingGame?->status === 'forfeit' ? 'forfeit' : 'final';
            $anchor->completed_at ??= now();
            $anchor->save();

            $this->cancelRemainingSeriesGames($anchor);
            $this->propagateMatchResult($anchor, $winnerId);

            return;
        }

        $hasCompletedGame = $allGames->contains(
            fn (MatchModel $game): bool => in_array($game->status, ['final', 'forfeit'], true),
        );

        $anchor->winner_team_id = null;
        $anchor->series_winner_team_id = null;
        $anchor->status = $hasCompletedGame ? 'in_progress' : 'scheduled';
        $anchor->completed_at = null;
        $anchor->save();

        $this->syncSeriesTeams($anchor);
        $this->activateNextSeriesGame($anchor);
    }

    public function cancelRemainingSeriesGames(MatchModel $anchor): void
    {
        $childIds = MatchModel::where('series_id', $anchor->id)
            ->whereIn('status', ['conditional', 'scheduled'])
            ->pluck('id');

        $this->releaseSlots($childIds);

        MatchModel::whereIn('id', $childIds)->update(['status' => 'cancelled']);
    }

    /**
     * @param  iterable<int|string>  $matchIds
     */
    protected function releaseSlots(iterable $matchIds): void
    {
        $ids = collect($matchIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($ids->isNotEmpty()) {
            MatchSlot::whereIn('match_id', $ids)->delete();
        }
    }

    protected function activateNextSeriesGame(MatchModel $anchor): void
    {
        $games = MatchModel::where('series_id', $anchor->id)
            ->orderBy('game_number')
            ->get();

        $next = $games->first(
            fn (MatchModel $game): bool => in_array($game->status, ['conditional', 'scheduled'], true),
        );

        if (! $next instanceof MatchModel) {
            $lastCompleted = $games
                ->filter(fn (MatchModel $game): bool => in_array($game->status, ['final', 'forfeit'], true))
                ->max('game_number') ?? 0;

            $next = $games->first(
                fn (MatchModel $game): bool => $game->status === 'cancelled' && (int) $game->game_number > (int) $lastCompleted,
            );
        }

        if (! $next instanceof MatchModel) {
            return;
        }

        $next->status = 'scheduled';
        $next->home_team_id = $anchor->home_team_id;
        $next->away_team_id = $anchor->away_team_id;
        $next->save();

        app(SchedulingService::class)->scheduleSeriesGame($next, $anchor);
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
