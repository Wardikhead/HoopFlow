# Round-robin playoff window reservation design

- **Date:** 2026-11-13
- **Owner:** Jeward T. Mahinay
- **Status:** Approved for planning
- **Scope:** `src/` round-robin schedule planning, remaining-games planning, plan validation, schedule preview and calendar presentation, audit metadata, and tests.

## Problem

The full round-robin planner packs group rounds from the tournament start date forward until the end date. Nothing reserves calendar space for the playoff bracket that is generated later.

That creates a dead end:

- The generated group schedule can occupy every eligible match day through the tournament end.
- Once group results exist, the full generate/rebuild path is locked.
- `SchedulingService::planRemaining()` starts after the latest published group match day and then needs more days than the tournament has left, so it fails.
- The organizer cannot rebuild because published or played group slots must never move, and cannot schedule playoffs because there is no room.

The tournament dates and the playoff configuration already contain enough information to reserve the right amount of space up front. The scheduler should use it automatically.

## Goals

1. Reserve enough eligible match days for the configured playoff bracket before any group game is planned.
2. Derive the reservation from `playoff_teams` and the best-of settings; do not add a manual playoff start date.
3. Keep group rounds before the reserved window and fail clearly when they cannot fit.
4. Keep the existing incremental rules: never move, delete, or replace published or played group slots; full generate/rebuild remains locked once results exist.
5. Let playoffs start as soon as the last group game is published when the group stage finishes early.
6. Show the reserved window in the schedule preview and shade it on the calendar.
7. Warn clearly when a schedule published before this change leaves no room.
8. Cover the behavior with unit and feature tests.

## Non-goals

- No manual "playoff start date" setting.
- No repair or migration flow that moves published group slots.
- No automatic extension of the tournament end date.
- No per-court or per-venue reservation.
- No persisted reservation table or new column.
- No change to bracket generation, result handling, standings, or playoff seeding.
- No reservation for single-elimination or double-elimination tournaments.
- No support for playoff fields larger than 16 teams.

## Relationship to prior specs

- The bracket engine spec (`2026-11-11-bracket-engine-playoff-series-design.md`) defines best-of series and one game per eligible day. This spec uses that footprint when reserving days.
- The bracket series/schedule UX spec (`2026-11-12-bracket-series-schedule-ux-design.md`) defines the preview dialog and plan validation this spec extends.
- The tournament lifecycle spec (`2026-11-12-tournament-status-lifecycle-design.md`) treats a configured `playoff_teams` value as "round robin with playoffs". This spec reserves space whenever that value is a valid playoff field.
- The incremental remaining-games work (`feat: schedule remaining playoff games after results`) stays intact. Reservation only changes where the group stage may end.

## Decisions

### 1. Reservation model

Add these methods to `App\Services\SchedulingService`:

```php
/**
 * @return array{
 *     days: int,
 *     rounds: list<array{label: string, bestOf: int, days: int}>
 * }|null
 */
public function playoffFootprint(Tournament $tournament): ?array;

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
public function playoffWindow(Tournament $tournament, array $options = []): ?array;
```

`playoffFootprint()` returns `null` unless all of these are true:

- `$tournament->format()` is `round-robin`.
- `playoff_teams` is one of `2`, `4`, `8`, or `16`.

`playoffWindow()` returns `null` when the footprint is `null`, or when the footprint does not fit between the tournament start date and the requested end date (default: tournament end) using the requested weekdays (default: all days). The window is a property of the tournament configuration, not of a particular preview start date; whether the group stage has room before it is checked separately.

Both methods are pure: they read tournament rules and dates but write nothing.

### 2. Footprint calculation

The playoff bracket is a single-elimination bracket seeded from the standings. Each round is scheduled on its own consecutive eligible days, and every game in a best-of series is pre-scheduled one per eligible day.

| Playoff teams | Rounds, earliest to final | Reserved days |
|---|---|---|
| 2 | Final (`best_of_final`) | `best_of_final` |
| 4 | Semifinal (`best_of_semifinal`), Final (`best_of_final`) | `best_of_semifinal + best_of_final` |
| 8 | Quarterfinal (`best_of_quarterfinal`), Semifinal (`best_of_semifinal`), Final (`best_of_final`) | sum of the three |
| 16 | Round of 16 (best-of-1), Quarterfinal (`best_of_quarterfinal`), Semifinal (`best_of_semifinal`), Final (`best_of_final`) | `1 +` sum of the three |

