# Bracket Series Actions and Schedule UX Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement the approved design in `docs/superpowers/specs/2026-11-12-bracket-series-schedule-ux-design.md`: per-game series actions, a game chooser, exact per-game box scores, random bracket regeneration, pre-scheduled series games on daily lanes, an editable schedule preview with exact publish, a rebuild wizard, slot release, and role-safe controls.

**Architecture:** Keep the anchor-match series model and the existing Livewire component boundaries. Push scheduling and series lifecycle rules into `SchedulingService` and `BracketService`; keep Livewire components responsible for authorization, state, validation display, and navigation. The schedule preview is Livewire state only — no new table. Publish deletes and recreates `match_slots` from the exact preview plan.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4, Blade, Bootstrap SCSS, Pest 4, SQLite for tests.

**Spec:** `docs/superpowers/specs/2026-11-12-bracket-series-schedule-ux-design.md`

**Conventions:** Work from the repository root. All application commands run in `src`. Follow existing Pest style (`it(...)`, helper functions, `Livewire::actingAs`). Do not touch `.obsidian/` or `.kilo/`. Commit only the files listed in each task.

---

## Pre-flight

1. Confirm the tree and note unrelated changes:

```powershell
git status --short
```

Expected: only `.obsidian/graph.json` and untracked `.kilo/` are unrelated. Leave them alone.

2. Read the spec and the core files before starting:

```powershell
Get-Content docs/superpowers/specs/2026-11-12-bracket-series-schedule-ux-design.md
Get-Content src/app/Services/SchedulingService.php
Get-Content src/app/Services/BracketService.php
Get-Content src/app/Livewire/Organizer/ScheduleShow.php
```

3. Run the current suite once to establish a baseline:

```powershell
cd src
php artisan test
```

Expected: the existing suite passes before any change.

4. Confirm the demo double-elimination window before Task 8:

```powershell
Select-String -Path src/database/seeders/BracketDemoSeeder.php -Pattern "demo-double-elimination" -Context 0,8
```

Expected: `start => '2026-12-08'`, `end => '2026-12-16'`; Task 8 extends the end date.

---

## Task 1: Pre-schedule every series game on a daily lane

**Files:**
- Modify: `src/app/Services/SchedulingService.php`
- Test: `src/tests/Unit/Services/SchedulingServiceTest.php`

**Why:** `plan()` currently selects only `status = 'scheduled'`, so series Game 2+ (`conditional`) never get slots. Replace that with series units placed on consecutive eligible days, one game per day, on one court and one tip-off time.

### Step 1: Write the failing tests

Replace the first three tests in `src/tests/Unit/Services/SchedulingServiceTest.php` with the tests below, and add the imports/helpers at the top. Keep the round-robin test.

```php
use App\Models\Court;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Tournament;
use App\Services\BracketService;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function schedulingTwoTeamTournament(string $start = '2026-07-10', string $end = '2026-07-18'): Tournament
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    Registration::where('tournament_id', $tournament->id)
        ->where('status', 'approved')
        ->orderBy('seed')
        ->get()
        ->slice(2)
        ->each(fn (Registration $registration) => $registration->update(['status' => 'pending']));

    $tournament->update([
        'start_date' => $start,
        'end_date' => $end,
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'slots' => 2,
            'best_of_final' => 3,
        ]),
    ]);

    return $tournament->fresh();
}

function schedulingSeriesGames(Tournament $tournament)
{
    $anchor = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->firstOrFail();

    return MatchModel::where(function ($query) use ($anchor): void {
        $query->where('id', $anchor->id)->orWhere('series_id', $anchor->id);
    })->orderBy('game_number')->get();
}

it('pre-schedules every series game one per eligible day on one court and tip-off', function () {
    $tournament = schedulingTwoTeamTournament();
    (new BracketService)->generate($tournament, 'single-elimination');

    $games = schedulingSeriesGames($tournament);
    $plan = (new SchedulingService)->plan($tournament);
    $rows = collect($plan['assignments'])->keyBy('matchId');

    expect($games)->toHaveCount(3)
        ->and($rows)->toHaveCount(3)
        ->and($games->every(fn (MatchModel $game): bool => $rows->has($game->id)))->toBeTrue();

    $starts = $games->map(fn (MatchModel $game): Carbon => Carbon::parse($rows[$game->id]['scheduledAt']));

    expect($starts->map(fn (Carbon $start): string => $start->format('H:i:s'))->unique())
        ->toHaveCount(1)
        ->and($starts->map(fn (Carbon $start): string => $start->format('Y-m-d'))->unique())
        ->toHaveCount(3)
        ->and(collect($plan['assignments'])->pluck('courtId')->unique())
        ->toHaveCount(1);
});

it('skips excluded weekdays and resumes the series on the next eligible day', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-24');
    (new BracketService)->generate($tournament, 'single-elimination');

    $games = schedulingSeriesGames($tournament);
    $plan = (new SchedulingService)->plan($tournament, ['weekdays' => [5, 6]]);
    $rows = collect($plan['assignments'])->keyBy('matchId');
    $dates = $games->map(fn (MatchModel $game): string => substr($rows[$game->id]['scheduledAt'], 0, 10));

    expect($dates->values()->all())->toBe(['2026-07-10', '2026-07-11', '2026-07-17']);
});

it('fails the plan with a projected end date and writes nothing', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-11');
    (new BracketService)->generate($tournament, 'single-elimination');

    $before = App\Models\MatchSlot::where('tournament_id', $tournament->id)->count();

    expect(fn () => (new SchedulingService)->plan($tournament))
        ->toThrow(InvalidArgumentException::class, 'The tournament ends Jul 11, 2026, but this plan needs through Jul 12, 2026.');

    expect(App\Models\MatchSlot::where('tournament_id', $tournament->id)->count())->toBe($before);
});

it('starts the next round after the last game of the previous round', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-31',
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'slots' => 8,
            'best_of_semifinal' => 3,
            'best_of_final' => 3,
        ]),
    ]);
    $tournament = $tournament->fresh();

    (new BracketService)->generate($tournament, 'single-elimination');

    $plan = (new SchedulingService)->plan($tournament);
    $rows = collect($plan['assignments'])->keyBy('matchId');
    $semifinalAnchors = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->get();
    $finalAnchor = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->whereIn('stage', ['winners'])
        ->whereRaw('round_number = (select max(round_number) from matches where tournament_id = ? and series_id is null)', [$tournament->id])
        ->firstOrFail();

    $semifinalGames = MatchModel::whereIn('series_id', $semifinalAnchors->pluck('id'))
        ->orWhereIn('id', $semifinalAnchors->pluck('id'))
        ->orderBy('game_number')
        ->get();
    $finalGames = MatchModel::where(function ($query) use ($finalAnchor): void {
        $query->where('id', $finalAnchor->id)->orWhere('series_id', $finalAnchor->id);
    })->orderBy('game_number')->get();

    $lastSemifinal = $semifinalGames
        ->map(fn (MatchModel $game): Carbon => Carbon::parse($rows[$game->id]['scheduledAt']))
        ->max();
    $firstFinal = Carbon::parse($rows[$finalGames->first()->id]['scheduledAt']);

    expect($firstFinal->greaterThan($lastSemifinal))->toBeTrue()
        ->and($semifinalAnchors->count())->toBe(2);

    $semifinalRows = collect($plan['assignments'])
        ->filter(fn (array $row): bool => $semifinalGames->contains('id', $row['matchId']))
        ->keyBy('matchId');

    $dayOne = $semifinalAnchors->map(
        fn (MatchModel $anchor): array => $semifinalRows[$anchor->id],
    );

    expect($dayOne->pluck('courtId')->unique())->toHaveCount(2)
        ->and($dayOne->map(fn (array $row): string => substr($row['scheduledAt'], 11))->unique())
        ->toHaveCount(1);
});
```

### Step 2: Run the tests to confirm the failure

```powershell
cd src
php artisan test --filter="pre-schedules every series game|skips excluded weekdays|fails the plan with a projected end date|starts the next round"
```

Expected: FAIL. The first test finds one assignment instead of three; the weekday and end-date tests fail because conditional children are ignored.

### Step 3: Implement series units and lanes in `SchedulingService`

Replace `plan()` and add the helpers below. Keep the public shape and append the new keys. Use copies of `Carbon` dates; never mutate `$baseDate` in a loop.

```php
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
 *     summary: array{games: int, matchDays: int, firstDate: string|null, lastDate: string|null, seriesCount: int}
 * }
 */
public function plan(Tournament $tournament, array $options = []): array
{
    $matches = MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->whereIn('status', ['scheduled', 'conditional'])
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
        ->map(function (Collection $group, string $key): array {
            [$stage, $round] = explode('|', $key);

            return [
                'stage' => $stage,
                'round' => (int) $round,
                'sequence' => $this->roundSequence($stage, (int) $round),
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
    $lastDate = null;

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
                    $lastDate = $lastDate === null || $start->gt($lastDate) ? $start->copy() : $lastDate;
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
 * @return Collection<int, array{anchor: MatchModel, stage: string, round: int, games: Collection<int, MatchModel>}>
 */
protected function seriesUnits(Collection $matches): Collection
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
```

Notes for the implementer:
- `findLane()` is deliberately limited to one tip-off and one court per series; do not fall back to a different time for Game 2.
- The team-rest check uses the unit anchor's teams. Series children always carry the same teams once known; unknown teams skip the check.
- `nextEligibleDay($candidate, 0, ...)` must not return a day before `$candidate`.
- Keep `roundSequence()` unchanged.

### Step 4: Run the tests

```powershell
cd src
php artisan test --filter="pre-schedules every series game|skips excluded weekdays|fails the plan with a projected end date|starts the next round|orders round-robin matchdays"
```

Expected: PASS. If the end-date message differs by one day, inspect the projected series Game 3 date and fix the candidate-day calculation, not the assertion.

### Step 5: Commit

```powershell
git add src/app/Services/SchedulingService.php src/tests/Unit/Services/SchedulingServiceTest.php
git commit -m "feat(schedule): pre-schedule series games on daily lanes"
```

---

## Task 2: Publish the exact preview, validate it, and guard per-game moves

**Files:**
- Modify: `src/app/Services/SchedulingService.php`
- Test: `src/tests/Unit/Services/SchedulingServiceTest.php`

**Why:** `publishSchedule()` currently calls `generate()` again and discards the preview. Add `publish()`, `validatePlan()`, and the schedule-window/series-order checks used by both publish and per-game moves.

### Step 1: Write the failing tests

Append these tests to `src/tests/Unit/Services/SchedulingServiceTest.php`. Add `use App\Models\MatchSlot;` to the imports.

