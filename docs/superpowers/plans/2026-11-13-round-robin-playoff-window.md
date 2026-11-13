# Round-Robin Playoff Window Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reserve the last eligible match days for the round-robin playoffs so incremental playoff scheduling always has room, while never moving published or played group slots.

**Architecture:** Add pure footprint/window calculations to `SchedulingService`, use the window's `groupEndDate` as a hard cutoff in the full planner, enforce it in plan validation, and surface the window in the preview dialog and calendar. Remaining-games planning stays additive and unchanged in its slot-handling rules.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4, Blade, Bootstrap 5 SCSS, Pest, Carbon, SQLite for tests.

## Global Constraints

- Run all commands from `src/`.
- Run tests sequentially, never in parallel.
- Use `COMPOSER_PROCESS_TIMEOUT=0` for `composer quality`.
- Do not touch `.obsidian/graph.json` or untracked `.kilo/`.
- Never move, delete, or replace published or played group slots.
- Full generate/rebuild stays locked once `final`, `forfeit`, or `in_progress` results exist.
- Follow existing Pint, PHPStan, Livewire, and Blade conventions.
- Best-of values are only `1`, `3`, `5`, `7`, or `9`; anything else falls back to `1`.
- Reservation applies only to `round-robin` tournaments with `playoff_teams` equal to `2`, `4`, `8`, or `16`.
- No database migration or schema change.

## File Structure

- `src/app/Services/SchedulingService.php` — footprint, window, overlap, planner cutoff, validation, audit metadata.
- `src/app/Livewire/Organizer/ScheduleShow.php` — preview/calendar computed properties and overlap warning data.
- `src/resources/views/livewire/organizer/schedule-show.blade.php` — preview panel, legacy warning, calendar classes, legend.
- `src/resources/css/_hoopflow.scss` — reserved calendar and week-strip styles.
- `src/tests/Unit/Services/SchedulingServiceTest.php` — service tests and fixtures.
- `src/tests/Feature/Pages/ScheduleTest.php` — schedule page tests and legacy fixture.
- `docs/superpowers/plans/2026-11-13-round-robin-playoff-window.md` — this plan.

---

### Task 1: Playoff footprint

**Files:**
- Modify: `src/app/Services/SchedulingService.php`
- Test: `src/tests/Unit/Services/SchedulingServiceTest.php`

**Interfaces:**
- Produces: `SchedulingService::playoffFootprint(Tournament $tournament): ?array` returning `['days' => int, 'rounds' => list<array{label: string, bestOf: int, days: int}>]`.
- Produces: `SchedulingService::bestOfValue(array $rules, string $key): int` (protected helper).

- [ ] **Step 1: Add the test fixture helper**

Append after the existing `schedulingRoundRobinPlayoffTournament()` helper in `src/tests/Unit/Services/SchedulingServiceTest.php`:

```php
/**
 * @param  array<string, mixed>  $rules
 */
function schedulingConfiguredTournament(
    array $rules,
    string $start = '2026-07-10',
    string $end = '2026-07-31',
): Tournament {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'start_date' => $start,
        'end_date' => $end,
        'rules_json' => array_merge($tournament->rules_json ?? [], $rules),
    ]);

    return $tournament->fresh();
}
```

- [ ] **Step 2: Write the failing footprint tests**

Append after the fixture helper:

```php
it('reserves a four-day footprint for four playoff teams and a best-of-three final', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ]);

    $footprint = (new SchedulingService)->playoffFootprint($tournament);

    expect($footprint)->not->toBeNull()
        ->and($footprint['days'])->toBe(4)
        ->and($footprint['rounds'])->toBe([
            ['label' => 'Semifinal', 'bestOf' => 1, 'days' => 1],
            ['label' => 'Final', 'bestOf' => 3, 'days' => 3],
        ]);
});

it('sums quarterfinal, semifinal, and final best-of games for eight playoff teams', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 8,
        'best_of_quarterfinal' => 3,
        'best_of_semifinal' => 5,
        'best_of_final' => 7,
    ]);

    $footprint = (new SchedulingService)->playoffFootprint($tournament);

    expect($footprint['days'])->toBe(15)
        ->and($footprint['rounds'])->toBe([
            ['label' => 'Quarterfinal', 'bestOf' => 3, 'days' => 3],
            ['label' => 'Semifinal', 'bestOf' => 5, 'days' => 5],
            ['label' => 'Final', 'bestOf' => 7, 'days' => 7],
        ]);
});

it('includes a single round-of-16 day for sixteen playoff teams', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 16,
    ]);

    $footprint = (new SchedulingService)->playoffFootprint($tournament);

    expect($footprint['days'])->toBe(4)
        ->and($footprint['rounds'])->toBe([
            ['label' => 'Round of 16', 'bestOf' => 1, 'days' => 1],
            ['label' => 'Quarterfinal', 'bestOf' => 1, 'days' => 1],
            ['label' => 'Semifinal', 'bestOf' => 1, 'days' => 1],
            ['label' => 'Final', 'bestOf' => 1, 'days' => 1],
        ]);
});

it('falls back to one day for invalid best-of values', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 2,
        'best_of_final' => 4,
    ]);

    $footprint = (new SchedulingService)->playoffFootprint($tournament);

    expect($footprint['days'])->toBe(1)
        ->and($footprint['rounds'])->toBe([
            ['label' => 'Final', 'bestOf' => 1, 'days' => 1],
        ]);
});

it('does not reserve a footprint without a round-robin playoff field', function () {
    $single = schedulingConfiguredTournament([
        'format' => 'single-elimination',
        'playoff_teams' => 4,
    ]);
    $none = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 0,
    ]);
    $odd = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 6,
    ]);

    $service = new SchedulingService;

    expect($service->playoffFootprint($single))->toBeNull()
        ->and($service->playoffFootprint($none))->toBeNull()
        ->and($service->playoffFootprint($odd))->toBeNull();
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="footprint|playoff teams|best-of values|playoff field"
```

