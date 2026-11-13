<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Court;
use App\Models\GameRule;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Tournament;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SchedulingService
{
    /**
     * Build a schedule plan without writing it to the database.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     assignments: array<int, array{matchId: int, courtId: int, scheduledAt: string}>,
     *     courts: Collection<int, Court>,
     *     slotMinutes: int,
     *     restMinutes: int,
     *     startDate: string,
     *     endDate: string|null,
     *     weekdays: list<int>,
     *     playoffWindow: array{startDate: string, endDate: string, groupEndDate: string|null, dates: list<string>, dayCount: int, rounds: list<array{label: string, bestOf: int, days: int}>}|null,
     *     summary: array{games: int, matchDays: int, firstDate: string|null, lastDate: string|null, seriesCount: int}
     * }
     */
    public function plan(Tournament $tournament, array $options = []): array
    {
        return $this->buildPlan($tournament, $options, false);
    }

    /**
     * Build a plan for games that do not have a court slot yet. Used after
     * results exist, when a full rebuild is locked but playoff games still
     * need to be placed on the calendar.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     assignments: array<int, array{matchId: int, courtId: int, scheduledAt: string}>,
     *     courts: Collection<int, Court>,
     *     slotMinutes: int,
     *     restMinutes: int,
     *     startDate: string,
     *     endDate: string|null,
     *     weekdays: list<int>,
     *     playoffWindow: array{startDate: string, endDate: string, groupEndDate: string|null, dates: list<string>, dayCount: int, rounds: list<array{label: string, bestOf: int, days: int}>}|null,
     *     summary: array{games: int, matchDays: int, firstDate: string|null, lastDate: string|null, seriesCount: int}
     * }
     */
    public function planRemaining(Tournament $tournament, array $options = []): array
    {
        return $this->buildPlan($tournament, $options, true);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     assignments: array<int, array{matchId: int, courtId: int, scheduledAt: string}>,
     *     courts: Collection<int, Court>,
     *     slotMinutes: int,
     *     restMinutes: int,
     *     startDate: string,
     *     endDate: string|null,
     *     weekdays: list<int>,
     *     playoffWindow: array{startDate: string, endDate: string, groupEndDate: string|null, dates: list<string>, dayCount: int, rounds: list<array{label: string, bestOf: int, days: int}>}|null,
     *     summary: array{games: int, matchDays: int, firstDate: string|null, lastDate: string|null, seriesCount: int}
     * }
     */
    protected function buildPlan(Tournament $tournament, array $options, bool $remaining): array
    {
        $matches = MatchModel::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', ['scheduled', 'conditional'])
            ->when($remaining, fn ($query) => $query->whereDoesntHave('matchSlots'))
            ->with('bracket:id,format')
            ->get()
            ->reject(fn (MatchModel $match): bool => $match->isByeGame())
            ->values();

        if ($matches->isEmpty()) {
            throw new InvalidArgumentException($remaining
                ? 'Every remaining game already has a court and time.'
                : 'Generate a bracket before scheduling.');
        }

        if (! $remaining) {
            $hasResults = MatchModel::query()
                ->where('tournament_id', $tournament->id)
                ->whereIn('status', ['final', 'forfeit', 'in_progress'])
                ->get()
                ->contains(fn (MatchModel $match): bool => ! $match->isByeGame());

            if ($hasResults) {
                throw new InvalidArgumentException('The schedule is locked because results exist.');
            }
        }

        $courts = $this->courts($tournament);

        if ($courts->isEmpty()) {
            throw new InvalidArgumentException('Choose at least one court for this tournament.');
        }

        $tournamentStart = $tournament->start_date?->copy()->startOfDay();
        $tournamentEnd = $tournament->end_date?->copy()->startOfDay();
        $remainingStart = $remaining ? $this->remainingStartDate($tournament) : null;

        $requestedStart = isset($options['startDate']) && is_string($options['startDate']) && $options['startDate'] !== ''
            ? Carbon::parse($options['startDate'])->startOfDay()
            : null;

        if ($requestedStart instanceof Carbon && $remainingStart instanceof Carbon && $requestedStart->lt($remainingStart)) {
            throw new InvalidArgumentException(sprintf(
                'Remaining games must start on or after %s, after the last published match day.',
                $remainingStart->format('M j, Y'),
            ));
        }

        $startDate = $requestedStart
            ?? $remainingStart
            ?? $tournamentStart
            ?? Carbon::today();

        if ($tournamentStart instanceof Carbon && $startDate->lt($tournamentStart)) {
            throw new InvalidArgumentException('Start date must be within the tournament dates.');
        }

        $endDate = null;

        if (isset($options['endDate']) && is_string($options['endDate']) && $options['endDate'] !== '') {
            $endDate = Carbon::parse($options['endDate'])->startOfDay();
        } elseif ($tournamentEnd instanceof Carbon) {
            $endDate = $tournamentEnd->copy();
        }

        if ($remaining && $remainingStart instanceof Carbon && $endDate instanceof Carbon && $remainingStart->gt($endDate)) {
            $lastSlot = MatchSlot::query()
                ->where('tournament_id', $tournament->id)
                ->whereNotNull('start_at')
                ->max('start_at');

            throw new InvalidArgumentException(sprintf(
                'The published schedule leaves no room for the remaining games: the last match day is %s and the tournament ends %s. Extend the end date or reschedule group games.',
                $lastSlot !== null ? Carbon::parse((string) $lastSlot)->format('M j, Y') : $remainingStart->format('M j, Y'),
                $endDate->format('M j, Y'),
            ));
        }

        if ($endDate instanceof Carbon) {
            if ($endDate->lt($startDate)) {
                throw new InvalidArgumentException('The end date must be on or after the start date.');
            }

            if ($tournamentEnd instanceof Carbon && $endDate->gt($tournamentEnd)) {
                throw new InvalidArgumentException('The end date must be on or before the tournament end date.');
            }
        }

        $weekdays = isset($options['weekdays']) && is_array($options['weekdays'])
            ? array_values(array_unique(array_map('intval', $options['weekdays'])))
            : range(1, 7);

        $weekdays = array_values(array_filter($weekdays, fn (int $day): bool => $day >= 1 && $day <= 7));

        if ($weekdays === []) {
            throw new InvalidArgumentException('Select at least one match day.');
        }

        $footprint = $this->playoffFootprint($tournament);
        $playoffWindow = null;
        $groupEndDate = null;

        if ($footprint !== null && $endDate instanceof Carbon) {
            $playoffWindow = $this->playoffWindow($tournament, [
                'endDate' => $endDate->format('Y-m-d'),
                'weekdays' => $weekdays,
            ]);

            if (! $remaining) {
                if ($playoffWindow === null) {
                    throw new InvalidArgumentException(sprintf(
                        'This schedule window has %d eligible match days, but the playoffs need %d. Extend the end date or reduce the playoff field / best-of games.',
                        $this->eligibleDayCount($startDate, $endDate, $weekdays),
                        $footprint['days'],
                    ));
                }

                $groupEnd = $playoffWindow['groupEndDate'] !== null
                    ? Carbon::parse($playoffWindow['groupEndDate'])->startOfDay()
                    : null;

                if (! $groupEnd instanceof Carbon || $groupEnd->lt($startDate)) {
                    throw new InvalidArgumentException(sprintf(
                        'The playoff window (%s–%s) leaves no match days for the group stage. Extend the end date or reduce the playoff field / best-of games.',
                        Carbon::parse($playoffWindow['startDate'])->format('M j, Y'),
                        Carbon::parse($playoffWindow['endDate'])->format('M j, Y'),
                    ));
                }

                $groupEndDate = $groupEnd;
            }
        }

        $slotMinutes = isset($options['slotMinutes']) && is_numeric($options['slotMinutes'])
            ? (int) $options['slotMinutes']
            : $this->slotMinutes($tournament);

        if ($slotMinutes < 10) {
            throw new InvalidArgumentException('Slot length must be at least 10 minutes.');
        }

        $restMinutes = isset($options['restMinutes']) && is_numeric($options['restMinutes'])
            ? (int) $options['restMinutes']
            : $this->restMinutes($tournament);

        if ($restMinutes < 0) {
            throw new InvalidArgumentException('Rest minutes cannot be negative.');
        }

        $units = $this->seriesUnits($matches);

        $rounds = $units
            ->groupBy(fn (array $unit): string => $unit['stage'].'|'.$unit['round'])
            ->map(function (\Illuminate\Support\Collection $group, string $key): array {
                [$stage, $round] = explode('|', $key);

                $format = $group->first()['anchor']->bracket?->format;

                return [
                    'stage' => $stage,
                    'round' => (int) $round,
                    'sequence' => $format === 'single_elim' && $stage === 'winners'
                        ? -(int) $round
                        : $this->roundSequence($stage, (int) $round),
                    'stage_rank' => $stage === 'winners' ? 0 : 1,
                    'units' => $group->sortBy(fn (array $unit): int => (int) $unit['anchor']->match_number)->values(),
                ];
            })
            ->sort(function (array $a, array $b): int {
                return [$a['sequence'], $a['stage_rank'], $a['round']]
                    <=> [$b['sequence'], $b['stage_rank'], $b['round']];
            })
            ->values();

        $existingSlots = $remaining
            ? MatchSlot::query()
                ->where('tournament_id', $tournament->id)
                ->whereNotNull('start_at')
                ->with('match:id,home_team_id,away_team_id')
                ->get()
            : new Collection;

        $baseDate = $startDate->copy();
        $assignments = [];
        $laneSlots = [];
        $teamTimes = [];
        $previousSequenceLastDay = null;

        foreach ($existingSlots as $slot) {
            if (! $slot->start_at instanceof Carbon) {
                continue;
            }

            foreach (array_filter([$slot->match?->home_team_id, $slot->match?->away_team_id]) as $teamId) {
                $teamTimes[(int) $teamId][] = $slot->start_at->getTimestamp();
            }
        }

        foreach ($rounds as $round) {
            $earliestDay = $previousSequenceLastDay === null ? 0 : $previousSequenceLastDay + 1;
            $sequenceStart = $this->nextEligibleDay($baseDate, $earliestDay, $weekdays);
            $sequenceLastDay = null;
            $baseHour = $this->roundStartHour($round['units']->count());

            foreach ($round['units'] as $unit) {
                $candidate = $sequenceStart->copy();
                $placed = false;

                while (! $placed) {
                    $candidate = $this->nextEligibleDay($candidate, 0, $weekdays);
                    $gameDays = $this->eligibleGameDays($candidate, count($unit['games']), $weekdays);
                    $projectedLast = $gameDays[count($gameDays) - 1];

                    $cutoff = $groupEndDate ?? $endDate;

                    if ($cutoff instanceof Carbon && $projectedLast->gt($cutoff)) {
                        if ($remaining) {
                            throw new InvalidArgumentException(sprintf(
                                'The remaining games need through %s, but the tournament ends %s. Extend the end date or free a day in the playoff window by rescheduling group games.',
                                $projectedLast->format('M j, Y'),
                                $cutoff->format('M j, Y'),
                            ));
                        }

                        if ($groupEndDate instanceof Carbon && $playoffWindow !== null) {
                            throw new InvalidArgumentException(sprintf(
                                'The group stage must finish by %s to keep %s–%s free for the playoffs, but this plan needs through %s. Extend the end date, add match days, or shorten slots/rest.',
                                $groupEndDate->format('M j, Y'),
                                Carbon::parse($playoffWindow['startDate'])->format('M j, Y'),
                                Carbon::parse($playoffWindow['endDate'])->format('M j, Y'),
                                $projectedLast->format('M j, Y'),
                            ));
                        }

                        throw new InvalidArgumentException(sprintf(
                            'The tournament ends %s, but this plan needs through %s. Add weekdays, shorten slots, or extend the end date.',
                            $cutoff->format('M j, Y'),
                            $projectedLast->format('M j, Y'),
                        ));
                    }

                    $placement = $this->findLane($unit, $gameDays, $courts, $baseHour, $slotMinutes, $restMinutes, $laneSlots, $teamTimes, $existingSlots);

                    if ($placement === null) {
                        $candidate->addDay();

                        continue;
                    }

                    foreach ($unit['games'] as $index => $game) {
                        $start = $gameDays[$index]->copy()->setTimeFromTimeString($placement['time']);
                        $assignments[] = [
                            'matchId' => (int) $game->id,
                            'courtId' => (int) $placement['court']->id,
                            'scheduledAt' => $start->format('Y-m-d H:i:s'),
                        ];

                        $laneSlots[$gameDays[$index]->format('Y-m-d')][$placement['court']->id][$placement['time']] = true;
                    }

                    $sequenceLastDay = $sequenceLastDay === null
                        ? $this->dayOffset($baseDate, $gameDays[count($gameDays) - 1])
                        : max($sequenceLastDay, $this->dayOffset($baseDate, $gameDays[count($gameDays) - 1]));
                    $placed = true;
                }
            }

            $previousSequenceLastDay = $sequenceLastDay;
        }

        $matchDays = collect($assignments)
            ->map(fn (array $assignment): string => substr($assignment['scheduledAt'], 0, 10))
            ->unique()
            ->sort()
            ->values();

        return [
            'assignments' => $assignments,
            'courts' => $courts,
            'slotMinutes' => $slotMinutes,
            'restMinutes' => $restMinutes,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate?->format('Y-m-d'),
            'weekdays' => $weekdays,
            'playoffWindow' => $playoffWindow,
            'summary' => [
                'games' => count($assignments),
                'matchDays' => $matchDays->count(),
                'firstDate' => $matchDays->first(),
                'lastDate' => $matchDays->last(),
                'seriesCount' => $units->filter(fn (array $unit): bool => count($unit['games']) > 1)->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, MatchModel>  $matches
     * @return \Illuminate\Support\Collection<int, array{anchor: MatchModel, stage: string, round: int, games: Collection<int, MatchModel>}>
     */
    protected function seriesUnits(Collection $matches): \Illuminate\Support\Collection
    {
        $anchors = $matches->whereNull('series_id')->filter(
            fn (MatchModel $match): bool => $match->status !== 'conditional',
        );
        $children = $matches->whereNotNull('series_id')->groupBy('series_id');

        return $anchors->map(function (MatchModel $anchor) use ($children): array {
            $games = new Collection([$anchor]);
            $seriesGames = $children->get($anchor->id);

            if ($seriesGames instanceof Collection) {
                $games = $games->merge($seriesGames);
            }

            $games = $games->sortBy('game_number')->values();

            return [
                'anchor' => $anchor,
                'stage' => (string) $anchor->stage,
                'round' => (int) $anchor->round_number,
                'games' => $games,
            ];
        })->values();
    }

    /**
     * @param  list<int>  $weekdays
     */
    protected function nextEligibleDay(Carbon $date, int $offset, array $weekdays): Carbon
    {
        $candidate = $date->copy()->startOfDay()->addDays($offset);

        while (! in_array((int) $candidate->isoWeekday(), $weekdays, true)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    /**
     * @param  list<int>  $weekdays
     * @return list<Carbon>
     */
    protected function eligibleGameDays(Carbon $start, int $count, array $weekdays): array
    {
        $days = [];
        $candidate = $this->nextEligibleDay($start, 0, $weekdays);

        while (count($days) < $count) {
            $days[] = $candidate->copy();
            $candidate = $this->nextEligibleDay($candidate, 1, $weekdays);
        }

        return $days;
    }

    /**
     * @param  list<int>  $weekdays
     */
    protected function eligibleDayCount(Carbon $start, Carbon $end, array $weekdays): int
    {
        $count = 0;
        $cursor = $start->copy()->startOfDay();
        $limit = $end->copy()->startOfDay();

        while ($cursor->lte($limit)) {
            if (in_array((int) $cursor->isoWeekday(), $weekdays, true)) {
                $count++;
            }

            $cursor->addDay();
        }

        return $count;
    }

    /**
     * @param  array{anchor: MatchModel, stage: string, round: int, games: Collection<int, MatchModel>}  $unit
     * @param  list<Carbon>  $gameDays
     * @param  Collection<int, Court>  $courts
     * @param  array<string, array<int, array<string, bool>>>  $laneSlots
     * @param  array<int, list<int>>  $teamTimes
     * @param  Collection<int, MatchSlot>  $existingSlots
     * @return array{court: Court, time: string}|null
     */
    protected function findLane(
        array $unit,
        array $gameDays,
        Collection $courts,
        int $baseHour,
        int $slotMinutes,
        int $restMinutes,
        array $laneSlots,
        array &$teamTimes,
        Collection $existingSlots,
    ): ?array {
        $step = $slotMinutes + $restMinutes;
        $minutes = $baseHour * 60;

        while ($minutes + $slotMinutes <= 23 * 60 + 59) {
            $time = sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);

            foreach ($courts as $court) {
                $free = true;

                foreach ($gameDays as $day) {
                    if (isset($laneSlots[$day->format('Y-m-d')][$court->id][$time])) {
                        $free = false;
                        break;
                    }

                    $start = $day->copy()->setTimeFromTimeString($time);

                    if ($this->existingSlotConflict($existingSlots, (int) $court->id, $start, $slotMinutes, $restMinutes) instanceof MatchSlot) {
                        $free = false;
                        break;
                    }

                    $timestamp = $start->getTimestamp();

                    foreach ([$unit['anchor']->home_team_id, $unit['anchor']->away_team_id] as $teamId) {
                        if ($teamId === null) {
                            continue;
                        }

                        foreach ($teamTimes[$teamId] ?? [] as $otherTimestamp) {
                            if (abs($otherTimestamp - $timestamp) < ($slotMinutes + $restMinutes) * 60) {
                                $free = false;
                                break 3;
                            }
                        }
                    }
                }

                if ($free) {
                    foreach ($gameDays as $day) {
                        $timestamp = $day->copy()->setTimeFromTimeString($time)->getTimestamp();

                        foreach ([$unit['anchor']->home_team_id, $unit['anchor']->away_team_id] as $teamId) {
                            if ($teamId !== null) {
                                $teamTimes[$teamId][] = $timestamp;
                            }
                        }
                    }

                    return ['court' => $court, 'time' => $time];
                }
            }

            $minutes += $step;
        }

        return null;
    }

    /**
     * @param  Collection<int, MatchSlot>  $slots
     */
    protected function existingSlotConflict(
        Collection $slots,
        int $courtId,
        Carbon $start,
        int $slotMinutes,
        int $restMinutes,
    ): ?MatchSlot {
        $end = $start->copy()->addMinutes($slotMinutes + $restMinutes);

        return $slots->first(function (MatchSlot $slot) use ($courtId, $start, $end, $slotMinutes, $restMinutes): bool {
            if ((int) $slot->court_id !== $courtId || ! $slot->start_at instanceof Carbon) {
                return false;
            }

            $otherStart = $slot->start_at->copy();
            $otherEnd = $otherStart->copy()->addMinutes($slotMinutes + $restMinutes);

            return $start->lt($otherEnd) && $otherStart->lt($end);
        });
    }

    protected function dayOffset(Carbon $base, Carbon $date): int
    {
        return (int) $base->copy()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);
    }

    protected function roundSequence(string $stage, int $round): int
    {
        return match ($stage) {
            'round_robin' => $round,
            'winners' => max(1, 2 * $round - 1),
            'losers' => $round + 1,
            'grand_final' => 10_000,
            'reset' => 10_001,
            default => 10_002 + $round,
        };
    }

    protected function roundStartHour(int $matchCount): int
    {
        return match (true) {
            $matchCount >= 16 => 8,
            $matchCount >= 8 => 9,
            $matchCount >= 4 => 13,
            $matchCount >= 2 => 15,
            default => 16,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(Tournament $tournament, array $options = []): int
    {
        return $this->publish($tournament, $this->plan($tournament, $options));
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public function publish(Tournament $tournament, array $plan, string $auditAction = 'schedule.publish'): int
    {
        $validation = $this->validatePlan($tournament, $plan);

        if (! $validation['valid']) {
            $message = $validation['errors'] === []
                ? 'The schedule plan is invalid.'
                : (string) reset($validation['errors']);

            throw new InvalidArgumentException($message);
        }

        return DB::transaction(function () use ($tournament, $plan, $auditAction): int {
            MatchSlot::where('tournament_id', $tournament->id)->delete();

            $dates = [];
            $matches = 0;

            foreach ($plan['assignments'] as $assignment) {
                $start = Carbon::parse($assignment['scheduledAt']);
                $dates[] = $start->format('Y-m-d');
                $matches++;

                MatchSlot::create([
                    'match_id' => (int) $assignment['matchId'],
                    'tournament_id' => $tournament->id,
                    'court_id' => (int) $assignment['courtId'],
                    'start_at' => $start,
                    'end_at' => $start->copy()->addMinutes((int) $plan['slotMinutes']),
                    'status' => 'published',
                    'version' => 1,
                    'published_at' => now(),
                ]);
            }

            $dates = array_values(array_unique($dates));
            sort($dates);
            $window = is_array($plan['playoffWindow'] ?? null) ? $plan['playoffWindow'] : null;

            app(AuditLogger::class)->log(
                $auditAction,
                'tournament',
                $tournament->id,
                null,
                [
                    'courts' => count($plan['courts']),
                    'slotMinutes' => (int) $plan['slotMinutes'],
                    'restMinutes' => (int) $plan['restMinutes'],
                    'matches' => $matches,
                    'weekdays' => $plan['weekdays'] ?? range(1, 7),
                    'endDate' => $plan['endDate'] ?? null,
                    'playoffWindowStart' => $window['startDate'] ?? null,
                    'playoffWindowEnd' => $window['endDate'] ?? null,
                    'playoffReservedDays' => $window['dayCount'] ?? 0,
                    'firstDate' => $dates[0] ?? null,
                    'lastDate' => $dates === [] ? null : $dates[array_key_last($dates)],
                ],
                $tournament->organization_id,
            );

            return $matches;
        });
    }

    /**
     * Publish a remaining-games plan without touching any published slot.
     *
     * @param  array<string, mixed>  $plan
     */
    public function publishRemaining(Tournament $tournament, array $plan): int
    {
        $validation = $this->validatePlan($tournament, $plan, true);

        if (! $validation['valid']) {
            $message = $validation['errors'] === []
                ? 'The schedule plan is invalid.'
                : (string) reset($validation['errors']);

            throw new InvalidArgumentException($message);
        }

        return DB::transaction(function () use ($tournament, $plan): int {
            $dates = [];
            $matches = 0;

            foreach ($plan['assignments'] as $assignment) {
                $matchId = (int) $assignment['matchId'];

                if (MatchSlot::query()->where('match_id', $matchId)->exists()) {
                    throw new InvalidArgumentException('One of these games already has a court and time. Rebuild the preview.');
                }

                $start = Carbon::parse($assignment['scheduledAt']);
                $dates[] = $start->format('Y-m-d');
                $matches++;

                MatchSlot::create([
                    'match_id' => $matchId,
                    'tournament_id' => $tournament->id,
                    'court_id' => (int) $assignment['courtId'],
                    'start_at' => $start,
                    'end_at' => $start->copy()->addMinutes((int) $plan['slotMinutes']),
                    'status' => 'published',
                    'version' => 1,
                    'published_at' => now(),
                ]);
            }

            $dates = array_values(array_unique($dates));
            sort($dates);
            $window = is_array($plan['playoffWindow'] ?? null) ? $plan['playoffWindow'] : null;

            app(AuditLogger::class)->log(
                'schedule.remaining',
                'tournament',
                $tournament->id,
                null,
                [
                    'courts' => count($plan['courts']),
                    'slotMinutes' => (int) $plan['slotMinutes'],
                    'restMinutes' => (int) $plan['restMinutes'],
                    'matches' => $matches,
                    'weekdays' => $plan['weekdays'] ?? range(1, 7),
                    'endDate' => $plan['endDate'] ?? null,
                    'playoffWindowStart' => $window['startDate'] ?? null,
                    'playoffWindowEnd' => $window['endDate'] ?? null,
                    'playoffReservedDays' => $window['dayCount'] ?? 0,
                    'firstDate' => $dates[0] ?? null,
                    'lastDate' => $dates === [] ? null : $dates[array_key_last($dates)],
                ],
                $tournament->organization_id,
            );

            return $matches;
        });
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{errors: array<int, string>, warnings: array<int, string>, valid: bool}
     */
    public function validatePlan(Tournament $tournament, array $plan, bool $remaining = false): array
    {
        $errors = [];
        $warnings = [];

        foreach (['assignments', 'courts', 'slotMinutes', 'restMinutes', 'startDate'] as $key) {
            if (! array_key_exists($key, $plan)) {
                $errors[0] = 'The schedule plan is missing its '.$key.'.';

                return ['errors' => $errors, 'warnings' => $warnings, 'valid' => false];
            }
        }

        if (! is_array($plan['assignments']) || (int) $plan['slotMinutes'] < 10 || (int) $plan['restMinutes'] < 0) {
            $errors[0] = 'The schedule plan has invalid timing values.';

            return ['errors' => $errors, 'warnings' => $warnings, 'valid' => false];
        }

        $courts = $this->courts($tournament)->keyBy('id');
        $schedulable = MatchModel::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', ['scheduled', 'conditional'])
            ->when($remaining, fn ($query) => $query->whereDoesntHave('matchSlots'))
            ->with('bracket:id,phase')
            ->get()
            ->reject(fn (MatchModel $match): bool => $match->isByeGame())
            ->filter(fn (MatchModel $match): bool => $match->status === 'scheduled' || $match->series_id !== null)
            ->keyBy('id');

        $startDate = Carbon::parse((string) $plan['startDate'])->startOfDay();
        $tournamentStart = $tournament->start_date?->copy()->startOfDay();
        $remainingStart = $remaining ? $this->remainingStartDate($tournament) : null;

        if ($remainingStart instanceof Carbon && $startDate->lt($remainingStart)) {
            $errors[0] = 'Remaining games must start on or after '.$remainingStart->format('M j, Y').', after the last published match day.';
        }

        $endDate = isset($plan['endDate']) && is_string($plan['endDate']) && $plan['endDate'] !== ''
            ? Carbon::parse($plan['endDate'])->startOfDay()
            : $tournament->end_date?->copy()->startOfDay();
        $weekdays = is_array($plan['weekdays'] ?? null) ? array_map('intval', $plan['weekdays']) : range(1, 7);
        $windowOptions = ['weekdays' => array_values($weekdays)];

        if ($endDate instanceof Carbon) {
            $windowOptions['endDate'] = $endDate->format('Y-m-d');
        }

        $playoffWindow = $this->playoffWindow($tournament, $windowOptions);
        $groupEndDate = ! $remaining && $playoffWindow !== null && $playoffWindow['groupEndDate'] !== null
            ? Carbon::parse($playoffWindow['groupEndDate'])->startOfDay()
            : null;
        $seen = [];
        $rows = [];

        foreach ($plan['assignments'] as $assignment) {
            $matchId = isset($assignment['matchId']) && is_numeric($assignment['matchId'])
                ? (int) $assignment['matchId']
                : 0;

            if ($matchId === 0 || ! $schedulable->has($matchId)) {
                $errors[$matchId] = 'This match does not belong to the tournament or cannot be scheduled.';

                continue;
            }

            if (isset($seen[$matchId])) {
                $errors[$matchId] = 'This match appears more than once in the plan.';

                continue;
            }

            $seen[$matchId] = true;
            $courtId = isset($assignment['courtId']) && is_numeric($assignment['courtId'])
                ? (int) $assignment['courtId']
                : 0;

            if (! $courts->has($courtId)) {
                $errors[$matchId] = 'Choose a valid court for this match.';

                continue;
            }

            try {
                $start = Carbon::parse((string) ($assignment['scheduledAt'] ?? ''));
            } catch (\Exception) {
                $errors[$matchId] = 'Enter a valid date and time for this match.';

                continue;
            }

            if ($startDate->isAfter($start)) {
                $errors[$matchId] = 'Start date must be within the tournament dates.';

                continue;
            }

            if ($tournamentStart instanceof Carbon && $start->lt($tournamentStart)) {
                $errors[$matchId] = 'Start date must be within the tournament dates.';

                continue;
            }

            if ($endDate instanceof Carbon && $start->gt($endDate->copy()->endOfDay())) {
                $errors[$matchId] = 'The tournament ends '.$endDate->format('M j, Y').'. Choose an earlier date.';

                continue;
            }

            $scheduledMatch = $schedulable->get($matchId);

            if (
                $groupEndDate instanceof Carbon
                && $playoffWindow !== null
                && $scheduledMatch instanceof MatchModel
                && $scheduledMatch->bracket?->phase === 'group'
                && $start->gt($groupEndDate->copy()->endOfDay())
            ) {
                $errors[$matchId] = sprintf(
                    'This game is after the group-stage deadline (%s). Days from %s–%s are reserved for the playoffs. Schedule it earlier.',
                    $groupEndDate->format('M j, Y'),
                    Carbon::parse($playoffWindow['startDate'])->format('M j, Y'),
                    Carbon::parse($playoffWindow['endDate'])->format('M j, Y'),
                );

                continue;
            }

            if (! in_array((int) $start->isoWeekday(), $weekdays, true)) {
                $warnings[$matchId] = 'This game is outside the preferred match days.';
            }

            $rows[] = [
                'matchId' => $matchId,
                'courtId' => $courtId,
                'start' => $start,
                'match' => $schedulable->get($matchId),
            ];
        }

        if (! $remaining) {
            foreach ($schedulable as $matchId => $match) {
                if (! isset($seen[(int) $matchId])) {
                    $errors[(int) $matchId] = 'This match is missing from the plan.';
                }
            }
        }

        $slotMinutes = (int) $plan['slotMinutes'];
        $restMinutes = (int) $plan['restMinutes'];

        foreach ($rows as $index => $row) {
            foreach (array_slice($rows, $index + 1) as $other) {
                if ($row['courtId'] !== $other['courtId']) {
                    continue;
                }

                $end = $row['start']->copy()->addMinutes($slotMinutes + $restMinutes);
                $otherEnd = $other['start']->copy()->addMinutes($slotMinutes + $restMinutes);

                if ($row['start']->lt($otherEnd) && $other['start']->lt($end)) {
                    $court = $courts->get($row['courtId']);
                    $message = ($court->name ?? 'Court').' is already booked at '.$other['start']->format('M j, g:i A').'. Pick another court or time.';
                    $errors[$row['matchId']] ??= $message;
                    $errors[$other['matchId']] ??= $message;
                }
            }
        }

        foreach ($rows as $row) {
            $teamIds = array_filter([
                $row['match']?->home_team_id,
                $row['match']?->away_team_id,
            ]);

            foreach ($rows as $other) {
                if ($row['matchId'] === $other['matchId']) {
                    continue;
                }

                $otherTeams = array_filter([
                    $other['match']?->home_team_id,
                    $other['match']?->away_team_id,
                ]);

                if (array_intersect($teamIds, $otherTeams) === []) {
                    continue;
                }

                if (abs($row['start']->getTimestamp() - $other['start']->getTimestamp()) < ($slotMinutes + $restMinutes) * 60) {
                    $match = $row['match'];
                    $team = $match instanceof MatchModel
                        ? ($match->home_team_id !== null ? $match->homeTeam : $match->awayTeam)
                        : null;
                    $teamName = $team->name;
                    $errors[$row['matchId']] ??= $teamName.' would play twice within '.($slotMinutes + $restMinutes).' minutes. Move one game.';
                }
            }
        }

        if ($remaining) {
            $publishedSlots = MatchSlot::query()
                ->where('tournament_id', $tournament->id)
                ->whereNotNull('start_at')
                ->get();

            foreach ($rows as $row) {
                $conflict = $this->existingSlotConflict(
                    $publishedSlots,
                    $row['courtId'],
                    $row['start'],
                    $slotMinutes,
                    $restMinutes,
                );

                if (! $conflict instanceof MatchSlot) {
                    continue;
                }

                $court = $courts->get($row['courtId']);
                $errors[$row['matchId']] ??= ($court->name ?? 'Court')
                    .' is already booked at '.($conflict->start_at?->format('M j, g:i A') ?? 'that time')
                    .'. Pick another court or time.';
            }

            foreach ($rows as $row) {
                $match = $row['match'];

                if (! $match instanceof MatchModel) {
                    continue;
                }

                $teamName = $this->teamRestConflict($tournament, $match, $row['start'], $slotMinutes, $restMinutes);

                if ($teamName !== null) {
                    $errors[$row['matchId']] ??= $teamName.' would play twice within '.($slotMinutes + $restMinutes).' minutes. Move one game.';
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'valid' => $errors === []];
    }

    /**
     * Put a newly activated series game on the series' court and tip-off lane
     * on the next eligible day after the previous game. Returns null when there
     * is no reference slot or no free day before the tournament ends.
     */
    public function scheduleSeriesGame(MatchModel $game, MatchModel $anchor): ?MatchSlot
    {
        $existing = $game->matchSlots()->first();

        if ($existing instanceof MatchSlot) {
            return $existing;
        }

        $tournament = $game->tournament;

        if (! $tournament instanceof Tournament) {
            return null;
        }

        $reference = $this->seriesReferenceSlot($anchor);

        if (! $reference instanceof MatchSlot || $reference->start_at === null) {
            return null;
        }

        $courtId = (int) $reference->court_id;
        $time = $reference->start_at->format('H:i:s');
        $slotMinutes = $this->slotMinutes($tournament);
        $restMinutes = $this->restMinutes($tournament);
        $weekdays = $this->plannedWeekdays($tournament) ?? $this->seriesWeekdays($anchor);

        if ($weekdays === []) {
            $weekdays = range(1, 7);
        }

        $day = $reference->start_at->copy()->startOfDay()->addDay();
        $endDate = $tournament->end_date?->copy()->startOfDay();

        while ($endDate === null || $day->lte($endDate)) {
            if (in_array((int) $day->isoWeekday(), $weekdays, true)) {
                $start = $day->copy()->setTimeFromTimeString($time);

                if (! $this->courtHasConflict($tournament, $courtId, $start, $slotMinutes, $restMinutes, $game->id)) {
                    $slot = MatchSlot::create([
                        'match_id' => $game->id,
                        'tournament_id' => $tournament->id,
                        'court_id' => $courtId,
                        'start_at' => $start,
                        'end_at' => $start->copy()->addMinutes($slotMinutes),
                        'status' => 'published',
                        'version' => 1,
                        'published_at' => now(),
                    ]);

                    app(AuditLogger::class)->log(
                        'schedule.series',
                        'match',
                        $game->id,
                        null,
                        [
                            'courtId' => $courtId,
                            'scheduledAt' => $start->toIso8601String(),
                            'seriesId' => $anchor->id,
                            'gameNumber' => $game->game_number,
                        ],
                        $tournament->organization_id,
                    );

                    return $slot;
                }
            }

            $day->addDay();
        }

        return null;
    }

    /**
     * @return list<int>|null
     */
    protected function plannedWeekdays(Tournament $tournament): ?array
    {
        $log = AuditLog::query()
            ->where('entity_type', 'tournament')
            ->where('entity_id', $tournament->id)
            ->whereIn('action', ['schedule.publish', 'schedule.rebuild', 'schedule.generate', 'schedule.remaining'])
            ->orderByDesc('id')
            ->first();

        $after = $log?->after_json;

        if (! is_array($after) || ! isset($after['weekdays']) || ! is_array($after['weekdays'])) {
            return null;
        }

        $weekdays = array_values(array_filter(
            array_map('intval', $after['weekdays']),
            fn (int $day): bool => $day >= 1 && $day <= 7,
        ));

        return $weekdays === [] ? null : $weekdays;
    }

    /**
     * @return list<int>
     */
    protected function seriesWeekdays(MatchModel $anchor): array
    {
        return MatchModel::query()
            ->where(function ($query) use ($anchor): void {
                $query->where('id', $anchor->id)->orWhere('series_id', $anchor->id);
            })
            ->with('matchSlots')
            ->get()
            ->flatMap(fn (MatchModel $seriesGame) => $seriesGame->matchSlots)
            ->filter(fn (MatchSlot $slot): bool => $slot->start_at !== null)
            ->map(fn (MatchSlot $slot): int => (int) $slot->start_at->isoWeekday())
            ->unique()
            ->values()
            ->all();
    }

    public function reschedule(MatchModel $match, ?int $courtId, ?string $scheduledAt): MatchSlot
    {
        $tournament = $match->tournament;

        if (! $tournament instanceof Tournament) {
            throw new InvalidArgumentException('This match is not attached to a tournament.');
        }

        $courtId ??= $match->matchSlots()->value('court_id');

        if ($courtId === null || ! $this->courts($tournament)->contains('id', $courtId)) {
            throw new InvalidArgumentException('Unknown court.');
        }

        if ($scheduledAt === null || trim($scheduledAt) === '') {
            throw new InvalidArgumentException('Choose a date and time.');
        }

        try {
            $start = Carbon::parse($scheduledAt);
        } catch (\Exception) {
            throw new InvalidArgumentException('Invalid date/time.');
        }

        $slotMinutes = $this->slotMinutes($tournament);
        $restMinutes = $this->restMinutes($tournament);

        if (! in_array($match->status, ['scheduled', 'conditional'], true)) {
            throw new InvalidArgumentException('Only scheduled or if-needed games can be moved.');
        }

        $tournamentStart = $tournament->start_date?->copy()->startOfDay();
        $tournamentEnd = $tournament->end_date?->copy()->startOfDay();

        if ($tournamentStart instanceof Carbon && $start->lt($tournamentStart)) {
            throw new InvalidArgumentException('Start date must be within the tournament dates.');
        }

        if ($tournamentEnd instanceof Carbon && $start->gt($tournamentEnd->copy()->endOfDay())) {
            throw new InvalidArgumentException('The tournament ends '.$tournamentEnd->format('M j, Y').'. Choose an earlier date.');
        }

        if ($match->series_id !== null || $match->best_of > 1) {
            $anchorId = $match->series_id !== null ? (int) $match->series_id : (int) $match->id;
            $seriesGames = MatchModel::query()
                ->where(function ($query) use ($anchorId): void {
                    $query->where('id', $anchorId)->orWhere('series_id', $anchorId);
                })
                ->with('matchSlots')
                ->orderBy('game_number')
                ->get()
                ->keyBy('game_number');

            $previous = $seriesGames->get((int) $match->game_number - 1);
            $next = $seriesGames->get((int) $match->game_number + 1);
            $previousSlot = $previous?->matchSlots->first();
            $nextSlot = $next?->matchSlots->first();

            if ($previousSlot?->start_at instanceof Carbon && $start->lte($previousSlot->start_at)) {
                throw new InvalidArgumentException('Game '.$match->game_number.' must start after Game '.$previous->game_number.' ('.$previousSlot->start_at->format('M j, g:i A').').');
            }

            if ($nextSlot?->start_at instanceof Carbon && $start->gte($nextSlot->start_at)) {
                throw new InvalidArgumentException('Game '.$match->game_number.' must start before Game '.$next->game_number.' ('.$nextSlot->start_at->format('M j, g:i A').').');
            }
        }

        $restConflict = $this->teamRestConflict($tournament, $match, $start, $slotMinutes, $restMinutes);

        if ($restConflict !== null) {
            throw new InvalidArgumentException($restConflict.' would play twice within '.($slotMinutes + $restMinutes).' minutes. Move one game.');
        }

        if ($this->courtHasConflict($tournament, $courtId, $start, $slotMinutes, $restMinutes, $match->id)) {
            throw new InvalidArgumentException('This court is already booked at that time.');
        }

        $slot = $match->matchSlots()->first();
        $previousCourtId = $slot?->court_id !== null ? (int) $slot->court_id : null;
        $previousStart = $slot?->start_at?->toIso8601String();

        if (! $slot instanceof MatchSlot) {
            $slot = new MatchSlot;
            $slot->match_id = $match->id;
            $slot->tournament_id = $tournament->id;
            $slot->status = 'published';
            $slot->version = 1;
            $slot->published_at = now();
        }

        $slot->court_id = $courtId;
        $slot->start_at = $start;
        $slot->end_at = $start->copy()->addMinutes($slotMinutes);
        $slot->save();

        app(AuditLogger::class)->log(
            'schedule.reschedule',
            'match',
            $match->id,
            [
                'courtId' => $previousCourtId,
                'scheduledAt' => $previousStart,
            ],
            [
                'courtId' => $courtId,
                'scheduledAt' => $start->toIso8601String(),
            ],
            $tournament->organization_id,
        );

        return $slot;
    }

    protected function seriesReferenceSlot(MatchModel $anchor): ?MatchSlot
    {
        return MatchModel::query()
            ->where(function ($query) use ($anchor): void {
                $query->where('id', $anchor->id)->orWhere('series_id', $anchor->id);
            })
            ->with(['matchSlots' => fn ($query) => $query->orderByDesc('start_at')])
            ->get()
            ->flatMap(fn (MatchModel $game) => $game->matchSlots)
            ->filter(fn (MatchSlot $slot): bool => $slot->start_at !== null)
            ->sortByDesc(fn (MatchSlot $slot): int => $slot->start_at->timestamp)
            ->first();
    }

    protected function courtHasConflict(
        Tournament $tournament,
        int $courtId,
        Carbon $start,
        int $slotMinutes,
        int $restMinutes,
        ?int $ignoreMatchId = null,
    ): bool {
        $end = $start->copy()->addMinutes($slotMinutes + $restMinutes);

        return MatchSlot::query()
            ->where('tournament_id', $tournament->id)
            ->where('court_id', $courtId)
            ->when($ignoreMatchId !== null, fn ($query) => $query->where('match_id', '!=', $ignoreMatchId))
            ->get()
            ->contains(function (MatchSlot $other) use ($start, $end, $slotMinutes, $restMinutes): bool {
                if ($other->start_at === null) {
                    return false;
                }

                $otherStart = $other->start_at->copy();
                $otherEnd = $otherStart->copy()->addMinutes($slotMinutes + $restMinutes);

                return $start->lt($otherEnd) && $otherStart->lt($end);
            });
    }

    protected function teamRestConflict(
        Tournament $tournament,
        MatchModel $match,
        Carbon $start,
        int $slotMinutes,
        int $restMinutes,
    ): ?string {
        $teamIds = array_values(array_filter([$match->home_team_id, $match->away_team_id]));

        if ($teamIds === []) {
            return null;
        }

        $conflict = MatchSlot::query()
            ->where('tournament_id', $tournament->id)
            ->where('match_id', '!=', $match->id)
            ->whereHas('match', function ($query) use ($teamIds): void {
                $query->whereIn('home_team_id', $teamIds)->orWhereIn('away_team_id', $teamIds);
            })
            ->with('match.homeTeam', 'match.awayTeam')
            ->get()
            ->first(function (MatchSlot $slot) use ($start, $slotMinutes, $restMinutes): bool {
                return $slot->start_at instanceof Carbon
                    && abs($slot->start_at->getTimestamp() - $start->getTimestamp()) < ($slotMinutes + $restMinutes) * 60;
            });

        if ($conflict === null) {
            return null;
        }

        $slot = $conflict;

        $conflictingTeams = array_values(array_filter([
            $slot->match?->home_team_id,
            $slot->match?->away_team_id,
        ]));
        $teamId = array_values(array_intersect($teamIds, $conflictingTeams))[0] ?? null;

        if ($teamId === null) {
            return 'A team';
        }

        $match = $slot->match;
        $team = $match instanceof MatchModel
            ? ($match->home_team_id === $teamId ? $match->homeTeam : $match->awayTeam)
            : null;

        return $team->name;
    }

    /**
     * @return Collection<int, Court>
     */
    public function courts(Tournament $tournament): Collection
    {
        $venueIds = MatchSlot::query()
            ->where('tournament_id', $tournament->id)
            ->with('court:id,venue_id')
            ->get()
            ->pluck('court.venue_id')
            ->filter()
            ->unique()
            ->values();

        if ($venueIds->isEmpty()) {
            $rules = $tournament->rules_json;
            $venueId = is_array($rules) && isset($rules['venue_id']) && is_numeric($rules['venue_id'])
                ? (int) $rules['venue_id']
                : null;

            if ($venueId !== null) {
                $venueIds = collect([$venueId]);
            }
        }

        if ($venueIds->isEmpty()) {
            $venueIds = Venue::query()
                ->where('organization_id', $tournament->organization_id)
                ->pluck('id');
        }

        return Court::query()
            ->whereIn('venue_id', $venueIds)
            ->orderBy('id')
            ->get();
    }

    public function startDate(Tournament $tournament): string
    {
        return $tournament->start_date?->format('Y-m-d') ?? now()->format('Y-m-d');
    }

    /**
     * The first day an incremental schedule may use: the day after the latest
     * published slot, so new games never jump ahead of an existing match.
     */
    public function remainingStartDate(Tournament $tournament): Carbon
    {
        $start = $tournament->start_date?->copy()->startOfDay() ?? Carbon::today();
        $lastSlot = MatchSlot::query()
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('start_at')
            ->max('start_at');

        if ($lastSlot === null) {
            return $start;
        }

        $dayAfter = Carbon::parse((string) $lastSlot)->startOfDay()->addDay();

        return $dayAfter->gt($start) ? $dayAfter : $start;
    }

    /**
     * @return array{
     *     days: int,
     *     rounds: list<array{label: string, bestOf: int, days: int}>
     * }|null
     */
    public function playoffFootprint(Tournament $tournament): ?array
    {
        if ($tournament->format() !== 'round-robin') {
            return null;
        }

        $rules = is_array($tournament->rules_json) ? $tournament->rules_json : [];
        $teams = isset($rules['playoff_teams']) && is_numeric($rules['playoff_teams'])
            ? (int) $rules['playoff_teams']
            : 0;

        if (! in_array($teams, [2, 4, 8, 16], true)) {
            return null;
        }

        /** @var list<array{0: string, 1: string|null}> $ladder */
        $ladder = match ($teams) {
            2 => [['Final', 'best_of_final']],
            4 => [['Semifinal', 'best_of_semifinal'], ['Final', 'best_of_final']],
            8 => [
                ['Quarterfinal', 'best_of_quarterfinal'],
                ['Semifinal', 'best_of_semifinal'],
                ['Final', 'best_of_final'],
            ],
            16 => [
                ['Round of 16', null],
                ['Quarterfinal', 'best_of_quarterfinal'],
                ['Semifinal', 'best_of_semifinal'],
                ['Final', 'best_of_final'],
            ],
            default => [],
        };

        if ($ladder === []) {
            return null;
        }

        $rounds = [];
        $days = 0;

        foreach ($ladder as [$label, $key]) {
            $bestOf = $key === null ? 1 : $this->bestOfValue($rules, $key);
            $rounds[] = ['label' => $label, 'bestOf' => $bestOf, 'days' => $bestOf];
            $days += $bestOf;
        }

        return [
            'days' => $days,
            'rounds' => $rounds,
        ];
    }

    /**
     * @param  array{endDate?: string, weekdays?: list<int>}  $options
     * @return array{
     *     startDate: string,
     *     endDate: string,
     *     groupEndDate: string|null,
     *     dates: list<string>,
     *     dayCount: int,
     *     rounds: list<array{label: string, bestOf: int, days: int}>
     * }|null
     */
    public function playoffWindow(Tournament $tournament, array $options = []): ?array
    {
        $footprint = $this->playoffFootprint($tournament);

        if ($footprint === null) {
            return null;
        }

        $tournamentStart = $tournament->start_date?->copy()->startOfDay();

        if (! $tournamentStart instanceof Carbon) {
            return null;
        }

        $tournamentEnd = $tournament->end_date?->copy()->startOfDay();
        $hasRequestedEnd = isset($options['endDate']) && is_string($options['endDate']) && $options['endDate'] !== '';

        if ($hasRequestedEnd) {
            $endDate = Carbon::parse($options['endDate'])->startOfDay();
        } elseif ($tournamentEnd instanceof Carbon) {
            $endDate = $tournamentEnd->copy();
        } else {
            return null;
        }

        if ($endDate->lt($tournamentStart)) {
            return null;
        }

        $weekdays = isset($options['weekdays']) && is_array($options['weekdays'])
            ? array_values(array_unique(array_map('intval', $options['weekdays'])))
            : range(1, 7);

        $weekdays = array_values(array_filter($weekdays, fn (int $day): bool => $day >= 1 && $day <= 7));

        if ($weekdays === []) {
            $weekdays = range(1, 7);
        }

        $dates = [];
        $cursor = $endDate->copy();

        while ($cursor->gte($tournamentStart) && count($dates) < $footprint['days']) {
            if (in_array((int) $cursor->isoWeekday(), $weekdays, true)) {
                $dates[] = $cursor->format('Y-m-d');
            }

            $cursor->subDay();
        }

        if (count($dates) < $footprint['days']) {
            return null;
        }

        $dates = array_reverse($dates);
        $windowStart = Carbon::parse($dates[0])->startOfDay();
        $groupEndDate = $windowStart->copy()->subDay();

        while ($groupEndDate->gte($tournamentStart) && ! in_array((int) $groupEndDate->isoWeekday(), $weekdays, true)) {
            $groupEndDate->subDay();
        }

        return [
            'startDate' => $dates[0],
            'endDate' => $dates[count($dates) - 1],
            'groupEndDate' => $groupEndDate->gte($tournamentStart) ? $groupEndDate->format('Y-m-d') : null,
            'dates' => $dates,
            'dayCount' => count($dates),
            'rounds' => $footprint['rounds'],
        ];
    }

    /**
     * @param  array<string, mixed>  $window
     * @return list<string>
     */
    public function playoffWindowOverlap(Tournament $tournament, array $window): array
    {
        $start = isset($window['startDate']) && is_string($window['startDate'])
            ? Carbon::parse($window['startDate'])->startOfDay()
            : null;
        $end = isset($window['endDate']) && is_string($window['endDate'])
            ? Carbon::parse($window['endDate'])->startOfDay()
            : null;

        if (! $start instanceof Carbon || ! $end instanceof Carbon) {
            return [];
        }

        return MatchSlot::query()
            ->where('tournament_id', $tournament->id)
            ->where('status', 'published')
            ->whereNotNull('start_at')
            ->whereHas('match.bracket', fn ($query) => $query->where('phase', 'group'))
            ->pluck('start_at')
            ->map(fn ($startAt): string => Carbon::parse((string) $startAt)->format('Y-m-d'))
            ->filter(fn (string $date): bool => $date >= $start->format('Y-m-d') && $date <= $end->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    protected function bestOfValue(array $rules, string $key): int
    {
        $value = $rules[$key] ?? 1;

        return is_numeric($value) && in_array((int) $value, [1, 3, 5, 7, 9], true)
            ? (int) $value
            : 1;
    }

    public function slotMinutes(Tournament $tournament): int
    {
        $rules = $tournament->rules_json;
        $value = is_array($rules) ? ($rules['slotMinutes'] ?? $rules['slot_minutes'] ?? null) : null;

        return is_numeric($value) && (int) $value >= 10 ? (int) $value : 30;
    }

    public function restMinutes(Tournament $tournament): int
    {
        $rules = $tournament->rules_json;
        $value = is_array($rules) ? ($rules['restMinutes'] ?? $rules['rest_minutes'] ?? null) : null;

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $rule = GameRule::query()->where('tournament_id', $tournament->id)->first();

        return $rule?->rest_minutes !== null ? (int) $rule->rest_minutes : 15;
    }
}