```php
function schedulingCourtForPlan(Tournament $tournament, int $excludeId): Court
{
    return app(SchedulingService::class)
        ->courts($tournament)
        ->first(fn (Court $court): bool => (int) $court->id !== $excludeId)
        ?? throw new RuntimeException('This test needs two courts.');
}

it('publishes the exact preview plan, including an edited court', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-24');
    (new BracketService)->generate($tournament, 'single-elimination');

    $service = new SchedulingService;
    $plan = $service->plan($tournament);
    $edited = $plan['assignments'][1];
    $otherCourt = schedulingCourtForPlan($tournament, (int) $edited['courtId']);
    $plan['assignments'][1]['courtId'] = (int) $otherCourt->id;

    $count = $service->publish($tournament, $plan);

    $slot = MatchSlot::where('match_id', $edited['matchId'])->firstOrFail();

    expect($count)->toBe(3)
        ->and(MatchSlot::where('tournament_id', $tournament->id)->count())->toBe(3)
        ->and((int) $slot->court_id)->toBe((int) $otherCourt->id)
        ->and($slot->start_at?->format('Y-m-d H:i:s'))->toBe($edited['scheduledAt']);
});

it('rejects a plan row outside the tournament window', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-24');
    (new BracketService)->generate($tournament, 'single-elimination');

    $service = new SchedulingService;
    $plan = $service->plan($tournament);
    $matchId = (int) $plan['assignments'][0]['matchId'];
    $plan['assignments'][0]['scheduledAt'] = '2026-07-25 10:00:00';

    $result = $service->validatePlan($tournament, $plan);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey($matchId);
});

it('warns but does not block a manual weekday override', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-24');
    (new BracketService)->generate($tournament, 'single-elimination');

    $service = new SchedulingService;
    $plan = $service->plan($tournament, ['weekdays' => [5, 6]]);
    $matchId = (int) $plan['assignments'][0]['matchId'];
    $plan['assignments'][0]['scheduledAt'] = '2026-07-12 15:00:00';

    $result = $service->validatePlan($tournament, $plan);

    expect($result['warnings'])->toHaveKey($matchId)
        ->and($result['errors'])->not->toHaveKey($matchId);
});

it('rejects a per-game move that breaks series order or the end date', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-18');
    (new BracketService)->generate($tournament, 'single-elimination');

    $service = new SchedulingService;
    $service->generate($tournament);
    $games = schedulingSeriesGames($tournament);
    $game1 = $games->firstWhere('game_number', 1);
    $game2 = $games->firstWhere('game_number', 2);
    $game3 = $games->firstWhere('game_number', 3);
    $game1Start = $game1->matchSlots()->firstOrFail()->start_at;

    expect(fn () => $service->reschedule($game2, null, $game1Start->format('Y-m-d H:i:s')))
        ->toThrow(InvalidArgumentException::class, 'Game 2 must start after Game 1');

    expect(fn () => $service->reschedule($game3, null, '2026-07-19 15:00:00'))
        ->toThrow(InvalidArgumentException::class, 'The tournament ends Jul 18, 2026. Choose an earlier date.');
});

it('refuses to move a game that is already final', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-18');
    (new BracketService)->generate($tournament, 'single-elimination');

    $service = new SchedulingService;
    $service->generate($tournament);
    $game1 = schedulingSeriesGames($tournament)->firstWhere('game_number', 1);
    $game1->update(['status' => 'final']);

    expect(fn () => $service->reschedule($game1, null, '2026-07-11 15:00:00'))
        ->toThrow(InvalidArgumentException::class, 'Only scheduled or if-needed games can be moved.');
});
```

### Step 2: Run the tests to confirm the failure

```powershell
cd src
php artisan test --filter="publishes the exact preview|rejects a plan row|warns but does not block|rejects a per-game move|refuses to move a game"
```

Expected: FAIL. `publish()` and `validatePlan()` do not exist; `reschedule()` still accepts a final game and ignores series order/end dates.

### Step 3: Implement publish, validation, and move guards

Add `use Illuminate\Support\Facades\DB;` at the top of `SchedulingService.php`, then add these methods. Keep `generate()` as a thin wrapper.

```php
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

        foreach ($plan['assignments'] as $assignment) {
            $start = Carbon::parse($assignment['scheduledAt']);

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

        $assignments = collect($plan['assignments']);
        $dates = $assignments->map(fn (array $row): string => substr($row['scheduledAt'], 0, 10))->sort()->values();

        app(AuditLogger::class)->log(
            $auditAction,
            'tournament',
            $tournament->id,
            null,
            [
                'courts' => count($plan['courts']),
                'slotMinutes' => (int) $plan['slotMinutes'],
                'restMinutes' => (int) $plan['restMinutes'],
                'matches' => $assignments->count(),
                'weekdays' => $plan['weekdays'] ?? range(1, 7),
                'endDate' => $plan['endDate'] ?? null,
                'firstDate' => $dates->first(),
                'lastDate' => $dates->last(),
            ],
            $tournament->organization_id,
        );

        return $assignments->count();
    });
}

/**
 * @param  array<string, mixed>  $plan
 * @return array{errors: array<int, string>, warnings: array<int, string>, valid: bool}
 */
public function validatePlan(Tournament $tournament, array $plan): array
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
        ->get()
        ->reject(fn (MatchModel $match): bool => $match->isByeGame())
        ->filter(fn (MatchModel $match): bool => $match->status === 'scheduled' || $match->series_id !== null)
        ->keyBy('id');

    $startDate = Carbon::parse((string) $plan['startDate'])->startOfDay();
    $tournamentStart = $tournament->start_date?->copy()->startOfDay();
    $endDate = isset($plan['endDate']) && is_string($plan['endDate']) && $plan['endDate'] !== ''
        ? Carbon::parse($plan['endDate'])->startOfDay()
        : $tournament->end_date?->copy()->startOfDay();
    $weekdays = is_array($plan['weekdays'] ?? null) ? array_map('intval', $plan['weekdays']) : range(1, 7);
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

    foreach ($schedulable as $matchId => $match) {
        if (! isset($seen[(int) $matchId])) {
            $errors[(int) $matchId] = 'This match is missing from the plan.';
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
                $message = ($court?->name ?? 'Court').' is already booked at '.$other['start']->format('M j, g:i A').'. Pick another court or time.';
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
                $team = $row['match']?->homeTeam?->name ?? 'A team';
                $errors[$row['matchId']] ??= $team.' would play twice within '.($slotMinutes + $restMinutes).' minutes. Move one game.';
            }
        }
    }

    return ['errors' => $errors, 'warnings' => $warnings, 'valid' => $errors === []];
}
```

Update `reschedule()` with these guards after `$start`, `$slotMinutes`, and `$restMinutes` are computed (currently lines 320-321) and before the court conflict check (line 323):

```php
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
```

Add the helper:

```php
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

    return $slot->match?->home_team_id === $teamId
        ? ($slot->match?->homeTeam?->name ?? 'A team')
        : ($slot->match?->awayTeam?->name ?? 'A team');
}
```

### Step 4: Run the tests

```powershell
cd src
php artisan test --filter="publishes the exact preview|rejects a plan row|warns but does not block|rejects a per-game move|refuses to move a game|auto-schedules the next series game"
```

Expected: PASS. The last existing test may need its assertion changed in Task 3 because scheduling now happens up front; if it fails here, stop and fix `plan()` rather than deleting the test.

### Step 5: Commit

```powershell
git add src/app/Services/SchedulingService.php src/tests/Unit/Services/SchedulingServiceTest.php
git commit -m "feat(schedule): publish exact plans and validate moves"
```

---

## Task 3: Shuffle regeneration, release cancelled slots, and reactivate after correction

**Files:**
- Modify: `src/app/Services/BracketService.php`
- Modify: `src/app/Services/SchedulingService.php` (`scheduleSeriesGame()`)
- Test: `src/tests/Unit/Services/BracketServiceTest.php`
- Test: `src/tests/Unit/Services/Bracket/SeriesLifecycleTest.php`

**Why:** Regenerate must shuffle approved teams and clear slots; a decided series must release unused slots; correcting a series back to undecided must revive the next game on the same lane when room exists.

### Step 1: Write the failing tests

Append to `src/tests/Unit/Services/BracketServiceTest.php`. Add `use App\Models\MatchSlot;` and `use App\Services\SchedulingService;` to the imports.

```php
it('regenerates with shuffled teams, random seeding, and no published slots', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $service = new BracketService;

    $service->generate($tournament, 'single-elimination');
    $firstBracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))->firstOrFail();
    $before = MatchModel::where('bracket_id', $firstBracket->id)
        ->where('round_number', 1)
        ->orderBy('match_number')
        ->get()
        ->flatMap(fn (MatchModel $match): array => [$match->home_team_id, $match->away_team_id])
        ->filter()
        ->map(fn ($id): int => (int) $id)
        ->values()
        ->all();

    (new SchedulingService)->generate($tournament);
    expect(MatchSlot::where('tournament_id', $tournament->id)->count())->toBeGreaterThan(0);

    $service->generate($tournament, 'single-elimination', true);

    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))->firstOrFail();
    $after = MatchModel::where('bracket_id', $bracket->id)
        ->where('round_number', 1)
        ->orderBy('match_number')
        ->get()
        ->flatMap(fn (MatchModel $match): array => [$match->home_team_id, $match->away_team_id])
        ->filter()
        ->map(fn ($id): int => (int) $id)
        ->sort()
        ->values()
        ->all();

    sort($before);

    $audit = AuditLog::where('action', 'bracket.generate')->orderByDesc('id')->firstOrFail();

    expect($bracket->seeding_method)->toBe('random')
        ->and($after)->toBe($before)
        ->and(MatchSlot::where('tournament_id', $tournament->id)->count())->toBe(0)
        ->and($audit->after_json['shuffled'] ?? null)->toBeTrue()
        ->and($audit->after_json['seedingMethod'] ?? null)->toBe('random');
});
```

Append to `src/tests/Unit/Services/Bracket/SeriesLifecycleTest.php`. Add `use App\Models\MatchSlot;` and `use App\Services\SchedulingService;` to its imports.

```php
it('releases the unused game slot when a series ends 2-0 and revives it after a correction', function () {
    $tournament = seriesTournament(['best_of_final' => 3]);
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $scheduler = new SchedulingService;
    $scheduler->generate($tournament);

    $bracket = seriesBracket($tournament);
    $final = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();
    $teams = $tournament->registrations()
        ->where('status', 'approved')
        ->orderBy('seed')
        ->pluck('team_id')
        ->take(2)
        ->values();

    $final->home_team_id = (int) $teams[0];
    $final->away_team_id = (int) $teams[1];
    $final->save();
    $service->syncSeriesTeams($final);

    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    $game3 = MatchModel::where('series_id', $final->id)->where('game_number', 3)->firstOrFail();
    $game3Slot = $game3->matchSlots()->firstOrFail();

    $service->recordScore($final->fresh(), 80, 70);
    $service->recordScore($game2->fresh(), 80, 70);

    expect($game3->fresh()->status)->toBe('cancelled')
        ->and(MatchSlot::whereKey($game3Slot->id)->exists())->toBeFalse()
        ->and($game2->fresh()->matchSlots()->exists())->toBeTrue();

    $service->correctScore($game2->fresh(), 70, 80, 'Referee review changed the winner.');

    $game3->refresh();

    expect($game3->status)->toBe('scheduled')
        ->and($game3->matchSlots()->first())->not->toBeNull()
        ->and((int) $game3->matchSlots()->first()->court_id)->toBe((int) $game3Slot->court_id)
        ->and($game3->matchSlots()->first()->start_at?->format('Y-m-d'))->toBe($game3Slot->start_at?->format('Y-m-d'));
});
```

### Step 2: Run the tests to confirm the failure

```powershell
cd src
php artisan test --filter="regenerates with shuffled teams|releases the unused game slot"
```

Expected: FAIL. `generate()` has no `$shuffle` parameter; the audit lacks `shuffled`; the cancelled Game 3 slot still exists; correction leaves Game 3 cancelled.

### Step 3: Implement the BracketService changes

Update the signature and the ordering block in `generate()`:

```php
public function generate(
    Tournament $tournament,
    string $format = 'single-elimination',
    bool $shuffle = false,
): array {
    // ... existing validation unchanged ...

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
            // ... existing fields ...
            'seeding_method' => $shuffle ? 'random' : 'manual',
            // ...
        ]);

        // ... builders and persistPlan unchanged ...

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
```

Add the slot-release helper and call it from all three cancellation paths:

```php
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

public function cancelRemainingSeriesGames(MatchModel $anchor): void
{
    $childIds = MatchModel::where('series_id', $anchor->id)
        ->whereIn('status', ['conditional', 'scheduled'])
        ->pluck('id');

    $this->releaseSlots($childIds);

    MatchModel::whereIn('id', $childIds)->update(['status' => 'cancelled']);
}
```

In `applyBye()`, replace the inline update with:

```php
if ($match->best_of > 1) {
    $childIds = MatchModel::where('series_id', $match->id)
        ->whereIn('status', ['conditional', 'scheduled'])
        ->pluck('id');

    $this->releaseSlots($childIds);
    MatchModel::whereIn('id', $childIds)->update(['status' => 'cancelled']);
    $match->best_of = 1;
}
```

In `cancelMatch()`, do the same before nulling the anchor fields.

Update `activateNextSeriesGame()` to revive a cancelled child when the series is undecided again:

```php
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
```

### Step 4: Rewrite `scheduleSeriesGame()` for the next eligible day

Replace the same-day search in `SchedulingService::scheduleSeriesGame()` with a next-day search that reuses the series court and tip-off time:

