<?php

namespace App\Services;

use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Division;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BracketService
{
    /**
     * @return array{matches: int, byes: int}
     */
    public function generate(Tournament $tournament, string $format = 'single-elimination'): array
    {
        if (! in_array($format, ['single-elimination', 'round-robin'], true)) {
            throw new \InvalidArgumentException('Invalid bracket format.');
        }

        $registrations = Registration::where('tournament_id', $tournament->id)
            ->where('status', 'approved')
            ->get();

        if ($registrations->count() < 2) {
            throw new \InvalidArgumentException('At least two approved teams are required.');
        }

        $division = $tournament->divisions()->first();
        if (! $division instanceof Division) {
            $division = Division::create([
                'tournament_id' => $tournament->id,
                'name' => 'Default Division',
            ]);
        }

        return DB::transaction(function () use ($tournament, $format, $registrations, $division): array {
            MatchModel::where('tournament_id', $tournament->id)->delete();

            $approved = $registrations->sortBy(fn (Registration $registration): int => $registration->seed ?? 999)->values();

            $bracket = Bracket::create([
                'division_id' => $division->id,
                'name' => $tournament->name.' Bracket',
                'format' => $format === 'single-elimination' ? 'single_elim' : 'round_robin',
                'seeding_method' => 'manual',
                'status' => 'generated',
                'generated_at' => now(),
            ]);

            if ($format === 'single-elimination') {
                return $this->generateSingleElimination($tournament, $bracket, $approved);
            }

            return $this->generateRoundRobin($tournament, $bracket, $approved);
        });
    }

    /**
     * @param  Collection<int, Registration>  $approved
     * @return array{matches: int, byes: int}
     */
    protected function generateSingleElimination(Tournament $tournament, Bracket $bracket, $approved): array
    {
        /** @var mixed $rawRules */
        $rawRules = $tournament->rules_json;
        /** @var array<string, mixed> $rules */
        $rules = is_array($rawRules) ? $rawRules : [];
        $size = $rules['slots'] ?? null;

        if ($size === null) {
            $count = $approved->count();
            $size = 1;
            while ($size < $count) {
                $size *= 2;
            }
            if ($size < 2) {
                $size = 2;
            }
        }

        $size = (int) $size;

        if (! in_array($size, [2, 4, 8, 16, 32], true)) {
            throw new \InvalidArgumentException('Bracket size must be 2, 4, 8, 16, or 32.');
        }

        $ordered = $approved->values()->all();
        usort($ordered, fn (Registration $a, Registration $b): int => ($a->seed ?? 999) <=> ($b->seed ?? 999));

        $orderedSlots = [];
        foreach ($this->seedOrder($size) as $seedNumber) {
            $orderedSlots[] = $ordered[$seedNumber - 1] ?? null;
        }

        $roundSizes = [];
        for ($roundSize = $size; $roundSize >= 2; $roundSize = (int) ($roundSize / 2)) {
            $roundSizes[] = $roundSize;
        }

        $labels = [
            2 => 'Championship Final',
            4 => 'Semifinals',
            8 => 'Quarterfinals',
            16 => 'Round of 16',
            32 => 'Round of 32',
        ];

        /** @var array<int, array<int, MatchModel>> $byRound */
        $byRound = [];
        $nextId = (int) (MatchModel::max('id') ?? 0);

        foreach ($roundSizes as $roundIndex => $roundSize) {
            $nextRoundSize = $roundSizes[$roundIndex + 1] ?? null;
            $count = (int) ($roundSize / 2);
            $roundMatches = [];

            for ($slot = 1; $slot <= $count; $slot++) {
                $match = new MatchModel([
                    'bracket_id' => $bracket->id,
                    'tournament_id' => $tournament->id,
                    'round_number' => $roundSize,
                    'match_number' => $slot,
                    'home_team_id' => null,
                    'away_team_id' => null,
                    'home_score' => null,
                    'away_score' => null,
                    'winner_team_id' => null,
                    'status' => 'scheduled',
                    'completed_at' => null,
                    'forfeit_reason' => null,
                ]);
                $match->id = ++$nextId;
                $match->save();
                $roundMatches[] = $match;
            }

            $byRound[$roundSize] = $roundMatches;
        }

        $firstRoundSize = $size;
        $firstRound = $byRound[$firstRoundSize];

        foreach ($orderedSlots as $index => $registration) {
            $match = $firstRound[(int) floor($index / 2)];
            $teamId = $registration instanceof Registration ? $registration->team_id : null;
            if ($index % 2 === 0) {
                $match->home_team_id = $teamId;
            } else {
                $match->away_team_id = $teamId;
            }
            $match->save();
        }

        $byes = 0;
        foreach ($firstRound as $slotIndex => $match) {
            $isBye = false;
            if ($match->home_team_id && ! $match->away_team_id) {
                $isBye = true;
                $match->status = 'final';
                $match->winner_team_id = $match->home_team_id;
            }
            if (! $match->home_team_id && $match->away_team_id) {
                $isBye = true;
                $match->status = 'final';
                $match->winner_team_id = $match->away_team_id;
            }
            if ($isBye) {
                $byes++;
                $match->save();
            }

            if (! $match->winner_team_id) {
                continue;
            }

            $nextRoundSize = (int) ($firstRoundSize / 2);
            if ($nextRoundSize < 2 || ! isset($byRound[$nextRoundSize])) {
                continue;
            }

            $nextMatch = $byRound[$nextRoundSize][(int) floor($slotIndex / 2)];
            $nextSlot = ($slotIndex % 2 === 0) ? 1 : 2;
            if ($nextSlot === 1) {
                $nextMatch->home_team_id = $match->winner_team_id;
            } else {
                $nextMatch->away_team_id = $match->winner_team_id;
            }
            $nextMatch->save();
        }

        // Create bracket slots.
        foreach ($roundSizes as $roundIndex => $roundSize) {
            $roundMatches = $byRound[$roundSize];
            $previousRoundSize = $roundSize * 2;
            $previousRound = $byRound[$previousRoundSize] ?? null;

            foreach ($roundMatches as $matchIndex => $match) {
                $homeSourceMatch = null;
                $awaySourceMatch = null;
                if (is_array($previousRound)) {
                    $homeSourceMatch = $previousRound[$matchIndex * 2] ?? null;
                    $awaySourceMatch = $previousRound[$matchIndex * 2 + 1] ?? null;
                }

                $isFirstRound = $roundSize === $firstRoundSize;

                BracketSlot::create([
                    'bracket_id' => $bracket->id,
                    'round_number' => $roundSize,
                    'match_number' => $match->match_number,
                    'slot' => 'home',
                    'source_type' => $isFirstRound ? 'seed' : 'winner',
                    'source_match_id' => $homeSourceMatch instanceof MatchModel ? $homeSourceMatch->id : null,
                    'source_outcome' => $isFirstRound ? null : 'winner',
                    'team_id' => $match->home_team_id,
                ]);

                BracketSlot::create([
                    'bracket_id' => $bracket->id,
                    'round_number' => $roundSize,
                    'match_number' => $match->match_number,
                    'slot' => 'away',
                    'source_type' => $isFirstRound ? 'seed' : 'winner',
                    'source_match_id' => $awaySourceMatch instanceof MatchModel ? $awaySourceMatch->id : null,
                    'source_outcome' => $isFirstRound ? null : 'winner',
                    'team_id' => $match->away_team_id,
                ]);
            }
        }

        $matchCount = MatchModel::where('bracket_id', $bracket->id)->count();

        return ['matches' => $matchCount, 'byes' => $byes];
    }

    /**
     * @param  Collection<int, Registration>  $approved
     * @return array{matches: int, byes: int}
     */
    protected function generateRoundRobin(Tournament $tournament, Bracket $bracket, $approved): array
    {
        /** @var mixed $rawRules */
        $rawRules = $tournament->rules_json;
        /** @var array<string, mixed> $rules */
        $rules = is_array($rawRules) ? $rawRules : [];
        $size = $rules['slots'] ?? null;

        $ordered = $approved->values()->all();
        usort($ordered, fn (Registration $a, Registration $b): int => ($a->seed ?? 999) <=> ($b->seed ?? 999));

        $teams = $ordered;
        $arr = $teams;
        if (count($arr) % 2 !== 0) {
            $arr[] = null;
        }
        $n = count($arr);

        if ($size === null) {
            $size = $approved->count();
        }
        $size = (int) $size;
        if ($size < 2 || $size > 16) {
            throw new \InvalidArgumentException('Round robin requires 2 to 16 teams.');
        }

        $rounds = [];
        for ($r = 0; $r < $n - 1; $r++) {
            $round = [];
            for ($i = 0; $i < (int) ($n / 2); $i++) {
                $round[] = [$arr[$i], $arr[$n - 1 - $i]];
            }
            $rounds[] = $round;
            $last = array_pop($arr);
            array_splice($arr, 1, 0, [$last]);
        }

        $nextId = (int) (MatchModel::max('id') ?? 0);
        $byes = 0;

        foreach ($rounds as $ri => $round) {
            $roundNum = $ri + 1;
            foreach ($round as $slot => $pair) {
                [$a, $b] = $pair;
                $teamA = $a instanceof Registration ? $a->team_id : null;
                $teamB = $b instanceof Registration ? $b->team_id : null;

                $match = new MatchModel([
                    'bracket_id' => $bracket->id,
                    'tournament_id' => $tournament->id,
                    'round_number' => $roundNum,
                    'match_number' => $slot + 1,
                    'home_team_id' => $teamA,
                    'away_team_id' => $teamB,
                    'home_score' => null,
                    'away_score' => null,
                    'winner_team_id' => null,
                    'status' => 'scheduled',
                    'completed_at' => null,
                    'forfeit_reason' => null,
                ]);

                if ($teamA && ! $teamB) {
                    $match->status = 'final';
                    $match->winner_team_id = $teamA;
                }
                if (! $teamA && $teamB) {
                    $match->status = 'final';
                    $match->winner_team_id = $teamB;
                }

                $match->id = ++$nextId;
                $match->save();

                BracketSlot::create([
                    'bracket_id' => $bracket->id,
                    'round_number' => $roundNum,
                    'match_number' => $match->match_number,
                    'slot' => 'home',
                    'source_type' => 'seed',
                    'source_match_id' => null,
                    'source_outcome' => null,
                    'team_id' => $teamA,
                ]);

                BracketSlot::create([
                    'bracket_id' => $bracket->id,
                    'round_number' => $roundNum,
                    'match_number' => $match->match_number,
                    'slot' => 'away',
                    'source_type' => 'seed',
                    'source_match_id' => null,
                    'source_outcome' => null,
                    'team_id' => $teamB,
                ]);
            }
        }

        $matchCount = MatchModel::where('bracket_id', $bracket->id)->count();

        return ['matches' => $matchCount, 'byes' => 0];
    }

    /**
     * @return array<int, int>
     */
    protected function seedOrder(int $size): array
    {
        $order = [1, 2];
        while (count($order) < $size) {
            $complement = count($order) * 2 + 1;
            $new = [];
            foreach ($order as $seed) {
                $new[] = $seed;
                $new[] = $complement - $seed;
            }
            $order = $new;
        }

        return $order;
    }
}