Expected: FAIL with `Call to undefined method App\Services\SchedulingService::playoffFootprint()`.

- [ ] **Step 4: Implement the footprint**

Insert these methods in `src/app/Services/SchedulingService.php` after `remainingStartDate()` and before `slotMinutes()`:

```php
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
 * @param  array<string, mixed>  $rules
 */
protected function bestOfValue(array $rules, string $key): int
{
    $value = $rules[$key] ?? 1;

    return is_numeric($value) && in_array((int) $value, [1, 3, 5, 7, 9], true)
        ? (int) $value
        : 1;
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="footprint|playoff teams|best-of values|playoff field"
```

Expected: PASS.

- [ ] **Step 6: Format and commit**

Run:

```powershell
vendor/bin/pint app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git add app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git commit -m "feat: calculate round-robin playoff footprint"
```

---

### Task 2: Playoff window and group deadline

**Files:**
- Modify: `src/app/Services/SchedulingService.php`
- Test: `src/tests/Unit/Services/SchedulingServiceTest.php`

**Interfaces:**
- Consumes: `playoffFootprint()` from Task 1.
- Produces: `SchedulingService::playoffWindow(Tournament $tournament, array $options = []): ?array` returning `startDate`, `endDate`, `groupEndDate`, `dates`, `dayCount`, `rounds`.

- [ ] **Step 1: Write the failing window tests**

Append to `src/tests/Unit/Services/SchedulingServiceTest.php`:

```php
it('reserves the last eligible days before the tournament ends', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ]);

    $window = (new SchedulingService)->playoffWindow($tournament);

    expect($window)->not->toBeNull()
        ->and($window['startDate'])->toBe('2026-07-28')
        ->and($window['endDate'])->toBe('2026-07-31')
        ->and($window['groupEndDate'])->toBe('2026-07-27')
        ->and($window['dates'])->toBe([
            '2026-07-28',
            '2026-07-29',
            '2026-07-30',
            '2026-07-31',
        ])
        ->and($window['dayCount'])->toBe(4)
        ->and($window['rounds'])->toBe([
            ['label' => 'Semifinal', 'bestOf' => 1, 'days' => 1],
            ['label' => 'Final', 'bestOf' => 3, 'days' => 3],
        ]);
});

it('reserves only selected weekdays when walking backwards', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 2,
        'best_of_final' => 3,
    ]);

    $window = (new SchedulingService)->playoffWindow($tournament, ['weekdays' => [5, 6]]);

    expect($window)->not->toBeNull()
        ->and($window['dates'])->toBe(['2026-07-24', '2026-07-25', '2026-07-31'])
        ->and($window['groupEndDate'])->toBe('2026-07-18');
});

it('moves the window when the end date is overridden', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ]);

    $window = (new SchedulingService)->playoffWindow($tournament, ['endDate' => '2026-07-17']);

    expect($window['dates'])->toBe([
        '2026-07-14',
        '2026-07-15',
        '2026-07-16',
        '2026-07-17',
    ])->and($window['groupEndDate'])->toBe('2026-07-13');
});

it('returns no window when the tournament is too short for the playoffs', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ], '2026-07-10', '2026-07-12');

    expect((new SchedulingService)->playoffWindow($tournament))->toBeNull();
});

it('returns no window when the tournament has no end date', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
    ]);
    $tournament->update(['end_date' => null]);

    expect((new SchedulingService)->playoffWindow($tournament->fresh()))->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="reserves the last eligible days|selected weekdays|end date is overridden|too short|no end date"
```

Expected: FAIL with `Call to undefined method App\Services\SchedulingService::playoffWindow()`.

- [ ] **Step 3: Implement the window and eligible-day helper**

Insert `playoffWindow()` immediately after `playoffFootprint()` in `src/app/Services/SchedulingService.php`:

```php
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
```

Insert this protected helper next to `eligibleGameDays()`:

```php
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
```