Best-of values are read exactly like `BracketService::bestOfRule()`: only `1`, `3`, `5`, `7`, or `9` are valid, anything else falls back to `1`. Earlier rounds with no configured best-of value use 1 day.

Example: 4 playoff teams, best-of-1 semifinal, best-of-3 final, tournament end August 8 → 4 reserved days: August 5 (semifinal), August 6–8 (final).

A series may still need more days than the footprint if the available courts and time lanes cannot run multiple series in parallel. The footprint is the guaranteed minimum, not a court-capacity promise. The existing clear overflow error remains the fallback.

### 3. Window dates and group deadline

`playoffWindow()` walks backwards from the requested end date over the selected weekdays, collecting exactly `footprint['days']` dates:

1. Start at the end date at `startOfDay()`.
2. Include the day when its ISO weekday is selected.
3. Step back one day and repeat until the day count is filled.
4. The first collected day is `startDate`; the last is `endDate`; `dates` is the ascending list.
5. `groupEndDate` is the last selected weekday strictly before `startDate`.

The walk is bounded by the tournament start date. If fewer eligible days exist than the footprint needs, `playoffWindow()` returns `null`.

Example with Friday/Saturday match days and an end date of Friday, July 31, needing 3 days: reserved dates are July 24, 25, and 31; the group deadline is July 18.

The reservation is not a forced playoff start. If the group stage finishes before `groupEndDate`, the incremental planner starts the day after the last published group game and may use the unused days before the window.

### 4. Planner integration

`buildPlan()` computes the reservation after weekdays, start date, and end date are known.

**Generate and rebuild (`$remaining === false`):**

- When the footprint is non-null, compute the window with the plan's end date and weekdays. The window is derived from the tournament start, not from the preview start.
- If the window is `null`, throw:

  > This schedule window has {M} eligible match days, but the playoffs need {N}. Extend the end date or reduce the playoff field / best-of games.

- If `groupEndDate` is `null` or falls before the plan's start date, throw:

  > The playoff window ({start}–{end}) leaves no match days for the group stage. Extend the end date or reduce the playoff field / best-of games.

- Use `groupEndDate` as the hard cutoff for the group plan. The existing overflow check compares the projected last group day against it and throws:

  > The group stage must finish by {groupEndDate} to keep {windowStart}–{windowEnd} free for the playoffs, but this plan needs through {projectedLast}. Extend the end date, add match days, or shorten slots/rest.

- The tournament end date remains the plan's overall `endDate`; only the group cutoff is earlier.

**Remaining games (`$remaining === true`):**

- The start date stays `max(day after the latest published slot, tournament start)`.
- No cutoff is applied. Playoff games may use the reserved window and any free days before it.
- Compute the window for metadata and UI, but do not use it to reject a plan.
- If `remainingStartDate` is after the end date, throw:

  > The published schedule leaves no room for the remaining games: the last match day is {lastDay} and the tournament ends {endDate}. Extend the end date or reschedule group games.

- If a remaining plan still projects past the end date, throw:

  > The remaining games need through {projectedLast}, but the tournament ends {endDate}. Extend the end date or free a day in the playoff window by rescheduling group games.

No database rows are written by planning. Publishing still goes through `publish()` or `publishRemaining()`, and `publishRemaining()` still refuses to touch any match that already has a slot.

### 5. Plan output

Every plan gains a `playoffWindow` key:

```php
'playoffWindow' => [
    'startDate' => '2026-08-05',
    'endDate' => '2026-08-08',
    'groupEndDate' => '2026-08-04',
    'dates' => ['2026-08-05', '2026-08-06', '2026-08-07', '2026-08-08'],
    'dayCount' => 4,
    'rounds' => [
        ['label' => 'Semifinal', 'bestOf' => 1, 'days' => 1],
        ['label' => 'Final', 'bestOf' => 3, 'days' => 3],
    ],
],
```

The key is `null` when no reservation applies. `endDate` stays the tournament end date, not the group deadline, so existing consumers keep their meaning.

### 6. Validation

`validatePlan()` recomputes the window from the plan's end date and weekdays.

**Generate and rebuild validation:**

- When `groupEndDate` exists, a group-phase assignment after it is an error keyed by match id:

  > This game is after the group-stage deadline ({groupEndDate}). Days from {start}–{end} are reserved for the playoffs. Schedule it earlier.

