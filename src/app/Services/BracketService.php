<?php

namespace App\Services;

use App\Models\AuditLog;
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
        if (! in_array($format, ['single-elimination', 'round-robin', 'double-elimination'], true)) {
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
                'format' => match ($format) {
                    'single-elimination' => 'single_elim',
                    'double-elimination' => 'double_elim',
                    default => 'round_robin',
                },
                'seeding_method' => 'manual',
                'status' => 'generated',
                'generated_at' => now(),
            ]);

            if ($format === 'single-elimination') {
                $result = $this->generateSingleElimination($tournament, $bracket, $approved);
            } elseif ($format === 'double-elimination') {
                $result = $this->generateDoubleElimination($tournament, $bracket, $approved);
            } else {
                $result = $this->generateRoundRobin($tournament, $bracket, $approved);
            }

            app(AuditLogger::class)->log(
                'bracket.generate',
                'tournament',
                $tournament->id,
                null,
                [
                    'teams' => $registrations->count(),
                    'byes' => $result['byes'],
                    'matches' => $result['matches'],
                ],
                $tournament->organization_id,
            );

            return $result;
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

        // Resolve structural byes in later rounds where the missing-side feeder
        // has no real team (so no future opponent can appear).
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($roundSizes as $roundSize) {
                if ($roundSize === $firstRoundSize) {
                    continue;
                }
                $prevRoundSize = $roundSize * 2;
                if (! isset($byRound[$prevRoundSize])) {
                    continue;
                }
                foreach ($byRound[$roundSize] as $match) {
                    if (in_array($match->status, ['final', 'forfeit'], true)) {
                        continue;
                    }

                    $missingHome = $match->home_team_id === null && $match->away_team_id !== null;
                    $missingAway = $match->away_team_id === null && $match->home_team_id !== null;
                    if (! $missingHome && ! $missingAway) {
                        continue;
                    }

                    $feederIndex = $missingHome
                        ? ($match->match_number * 2 - 2)
                        : ($match->match_number * 2 - 1);
                    $feeder = $byRound[$prevRoundSize][$feederIndex] ?? null;
                    if (! $feeder instanceof MatchModel) {
                        continue;
                    }
                    if ($feeder->home_team_id !== null || $feeder->away_team_id !== null) {
                        continue;
                    }

                    $winnerId = $missingHome ? $match->away_team_id : $match->home_team_id;

                    $match->status = 'final';
                    $match->winner_team_id = $winnerId;
                    $match->save();
                    $byes++;
                    $changed = true;

                    $nextRoundSize = (int) ($roundSize / 2);
                    if ($nextRoundSize >= 2 && isset($byRound[$nextRoundSize])) {
                        $nextMatchNumber = (int) ceil($match->match_number / 2);
                        $nextMatch = $byRound[$nextRoundSize][$nextMatchNumber - 1] ?? null;
                        if ($nextMatch instanceof MatchModel) {
                            if ($match->match_number % 2 !== 0) {
                                $nextMatch->home_team_id = $winnerId;
                            } else {
                                $nextMatch->away_team_id = $winnerId;
                            }
                            $nextMatch->save();
                        }
                    }
                }
            }
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
     * @param  Collection<int, Registration>  $approved
     * @return array{matches: int, byes: int}
     */
    protected function generateDoubleElimination(Tournament $tournament, Bracket $bracket, $approved): array
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
        if ($size < 4) {
            throw new \InvalidArgumentException('Double elimination requires at least 4 slots.');
        }

        $ordered = $approved->values()->all();
        usort($ordered, fn (Registration $a, Registration $b): int => ($a->seed ?? 999) <=> ($b->seed ?? 999));

        $orderedSlots = [];
        foreach ($this->seedOrder($size) as $seedNumber) {
            $orderedSlots[] = $ordered[$seedNumber - 1] ?? null;
        }

        $nextId = (int) (MatchModel::max('id') ?? 0);
        $allMatches = [];

        $makeMatch = function (int $roundNumber, int $slot, string $roundCode, string $bracketType) use (&$nextId, $tournament, $bracket, &$allMatches): MatchModel {
            $match = new MatchModel([
                'bracket_id' => $bracket->id,
                'tournament_id' => $tournament->id,
                'round_number' => $roundNumber,
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
            $match->isBye = false;
            $match->nextMatchId = null;
            $match->nextSlot = null;
            $match->loserMatchId = null;
            $match->loserSlot = null;
            $allMatches[] = $match;

            return $match;
        };

        // WB R1
        $wbR1 = [];
        for ($slot = 1; $slot <= (int) ($size / 2); $slot++) {
            $wbR1[] = $makeMatch($size, $slot, 'W1', 'winners');
        }
        foreach ($orderedSlots as $index => $registration) {
            $match = $wbR1[(int) floor($index / 2)];
            $teamId = $registration instanceof Registration ? $registration->team_id : null;
            if ($index % 2 === 0) {
                $match->home_team_id = $teamId;
            } else {
                $match->away_team_id = $teamId;
            }
        }
        foreach ($wbR1 as $match) {
            if ($match->home_team_id && ! $match->away_team_id) {
                $match->isBye = true;
                $match->status = 'final';
                $match->winner_team_id = $match->home_team_id;
            }
            if (! $match->home_team_id && $match->away_team_id) {
                $match->isBye = true;
                $match->status = 'final';
                $match->winner_team_id = $match->away_team_id;
            }
        }
        $wbRounds = [$wbR1];

        // Additional WB rounds
        $wbRoundSize = (int) ($size / 2);
        $wbRoundNum = 2;
        while ($wbRoundSize >= 2) {
            $count = (int) ($wbRoundSize / 2);
            $round = [];
            for ($slot = 1; $slot <= $count; $slot++) {
                $round[] = $makeMatch($wbRoundSize, $slot, 'W'.$wbRoundNum, 'winners');
            }
            $prevRound = $wbRounds[count($wbRounds) - 1];
            foreach ($prevRound as $idx => $m) {
                $nextSlot = (int) floor($idx / 2) + 1;
                $nextMatch = $round[$nextSlot - 1] ?? null;
                if ($nextMatch) {
                    $m->nextMatchId = $nextMatch->id;
                    $m->nextSlot = ($idx % 2 === 0) ? 1 : 2;
                }
            }
            $wbRounds[] = $round;
            $wbRoundSize = (int) ($wbRoundSize / 2);
            $wbRoundNum++;
        }

        $wbFinal = $wbRounds[count($wbRounds) - 1][0];
        $wbSemi = count($wbRounds) >= 2 ? $wbRounds[count($wbRounds) - 2] : null;

        // Losers bracket
        $lbRounds = [];
        $lbSeq = 1;
        $lbRoundNumber = function () use (&$lbSeq): int {
            return 1000 + $lbSeq++;
        };

        // LB R1
        $lbR1Count = max(1, (int) floor(count($wbRounds[0]) / 2));
        $lbR1 = [];
        for ($slot = 1; $slot <= $lbR1Count; $slot++) {
            $lbR1[] = $makeMatch($lbRoundNumber(), $slot, 'L1', 'losers');
        }
        foreach ($wbRounds[0] as $idx => $m) {
            $lbMatch = $lbR1[(int) floor($idx / 2)] ?? null;
            if ($lbMatch) {
                $m->loserMatchId = $lbMatch->id;
                $m->loserSlot = ($idx % 2 === 0) ? 1 : 2;
            }
        }
        $lbRounds[] = $lbR1;

        if (count($wbRounds) >= 2) {
            $lbR2Count = count($lbR1);
            $lbR2 = [];
            for ($slot = 1; $slot <= $lbR2Count; $slot++) {
                $lbR2[] = $makeMatch($lbRoundNumber(), $slot, 'L2', 'losers');
            }
            foreach ($lbR1 as $idx => $m) {
                $lbMatch = $lbR2[(int) floor($idx / 2)] ?? ($lbR2[$idx] ?? null);
                if ($lbMatch) {
                    $m->nextMatchId = $lbMatch->id;
                    $m->nextSlot = ($idx % 2 === 0) ? 1 : 2;
                }
            }
            if ($wbSemi) {
                foreach ($wbSemi as $idx => $m) {
                    $lbMatch = $lbR2[$idx % count($lbR2)] ?? null;
                    if ($lbMatch && ! $m->loserMatchId) {
                        $m->loserMatchId = $lbMatch->id;
                        $m->loserSlot = $lbMatch->home_team_id ? 2 : 1;
                    }
                }
            }
            $lbRounds[] = $lbR2;

            if (count($lbR2) > 1) {
                $lbR3Count = max(1, (int) floor(count($lbR2) / 2));
                $lbR3 = [];
                for ($slot = 1; $slot <= $lbR3Count; $slot++) {
                    $lbR3[] = $makeMatch($lbRoundNumber(), $slot, 'L3', 'losers');
                }
                foreach ($lbR2 as $idx => $m) {
                    $lbMatch = $lbR3[(int) floor($idx / 2)] ?? null;
                    if ($lbMatch) {
                        $m->nextMatchId = $lbMatch->id;
                        $m->nextSlot = ($idx % 2 === 0) ? 1 : 2;
                    }
                }
                $lbRounds[] = $lbR3;

                if (count($wbRounds) >= 3) {
                    $lbR4Count = count($lbR3);
                    $lbR4 = [];
                    for ($slot = 1; $slot <= $lbR4Count; $slot++) {
                        $lbR4[] = $makeMatch($lbRoundNumber(), $slot, 'L4', 'losers');
                    }
                    foreach ($lbR3 as $idx => $m) {
                        $lbMatch = $lbR4[$idx % count($lbR4)] ?? null;
                        if ($lbMatch) {
                            $m->nextMatchId = $lbMatch->id;
                            $m->nextSlot = ($idx % 2 === 0) ? 1 : 2;
                        }
                    }
                    $wbFinal->loserMatchId = $lbR4[0]->id;
                    $wbFinal->loserSlot = 1;
                    $lbRounds[] = $lbR4;

                    if (count($lbR4) > 1) {
                        $lbFinalCount = max(1, (int) floor(count($lbR4) / 2));
                        $lbFinal = [];
                        for ($slot = 1; $slot <= $lbFinalCount; $slot++) {
                            $lbFinal[] = $makeMatch($lbRoundNumber(), $slot, 'LF', 'losers');
                        }
                        foreach ($lbR4 as $idx => $m) {
                            $lbMatch = $lbFinal[(int) floor($idx / 2)] ?? null;
                            if ($lbMatch) {
                                $m->nextMatchId = $lbMatch->id;
                                $m->nextSlot = ($idx % 2 === 0) ? 1 : 2;
                            }
                        }
                        $lbRounds[] = $lbFinal;
                    }
                }
            }
        }

        $lbChampionMatch = $lbRounds[count($lbRounds) - 1][0];

        // Grand final
        $gf = $makeMatch(2, 1, 'GF', 'grand-final');
        $wbFinal->nextMatchId = $gf->id;
        $wbFinal->nextSlot = 1;
        $lbChampionMatch->nextMatchId = $gf->id;
        $lbChampionMatch->nextSlot = 2;
        if (! $wbFinal->loserMatchId) {
            $lastLb = $lbRounds[count($lbRounds) - 1];
            $wbFinal->loserMatchId = $lastLb[0]->id;
            $wbFinal->loserSlot = 2;
        }
        $gf->loserMatchId = null;

        // Handle byes in WB R1
        foreach ($wbR1 as $match) {
            if (! $match->winner_team_id) {
                continue;
            }
            $nextMatch = null;
            foreach ($allMatches as $candidate) {
                if ($candidate->id === $match->nextMatchId) {
                    $nextMatch = $candidate;
                    break;
                }
            }
            if (! $nextMatch) {
                continue;
            }
            if ($match->nextSlot === 1) {
                $nextMatch->home_team_id = $match->winner_team_id;
            } else {
                $nextMatch->away_team_id = $match->winner_team_id;
            }
        }

        // Persist matches
        foreach ($allMatches as $match) {
            $match->save();
        }

        // Build slot sources via routing inversion
        $slotInfo = [];
        foreach ($allMatches as $m) {
            $slotInfo[$m->id] = [
                'home' => [
                    'source_type' => 'seed',
                    'source_match_id' => null,
                    'source_outcome' => null,
                    'team_id' => $m->home_team_id,
                ],
                'away' => [
                    'source_type' => 'seed',
                    'source_match_id' => null,
                    'source_outcome' => null,
                    'team_id' => $m->away_team_id,
                ],
            ];
        }

        foreach ($allMatches as $src) {
            if ($src->nextMatchId) {
                $target = null;
                foreach ($allMatches as $c) {
                    if ($c->id === $src->nextMatchId) {
                        $target = $c;
                        break;
                    }
                }
                if ($target) {
                    $slot = $src->nextSlot === 1 ? 'home' : 'away';
                    $slotInfo[$target->id][$slot] = [
                        'source_type' => 'winner',
                        'source_match_id' => $src->id,
                        'source_outcome' => 'winner',
                        'team_id' => $slot === 'home' ? $target->home_team_id : $target->away_team_id,
                    ];
                }
            }
            if ($src->loserMatchId) {
                $target = null;
                foreach ($allMatches as $c) {
                    if ($c->id === $src->loserMatchId) {
                        $target = $c;
                        break;
                    }
                }
                if ($target) {
                    $slot = $src->loserSlot === 1 ? 'home' : 'away';
                    $slotInfo[$target->id][$slot] = [
                        'source_type' => 'loser',
                        'source_match_id' => $src->id,
                        'source_outcome' => 'loser',
                        'team_id' => $slot === 'home' ? $target->home_team_id : $target->away_team_id,
                    ];
                }
            }
        }

        // Create bracket slots
        foreach ($allMatches as $m) {
            foreach (['home', 'away'] as $slot) {
                $info = $slotInfo[$m->id][$slot];
                BracketSlot::create([
                    'bracket_id' => $bracket->id,
                    'round_number' => $m->round_number,
                    'match_number' => $m->match_number,
                    'slot' => $slot,
                    'source_type' => $info['source_type'],
                    'source_match_id' => $info['source_match_id'],
                    'source_outcome' => $info['source_outcome'],
                    'team_id' => $info['team_id'],
                ]);
            }
        }

        $byes = 0;
        foreach ($wbR1 as $match) {
            if ($match->isBye) {
                $byes++;
            }
        }

        $matchCount = MatchModel::where('bracket_id', $bracket->id)->count();

        return ['matches' => $matchCount, 'byes' => $byes];
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
        if ($winnerId === null) {
            return;
        }

        $loserId = null;
        if ($match->home_team_id !== null && $match->away_team_id !== null) {
            $loserId = $match->home_team_id === $winnerId ? $match->away_team_id : $match->home_team_id;
        }

        $slots = BracketSlot::where('source_match_id', $match->id)->get();

        if ($slots->isEmpty()) {
            $format = $match->bracket?->format;

            if ($format === 'single_elim' || $format === null) {
                $nextRound = (int) ($match->round_number / 2);

                if ($nextRound >= 2) {
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

            return;
        }

        foreach ($slots as $slot) {
            $isLoserOutcome = $slot->source_outcome === 'loser';
            $value = $isLoserOutcome ? $loserId : $winnerId;

            if ($value === null) {
                continue;
            }

            $target = MatchModel::where('bracket_id', $slot->bracket_id)
                ->where('round_number', $slot->round_number)
                ->where('match_number', $slot->match_number)
                ->first();

            if ($target !== null && in_array($target->status, ['final', 'forfeit'], true)) {
                continue;
            }

            $slot->team_id = $value;
            $slot->save();

            if ($target !== null) {
                if ($slot->slot === 'home') {
                    $target->home_team_id = $value;
                } else {
                    $target->away_team_id = $value;
                }
                $target->save();
            }
        }
    }
}