```php
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

    if (! $reference instanceof MatchSlot || $reference->start_at === null || $reference->court_id === null) {
        return null;
    }

    $courtId = (int) $reference->court_id;
    $time = $reference->start_at->format('H:i:s');
    $slotMinutes = $this->slotMinutes($tournament);
    $restMinutes = $this->restMinutes($tournament);
    $weekdays = MatchModel::query()
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
```

The old `seriesReferenceSlot()` already finds the latest series slot; keep it. This makes activation reuse the pre-scheduled slot during normal play and only recreate it after a correction releases it.

### Step 5: Surface the correction warning

In `BracketShow::performCorrection()`, after `correctScore()` succeeds, check whether the revived active game still has no slot:

```php
$corrected = $match->fresh();
$anchor = $corrected instanceof MatchModel && $corrected->series_id !== null
    ? $corrected->seriesAnchor
    : $corrected;
$active = $anchor instanceof MatchModel && $anchor->best_of > 1
    ? $anchor->activeGame()
    : null;

if ($active instanceof MatchModel && $active->status === 'scheduled' && ! $active->matchSlots()->exists()) {
    $this->toast(
        'Game '.$active->game_number.' could not be rescheduled before the tournament end date. Add match days or extend the end date.',
        'Series game needs a slot',
        'warning',
    );
}
```

Keep the existing success toast. The service still returns normally; corrections are never blocked by a missing follow-up slot.

### Step 6: Run the tests

```powershell
cd src
php artisan test --filter="regenerates with shuffled teams|releases the unused game slot|supports a split series|creates every potential game"
```

Expected: PASS. If the correction test fails because Game 3 is still cancelled, confirm `activateNextSeriesGame()` includes the cancelled fallback.

### Step 7: Commit

```powershell
git add src/app/Services/BracketService.php src/app/Services/SchedulingService.php src/app/Livewire/Organizer/BracketShow.php src/tests/Unit/Services/BracketServiceTest.php src/tests/Unit/Services/Bracket/SeriesLifecycleTest.php
git commit -m "feat(bracket): shuffle regeneration and release cancelled slots"
```

---

## Task 4: Role-gated series actions and the game chooser

**Files:**
- Modify: `src/app/Livewire/Organizer/BracketShow.php`
- Modify: `src/resources/views/livewire/organizer/bracket-show.blade.php`
- Test: `src/tests/Feature/Pages/BracketTest.php`

**Why:** Coaches and fans currently see `Open`, `Score`, `Correct`, and `Enter final score`. Every series game also needs its own `View box score`, and organizers need a Game 1..N chooser before scoring.

### Step 1: Write the failing tests

Append these helpers and tests to `src/tests/Feature/Pages/BracketTest.php`. Add `use App\Models\Bracket;` if it is not already imported.

```php
function bracketSeriesFixture(): array
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    $tournament = $tournament->fresh();

    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');

    $final = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->orderByDesc('round_number')
        ->firstOrFail();
    $teams = $tournament->registrations()
        ->where('status', 'approved')
        ->orderBy('seed')
        ->pluck('team_id')
        ->take(2)
        ->values();

    $final->home_team_id = (int) $teams[0];
    $final->away_team_id = (int) $teams[1];
    $final->save();
    $service->syncSeriesTeams($final);
    $service->recordScore($final->fresh(), 80, 70);

    $games = MatchModel::where(function ($query) use ($final): void {
        $query->where('id', $final->id)->orWhere('series_id', $final->id);
    })->orderBy('game_number')->get();

    return [$tournament, $final->fresh(), $games, $service];
}

it('shows every series action to the organizer and only box scores to coach and fan', function () {
    [$tournament, $final, $games] = bracketSeriesFixture();
    $game2 = $games->firstWhere('game_number', 2);
    $game1 = $games->firstWhere('game_number', 1);

    Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->call('openMatchDetail', $final->id)
        ->assertSee('Correct score')
        ->assertSee('Open scoring')
        ->assertSee('View box score')
        ->assertSeeHtml('href="'.route('gameStats', ['match' => $game1->id]).'"')
        ->assertSeeHtml('href="'.route('gameStats', ['match' => $game2->id]).'"')
        ->assertSeeHtml('href="'.route('gameManage', ['match' => $game2->id]).'"');

    foreach (['coach.marcus@hoopflow.ph', 'fan.juan@hoopflow.ph'] as $email) {
        Livewire::actingAs(User::where('email', $email)->firstOrFail())
            ->test(BracketShow::class, ['id' => $tournament->id])
            ->call('openMatchDetail', $final->id)
            ->assertSee('View box score')
            ->assertDontSee('Correct score')
            ->assertDontSee('Open scoring')
            ->assertDontSee('Resume scoring')
            ->assertDontSee('Enter final score')
            ->assertDontSee('In-game score');
    }
});

it('opens the game chooser for a series and explains unavailable games', function () {
    [$tournament, $final, $games] = bracketSeriesFixture();
    $game2 = $games->firstWhere('game_number', 2);
    $game3 = $games->firstWhere('game_number', 3);

    Livewire::actingAs(User::where('email', 'organizer@hoopflow.ph')->firstOrFail())
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->call('openMatchDetail', $final->id)
        ->call('openGameChooser', $final->id)
        ->assertSet('showGameChooser', true)
        ->assertSee('Choose game')
        ->assertSee('Game 1 of 3')
        ->assertSee('Game 2 of 3')
        ->assertSee('Available after Game 2 is final')
        ->assertSeeHtml('href="'.route('gameManage', ['match' => $game2->id]).'"')
        ->assertSeeHtml('href="'.route('gameStats', ['match' => $game3->id]).'"');
});

it('regenerates the bracket with shuffled teams from the confirmation dialog', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    (new BracketService)->generate($tournament, 'double-elimination');

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->call('requestRegenerate')
        ->assertSet('confirmAction', 'regenerate')
        ->assertSee('shuffles all approved teams')
        ->call('confirm')
        ->assertDispatched('hf-toast');

    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))->firstOrFail();

    expect($bracket->seeding_method)->toBe('random');
});
```

### Step 2: Run the tests to confirm the failure

```powershell
cd src
php artisan test --filter="shows every series action|opens the game chooser|regenerates the bracket with shuffled"
```

Expected: FAIL. The chooser method does not exist; coach/fan assertions find organizer labels; regeneration still writes `manual`.

### Step 3: Add chooser state and shuffle wiring to `BracketShow`

Add the properties and computed methods:

```php
public bool $showGameChooser = false;

public ?int $chooserMatchId = null;

#[Computed]
public function chooserMatch(): ?MatchModel
{
    return $this->findTournamentMatch($this->chooserMatchId);
}

#[Computed]
public function chooserAnchor(): ?MatchModel
{
    $match = $this->chooserMatch;

    if (! $match instanceof MatchModel || $match->best_of <= 1) {
        return null;
    }

    return $match->series_id !== null ? $match->seriesAnchor : $match;
}

/**
 * @return Collection<int, MatchModel>
 */
#[Computed]
public function chooserGames(): Collection
{
    $anchor = $this->chooserAnchor;

    if (! $anchor instanceof MatchModel) {
        return new Collection;
    }

    return MatchModel::query()
        ->where(function ($query) use ($anchor): void {
            $query->where('id', $anchor->id)->orWhere('series_id', $anchor->id);
        })
        ->with(['homeTeam', 'awayTeam', 'matchSlots.court'])
        ->orderBy('game_number')
        ->get();
}

#[Computed]
public function chooserSummary(): string
{
    $anchor = $this->chooserAnchor;

    if (! $anchor instanceof MatchModel) {
        return '';
    }

    return ($anchor->homeTeam?->shortLabel() ?? 'Home').' vs '.($anchor->awayTeam?->shortLabel() ?? 'Away').' · '.$this->selectedSeriesSummary;
}
```

Add the methods:

```php
public function openGameChooser(int|string|null $matchId): void
{
    $match = $this->findTournamentMatch($matchId);

    if (! $match instanceof MatchModel) {
        return;
    }

    Gate::authorize('enterStats', $match);

    if ($match->best_of <= 1) {
        $this->redirect(route('gameManage', ['match' => $match->id]));

        return;
    }

    $this->chooserMatchId = (int) $match->id;
    $this->showGameChooser = true;
    $this->showMatchDetail = false;
    $this->showScoreConsole = false;
    $this->showCorrection = false;
    $this->showConfirm = false;
    $this->resetValidation();
}

public function closeGameChooser(): void
{
    $this->showGameChooser = false;
    $this->chooserMatchId = null;
}
```

Update `requestRegenerate()`:

```php
if ($this->kpis['matchesPlayed'] > 0) {
    $this->toast('Results already exist. Regenerating is locked — use Correct score instead.', 'Bracket locked', 'warning');

    return;
}

$this->openConfirm(
    'Regenerate bracket?',
    'This shuffles all approved teams, ignores seeding, and clears the published bracket and schedule.',
    'Regenerate bracket',
    'danger',
    'regenerate',
);
```

Update `performGenerate()` to pass the flag:

```php
$result = $bracketService->generate($tournament, $this->format, $regenerate);
```

Also add `$this->showGameChooser = false;` to `openMatchDetail()`, `closeMatchDetail()`, `performGenerate()`, and `performGeneratePlayoffs()` so the dialogs never stack.

### Step 4: Replace the series action cell in the Blade view

In `bracket-show.blade.php`, replace the `<td>` action cell inside `bracket-series-games` with:

```blade
<td>
    <div class="hf-btn-row">
        @if ($isOrganizer)
            @if ($seriesActive && $game->status === 'scheduled')
                <a
                    class="hf-btn hf-btn--sm hf-btn--primary"
                    href="{{ route('gameManage', ['match' => $game->id]) }}"
                    data-testid="series-open-{{ $game->id }}"
                    aria-label="Open scoring for Game {{ $game->game_number }}"
                >Open scoring</a>
                <button
                    type="button"
                    class="hf-btn hf-btn--sm"
                    wire:click="openScoreConsole({{ $game->id }})"
                    data-testid="series-score-{{ $game->id }}"
                    aria-label="Enter final score for Game {{ $game->game_number }}"
                >Enter final score</button>
            @elseif ($game->status === 'scheduled')
                <button
                    type="button"
                    class="hf-btn hf-btn--sm"
                    wire:click="openScoreConsole({{ $game->id }})"
                    data-testid="series-score-{{ $game->id }}"
                    aria-label="Enter final score for Game {{ $game->game_number }}"
                >Enter final score</button>
            @elseif ($game->status === 'in_progress')
                <a
                    class="hf-btn hf-btn--sm hf-btn--primary"
                    href="{{ route('gameManage', ['match' => $game->id]) }}"
                    data-testid="series-resume-{{ $game->id }}"
                    aria-label="Resume scoring for Game {{ $game->game_number }}"
                >Resume scoring</a>
            @elseif (in_array($game->status, ['final', 'forfeit'], true))
                <button
                    type="button"
                    class="hf-btn hf-btn--sm hf-btn--danger"
                    wire:click="openCorrection({{ $game->id }})"
                    data-testid="series-correct-{{ $game->id }}"
                    aria-label="Correct score for Game {{ $game->game_number }}"
                >Correct score</button>
            @endif
        @endif

        <a
            class="hf-btn hf-btn--sm"
            href="{{ route('gameStats', ['match' => $game->id]) }}"
            data-testid="series-stats-{{ $game->id }}"
            aria-label="View box score for Game {{ $game->game_number }}"
        >View box score</a>
    </div>
</td>
```

Replace the detail footer with:

```blade
<div class="hf-dialog__foot">
    <button type="button" class="hf-btn" wire:click="closeMatchDetail" data-testid="bracket-match-close">Close</button>
    <a class="hf-btn" href="{{ route('gameStats', ['match' => $detailMatch->id]) }}" data-testid="bracket-match-stats">View box score</a>
    @if ($isOrganizer)
        @if ($this->selectedSeriesGames->isNotEmpty())
            <button type="button" class="hf-btn" wire:click="openGameChooser({{ $detailMatch->id }})" data-testid="bracket-in-game">
                <i class="bi bi-clipboard-pulse"></i> In-game score &amp; player stats
            </button>
        @else
            <a class="hf-btn" href="{{ route('gameManage', ['match' => $detailMatch->id]) }}" data-testid="bracket-in-game">
                <i class="bi bi-clipboard-pulse"></i> In-game score &amp; player stats
            </a>
        @endif
    @endif
    @if ($isOrganizer && $this->selectedCanScore && $detailActiveGame)
        <button type="button" class="hf-btn hf-btn--primary" wire:click="openScoreConsole({{ $detailActiveGame->id }})" data-testid="bracket-enter-score">Enter final score</button>
    @endif
    @if ($isOrganizer && $this->selectedCanCorrect)
        <button type="button" class="hf-btn hf-btn--danger" wire:click="openCorrection({{ $detailMatch->id }})" data-testid="bracket-correct-score">Correct score</button>
    @endif
</div>
```