- [ ] **Step 4: Run the tests to verify they pass**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="reserves the last eligible days|selected weekdays|end date is overridden|too short|no end date"
```

Expected: PASS.

- [ ] **Step 5: Format and commit**

Run:

```powershell
vendor/bin/pint app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git add app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git commit -m "feat: reserve the round-robin playoff window"
```

---

### Task 3: Planner cutoff and reservation errors

**Files:**
- Modify: `src/app/Services/SchedulingService.php`
- Test: `src/tests/Unit/Services/SchedulingServiceTest.php`

**Interfaces:**
- Consumes: `playoffWindow()` and `eligibleDayCount()` from Task 2.
- Produces: every plan includes `playoffWindow` (the Task 2 shape, or `null`); full plans stop at `groupEndDate`.

- [ ] **Step 1: Write the failing planner tests**

Append to `src/tests/Unit/Services/SchedulingServiceTest.php`:

```php
it('plans group rounds before the reserved playoff window', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ]);
    (new BracketService)->generate($tournament, 'round-robin');

    $plan = (new SchedulingService)->plan($tournament);
    $dates = collect($plan['assignments'])
        ->map(fn (array $assignment): string => substr($assignment['scheduledAt'], 0, 10));

    expect($plan['playoffWindow'])->not->toBeNull()
        ->and($plan['playoffWindow']['startDate'])->toBe('2026-07-28')
        ->and($dates->max() <= '2026-07-27')->toBeTrue()
        ->and($plan['endDate'])->toBe('2026-07-31');
});

it('keeps the remaining playoff plan inside the reserved window', function () {
    [$tournament] = schedulingRoundRobinPlayoffTournament();

    $service = new SchedulingService;
    $window = $service->playoffWindow($tournament);
    $plan = $service->planRemaining($tournament);
    $lastDate = collect($plan['assignments'])
        ->map(fn (array $assignment): string => substr($assignment['scheduledAt'], 0, 10))
        ->max();

    expect($window)->not->toBeNull()
        ->and($lastDate <= $window['endDate'])->toBeTrue();
});

it('refuses a group plan when the tournament cannot hold the playoff window', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ], '2026-07-10', '2026-07-12');
    (new BracketService)->generate($tournament, 'round-robin');

    expect(fn () => (new SchedulingService)->plan($tournament))
        ->toThrow(
            InvalidArgumentException::class,
            'This schedule window has 3 eligible match days, but the playoffs need 4.',
        );

    expect(MatchSlot::query()->where('tournament_id', $tournament->id)->count())->toBe(0);
});

it('refuses a group plan when the playoff window consumes every match day', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ], '2026-07-10', '2026-07-13');
    (new BracketService)->generate($tournament, 'round-robin');

    expect(fn () => (new SchedulingService)->plan($tournament))
        ->toThrow(
            InvalidArgumentException::class,
            'leaves no match days for the group stage',
        );
});

it('refuses a group plan that runs into the reserved playoff window', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ], '2026-07-10', '2026-07-17');
    (new BracketService)->generate($tournament, 'round-robin');

    expect(fn () => (new SchedulingService)->plan($tournament))
        ->toThrow(
            InvalidArgumentException::class,
            'The group stage must finish by Jul 13, 2026 to keep Jul 14, 2026–Jul 17, 2026 free for the playoffs',
        );

    expect(MatchSlot::query()->where('tournament_id', $tournament->id)->count())->toBe(0);
});

it('keeps the existing end date error when no playoff window applies', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 0,
    ], '2026-07-10', '2026-07-12');
    (new BracketService)->generate($tournament, 'round-robin');

    expect(fn () => (new SchedulingService)->plan($tournament))
        ->toThrow(
            InvalidArgumentException::class,
            'The tournament ends Jul 12, 2026, but this plan needs through',
        );
});

it('keeps planning without an end date when no reservation is possible', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
    ]);
    $tournament->update(['end_date' => null]);
    (new BracketService)->generate($tournament->fresh(), 'round-robin');

    $plan = (new SchedulingService)->plan($tournament->fresh());

    expect($plan['playoffWindow'])->toBeNull()
        ->and($plan['assignments'])->not->toBeEmpty();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="reserved playoff window|playoff window consumes|cannot hold the playoff window|existing end date error|without an end date|remaining playoff plan inside"
```

Expected: FAIL because plans still run through the tournament end and have no `playoffWindow` key.

- [ ] **Step 3: Compute the window in `buildPlan()`**

In `src/app/Services/SchedulingService.php`, immediately after the `if ($weekdays === [])` block in `buildPlan()`, insert:

```php
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
```

- [ ] **Step 4: Add the remaining no-room check**

In `buildPlan()`, immediately after the `$endDate` assignment block (the `elseif ($tournamentEnd instanceof Carbon)` block) and before `if ($endDate instanceof Carbon)`, insert:

```php
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
```

- [ ] **Step 5: Replace the overflow check**

Replace this block inside the round placement loop:

```php
if ($endDate instanceof Carbon && $projectedLast->gt($endDate)) {
    throw new InvalidArgumentException(sprintf(
        'The tournament ends %s, but this plan needs through %s. Add weekdays, shorten slots, or extend the end date.',
        $endDate->format('M j, Y'),
        $projectedLast->format('M j, Y'),
    ));
}
```

with:

```php
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
```

- [ ] **Step 6: Add the plan metadata**

In the `return` array of `buildPlan()`, add this key after `'weekdays'`:

```php
'playoffWindow' => $playoffWindow,
```

Update the three return-shape docblocks (`plan()`, `planRemaining()`, `buildPlan()`) by adding:

```php
*     playoffWindow: array{startDate: string, endDate: string, groupEndDate: string|null, dates: list<string>, dayCount: int, rounds: list<array{label: string, bestOf: int, days: int}>}|null,
```

- [ ] **Step 7: Run the tests to verify they pass**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="reserved playoff window|playoff window consumes|cannot hold the playoff window|existing end date error|without an end date|remaining playoff plan inside"
```

