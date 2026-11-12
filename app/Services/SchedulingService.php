<?php

namespace App\Services;

use App\Models\Court;
use App\Models\GameRule;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Tournament;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
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
     *     summary: array{games: int, matchDays: int, firstDate: string|null, lastDate: string|null, seriesCount: int}
     * }
     */
    public function plan(Tournament $tournament, array $options = []): array
    {
        $matches = MatchModel::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', ['scheduled', 'conditional'])
            ->with('bracket:id,format')
            ->get()
            ->reject(fn (MatchModel $match): bool => $match->isByeGame())
            ->values();

        if ($matches->isEmpty()) {
            throw new InvalidArgumentException('Generate a bracket before scheduling.');
        }

        $hasResults = MatchModel::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('status', ['final', 'forfeit', 'in_progress'])
            ->get()
            ->contains(fn (MatchModel $match): bool => ! $match->isByeGame());

        if ($hasResults) {
            throw new InvalidArgumentException('The schedule is locked because results exist.');
        }

        $courts = $this->courts($tournament);

        if ($courts->isEmpty()) {
            throw new InvalidArgumentException('Choose at least one court for this tournament.');
        }

        $tournamentStart = $tournament->start_date?->copy()->startOfDay();
        $tournamentEnd = $tournament->end_date?->copy()->startOfDay();

        $startDate = isset($options['startDate']) && is_string($options['startDate']) && $options['startDate'] !== ''
            ? Carbon::parse($options['startDate'])->startOfDay()
            : ($tournamentStart ?? Carbon::today());

        if ($tournamentStart instanceof Carbon && $startDate->lt($tournamentStart)) {
            throw new InvalidArgumentException('Start date must be within the tournament dates.');
        }

        $endDate = null;

        if (isset($options['endDate']) && is_string($options['endDate']) && $options['endDate'] !== '') {
            $endDate = Carbon::parse($options['endDate'])->startOfDay();
        } elseif ($tournamentEnd instanceof Carbon) {
            $endDate = $tournamentEnd->copy();
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

        $baseDate = $startDate->copy();
        $assignments = [];
        $laneSlots = [];
        $teamTimes = [];
        $previousSequenceLastDay = null;

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

                    if ($endDate instanceof Carbon && $projectedLast->gt($endDate)) {
                        throw new InvalidArgumentException(sprintf(
                            'The tournament ends %s, but this plan needs through %s. Add weekdays, shorten slots, or extend the end date.',
                            $endDate->format('M j, Y'),
                            $projectedLast->format('M j, Y'),
                        ));
                    }

                    $placement = $this->findLane($unit, $gameDays, $courts, $baseHour, $slotMinutes, $restMinutes, $laneSlots, $teamTimes);

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
            $games = collect([$anchor])
                ->merge($children->get($anchor->id, collect()))
                ->sortBy('game_number')
                ->values();

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
     * @param  array{anchor: MatchModel, stage: string, round: int, games: Collection<int, MatchModel>}  $unit
     * @param  list<Carbon>  $gameDays
     * @param  Collection<int, Court>  $courts
     * @param  array<string, array<int, array<string, bool>>>  $laneSlots
     * @param  array<int, list<int>>  $teamTimes
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
        $plan = $this->plan($tournament, $options);
        $matchIds = $tournament->matches()->pluck('id');

        if ($matchIds->isNotEmpty()) {
            MatchSlot::query()->whereIn('match_id', $matchIds)->delete();
        }

        foreach ($plan['assignments'] as $assignment) {
            $start = Carbon::parse($assignment['scheduledAt']);

            MatchSlot::create([
                'match_id' => $assignment['matchId'],
                'tournament_id' => $tournament->id,
                'court_id' => $assignment['courtId'],
                'start_at' => $start,
                'end_at' => $start->copy()->addMinutes($plan['slotMinutes']),
                'status' => 'published',
                'version' => 1,
                'published_at' => now(),
            ]);
        }

        app(AuditLogger::class)->log(
            'schedule.generate',
            'tournament',
            $tournament->id,
            null,
            [
                'courts' => $plan['courts']->count(),
                'slotMinutes' => $plan['slotMinutes'],
                'restMinutes' => $plan['restMinutes'],
                'matches' => count($plan['assignments']),
            ],
            $tournament->organization_id,
        );

        return count($plan['assignments']);
    }

    /**
     * Put a newly activated series game on the same court as the previous game,
     * after the tournament rest period. Returns null when there is no previous
     * slot or no free time left on that court that day.
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
        $courtId = $reference?->court_id !== null ? (int) $reference->court_id : null;

        if (! $reference instanceof MatchSlot || $courtId === null) {
            return null;
        }

        $slotMinutes = $this->slotMinutes($tournament);
        $restMinutes = $this->restMinutes($tournament);
        $referenceEnd = $reference->end_at?->copy()
            ?? $reference->start_at?->copy()->addMinutes($slotMinutes);

        if (! $referenceEnd instanceof Carbon) {
            return null;
        }

        $start = $referenceEnd->addMinutes($restMinutes);
        $latestStart = $start->copy()->endOfDay()->subMinutes($slotMinutes);

        while ($start->lte($latestStart)) {
            if (! $this->courtHasConflict($tournament, $courtId, $start, $slotMinutes, $restMinutes)) {
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

            $start->addMinutes($slotMinutes);
        }

        return null;
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