Add the chooser dialog after the match-detail dialog and before the score console. Use the existing dialog classes and Alpine behavior:

```blade
@if ($showGameChooser)
    <dialog
        class="hf-dialog hf-dialog--wide"
        x-data
        x-init="if (! $el.open) $el.showModal()"
        x-on:cancel.prevent="$wire.closeGameChooser()"
        x-on:click="if ($event.target === $el) $wire.closeGameChooser()"
        wire:key="bracket-game-chooser"
        data-testid="bracket-game-chooser"
    >
        <div class="hf-dialog__head">
            <h2 class="hf-dialog__title">Choose game · {{ $this->chooserSummary }}</h2>
            <button type="button" class="hf-icon-btn" wire:click="closeGameChooser" aria-label="Close dialog" data-testid="dialog-close">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <div class="hf-dialog__body">
            <div class="hf-stack hf-gap-2">
                @foreach ($this->chooserGames as $game)
                    @php
                        $activeGame = $this->chooserAnchor?->activeGame();
                        $isActive = $activeGame instanceof \App\Models\MatchModel && (int) $activeGame->id === (int) $game->id;
                    @endphp
                    <div class="hf-card hf-card--flat" data-testid="chooser-game-{{ $game->id }}">
                        <div class="hf-between hf-wrap hf-gap-2">
                            <div>
                                <strong>{{ $game->seriesGameLabel() }}</strong>
                                <p class="hf-small hf-muted hf-mb-0">
                                    {{ $game->home_score ?? '–' }}–{{ $game->away_score ?? '–' }}
                                    · {{ $this->seriesGameSchedule($game) }}
                                    · {{ $game->statusLabel() }}
                                </p>
                            </div>
                            <div class="hf-btn-row">
                                @if ($isActive && $game->status === 'scheduled')
                                    <a class="hf-btn hf-btn--sm hf-btn--primary" href="{{ route('gameManage', ['match' => $game->id]) }}">Open scoring</a>
                                @elseif ($game->status === 'in_progress')
                                    <a class="hf-btn hf-btn--sm hf-btn--primary" href="{{ route('gameManage', ['match' => $game->id]) }}">Resume scoring</a>
                                @elseif (in_array($game->status, ['final', 'forfeit'], true))
                                    <button type="button" class="hf-btn hf-btn--sm hf-btn--danger" wire:click="openCorrection({{ $game->id }})">Correct score</button>
                                @elseif ($game->status === 'conditional')
                                    <button type="button" class="hf-btn hf-btn--sm" disabled>Available after Game {{ $game->game_number - 1 }} is final</button>
                                @else
                                    <button type="button" class="hf-btn hf-btn--sm" disabled>Series ended. This game is not needed.</button>
                                @endif
                                <a class="hf-btn hf-btn--sm" href="{{ route('gameStats', ['match' => $game->id]) }}">View box score</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="hf-dialog__foot">
            <button type="button" class="hf-btn" wire:click="closeGameChooser">Close</button>
        </div>
    </dialog>
@endif
```

### Step 5: Run the tests

```powershell
cd src
php artisan test --filter="shows every series action|opens the game chooser|regenerates the bracket with shuffled|forbids non-organizers"
```

Expected: PASS. If `assertSee('Game 1 of 3')` fails, use `seriesGameLabel()` in the chooser row instead of hand-built text.

### Step 6: Commit

```powershell
git add src/app/Livewire/Organizer/BracketShow.php src/resources/views/livewire/organizer/bracket-show.blade.php src/tests/Feature/Pages/BracketTest.php
git commit -m "feat(bracket): gate series actions and add game chooser"
```

---

## Task 5: Explicit game selection and a complete series switcher in GameManage

**Files:**
- Modify: `src/app/Livewire/Organizer/GameManage.php`
- Modify: `src/resources/views/livewire/organizer/game-manage.blade.php`
- Test: `src/tests/Feature/Pages/GameManageTest.php`

**Why:** The anchor redirect must stay, but an explicit child ID must be honored. Conditional and cancelled requests must explain why they cannot open, and the switcher must link finished games to their box score.

### Step 1: Write the failing tests

Append to `src/tests/Feature/Pages/GameManageTest.php` after the existing series tests:

```php
it('keeps an explicit child game and redirects an unavailable game with a warning', function () {
    $tournament = gmSeriesTournament();
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    [$final, $game2] = gmStartSeries($tournament, $service);

    $game3 = MatchModel::where('series_id', $final->id)
        ->where('game_number', 3)
        ->firstOrFail();

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $game2->id])
        ->assertSet('matchId', $game2->id)
        ->assertSee('Game 2 of 3');

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $game3->id])
        ->assertSet('matchId', $game2->id)
        ->assertDispatched('hf-toast')
        ->assertDontSee('Game 3 is available after Game 2 is final');
});

it('links finished games to the box score and explains unavailable switcher entries', function () {
    $tournament = gmSeriesTournament();
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    [$final, $game2] = gmStartSeries($tournament, $service);

    $game3 = MatchModel::where('series_id', $final->id)
        ->where('game_number', 3)
        ->firstOrFail();

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $game2->id])
        ->assertSeeHtml('href="'.route('gameStats', ['match' => $final->id]).'"')
        ->assertSee('Available after Game 2 is final')
        ->assertSeeHtml('aria-current="true"')
        ->assertSeeHtml('data-testid="ingame-series-game-'.$game3->id.'"');
});
```

### Step 2: Run the tests to confirm the failure

```powershell
cd src
php artisan test --filter="keeps an explicit child game|links finished games to the box score"
```

Expected: FAIL. The conditional Game 3 stays selected; the finished Game 1 is a plain span and Game 3 has no reason text.

### Step 3: Update `resolveActiveSeriesMatch()` and add the reason helper

Replace the method with:

```php
protected function resolveActiveSeriesMatch(MatchModel $match): MatchModel
{
    if ($match->best_of <= 1) {
        return $match;
    }

    $anchor = $match->series_id !== null
        ? MatchModel::find($match->series_id)
        : $match;

    if (! $anchor instanceof MatchModel) {
        return $match;
    }

    $isAnchorRequest = (int) $match->id === (int) $anchor->id;

    if (! $isAnchorRequest && in_array($match->status, ['scheduled', 'in_progress', 'final', 'forfeit'], true)) {
        return $match;
    }

    if (in_array($match->status, ['conditional', 'cancelled'], true)) {
        $active = $anchor->activeGame();

        if ($active instanceof MatchModel) {
            $message = $match->status === 'conditional'
                ? 'Game '.$match->game_number.' is available after Game '.($match->game_number - 1).' is final.'
                : 'The series ended '.max((int) $anchor->series_home_wins, (int) $anchor->series_away_wins).'–'.min((int) $anchor->series_home_wins, (int) $anchor->series_away_wins).'. Game '.$match->game_number.' is not needed.';

            $this->toast($message, 'Game unavailable', 'warning');

            return $active;
        }

        $this->toast(
            $match->status === 'conditional'
                ? 'Game '.$match->game_number.' is available after Game '.($match->game_number - 1).' is final.'
                : 'The series ended. Game '.$match->game_number.' is not needed.',
            'Game unavailable',
            'warning',
        );

        return $anchor;
    }

    return $anchor->activeGame() ?? $match;
}
```

Add:

```php
public function seriesGameUnavailableReason(MatchModel $game): ?string
{
    if ($game->status === 'conditional') {
        return 'Available after Game '.($game->game_number - 1).' is final.';
    }

    if ($game->status === 'cancelled') {
        $anchor = $this->seriesAnchor;

        if ($anchor instanceof MatchModel) {
            return 'Series ended '.max((int) $anchor->series_home_wins, (int) $anchor->series_away_wins).'–'.min((int) $anchor->series_home_wins, (int) $anchor->series_away_wins).'. This game is not needed.';
        }

        return 'This game is not needed.';
    }

    return null;
}
```

### Step 4: Replace the switcher loop in the Blade view

Replace lines 98-132 of `game-manage.blade.php` with:

```blade
<div class="hf-btn-row hf-mt-2" data-testid="ingame-series-games">
    @foreach ($this->seriesGames as $seriesGame)
        @php
            $seriesSlot = $seriesGame->matchSlots->first();
            $isCurrent = (int) $seriesGame->id === (int) $match->id;
            $tone = $this->seriesStatusTone($seriesGame);
            $seriesLabel = 'Game '.$seriesGame->game_number.' · '.$this->seriesStatusLabel($seriesGame);
            $seriesSchedule = $seriesSlot?->start_at !== null
                ? ($seriesSlot->court?->name ?? 'Court TBD').' '.$seriesSlot->start_at->format('M j, g:i A')
                : null;
            $unavailableReason = $this->seriesGameUnavailableReason($seriesGame);
        @endphp
        @if ($isCurrent)
            <span class="hf-chip hf-chip--{{ $tone }}" data-testid="ingame-series-game-{{ $seriesGame->id }}" aria-current="true">
                {{ $seriesLabel }}
                @if ($seriesSchedule)
                    <span class="hf-small">{{ $seriesSchedule }}</span>
                @endif
            </span>
        @elseif (in_array($seriesGame->status, ['scheduled', 'in_progress'], true))
            <a class="hf-chip hf-chip--{{ $tone }}" href="{{ route('gameManage', ['match' => $seriesGame->id]) }}" data-testid="ingame-series-game-{{ $seriesGame->id }}">
                {{ $seriesLabel }}
                @if ($seriesSchedule)
                    <span class="hf-small">{{ $seriesSchedule }}</span>
                @endif
            </a>
        @elseif (in_array($seriesGame->status, ['final', 'forfeit'], true))
            <a class="hf-chip hf-chip--{{ $tone }}" href="{{ route('gameStats', ['match' => $seriesGame->id]) }}" data-testid="ingame-series-game-{{ $seriesGame->id }}">
                {{ $seriesLabel }}
                @if ($seriesSchedule)
                    <span class="hf-small">{{ $seriesSchedule }}</span>
                @endif
            </a>
        @else
            <span class="hf-chip hf-chip--{{ $tone }}" data-testid="ingame-series-game-{{ $seriesGame->id }}" aria-disabled="true">
                {{ $seriesLabel }}
                @if ($unavailableReason)
                    <span class="hf-small">{{ $unavailableReason }}</span>
                @endif
                @if ($seriesSchedule)
                    <span class="hf-small">{{ $seriesSchedule }}</span>
                @endif
            </span>
        @endif
    @endforeach
</div>
```

Keep the existing banner, locked-state panel, next-game link, publish confirmation, and correction behavior unchanged.

### Step 5: Run the tests

```powershell
cd src
php artisan test --filter="keeps an explicit child game|links finished games to the box score|opens the active series game|series"
```

Expected: PASS. The existing anchor redirect test must still pass.

### Step 6: Commit

```powershell
git add src/app/Livewire/Organizer/GameManage.php src/resources/views/livewire/organizer/game-manage.blade.php src/tests/Feature/Pages/GameManageTest.php
git commit -m "feat(ingame): honor explicit series game selection"
```

---

## Task 6: Per-game box score context and states

**Files:**
- Modify: `src/app/Livewire/Organizer/GameStatsShow.php`
- Modify: `src/resources/views/livewire/organizer/game-stats-show.blade.php`
- Test: `src/tests/Feature/Pages/GameStatsTest.php`

**Why:** The page loads the exact match already, but it has no series strip and labels every box score "Official" unless it is a scheduled draft. Add series navigation plus explicit final, draft, scheduled, conditional, and cancelled states.