- This closes the loophole where an organizer edits a preview row and silently moves a group game into the window.
- Missing matches, court conflicts, team rest, and weekday warnings keep their current behavior.

**Remaining validation:**

- Playoff assignments inside the window are valid.
- The existing court, team-rest, and published-slot conflict checks are unchanged.

`validatePlan()` identifies group matches through the match's bracket phase, not through the round label.

### 7. Legacy schedules

Schedules published before this change are never altered. Their published group slots stay exactly where they are.

Add a helper for the preview:

```php
/**
 * @param  array<string, mixed>  $window
 * @return list<string>
 */
public function playoffWindowOverlap(Tournament $tournament, array $window): array;
```

- Returns the published group match dates that fall between the window's `startDate` and `endDate`, inclusive.
- Only group-phase brackets are considered; playoff slots are ignored.
- Returns an empty list when there is no overlap.

`ScheduleShow` shows a warning card in remaining mode when all of these are true:

- At least one unscheduled playoff game remains.
- A playoff window exists for the preview settings.
- `playoffWindowOverlap()` is non-empty.

Message:

> Published group games still occupy {start}–{end}, which is reserved for playoffs. Extend the end date or reschedule those group games; published slots are never moved automatically.

The warning is informational and does not block the preview. Publishing is still blocked only by validation errors or by the planner's no-room exception.

### 8. Preview and calendar UI

**Preview dialog (`resources/views/livewire/organizer/schedule-show.blade.php`):**

- Add a reserved-window panel directly under the existing summary line, test id `preview-playoff-window`:

  > **Playoffs reserved** · Aug 5–8, 2026 · 4 match days
  > Group stage must finish by Aug 4, 2026. Semifinal: 1 day · Final: 3 days.

- The panel recomputes from `previewEndDate` and `previewWeekdays`, so changing settings updates it live. The window dates themselves come from the tournament start; the group deadline line tells the organizer where the group stage must stop.
- Hide the panel when the footprint is `null`.
- When the window cannot fit, the existing error panel shows the planner message; the reserved panel is hidden.
- Add the legacy warning card with test id `preview-playoff-overlap` when the overlap condition is met.

`ScheduleShow` exposes:

```php
#[Computed]
public function previewPlayoffWindow(): ?array;
```

It calls `playoffWindow()` with the current preview settings.

**Calendar:**

- Add `reserved` to each `monthCells()` entry and each `weekDays()` entry.
- Add the `is-reserved` class to reserved calendar cells and week-strip buttons.
- Style with a dashed gold outline and a faint gold tint. `has-events` and `is-selected` keep their current styles; `is-selected` wins when both apply.
- Append “reserved for playoffs” to the cell's accessible label.
- Add a legend under the calendar, test id `schedule-playoff-window`:

  > Aug 5–8, 2026 reserved for playoffs

- The calendar uses the page context: weekdays already present in published slots when a schedule exists, otherwise all weekdays. The dialog panel uses the preview settings.
- Reserved days remain clickable and show whatever games are already published on them.

Add one computed property for the calendar:

```php
#[Computed]
public function calendarPlayoffWindow(): ?array;
```

### 9. Audit metadata

`publish()` and `publishRemaining()` add these keys to their existing audit metadata:

- `playoffWindowStart`
- `playoffWindowEnd`
- `playoffReservedDays`

Values come from the plan's `playoffWindow` and are `null`/`0` when no reservation applies. Existing audit keys do not change.

## Data model

No schema change.

- The reservation is derived from `tournaments.start_date`, `tournaments.end_date`, and `rules_json` (`format`, `playoff_teams`, `best_of_*`).
- No reservation is stored.
- Existing schedules are not backfilled or rewritten.

## Edge cases

- **No playoffs configured:** `playoff_teams` is `0`, absent, or invalid. No window is computed and planning behavior is unchanged.
- **Non-round-robin format:** No window, even when `playoff_teams` is present.
- **Tournament too short:** Fails before planning with the eligible-days message.
- **Group stage cannot fit:** Fails before planning with the group-deadline message; nothing is published.
- **Window consumes every eligible day:** `groupEndDate` is `null`; fails with the no-group-room message.
- **Group stage finishes early:** Remaining games start the day after the last published group game and may use free days before the window.
- **Preview end date moved earlier:** The window moves earlier and the group deadline moves with it.
- **Preview weekdays changed:** The window only uses selected weekdays; non-selected days inside the calendar range are not reserved.
- **Legacy schedule with group games in the window:** Preview shows the overlap warning; the planner reports the clear no-room or overflow message.
- **Best-of series runs short:** The unused pre-scheduled days remain available for later playoff rounds, as they do today.
- **Series cannot fit the court/time lanes:** The existing overflow error remains; the reservation guarantees days, not unlimited court capacity.
- **Invalid best-of value:** Falls back to 1 day, matching `BracketService::bestOfRule()`.
- **Results exist:** Generate/rebuild stays locked. Only the remaining-games path is available, and it never moves published slots.