Expected: PASS.

- [ ] **Step 8: Format and commit**

Run:

```powershell
vendor/bin/pint app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git add app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git commit -m "feat: stop group scheduling at the playoff window"
```

---

### Task 4: Legacy no-room error and overlap helper

**Files:**
- Modify: `src/app/Services/SchedulingService.php`
- Test: `src/tests/Unit/Services/SchedulingServiceTest.php`

**Interfaces:**
- Produces: `SchedulingService::playoffWindowOverlap(Tournament $tournament, array $window): list<string>`.
- Produces: clearer `planRemaining()` error when published slots leave no room.

- [ ] **Step 1: Add the legacy fixture**

Append to `src/tests/Unit/Services/SchedulingServiceTest.php`:

```php
/**
 * @return array{Tournament, Collection<int, MatchModel>}
 */
function schedulingLegacyRoundRobinPlayoffTournament(): array
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-16',
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'format' => 'round-robin',
            'playoff_teams' => 0,
        ]),
    ]);
    $tournament = $tournament->fresh();

    $bracketService = new BracketService;
    $bracketService->generate($tournament, 'round-robin');
    app(SchedulingService::class)->generate($tournament);

    $groupMatches = MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->whereHas('bracket', fn ($query) => $query->where('phase', 'group'))
        ->orderBy('id')
        ->get();

    foreach ($groupMatches as $match) {
        $bracketService->recordScore($match->fresh(), 80, 70);
    }

    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'playoff_teams' => 4,
        ]),
    ]);

    $bracketService->generatePlayoffs($tournament->fresh());

    return [$tournament->fresh(), $groupMatches];
}
```

- [ ] **Step 2: Write the failing tests**

Append after the fixture:

```php
it('reports no room when a legacy group schedule fills the tournament dates', function () {
    [$tournament] = schedulingLegacyRoundRobinPlayoffTournament();

    expect(fn () => (new SchedulingService)->planRemaining($tournament))
        ->toThrow(
            InvalidArgumentException::class,
            'The published schedule leaves no room for the remaining games',
        );
});

it('finds published group days inside the playoff window', function () {
    [$tournament] = schedulingLegacyRoundRobinPlayoffTournament();

    $service = new SchedulingService;
    $window = $service->playoffWindow($tournament);

    expect($window)->not->toBeNull()
        ->and($window['startDate'])->toBe('2026-07-15')
        ->and($window['endDate'])->toBe('2026-07-16')
        ->and($service->playoffWindowOverlap($tournament, $window))
        ->toBe(['2026-07-15', '2026-07-16']);
});

it('ignores playoff slots when finding group days in the window', function () {
    [$tournament] = schedulingRoundRobinPlayoffTournament();

    $playoffMatch = MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->whereHas('bracket', fn ($query) => $query->where('phase', 'playoffs'))
        ->firstOrFail();
    $court = app(SchedulingService::class)->courts($tournament)->firstOrFail();

    MatchSlot::create([
        'match_id' => $playoffMatch->id,
        'tournament_id' => $tournament->id,
        'court_id' => $court->id,
        'start_at' => '2026-07-18 15:00:00',
        'end_at' => '2026-07-18 15:30:00',
        'status' => 'published',
        'version' => 1,
        'published_at' => now(),
    ]);

    $window = ['startDate' => '2026-07-18', 'endDate' => '2026-07-18'];

    expect((new SchedulingService)->playoffWindowOverlap($tournament, $window))->toBe([]);
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="legacy group schedule|group days inside|ignores playoff slots"
```

Expected: FAIL with `Call to undefined method App\Services\SchedulingService::playoffWindowOverlap()` and the generic end-date error.

- [ ] **Step 4: Implement the overlap helper**

Insert after `playoffWindow()` in `src/app/Services/SchedulingService.php`:

```php
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
```

- [ ] **Step 5: Confirm the no-room check order**

The remaining no-room check added in Task 3 must run immediately after `$endDate` is assigned and **before** this existing block:

```php
if ($endDate instanceof Carbon) {
    if ($endDate->lt($startDate)) {
        throw new InvalidArgumentException('The end date must be on or after the start date.');
    }
```

If it is anywhere else, move it there. This ensures a legacy schedule ending on the tournament end date reports the no-room message instead of the generic end-date message.

- [ ] **Step 6: Run the tests to verify they pass**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="legacy group schedule|group days inside|ignores playoff slots"
```

Expected: PASS.

- [ ] **Step 7: Format and commit**

Run:

```powershell
vendor/bin/pint app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git add app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git commit -m "feat: detect legacy schedules with no playoff room"
```

---

### Task 5: Validate the group deadline

**Files:**
- Modify: `src/app/Services/SchedulingService.php`
- Test: `src/tests/Unit/Services/SchedulingServiceTest.php`

**Interfaces:**
- Consumes: `playoffWindow()` from Task 2.
- Produces: `validatePlan()` rejects group-phase assignments after `groupEndDate`; remaining playoff assignments in the window stay valid.

- [ ] **Step 1: Write the failing validation tests**

Append to `src/tests/Unit/Services/SchedulingServiceTest.php`:

```php
it('rejects a group game moved past the group-stage deadline', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ]);
    (new BracketService)->generate($tournament, 'round-robin');

    $service = new SchedulingService;
    $plan = $service->plan($tournament);
    $matchId = (int) $plan['assignments'][0]['matchId'];
    $plan['assignments'][0]['scheduledAt'] = '2026-07-28 15:00:00';

    $validation = $service->validatePlan($tournament, $plan);

    expect($validation['valid'])->toBeFalse()
        ->and($validation['errors'][$matchId])->toContain('after the group-stage deadline');
});