### Step 1: Write the failing tests

Append to `src/tests/Feature/Pages/GameStatsTest.php`. Add `use App\Models\Bracket;` and `use App\Services\BracketService;` to the imports.

```php
function gsSeriesFixture(): array
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    $tournament = $tournament->fresh();

    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))->firstOrFail();
    $final = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();
    $teams = $tournament->registrations()
        ->where('status', 'approved')
        ->orderBy('seed')
        ->pluck('team_id')
        ->take(2)
        ->values();

    $final->home_team_id = (int) $teams[0];
    $final->away_team_id = (int) $teams[1];
    $final->save();
    $service->syncSeriesTeams($final);
    $service->recordScore($final->fresh(), 80, 70);

    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    $game3 = MatchModel::where('series_id', $final->id)->where('game_number', 3)->firstOrFail();

    return [$tournament, $final->fresh(), $game2, $game3, $service];
}

it('shows the series strip and official context on a final game box score', function () {
    [, $final, $game2] = gsSeriesFixture();

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$final->id)
        ->assertOk()
        ->assertSee('Official box score')
        ->assertSee('Game 1 of 3')
        ->assertSeeHtml('data-testid="stats-series-games"')
        ->assertSeeHtml('href="'.route('gameStats', ['match' => $game2->id]).'"')
        ->assertSee('Game 1');
});

it('shows a scheduled empty state and then a draft warning for the same game', function () {
    [, , $game2] = gsSeriesFixture();

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$game2->id)
        ->assertOk()
        ->assertSee('No stats yet.')
        ->assertDontSee('Official box score');

    $loaded = app(GameStatsService::class)->load($game2);
    app(GameStatsService::class)->saveDraft($game2, $loaded);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$game2->id)
        ->assertOk()
        ->assertSee('Draft box score')
        ->assertSee('Draft stats — not official until the game is published.');
});

it('explains conditional and cancelled series games', function () {
    [, , , $game3, $service] = gsSeriesFixture();

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$game3->id)
        ->assertOk()
        ->assertSee('This game is only played if needed.');

    $game2 = MatchModel::where('series_id', $game3->series_id)->where('game_number', 2)->firstOrFail();
    $service->recordScore($game2->fresh(), 80, 70);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$game3->id)
        ->assertOk()
        ->assertSee('This game was cancelled when the series ended early.');
});

it('keeps the series strip visible but organizer actions hidden for fans', function () {
    [, $final] = gsSeriesFixture();

    $this->actingAs(gsUser('fan.juan@hoopflow.ph'))
        ->get('/game-stats?match='.$final->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="stats-series-games"')
        ->assertSee('Official box score')
        ->assertDontSee('Correct score')
        ->assertDontSee('Enter final score');
});

it('returns 404 for an unknown game id', function () {
    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match=999999')
        ->assertNotFound();
});
```

### Step 2: Run the tests to confirm the failure

```powershell
cd src
php artisan test --filter="shows the series strip|scheduled empty state|explains conditional|series strip visible|unknown game id"
```

Expected: FAIL. There is no series strip, no scheduled/conditional/cancelled copy, and the invalid ID still renders 200.

### Step 3: Add series and state computed properties to `GameStatsShow`

Add `use Illuminate\Database\Eloquent\Collection;` to the imports, then add:

```php
#[Computed]
public function seriesAnchor(): ?MatchModel
{
    $match = $this->match;

    if (! $match instanceof MatchModel || $match->best_of <= 1) {
        return null;
    }

    return $match->series_id !== null ? $match->seriesAnchor : $match;
}

/**
 * @return Collection<int, MatchModel>
 */
#[Computed]
public function seriesGames(): Collection
{
    $anchor = $this->seriesAnchor;

    if (! $anchor instanceof MatchModel) {
        return new Collection;
    }

    return MatchModel::query()
        ->where(function ($query) use ($anchor): void {
            $query->where('id', $anchor->id)->orWhere('series_id', $anchor->id);
        })
        ->orderBy('game_number')
        ->get();
}

#[Computed]
public function seriesSummary(): string
{
    $anchor = $this->seriesAnchor;

    if (! $anchor instanceof MatchModel) {
        return '';
    }

    $homeWins = (int) $anchor->series_home_wins;
    $awayWins = (int) $anchor->series_away_wins;
    $home = $anchor->homeTeam?->shortLabel() ?? 'Home';
    $away = $anchor->awayTeam?->shortLabel() ?? 'Away';

    if ($anchor->series_winner_team_id !== null) {
        $winner = (int) $anchor->series_winner_team_id === (int) $anchor->home_team_id ? $home : $away;

        return $winner.' wins the series '.max($homeWins, $awayWins).'–'.min($homeWins, $awayWins);
    }

    if ($homeWins === 0 && $awayWins === 0) {
        return 'Series not started';
    }

    if ($homeWins === $awayWins) {
        return 'Series tied '.$homeWins.'–'.$awayWins;
    }

    $leader = $homeWins > $awayWins ? $home : $away;

    return $leader.' leads '.max($homeWins, $awayWins).'–'.min($homeWins, $awayWins);
}

#[Computed]
public function boxScoreLabel(): string
{
    $match = $this->match;

    if (! $match instanceof MatchModel) {
        return 'Box score';
    }

    return match (true) {
        in_array($match->status, ['final', 'forfeit'], true) => 'Official box score',
        $this->hasStats => 'Draft box score',
        $match->status === 'conditional' => 'If needed',
        $match->status === 'cancelled' => 'Not needed',
        default => 'Box score not started',
    };
}

#[Computed]
public function boxScoreMessage(): string
{
    $match = $this->match;

    if (! $match instanceof MatchModel) {
        return '';
    }

    $slot = $match->matchSlots->first();
    $date = $slot?->start_at?->format('M j, Y') ?? 'TBD';
    $time = $slot?->start_at?->format('g:i A') ?? 'TBD';
    $court = $slot?->court?->name ?? 'Court TBD';

    return match (true) {
        $match->status === 'scheduled' && $this->hasStats => 'Draft stats — not official until the game is published.',
        $match->status === 'scheduled' => 'No stats yet. Scheduled '.$date.' at '.$time.' on '.$court.'.',
        $match->status === 'conditional' => 'This game is only played if needed.',
        $match->status === 'cancelled' => 'This game was cancelled when the series ended early.',
        default => '',
    };
}
```

Add the invalid-ID guard at the end of `mount()`:

```php
if ($this->matchId !== null && ! MatchModel::query()->whereKey($this->matchId)->exists()) {
    abort(404);
}
```

### Step 4: Update the Blade view

Add series context to the page description:

```blade
<p class="hf-page-desc">
    {{ $match->roundLabel() }} · {{ $court?->name ?? 'Court TBD' }} · {{ $tipoff }}.
    @if ($this->seriesAnchor)
        · {{ $match->seriesGameLabel() }} · {{ $this->seriesSummary }}
    @endif
</p>
```

Insert the series strip immediately after `</header>`:

```blade
@if ($this->seriesAnchor)
    <section class="hf-card hf-card--flat hf-mb-3" data-testid="stats-series">
        <div class="hf-btn-row" data-testid="stats-series-games">
            @foreach ($this->seriesGames as $seriesGame)
                @php
                    $isCurrent = (int) $seriesGame->id === (int) $match->id;
                    $summary = ($seriesGame->home_score ?? '–').'–'.($seriesGame->away_score ?? '–');
                @endphp
                @if ($isCurrent)
                    <span class="hf-chip hf-chip--primary" aria-current="true" data-testid="stats-series-game-{{ $seriesGame->id }}">
                        Game {{ $seriesGame->game_number }} · {{ $summary }} · {{ $seriesGame->statusLabel() }}
                    </span>
                @else
                    <a class="hf-chip hf-chip--slate" href="{{ route('gameStats', ['match' => $seriesGame->id]) }}" data-testid="stats-series-game-{{ $seriesGame->id }}">
                        Game {{ $seriesGame->game_number }} · {{ $summary }} · {{ $seriesGame->statusLabel() }}
                    </a>
                @endif
            @endforeach
        </div>
    </section>
@endif
```

Insert the state banner before `stats-box-score`:

```blade
@if ($this->boxScoreMessage !== '')
    <div class="hf-card hf-card--flat hf-mb-3" data-testid="stats-state-banner">
        <div class="hf-between hf-wrap">
            <span class="hf-chip hf-chip--{{ $match->status === 'cancelled' ? 'slate' : ($match->status === 'conditional' ? 'gold' : 'primary') }}">
                {{ $this->boxScoreLabel }}
            </span>
        </div>
        <p class="hf-small hf-mt-1 hf-mb-0">{{ $this->boxScoreMessage }}</p>
    </div>
@endif
```

Replace the box score eyebrow:

```blade
<span class="hf-eyebrow">{{ $this->boxScoreLabel }}</span>
```

### Step 5: Run the tests

```powershell
cd src
php artisan test --filter="shows the series strip|scheduled empty state|explains conditional|series strip visible|unknown game id|draft box score"
```

Expected: PASS. Existing role and period tests must keep passing.

### Step 6: Commit

```powershell
git add src/app/Livewire/Organizer/GameStatsShow.php src/resources/views/livewire/organizer/game-stats-show.blade.php src/tests/Feature/Pages/GameStatsTest.php
git commit -m "feat(stats): show per-game series context and box score states"
```

---

## Task 7: Editable preview, exact publish, and the rebuild wizard

**Files:**
- Modify: `src/app/Livewire/Organizer/ScheduleShow.php`
- Modify: `src/resources/views/livewire/organizer/schedule-show.blade.php`
- Test: `src/tests/Feature/Pages/ScheduleTest.php`

**Why:** The preview currently accepts no settings, `publishSchedule()` re-plans silently, and the one-click Regenerate/Manual manage actions are placeholders. Replace them with one editable preview panel and a Rebuild schedule entry point.

### Step 1: Write the failing tests

Append the helper and tests to `src/tests/Feature/Pages/ScheduleTest.php`. Add `use App\Models\AuditLog;` to the imports.

```php
function scheduleReadyTournament(): Tournament
{
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    MatchSlot::where('tournament_id', $tournament->id)->delete();
    MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->whereNotNull('home_team_id')
        ->whereNotNull('away_team_id')
        ->update([
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
            'series_winner_team_id' => null,
            'game_winner_team_id' => null,
        ]);

    return $tournament->fresh();
}

it('builds a preview from editable settings and publishes the exact edited row', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = scheduleReadyTournament();

    $component = Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openPreview')
        ->assertSet('showPreview', true)
        ->assertSet('previewMode', 'generate')
        ->assertSet('previewStartDate', '2026-05-15')
        ->assertSee('Build preview');

    $plan = $component->get('previewPlan');
    $edited = $plan['assignments'][count($plan['assignments']) - 1];
    $court = app(SchedulingService::class)
        ->courts($tournament)
        ->first(fn (Court $option): bool => (int) $option->id !== (int) $edited['courtId'])
        ?? throw new RuntimeException('This test needs another court.');

    $component
        ->call('updatePreviewAssignment', $edited['matchId'], (int) $court->id, $edited['scheduledAt'])
        ->call('publishSchedule')
        ->assertSet('showPreview', false)
        ->assertDispatched('hf-toast');

    expect((int) MatchSlot::where('match_id', $edited['matchId'])->value('court_id'))->toBe((int) $court->id)
        ->and(AuditLog::where('action', 'schedule.publish')->count())->toBe(1);
});

it('marks the preview stale when settings change and refuses to publish it', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = scheduleReadyTournament();

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openPreview')
        ->set('previewStartDate', '2026-05-16')
        ->assertSet('previewDirty', true)
        ->assertSee('Settings changed — rebuild the preview before publishing.')
        ->call('publishSchedule')
        ->assertSet('showPreview', true)
        ->assertDispatched('hf-toast');

    expect(MatchSlot::where('tournament_id', $tournament->id)->count())->toBe(0);
});

it('shows an actionable error when the preview window is impossible', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = scheduleReadyTournament();

    $component = Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openPreview')
        ->set('previewStartDate', '2026-05-23')
        ->call('buildPreview');

    $errors = $component->get('previewErrors');

    expect($errors)->not->toBeEmpty()
        ->and(implode(' ', $errors))->toContain('end date must be on or after the start date');

    $component->assertDispatched('hf-toast');
});

it('prefills rebuild from the published schedule and confirms replacement', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = scheduleReadyTournament();
    app(SchedulingService::class)->generate($tournament);

    $firstDate = substr((string) MatchSlot::where('tournament_id', $tournament->id)->min('start_at'), 0, 10);
    $oldCount = MatchSlot::where('tournament_id', $tournament->id)->count();

    $component = Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openRebuild')
        ->assertSet('showPreview', true)
        ->assertSet('previewMode', 'rebuild')
        ->assertSet('previewStartDate', $firstDate)
        ->assertSee('Rebuild schedule');

    $component->call('publishSchedule')
        ->assertSet('showConfirm', true)
        ->assertSet('confirmAction', 'rebuild')
        ->assertSee('replace the current schedule');

    $component->call('confirm')
        ->assertSet('showPreview', false)
        ->assertDispatched('hf-toast');

    expect(MatchSlot::where('tournament_id', $tournament->id)->count())->toBe($oldCount)
        ->and(AuditLog::where('action', 'schedule.rebuild')->count())->toBe(1);
});

it('removes cancelled series games from the calendar', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    $tournament = $tournament->fresh();

    $bracketService = new BracketService;
    $bracketService->generate($tournament, 'single-elimination');
    app(SchedulingService::class)->generate($tournament);

    $final = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();
    $teams = $tournament->registrations()->where('status', 'approved')->pluck('team_id')->take(2)->values();
    $final->home_team_id = $teams[0];
    $final->away_team_id = $teams[1];
    $final->save();
    $bracketService->syncSeriesTeams($final);

    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    $game3 = MatchModel::where('series_id', $final->id)->where('game_number', 3)->firstOrFail();
    $game3Date = $game3->matchSlots()->firstOrFail()->start_at->format('Y-m-d');

    $bracketService->recordScore($final->fresh(), 80, 70);
    $bracketService->recordScore($game2->fresh(), 80, 70);

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('selectDay', $game3Date)
        ->assertDontSeeHtml('data-testid="schedule-match-'.$game3->id.'"');

    expect(MatchSlot::where('match_id', $game3->id)->exists())->toBeFalse();
});
```