## Testing

Tests are written first and run sequentially.

### Unit: `SchedulingServiceTest`

- 4 playoff teams with a best-of-3 final reserves 4 days, reports the correct group deadline, and places no group game after it.
- 8 playoff teams with quarterfinal, semifinal, and final best-of values sums the footprint and returns the correct round breakdown.
- 16 playoff teams includes the best-of-1 Round of 16 day.
- Friday/Saturday weekdays reserve only eligible days and compute the correct group deadline.
- A best-of value outside `1, 3, 5, 7, 9` falls back to 1 day.
- A tournament shorter than the footprint throws the eligible-days message and publishes nothing.
- A footprint that consumes every eligible day throws the no-group-room message.
- A group stage that cannot fit before the deadline throws the group-deadline message and publishes nothing.
- Non-round-robin and `playoff_teams = 0` produce no window and unchanged schedule dates.
- The remaining plan starts after the last group day, fits inside the window, and leaves the group-slot snapshot unchanged.
- A legacy schedule generated without playoffs, then given a playoff field and results, throws the no-room message from `planRemaining()`.
- `playoffWindowOverlap()` returns the published group dates inside the window and ignores playoff slots.
- `validatePlan()` rejects a manually moved group game after the group deadline.
- `publish()` writes the window metadata to the audit entry.

### Feature: `ScheduleTest`

- The calendar shades reserved days and shows the legend.
- The preview dialog shows the reserved-window panel with the date range and round breakdown.
- Changing preview weekdays updates the reserved panel.
- A legacy schedule shows the overlap warning in the remaining preview.
- The existing remaining-games flow still publishes only unscheduled playoff games and leaves group slots untouched.

### Regression checks

- Existing round-robin scheduling tests keep passing, including round ordering and best-of series placement.
- Existing preview validation, court conflict, and team-rest tests keep passing.
- Existing schedule page and tournament lifecycle tests keep passing.
- The full quality gate passes.

## Risks

- Existing round-robin tests generate schedules with a playoff field and may now end earlier than before. Assertions that assumed the old last date should be updated only when that date was incidental.
- The calendar shading adds a second meaning to a day cell. The legend and accessible labels must make the state understandable without relying on color alone.
- The reservation is a date guarantee, not a capacity guarantee. A tournament with very few courts can still overflow and must keep the clear fallback error.
- Legacy schedules remain broken by design unless the organizer extends the end date or reschedules group games. The warning must say this plainly instead of implying an automatic repair.

## Rollout

1. Commit this spec.
2. Write the implementation plan.
3. Add failing unit tests for the footprint, window, planner cutoff, validation, overlap, and audit metadata.
4. Implement `playoffFootprint()`, `playoffWindow()`, `playoffWindowOverlap()`, and the planner/validation changes.
5. Add failing feature tests for the preview panel, calendar shading, and legacy warning.
6. Implement the Livewire and Blade changes and the calendar styles.
7. Run targeted tests sequentially.
8. Run `COMPOSER_PROCESS_TIMEOUT=0 composer quality` and report the actual output.
9. Commit the implementation without touching `.obsidian/graph.json` or untracked `.kilo/`.

## Acceptance criteria

- A round-robin tournament with 4 playoff teams and a best-of-3 final reserves 4 eligible days before the group stage is planned.
- The group schedule never includes a day after the computed group deadline.
- Scheduling remaining playoff games succeeds after group results exist without moving any group slot.
- A group plan that cannot fit before the window fails with the group-deadline message and publishes nothing.
- A tournament too short for the reservation fails with the eligible-days message.
- Playoffs start the day after the last group game when the group stage finishes early.
- The preview dialog shows the reserved window and round breakdown.
- The calendar shades reserved days and shows the legend.
- A legacy schedule shows the overlap warning in the remaining preview.
- Full generate/rebuild stays locked once results exist.
- The full quality gate passes.