it('allows remaining playoff games inside the reserved window', function () {
    [$tournament] = schedulingRoundRobinPlayoffTournament();

    $service = app(SchedulingService::class);
    $plan = $service->planRemaining($tournament);
    $plan['assignments'][0]['scheduledAt'] = '2026-07-30 15:00:00';

    $validation = $service->validatePlan($tournament, $plan, true);

    expect($validation['valid'])->toBeTrue();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="moved past the group-stage deadline|inside the reserved window"
```

Expected: the first test FAILS because the moved group game is accepted.

- [ ] **Step 3: Load the bracket and compute the validation cutoff**

In `validatePlan()`, add `->with('bracket:id,phase')` to the `$schedulable` query, immediately before `->get()`.

After the `$weekdays` assignment, insert:

```php
$windowOptions = ['weekdays' => array_values($weekdays)];

if ($endDate instanceof Carbon) {
    $windowOptions['endDate'] = $endDate->format('Y-m-d');
}

$playoffWindow = $this->playoffWindow($tournament, $windowOptions);
$groupEndDate = ! $remaining && $playoffWindow !== null && $playoffWindow['groupEndDate'] !== null
    ? Carbon::parse($playoffWindow['groupEndDate'])->startOfDay()
    : null;
```

- [ ] **Step 4: Reject group assignments after the cutoff**

Inside the assignment loop, after the existing end-date check and before the weekday warning, insert:

```php
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
```

- [ ] **Step 5: Run the tests to verify they pass**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="moved past the group-stage deadline|inside the reserved window"
```

Expected: PASS.

- [ ] **Step 6: Format and commit**

Run:

```powershell
vendor/bin/pint app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git add app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git commit -m "feat: validate the reserved group-stage deadline"
```

---

### Task 6: Audit metadata

**Files:**
- Modify: `src/app/Services/SchedulingService.php`
- Test: `src/tests/Unit/Services/SchedulingServiceTest.php`

**Interfaces:**
- Consumes: plan `playoffWindow` from Task 3.
- Produces: `playoffWindowStart`, `playoffWindowEnd`, `playoffReservedDays` in schedule audit metadata.

- [ ] **Step 1: Write the failing audit test**

Append to `src/tests/Unit/Services/SchedulingServiceTest.php`:

```php
it('records the reserved playoff window in the publish audit entry', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ]);
    (new BracketService)->generate($tournament, 'round-robin');
    app(SchedulingService::class)->generate($tournament);

    $log = AuditLog::query()
        ->where('action', 'schedule.publish')
        ->latest('id')
        ->firstOrFail();

    expect($log->after_json['playoffWindowStart'])->toBe('2026-07-28')
        ->and($log->after_json['playoffWindowEnd'])->toBe('2026-07-31')
        ->and($log->after_json['playoffReservedDays'])->toBe(4);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="publish audit entry"
```

Expected: FAIL with an undefined array key.

- [ ] **Step 3: Add the metadata**

In `publish()`, immediately before the `app(AuditLogger::class)->log(...)` call, insert:

```php
$window = is_array($plan['playoffWindow'] ?? null) ? $plan['playoffWindow'] : null;
```

Add these keys to the audit metadata array:

```php
'playoffWindowStart' => $window['startDate'] ?? null,
'playoffWindowEnd' => $window['endDate'] ?? null,
'playoffReservedDays' => $window['dayCount'] ?? 0,
```

Repeat the same variable and keys in `publishRemaining()`.

- [ ] **Step 4: Run the test to verify it passes**

Run:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php --filter="publish audit entry"
```

Expected: PASS.

- [ ] **Step 5: Format and commit**

Run:

```powershell
vendor/bin/pint app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git add app/Services/SchedulingService.php tests/Unit/Services/SchedulingServiceTest.php
git commit -m "feat: audit the reserved playoff window"
```

---

### Task 7: Preview panel and legacy warning

**Files:**
- Modify: `src/app/Livewire/Organizer/ScheduleShow.php`
- Modify: `src/resources/views/livewire/organizer/schedule-show.blade.php`
- Test: `src/tests/Feature/Pages/ScheduleTest.php`

**Interfaces:**
- Consumes: `playoffWindow()` and `playoffWindowOverlap()` from Tasks 2 and 4.
- Produces: `ScheduleShow::previewPlayoffWindow()` and `ScheduleShow::legacyPlayoffOverlap()` computed properties.

- [ ] **Step 1: Add the legacy feature fixture**

Append to `src/tests/Feature/Pages/ScheduleTest.php` after `scheduleRoundRobinPlayoffTournament()`:

```php
/**
 * @return array{Tournament, Collection<int, MatchModel>}
 */
function scheduleLegacyRoundRobinPlayoffTournament(): array
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-16',
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'format' => 'round-robin',
            'playoff_teams' => 0,
        ]),
    ]);
    $tournament = $tournament->fresh();

    $bracketService = new BracketService;
    $bracketService->generate($tournament, 'round-robin');
    app(SchedulingService::class)->generate($tournament);

    $groupMatches = MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->whereHas('bracket', fn ($query) => $query->where('phase', 'group'))
        ->orderBy('id')
        ->get();

    foreach ($groupMatches as $match) {
        $bracketService->recordScore($match->fresh(), 80, 70);
    }

    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'playoff_teams' => 4,
        ]),
    ]);

    $bracketService->generatePlayoffs($tournament->fresh());

    return [$tournament->fresh(), $groupMatches];
}
```

- [ ] **Step 2: Write the failing feature tests**

Append to `src/tests/Feature/Pages/ScheduleTest.php`:

```php
it('shows the reserved playoff window in the schedule preview', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    [$tournament] = scheduleRoundRobinPlayoffTournament();

    $component = Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openRemaining')
        ->assertSeeHtml('data-testid="preview-playoff-window"')
        ->assertSee('Playoffs reserved')
        ->assertSee('Jul 30, 2026–Jul 31, 2026')
        ->assertSee('Group stage must finish by Jul 29, 2026.')
        ->assertSee('Semifinal: 1 day')
        ->assertSee('Final: 1 day');

    $window = $component->instance()->previewPlayoffWindow;

    expect($window['dates'])->toBe(['2026-07-30', '2026-07-31'])
        ->and($window['groupEndDate'])->toBe('2026-07-29');
});