Also update the existing control test: replace `->assertSee('Manual manage')` with `->assertSee('Reschedule match')` and add `->assertDontSee('Manual manage')`.

### Step 2: Run the tests to confirm the failure

```powershell
cd src
php artisan test --filter="builds a preview from editable settings|marks the preview stale|impossible|prefills rebuild|removes cancelled series games|prototype schedule controls"
```

Expected: FAIL. `openRebuild`, `buildPreview`, `updatePreviewAssignment`, and the preview settings do not exist; publish re-plans; the calendar still shows cancelled matches.

### Step 3: Add preview/rebuild state and methods to `ScheduleShow`

Add the properties:

```php
public string $previewMode = 'generate';

public string $previewStartDate = '';

public string $previewEndDate = '';

/** @var list<int> */
public array $previewWeekdays = [1, 2, 3, 4, 5, 6, 7];

public int $previewSlotMinutes = 30;

public int $previewRestMinutes = 15;

public bool $previewDirty = false;

/** @var array<int, string> */
public array $previewErrors = [];

/** @var array<int, string> */
public array $previewWarnings = [];

/** @var array<string, mixed> */
public array $lastBuiltPlan = [];
```

Add computed helpers:

```php
#[Computed]
public function canRebuild(): bool
{
    return $this->isOrganizer
        && $this->hasBracket
        && $this->resultsCount === 0
        && $this->hasScheduledGames;
}
```

Update the filtered collections:

```php
#[Computed]
public function matches(): Collection
{
    if (! $this->tournament instanceof Tournament) {
        return new Collection;
    }

    return MatchModel::query()
        ->where('tournament_id', $this->tournament->id)
        ->where('status', '!=', 'cancelled')
        ->with(['homeTeam', 'awayTeam', 'matchSlots.court'])
        ->get();
}

#[Computed]
public function matchSlots(): Collection
{
    if (! $this->tournament instanceof Tournament) {
        return new Collection;
    }

    return MatchSlot::query()
        ->where('tournament_id', $this->tournament->id)
        ->whereHas('match', fn ($query) => $query->where('status', '!=', 'cancelled'))
        ->with(['match.homeTeam', 'match.awayTeam', 'court'])
        ->orderBy('start_at')
        ->orderBy('id')
        ->get();
}
```

Replace `openPreview()` and the regenerate methods with:

```php
public function openPreview(): void
{
    $this->startPreview('generate');
}

public function openRebuild(): void
{
    $this->startPreview('rebuild');
}

protected function startPreview(string $mode): void
{
    $tournament = $this->tournament;

    if (! $tournament instanceof Tournament) {
        return;
    }

    Gate::authorize('generateSchedule', $tournament);

    if ($this->resultsCount > 0) {
        $this->toast('Results already exist. Use Reschedule for individual games.', 'Schedule locked', 'warning');

        return;
    }

    if ($mode === 'rebuild' && ! $this->hasScheduledGames) {
        $this->toast('There is no published schedule to rebuild.', 'Nothing to rebuild', 'warning');

        return;
    }

    $service = app(SchedulingService::class);
    $firstSlot = $this->scheduledSlots->sortBy('start_at')->first();

    $this->previewMode = $mode;
    $this->previewStartDate = $mode === 'rebuild' && $firstSlot?->start_at !== null
        ? $firstSlot->start_at->format('Y-m-d')
        : $service->startDate($tournament);
    $this->previewEndDate = $tournament->end_date?->format('Y-m-d') ?? '';
    $this->previewWeekdays = $mode === 'rebuild'
        ? $this->scheduledSlots
            ->filter(fn (MatchSlot $slot): bool => $slot->start_at !== null)
            ->map(fn (MatchSlot $slot): int => (int) $slot->start_at->isoWeekday())
            ->unique()
            ->sort()
            ->values()
            ->all()
        : range(1, 7);
    $this->previewSlotMinutes = $service->slotMinutes($tournament);
    $this->previewRestMinutes = $service->restMinutes($tournament);

    if ($this->previewWeekdays === []) {
        $this->previewWeekdays = range(1, 7);
    }

    $this->showPreview = true;
    $this->showConfirm = false;
    $this->showReschedule = false;
    $this->resetValidation();
    $this->buildPreview();
}

public function buildPreview(): void
{
    $tournament = $this->tournament;

    if (! $tournament instanceof Tournament) {
        return;
    }

    Gate::authorize('generateSchedule', $tournament);

    try {
        $plan = app(SchedulingService::class)->plan($tournament, [
            'startDate' => $this->previewStartDate,
            'endDate' => $this->previewEndDate,
            'weekdays' => $this->previewWeekdays,
            'slotMinutes' => $this->previewSlotMinutes,
            'restMinutes' => $this->previewRestMinutes,
        ]);

        $validation = app(SchedulingService::class)->validatePlan($tournament, $plan);

        $this->previewPlan = $plan;
        $this->lastBuiltPlan = $plan;
        $this->previewErrors = $validation['errors'];
        $this->previewWarnings = $validation['warnings'];
        $this->previewDirty = false;
        $this->showPreview = true;
        $this->resetValidation();
    } catch (InvalidArgumentException $exception) {
        $this->previewErrors = [0 => $exception->getMessage()];
        $this->previewWarnings = [];
        $this->previewDirty = true;
        $this->toast($exception->getMessage(), 'Could not build preview', 'danger');
    }
}

public function togglePreviewWeekday(int $day): void
{
    if ($day < 1 || $day > 7) {
        return;
    }

    $this->previewWeekdays = in_array($day, $this->previewWeekdays, true)
        ? array_values(array_filter($this->previewWeekdays, fn (int $selected): bool => $selected !== $day))
        : [...$this->previewWeekdays, $day];

    sort($this->previewWeekdays);
    $this->previewDirty = true;
}

public function updatePreviewAssignment(int|string $matchId, int|string|null $courtId, ?string $scheduledAt): void
{
    $found = false;

    foreach ($this->previewPlan['assignments'] ?? [] as $index => $assignment) {
        if ((int) $assignment['matchId'] !== (int) $matchId) {
            continue;
        }

        if ($courtId !== null && is_numeric($courtId)) {
            $this->previewPlan['assignments'][$index]['courtId'] = (int) $courtId;
        }

        if (is_string($scheduledAt) && trim($scheduledAt) !== '') {
            $this->previewPlan['assignments'][$index]['scheduledAt'] = Carbon::parse($scheduledAt)->format('Y-m-d H:i:s');
        }

        $found = true;
        break;
    }

    if (! $found || ! $this->tournament instanceof Tournament) {
        return;
    }

    $validation = app(SchedulingService::class)->validatePlan($this->tournament, $this->previewPlan);
    $this->previewErrors = $validation['errors'];
    $this->previewWarnings = $validation['warnings'];
}

public function resetPreview(): void
{
    if ($this->lastBuiltPlan === []) {
        return;
    }

    $this->previewPlan = $this->lastBuiltPlan;

    if ($this->tournament instanceof Tournament) {
        $validation = app(SchedulingService::class)->validatePlan($this->tournament, $this->previewPlan);
        $this->previewErrors = $validation['errors'];
        $this->previewWarnings = $validation['warnings'];
    }

    $this->previewDirty = false;
}
```

Replace `publishSchedule()` and add the shared publisher:

```php
public function publishSchedule(): void
{
    $tournament = $this->tournament;

    if (! $tournament instanceof Tournament) {
        return;
    }

    Gate::authorize('generateSchedule', $tournament);

    if (($this->previewPlan['assignments'] ?? []) === []) {
        $this->toast('Build a preview before publishing.', 'No preview', 'warning');

        return;
    }

    if ($this->previewDirty) {
        $this->toast('Settings changed — rebuild the preview before publishing.', 'Preview is stale', 'warning');

        return;
    }

    $validation = app(SchedulingService::class)->validatePlan($tournament, $this->previewPlan);
    $this->previewErrors = $validation['errors'];
    $this->previewWarnings = $validation['warnings'];

    if (! $validation['valid']) {
        $this->toast((string) reset($validation['errors']), 'Fix the schedule first', 'danger');

        return;
    }

    if ($this->previewMode === 'rebuild') {
        $this->openConfirm(
            'Replace the current schedule?',
            'This will delete the published schedule and replace it with the preview. Per-game moves are kept until you publish.',
            'Replace schedule',
            'danger',
            'rebuild',
        );

        return;
    }

    $this->performPublish();
}

protected function performPublish(): void
{
    $tournament = $this->tournament;

    if (! $tournament instanceof Tournament) {
        return;
    }

    Gate::authorize('generateSchedule', $tournament);

    try {
        $count = app(SchedulingService::class)->publish(
            $tournament,
            $this->previewPlan,
            $this->previewMode === 'rebuild' ? 'schedule.rebuild' : 'schedule.publish',
        );

        $summary = $this->previewPlan['summary'] ?? [];
        $first = is_string($summary['firstDate'] ?? null) ? $summary['firstDate'] : null;

        $this->showPreview = false;
        $this->previewPlan = [];
        $this->lastBuiltPlan = [];
        $this->previewErrors = [];
        $this->previewWarnings = [];
        $this->previewDirty = false;

        if ($first !== null && $first !== '') {
            $this->selectedDay = $first;
            $this->currentMonth = substr($first, 0, 7);
        }

        $this->toast(
            $count.' games · '.($summary['matchDays'] ?? 0).' match days · ends '.($summary['lastDate'] ?? 'TBD').'.',
            $this->previewMode === 'rebuild' ? 'Schedule rebuilt' : 'Schedule published',
        );
    } catch (InvalidArgumentException $exception) {
        $this->previewErrors = [0 => $exception->getMessage()];
        $this->toast($exception->getMessage(), 'Could not publish schedule', 'danger');
    }
}
```

Update `confirm()`:

```php
public function confirm(): void
{
    $action = $this->confirmAction;
    $this->resetConfirm();

    if ($action === 'rebuild') {
        $this->performPublish();
    }
}
```

Delete `requestRegenerate()`, `manualManage()`, and the old `regenerate` branch in `confirm()`. Add the stale hook:

```php
public function updated(string $property): void
{
    if (in_array($property, ['previewStartDate', 'previewEndDate', 'previewWeekdays', 'previewSlotMinutes', 'previewRestMinutes'], true)) {
        $this->previewDirty = true;
    }
}
```

Update `previewRows()` to expose editable values and row markers:

```php
return array_map(function (array $assignment) use ($matches, $courts): array {
    $match = $matches->get($assignment['matchId']);
    $court = $courts->get($assignment['courtId']);
    $start = Carbon::parse($assignment['scheduledAt']);
    $matchId = (int) $assignment['matchId'];

    return [
        'matchId' => $matchId,
        'courtId' => (int) $assignment['courtId'],
        'scheduledAt' => $start->format('Y-m-d\TH:i'),
        'round' => $match?->roundLabel() ?? 'Match',
        'game' => $match?->best_of > 1 ? $match->seriesGameLabel() : null,
        'match' => ($match?->homeTeam?->shortLabel() ?? 'TBD').' vs '.($match?->awayTeam?->shortLabel() ?? 'TBD'),
        'court' => $court?->name ?? 'TBD',
        'slot' => $start->format('M d, Y').' · '.$start->format('g:i A'),
        'error' => $this->previewErrors[$matchId] ?? null,
        'warning' => $this->previewWarnings[$matchId] ?? null,
    ];
}, $assignments);
```

### Step 4: Replace the preview dialog and entry points in the Blade view

Header: replace the Regenerate and Manual manage buttons with:

```blade
@if ($this->isOrganizer && $this->hasScheduledGames && $this->resultsCount === 0)
    <button type="button" class="hf-btn" wire:click="openRebuild" data-testid="schedule-rebuild" wire:loading.attr="disabled">Rebuild schedule</button>
@endif
@if ($this->isOrganizer && $this->hasScheduledGames)
    <button type="button" class="hf-btn" wire:click="openReschedule" data-testid="schedule-reschedule" wire:loading.attr="disabled">Reschedule match</button>
@endif
```

Sidebar: when `canGenerate`, keep the `openPreview` button but label it `Build schedule preview`. When `canRebuild`, add:

```blade
<button type="button" class="hf-btn hf-btn--primary" wire:click="openRebuild" data-testid="schedule-rebuild-preview">Rebuild schedule</button>
```

Replace the entire `@if ($showPreview)` dialog with:

```blade
@if ($showPreview)
    <dialog
        class="hf-dialog hf-dialog--wide"
        x-data
        x-init="if (! $el.open) $el.showModal()"
        x-on:cancel.prevent="$wire.closePreview()"
        x-on:click="if ($event.target === $el) $wire.closePreview()"
        wire:key="schedule-preview-dialog"
        data-testid="schedule-preview-dialog"
    >
        <div class="hf-dialog__head">
            <h2 class="hf-dialog__title">{{ $previewMode === 'rebuild' ? 'Rebuild schedule' : 'Schedule preview' }}</h2>
            <button type="button" class="hf-icon-btn" wire:click="closePreview" aria-label="Close dialog" data-testid="dialog-close">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <div class="hf-dialog__body">
            <div class="hf-form-grid">
                <div class="hf-field">
                    <label class="hf-label" for="previewStart">Start date</label>
                    <input id="previewStart" class="hf-input" type="date" wire:model.live="previewStartDate" data-testid="preview-start">
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="previewEnd">End date</label>
                    <input id="previewEnd" class="hf-input" type="date" wire:model.live="previewEndDate" data-testid="preview-end">
                    @if ($previewEndDate === '')
                        <span class="hf-small hf-muted">No end date set.</span>
                    @endif
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="previewSlot">Slot minutes</label>
                    <input id="previewSlot" class="hf-input" type="number" min="10" max="240" wire:model.live="previewSlotMinutes" data-testid="preview-slot">
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="previewRest">Rest minutes</label>
                    <input id="previewRest" class="hf-input" type="number" min="0" max="120" wire:model.live="previewRestMinutes" data-testid="preview-rest">
                </div>
            </div>

            <div class="hf-field hf-mt-2">
                <span class="hf-label">Preferred match days</span>
                <div class="hf-btn-row">
                    @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day => $label)
                        <button
                            type="button"
                            class="hf-chip hf-chip--{{ in_array($day, $previewWeekdays, true) ? 'primary' : 'slate' }}"
                            wire:click="togglePreviewWeekday({{ $day }})"
                            aria-pressed="{{ in_array($day, $previewWeekdays, true) ? 'true' : 'false' }}"
                            data-testid="preview-weekday-{{ $day }}"
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <p class="hf-small hf-muted hf-mt-2" data-testid="preview-summary">
                {{ count($previewPlan['assignments'] ?? []) }} games ·
                {{ $previewPlan['summary']['matchDays'] ?? 0 }} match days ·
                ends {{ $previewPlan['summary']['lastDate'] ?? 'TBD' }} ·
                {{ count($previewWarnings) }} warnings ·
                {{ count($previewErrors) }} conflicts
            </p>

            @if ($previewDirty)
                <p class="hf-field-error" data-testid="preview-stale">Settings changed — rebuild the preview before publishing.</p>
            @endif

            @if ($previewErrors !== [])
                <div class="hf-card hf-card--flat hf-mb-2" data-testid="preview-errors">
                    @foreach ($previewErrors as $message)
                        <p class="hf-small hf-mb-0">{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            <div class="hf-table-wrap">
                <table class="hf-table">
                    <thead>
                        <tr>
                            <th scope="col">Round</th>
                            <th scope="col">Match</th>
                            <th scope="col">Court</th>
                            <th scope="col">Slot</th>
                            <th scope="col">Check</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->previewRows as $row)
                            <tr data-testid="preview-row-{{ $row['matchId'] }}">
                                <td>
                                    {{ $row['round'] }}
                                    @if ($row['game'])
                                        <span class="hf-small hf-muted">{{ $row['game'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $row['match'] }}</td>
                                <td>
                                    <select
                                        class="hf-select"
                                        wire:change="updatePreviewAssignment({{ $row['matchId'] }}, $event.target.value, '{{ $row['scheduledAt'] }}')"
                                        data-testid="preview-court-{{ $row['matchId'] }}"
                                    >
                                        @foreach ($courts as $courtOption)
                                            <option value="{{ $courtOption->id }}" @selected((int) $courtOption->id === $row['courtId'])>{{ $courtOption->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input
                                        class="hf-input"
                                        type="datetime-local"
                                        value="{{ $row['scheduledAt'] }}"
                                        wire:change="updatePreviewAssignment({{ $row['matchId'] }}, {{ $row['courtId'] }}, $event.target.value)"
                                        data-testid="preview-time-{{ $row['matchId'] }}"
                                    >
                                </td>
                                <td>
                                    @if ($row['error'])
                                        <span class="hf-field-error">{{ $row['error'] }}</span>
                                    @elseif ($row['warning'])
                                        <span class="hf-chip hf-chip--gold">{{ $row['warning'] }}</span>
                                    @else
                                        <span class="hf-chip hf-chip--success">OK</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="hf-dialog__foot">
            <button type="button" class="hf-btn" wire:click="closePreview" data-testid="schedule-preview-cancel">Cancel</button>
            <button type="button" class="hf-btn" wire:click="resetPreview" data-testid="preview-reset">Reset</button>
            <button type="button" class="hf-btn" wire:click="buildPreview" data-testid="preview-build" wire:loading.attr="disabled">Build preview</button>
            <button
                type="button"
                class="hf-btn hf-btn--primary"
                wire:click="publishSchedule"
                data-testid="schedule-preview-publish"
                wire:loading.attr="disabled"
                @disabled($previewDirty || $previewErrors !== [])
            >Publish schedule</button>
        </div>
    </dialog>
@endif
```

The confirm dialog already exists and handles `confirmAction === 'rebuild'` through the updated `confirm()`.

### Step 5: Run the tests

```powershell
cd src
php artisan test --filter="builds a preview from editable settings|marks the preview stale|impossible|prefills rebuild|removes cancelled series games|prototype schedule controls|reschedules a match"
```

Expected: PASS. If `wire:change` with `$event.target.value` does not update the preview, switch to a small Alpine handler that calls `$wire.updatePreviewAssignment(...)`; do not add a new JS bundle.

### Step 6: Commit

```powershell
git add src/app/Livewire/Organizer/ScheduleShow.php src/resources/views/livewire/organizer/schedule-show.blade.php src/tests/Feature/Pages/ScheduleTest.php
git commit -m "feat(schedule): editable preview and rebuild wizard"
```

---

## Task 8: Audit labels and demo fixtures for the new schedule actions

**Files:**
- Modify: `src/app/Livewire/Organizer/AuditLogIndex.php`
- Modify: `src/tests/Feature/Pages/AuditTrailTest.php`
- Modify: `src/database/seeders/BracketDemoSeeder.php`
- Modify: `src/tests/Feature/Database/BracketDemoSeederTest.php`

**Why:** `generate()` now publishes and logs `schedule.publish`, and rebuilds log `schedule.rebuild`. The audit page must label both. The demo double-elimination tournament must fit every pre-scheduled series game, so its window and slot expectations change.

### Step 1: Write the failing tests

In `src/tests/Feature/Pages/AuditTrailTest.php`, replace the schedule generation test (currently around line 207) with:

```php
it('records an audit entry when a schedule is published', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->update([
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
        ]);

    $this->actingAs($organizer);

    $lastId = (int) (AuditLog::where('action', 'schedule.publish')->max('id') ?? 0);

    app(SchedulingService::class)->generate($tournament);

    $log = AuditLog::where('action', 'schedule.publish')->where('id', '>', $lastId)->firstOrFail();

    expect($log->entity_type)->toBe('tournament')
        ->and((int) $log->entity_id)->toBe((int) $tournament->id)
        ->and((int) $log->actor_user_id)->toBe((int) $organizer->id)
        ->and($log->after_json['courts'])->toBe(4)
        ->and($log->after_json['slotMinutes'])->toBe(30)
        ->and($log->after_json['restMinutes'])->toBe(15)
        ->and($log->after_json['matches'])->toBeGreaterThan(0)
        ->and($log->after_json['firstDate'])->not->toBeNull()
        ->and($log->after_json['lastDate'])->not->toBeNull();
});

it('labels schedule publishes and rebuilds on the audit page', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->update([
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
        ]);

    $service = app(SchedulingService::class);
    $service->publish($tournament, $service->plan($tournament));
    $service->publish($tournament, $service->plan($tournament), 'schedule.rebuild');

    $this->actingAs($organizer)
        ->get('/audit')
        ->assertOk()
        ->assertSee('Schedule published')
        ->assertSee('Schedule rebuilt');
});
```

In `src/tests/Feature/Database/BracketDemoSeederTest.php`, replace the final scheduling test (currently "schedules every playable game and no conditional series game") with:

```php
it('schedules every playable and potential game inside the tournament dates', function () {
    $expected = [
        'demo-single-elimination' => 15,
        'demo-double-elimination' => 46,
        'demo-round-robin' => 28,
    ];

    foreach ($expected as $slug => $slotCount) {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();

        expect(MatchSlot::where('tournament_id', $tournament->id)->count())->toBe($slotCount);
    }

    $unscheduled = MatchModel::query()
        ->whereIn('status', ['scheduled', 'conditional'])
        ->whereDoesntHave('matchSlots')
        ->count();

    expect($unscheduled)->toBe(0);

    foreach (array_keys($expected) as $slug) {
        $tournament = Tournament::where('slug', $slug)->firstOrFail();
        $slots = MatchSlot::where('tournament_id', $tournament->id)->get();
        $first = Carbon::parse($slots->min('start_at'))->startOfDay();
        $last = Carbon::parse($slots->max('start_at'))->startOfDay();

        expect($first->gte($tournament->start_date?->copy()->startOfDay()))->toBeTrue()
            ->and($last->lte($tournament->end_date?->copy()->startOfDay()))->toBeTrue();
    }

    $conditional = MatchModel::where('status', 'conditional')->pluck('id');

    expect($conditional)->not->toBeEmpty()
        ->and(MatchSlot::whereIn('match_id', $conditional)->count())->toBe($conditional->count());
});
```