it('updates the reserved playoff window when preview weekdays change', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    [$tournament] = scheduleRoundRobinPlayoffTournament();

    $component = Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openRemaining');

    foreach ([2, 3, 4, 5, 6, 7] as $day) {
        $component->call('togglePreviewWeekday', $day);
    }

    $component
        ->assertSet('previewWeekdays', [1])
        ->assertSee('Jul 20, 2026–Jul 27, 2026');
});

it('warns when a legacy group schedule occupies the playoff window', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    [$tournament] = scheduleLegacyRoundRobinPlayoffTournament();

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openRemaining')
        ->assertSet('showPreview', true)
        ->assertSeeHtml('data-testid="preview-playoff-overlap"')
        ->assertSee('Published group games still occupy')
        ->assertSee('Jul 15–Jul 16, 2026');
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run:

```powershell
php artisan test tests/Feature/Pages/ScheduleTest.php --filter="reserved playoff window|preview weekdays change|legacy group schedule"
```

Expected: FAIL because the preview panel and warning are missing.

- [ ] **Step 4: Add the computed properties**

In `src/app/Livewire/Organizer/ScheduleShow.php`, add `'bracket:id,format,phase'` to the `with()` list in `matches()`. Include `format` so `roundLabel()` still labels double-elimination rounds correctly:

```php
->with(['homeTeam', 'awayTeam', 'matchSlots.court', 'bracket:id,format,phase'])
```

Add these computed properties after `restMinutes()`:

```php
/**
 * @return array{
 *     startDate: string,
 *     endDate: string,
 *     groupEndDate: string|null,
 *     dates: list<string>,
 *     dayCount: int,
 *     rounds: list<array{label: string, bestOf: int, days: int}>
 * }|null
 */
#[Computed]
public function previewPlayoffWindow(): ?array
{
    if (! $this->tournament instanceof Tournament) {
        return null;
    }

    $options = ['weekdays' => $this->previewWeekdays];

    if ($this->previewEndDate !== '') {
        $options['endDate'] = $this->previewEndDate;
    }

    return app(SchedulingService::class)->playoffWindow($this->tournament, $options);
}

/**
 * @return list<string>
 */
#[Computed]
public function legacyPlayoffOverlap(): array
{
    if ($this->previewMode !== 'remaining' || ! $this->tournament instanceof Tournament) {
        return [];
    }

    $hasUnscheduledPlayoff = $this->matches
        ->filter(fn (MatchModel $match): bool => $match->matchSlots->isEmpty()
            && ! $match->isByeGame()
            && $match->bracket?->phase === 'playoffs')
        ->isNotEmpty();

    if (! $hasUnscheduledPlayoff) {
        return [];
    }

    $window = $this->previewPlayoffWindow;

    if ($window === null) {
        return [];
    }

    return app(SchedulingService::class)->playoffWindowOverlap($this->tournament, $window);
}
```

Add matching `@property-read` lines to the class docblock:

```php
 * @property-read array{startDate: string, endDate: string, groupEndDate: string|null, dates: list<string>, dayCount: int, rounds: list<array{label: string, bestOf: int, days: int}>}|null $previewPlayoffWindow
 * @property-read list<string> $legacyPlayoffOverlap
```

- [ ] **Step 5: Render the panel and warning**

In `src/resources/views/livewire/organizer/schedule-show.blade.php`, immediately after the existing `preview-summary` paragraph and before `@if ($previewDirty)`, insert:

```blade
@php
    $playoffWindow = $this->previewPlayoffWindow;
@endphp

@if ($playoffWindow !== null)
    <div class="hf-card hf-card--flat hf-mt-2" data-testid="preview-playoff-window">
        <div class="hf-between">
            <strong>Playoffs reserved</strong>
            <span class="hf-small hf-muted">
                {{ \Carbon\Carbon::parse($playoffWindow['startDate'])->format('M j, Y') }}–{{ \Carbon\Carbon::parse($playoffWindow['endDate'])->format('M j, Y') }}
                · {{ $playoffWindow['dayCount'] }} {{ \Illuminate\Support\Str::plural('match day', $playoffWindow['dayCount']) }}
            </span>
        </div>
        <p class="hf-small hf-muted hf-mb-0 hf-mt-1">
            @if ($playoffWindow['groupEndDate'] !== null)
                Group stage must finish by {{ \Carbon\Carbon::parse($playoffWindow['groupEndDate'])->format('M j, Y') }}.
            @else
                The playoff window uses every remaining match day.
            @endif
            @foreach ($playoffWindow['rounds'] as $round)
                {{ $round['label'] }}: {{ $round['days'] }} {{ \Illuminate\Support\Str::plural('day', $round['days']) }}@if (! $loop->last) · @endif
            @endforeach
        </p>
    </div>
@endif

@php
    $legacyOverlap = $this->legacyPlayoffOverlap;
@endphp

@if ($legacyOverlap !== [])
    <div class="hf-callout hf-callout--warning hf-mt-2" data-testid="preview-playoff-overlap">
        <strong><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Playoff window occupied</strong>
        <p class="hf-small hf-mb-0">
            Published group games still occupy {{ \Carbon\Carbon::parse($legacyOverlap[0])->format('M j') }}–{{ \Carbon\Carbon::parse($legacyOverlap[count($legacyOverlap) - 1])->format('M j, Y') }}, which is reserved for playoffs. Extend the end date or reschedule those group games; published slots are never moved automatically.
        </p>
    </div>
@endif
```

- [ ] **Step 6: Run the tests to verify they pass**

Run:

```powershell
php artisan test tests/Feature/Pages/ScheduleTest.php --filter="reserved playoff window|preview weekdays change|legacy group schedule"
```

Expected: PASS.

- [ ] **Step 7: Format and commit**

Run:

```powershell
vendor/bin/pint app/Livewire/Organizer/ScheduleShow.php tests/Feature/Pages/ScheduleTest.php
git add app/Livewire/Organizer/ScheduleShow.php resources/views/livewire/organizer/schedule-show.blade.php tests/Feature/Pages/ScheduleTest.php
git commit -m "feat: show the playoff window in the schedule preview"
```

---

### Task 8: Calendar shading and legend

**Files:**
- Modify: `src/app/Livewire/Organizer/ScheduleShow.php`
- Modify: `src/resources/views/livewire/organizer/schedule-show.blade.php`
- Modify: `src/resources/css/_hoopflow.scss`
- Test: `src/tests/Feature/Pages/ScheduleTest.php`

**Interfaces:**
- Consumes: `playoffWindow()` from Task 2.
- Produces: `ScheduleShow::calendarPlayoffWindow()`; `monthCells()` and `weekDays()` entries gain `reserved`.

- [ ] **Step 1: Write the failing calendar test**

Append to `src/tests/Feature/Pages/ScheduleTest.php`:

```php
it('shades the reserved playoff days on the calendar', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    [$tournament] = scheduleRoundRobinPlayoffTournament();

    $component = Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->assertSeeHtml('data-testid="schedule-playoff-window"')
        ->assertSee('reserved for playoffs')
        ->assertSeeHtml('hf-calendar-cell is-reserved');

    $cells = collect($component->instance()->monthCells)->keyBy('key');

    expect($cells['2026-07-30']['reserved'])->toBeTrue()
        ->and($cells['2026-07-31']['reserved'])->toBeTrue()
        ->and($cells['2026-07-29']['reserved'])->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```powershell
php artisan test tests/Feature/Pages/ScheduleTest.php --filter="shades the reserved playoff days"
```

Expected: FAIL because the legend and `reserved` data are missing.

- [ ] **Step 3: Add the calendar window computed property**

Add after `legacyPlayoffOverlap()` in `ScheduleShow.php`:

```php
/**
 * @return array{
 *     startDate: string,
 *     endDate: string,
 *     groupEndDate: string|null,
 *     dates: list<string>,
 *     dayCount: int,
 *     rounds: list<array{label: string, bestOf: int, days: int}>
 * }|null
 */
#[Computed]
public function calendarPlayoffWindow(): ?array
{
    if (! $this->tournament instanceof Tournament) {
        return null;
    }

    $weekdays = $this->scheduledSlots
        ->filter(fn (MatchSlot $slot): bool => $slot->start_at !== null)
        ->map(fn (MatchSlot $slot): int => (int) $slot->start_at->isoWeekday())
        ->unique()
        ->sort()
        ->values()
        ->all();

    return app(SchedulingService::class)->playoffWindow($this->tournament, [
        'weekdays' => array_values($weekdays === [] ? range(1, 7) : $weekdays),
    ]);
}
```

Add the matching `@property-read` line to the class docblock, and update the existing `monthCells` and `weekDays` shapes:

```php
 * @property-read array{startDate: string, endDate: string, groupEndDate: string|null, dates: list<string>, dayCount: int, rounds: list<array{label: string, bestOf: int, days: int}>}|null $calendarPlayoffWindow
 * @property-read array<int, array{key: string, day: int, count: int, outside: bool, reserved: bool}> $monthCells
 * @property-read array<int, array{key: string, weekday: string, day: int, count: int, reserved: bool}> $weekDays
```

- [ ] **Step 4: Add `reserved` to the cell data**

In `monthCells()`, add this before the loop:

```php
$playoffWindow = $this->calendarPlayoffWindow;
$reservedDays = $playoffWindow['dates'] ?? [];
```

Add `reserved` to the cell array:

```php
'reserved' => in_array($key, $reservedDays, true),
```

Update the method's return docblock to include `reserved: bool`.

In `weekDays()`, add the same two lines before the loop and the same `reserved` key. Update its docblock too.

- [ ] **Step 5: Render the shading and legend**

In the calendar cell button, change the class to:

```blade
class="hf-calendar-cell{{ $cell['count'] ? ' has-events' : '' }}{{ $cell['reserved'] ? ' is-reserved' : '' }}{{ $cell['key'] === $this->selectedDay ? ' is-selected' : '' }}"
```

Add the `data-reserved` attribute after `data-testid`:

```blade
data-reserved="{{ $cell['reserved'] ? 'true' : 'false' }}"
```

Append to the aria-label:

```blade
{{ $cell['reserved'] ? ', reserved for playoffs' : '' }}
```

In the week-strip button, change the class to:

```blade
class="{{ $day['reserved'] ? 'is-reserved' : '' }}{{ $day['key'] === $this->selectedDay ? ' is-selected' : '' }}"
```

Immediately after the week-strip `@endforeach` and closing `</div>`, add:

```blade
@php
    $calendarWindow = $this->calendarPlayoffWindow;
@endphp

@if ($calendarWindow !== null)
    <p class="hf-small hf-muted hf-mt-2 hf-mb-0" data-testid="schedule-playoff-window">
        <i class="bi bi-calendar-check" aria-hidden="true"></i>
        {{ \Carbon\Carbon::parse($calendarWindow['startDate'])->format('M j, Y') }}–{{ \Carbon\Carbon::parse($calendarWindow['endDate'])->format('M j, Y') }} reserved for playoffs
    </p>
@endif
```

- [ ] **Step 6: Add the reserved styles**

In `src/resources/css/_hoopflow.scss`, after `.hf-calendar-cell.is-selected` and before `.hf-calendar-count`, insert:

```scss
.hf-calendar-cell.is-reserved {
  border-style: dashed;
  border-color: color-mix(in srgb, var(--hf-gold) 70%, transparent);
}

.hf-calendar-cell.is-reserved:not(.has-events) {
  background: color-mix(in srgb, var(--hf-gold) 8%, transparent);
}

.hf-calendar-cell.is-reserved.is-selected {
  border-style: solid;
  border-color: var(--hf-ink);
  background: var(--hf-ink);
}
```

After `.hf-week-strip button.is-selected`, insert:

```scss
.hf-week-strip button.is-reserved {
  border-style: dashed;
  border-color: color-mix(in srgb, var(--hf-gold) 70%, transparent);
  background: color-mix(in srgb, var(--hf-gold) 8%, var(--hf-surface-2));
}

.hf-week-strip button.is-reserved.is-selected {
  border-style: solid;
  border-color: var(--hf-ink);
  background: var(--hf-ink);
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run:

```powershell
php artisan test tests/Feature/Pages/ScheduleTest.php --filter="shades the reserved playoff days"
```

Expected: PASS.

- [ ] **Step 8: Format and commit**

Run:

```powershell
vendor/bin/pint app/Livewire/Organizer/ScheduleShow.php tests/Feature/Pages/ScheduleTest.php
git add app/Livewire/Organizer/ScheduleShow.php resources/views/livewire/organizer/schedule-show.blade.php resources/css/_hoopflow.scss tests/Feature/Pages/ScheduleTest.php
git commit -m "feat: shade the reserved playoff days on the calendar"
```

---

### Task 9: Full quality gate

**Files:**
- No planned code changes.

- [ ] **Step 1: Run the unit and feature suites for the touched files**

Run sequentially:

```powershell
php artisan test tests/Unit/Services/SchedulingServiceTest.php
php artisan test tests/Feature/Pages/ScheduleTest.php
php artisan test tests/Feature/Pages/AuditTrailTest.php
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php
```

Expected: PASS in each command.

- [ ] **Step 2: Run the full quality gate**

Run:

```powershell
$env:COMPOSER_PROCESS_TIMEOUT = 0; composer quality
```

Expected: Pint clean, PHPStan `[OK] No errors`, and the full Pest suite passing.

- [ ] **Step 3: Fix any failures and repeat**

If Pint, PHPStan, or a test fails, fix the cause and rerun the exact failing command, then rerun `composer quality`. Do not lower the gate.

- [ ] **Step 4: Commit any fixes**

If Step 3 changed files, run:

```powershell
git add app/ tests/ resources/
git commit -m "fix: stabilize the playoff window reservation"
```

- [ ] **Step 5: Report the actual output**

Report the final Pint, PHPStan, and test counts verbatim, including the total passed tests and assertions. Do not claim success without the command output.