Add `use Carbon\Carbon;` to the imports.

### Step 2: Run the tests to confirm the failure

```powershell
cd src
php artisan test --filter="records an audit entry when a schedule is published|labels schedule publishes|schedules every playable and potential game"
```

Expected: FAIL. The audit entries are still `schedule.generate`, the labels do not exist, the demo double-elimination has 30 slots and a too-short window, and conditional games have no slots.

### Step 3: Add audit labels and summaries

In `src/app/Livewire/Organizer/AuditLogIndex.php`, add to `ACTION_LABELS` and keep the legacy label:

```php
'schedule.publish' => 'Schedule published',
'schedule.rebuild' => 'Schedule rebuilt',
'schedule.generate' => 'Schedule generated',
'schedule.reschedule' => 'Match rescheduled',
```

In `summary()`, route all three schedule actions through the existing helper:

```php
'schedule.publish', 'schedule.rebuild', 'schedule.generate' => $this->scheduleSummary($after),
```

Keep `schedule.generate` because `PrototypeSeeder` still seeds that action and the audit page must render it.

### Step 4: Extend the demo window and update expectations

In `src/database/seeders/BracketDemoSeeder.php`, change the double-elimination end date:

```php
'start' => '2026-12-08',
'end' => '2027-01-15',
```

Do not change the single-elimination (`2026-12-01` to `2026-12-04`) or round-robin (`2026-12-15` to `2026-12-21`) windows. Four daily rounds/one round per day fit as follows:

- Single elimination: 3 rounds → 3 match days.
- Double elimination: 4 winners rounds plus losers rounds and the grand final, with the quarterfinal series needing 3 consecutive days → comfortably inside 39 days.
- Round robin: 7 rounds, one round per day → exactly 7 days.

If the double-elimination seeding fails with "The tournament ends ... but this plan needs through ...", extend only the end date until it passes; do not delete series games or weaken the test.

### Step 5: Run the tests

```powershell
cd src
php artisan test --filter="records an audit entry|labels schedule|BracketDemoSeeder|schedules every playable and potential game"
```

Expected: PASS. The demo double-elimination now seeds 46 slots (30 anchors plus 16 series children).

### Step 6: Commit

```powershell
git add src/app/Livewire/Organizer/AuditLogIndex.php src/tests/Feature/Pages/AuditTrailTest.php src/database/seeders/BracketDemoSeeder.php src/tests/Feature/Database/BracketDemoSeederTest.php
git commit -m "test(demo): align audit labels and seeded series schedules"
```

---

## Task 9: Full quality gate and acceptance verification

**Files:** none (verification only)

**Why:** Every earlier task verifies one slice. This task proves the full product behavior and the repository quality gate before anyone claims the work is complete.

### Step 1: Run the complete automated suite

```powershell
cd src
php artisan test
```

Expected: PASS with zero failures. Record the final "Tests: ... passed" line. If anything fails, return to the owning task and fix it there; do not weaken assertions to get green.

### Step 2: Run the repository quality gate

```powershell
cd src
composer quality
```

`composer quality` runs, in order:

1. `pint --test` — style and unused imports.
2. `phpstan analyse --memory-limit=512M` — static analysis.
3. `php artisan config:clear` then `php artisan test` — the full suite again.

Expected: all three pass. If Pint reports style issues, run `vendor/bin/pint --dirty`, inspect the diff, and rerun `composer quality`. If PHPStan reports a type problem, fix the production code or add a precise docblock; do not lower the analysis level. Report the actual final output in the handoff, including any warnings that are not failures.

### Step 3: Verify the acceptance walkthrough

Seed a clean demo database and start the app:

```powershell
cd src
php artisan migrate:fresh
php artisan db:seed --class=BracketDemoSeeder
php artisan serve
```

Sign in with the seeded accounts (`organizer@hoopflow.ph`, `coach.marcus@hoopflow.ph`, `fan.juan@hoopflow.ph`; password `password`). Check each item:

1. **Bracket action matrix** — open a series in the double-elimination demo.
   - Organizer: a final game shows `Correct score` and `View box score`; a scheduled game shows `Enter final score`, `Open scoring` when active, and `View box score`; a conditional or cancelled game still shows `View box score`.
   - Coach and fan: every series game shows only `View box score`; no `Correct score`, `Open`, `Score`, `Enter final score`, or `In-game score & player stats`.
2. **Game chooser** — organizer clicks `In-game score & player stats` on a series.
   - Dialog lists Game 1..N with score, schedule, and status.
   - The active game opens scoring; a conditional game explains `Available after Game N-1 is final`; a decided game explains it is not needed; every row has `View box score`.
3. **Per-game box score** — open each row's `View box score`.
   - Final: `Official box score`.
   - Scheduled with no stats: `No stats yet.` plus date, time, court.
   - Scheduled with a draft: `Draft box score` and the not-official warning.
   - Conditional: `This game is only played if needed.`
   - Cancelled: `This game was cancelled when the series ended early.`
   - The series strip stays visible and links to the other games.
4. **Generated schedule** — on the single-elimination or double-elimination demo, confirm every series game has a slot. Games in one series share a court and tip-off time and land on consecutive eligible days; the next round starts after the previous round's last game.
5. **Editable preview** — click `Build schedule preview` on a tournament with no results.
   - Change start date, end date, weekdays, slot minutes, or rest minutes: the stale hint appears and Publish is disabled.
   - Rebuild the preview; edit one row's court and time; publish; confirm the database/calendar matches the edited row exactly.
   - Set the start date after the end date and confirm the actionable error.
6. **Rebuild** — on a published schedule with no results, click `Rebuild schedule`.
   - The panel prefills the first published date, the weekdays actually used, and the rules timing.
   - Publishing asks for confirmation, then replaces the schedule and reports games, match days, and end date.
7. **Regenerate bracket** — with no results, confirm regeneration shuffles teams, sets random seeding, and clears the published schedule.
8. **Calendar consistency** — end a best-of-3 series 2-0; the unused game disappears from the calendar and its slot is released. Correct the series back to 1-1; the game returns on the same lane when the window allows, or the organizer sees a warning that it could not be rescheduled.
9. **Responsive check** — inspect the bracket series table, chooser, preview dialog, and schedule page at 375, 768, and 1280 px. Tables stack without hiding the `View box score` action; dialogs close on Escape and return focus to the trigger.

### Step 4: Confirm git hygiene and report

```powershell
git status --short
git log --oneline -12
```

Expected: only `.obsidian/graph.json` (pre-existing) and untracked `.kilo/` remain. Every task commit is present. Do not commit either unrelated path.

Final handoff must state:

- the exact `php artisan test` summary,
- the exact `composer quality` result,
- which acceptance items were exercised manually and any that were not,
- any residual limitation (for example, a rescheduled follow-up game that cannot fit before the end date).

---

## Plan self-review

### Spec coverage

| Spec area | Implementing tasks | Notes |
|---|---|---|
| Per-game series actions and accurate box score | 4, 6 | Every series row has `View box score`; organizer-only Correct/Open/Score; chooser and stats page preserve the exact game ID. |
| Game 1..N selection before in-game scoring | 4, 5 | Chooser in the bracket; explicit child selection and series switcher in `GameManage`. |
| Every series game scheduled | 1, 8 | `plan()` schedules series units including conditional children; demo fixtures assert 46 slots. |
| Random bracket regeneration | 3, 4 | `generate(..., true)` shuffles, writes `seeding_method = random`, audit fields, and clears slots via publish. |
| Preview with editable settings and exact publish | 2, 7 | `validatePlan()`, `publish()` exact assignments, stale guard, per-row court/time edits, reset. |
| Rebuild with weekdays and end-date limits | 1, 2, 7 | Weekday chips, end-date validation, projected-end error, rebuild prefill and confirmation. |
| Role-safe controls | 4, 6 | Blade `$isOrganizer` gating for series actions, detail footer, chooser, and stats buttons; policies remain authoritative. |
| Slot release and correction revival | 3, 7 | `releaseSlots()` in all cancellation paths; cancelled calendar filtering; fallback activation and warning. |
| Error handling and feedback | 1–8 | Specific toast/inline messages, stale preview, conflict rows, unavailable-game reasons, empty box score states. |
| Demo fixtures and audit labels | 8 | 46 double-elimination slots, extended window, `schedule.publish`/`schedule.rebuild` labels while keeping legacy `schedule.generate`. |

### Placeholder and consistency scan

- No `<!-- APPEND -->`, `TODO`, or unfinished command blocks remain in this plan.
- `SchedulingService::plan()` returns `assignments`, `courts`, `slotMinutes`, `restMinutes`, `startDate`, `endDate`, `weekdays`, and `summary`; every consumer in Tasks 2 and 7 uses those keys.
- `SchedulingService::publish(Tournament, array, string = 'schedule.publish')` returns `int`; `generate()` remains `int`; `BracketService::generate()` remains `array` and gains the optional `bool $shuffle`.
- `validatePlan()` returns `errors`, `warnings`, and `valid`; both keys are keyed by match ID and stored in `array<int, string>` Livewire properties.
- `BracketShow` chooser state (`showGameChooser`, `chooserMatchId`) and computed methods (`chooserMatch`, `chooserAnchor`, `chooserGames`, `chooserSummary`) are new names and do not collide with the existing series computed methods.
- `GameStatsShow` adds `seriesAnchor`, `seriesGames`, `seriesSummary`, `boxScoreLabel`, and `boxScoreMessage`; no existing method or property uses those names.
- `GameManage` adds `seriesGameUnavailableReason()` and keeps the existing `seriesGames`, `seriesAnchor`, `seriesStatusLabel`, and `seriesStatusTone` contracts.
- `ScheduleShow` reuses the existing `showPreview`, `previewPlan`, `showConfirm`, `openConfirm`, `resetConfirm`, `toast`, `scheduledSlots`, `resultsCount`, and `hasScheduledGames`; new names are prefixed `preview*` and do not collide.
- Blade snippets use variables already defined in their views: `$isOrganizer` (bracket and schedule views), `$courts` (schedule view), `$match`, `$game`, `$row`, and the computed properties declared above.

### Ordering and dependencies

1. Task 1 establishes the new scheduling model and plan shape.
2. Task 2 adds validation and exact publish on top of that shape; all later schedule UI depends on both.
3. Task 3 changes bracket lifecycle behavior and slot release; it must precede the calendar and box score state tests.
4. Tasks 4–6 are presentation and navigation, each depending only on the completed services.
5. Task 7 depends on Tasks 1–3 and on the rebuild entry points.
6. Task 8 depends on Task 2 (audit action) and Task 1 (series slots).
7. Task 9 is the final gate; it must run after every commit.

No task relies on a signature introduced later. Each commit leaves the suite runnable, except the deliberate red/green test steps inside a task.

### Known risks and mitigations

- **Livewire `wire:change` expressions** — if `$event.target.value` does not bind in this Livewire version, use the existing Alpine bridge (`x-on:change="$wire.updatePreviewAssignment(...)"`) without adding a JS bundle.
- **Dialog stacking** — the rebuild confirmation opens above the preview dialog. Verify Escape and backdrop behavior manually; if the preview blocks the confirmation, close the preview state before opening the confirmation and reopen it on cancel.
- **Date math** — assert month names in messages, not raw dates, where the spec allows. If a projected end lands one day later than expected, fix the candidate-day loop rather than relaxing the assertion.
- **SQLite vs PostgreSQL** — the tests run on SQLite; `publish()` uses Eloquent, not raw insert SQL, so timestamps and sequences stay portable.
- **PHPStan on Blade-heavy components** — add precise property docblocks only where the component exposes computed arrays; do not suppress errors globally.
- **Seeder windows** — if the double-elimination plan still exceeds `2027-01-15`, extend the end date rather than deleting pre-scheduled series games; the point of Task 8 is that every potential game fits.

### Definition of done

- `php artisan test` is green.
- `composer quality` is green.
- Every acceptance item in Task 9 has been exercised, with the actual output reported.
- `git status --short` shows only the pre-existing `.obsidian/graph.json` and untracked `.kilo/`.
- No new migration, no Tailwind/Bootstrap JS, no committed `.env` or credentials.


