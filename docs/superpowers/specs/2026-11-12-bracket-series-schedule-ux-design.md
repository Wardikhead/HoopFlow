# Bracket series actions, random regeneration, and schedule UX design

- **Date:** 2026-11-12
- **Owner:** Jeward T. Mahinay
- **Status:** Approved for planning
- **Scope:** `src/` bracket series actions, game selection, box score context, bracket regeneration, scheduling engine, schedule preview/rebuild UI, tests, and demo fixtures.

## Problem

The bracket and schedule features are functionally connected but currently disagree with each other.

- In a series, the action cell shows only one action per game. A final game offers Correct but not View box score; a scheduled game offers Score but not View box score. Correct, Score, and Open are rendered without role checks, so coaches and fans see organizer controls.
- "In-game score & player stats" always jumps to the active game. An organizer with a best-of-3 series cannot choose which game to open first.
- `SchedulingService::plan()` only schedules matches with `status = 'scheduled'`. Series Game 2+ are created as `conditional`, so they stay unscheduled until the series advances. When activated, `scheduleSeriesGame()` tries to put the next game on the same day after the previous game, producing back-to-back games instead of one game per day.
- The schedule has no end-date protection. A plan or a per-game move can run past the tournament's `end_date` without warning.
- The schedule preview accepts no settings and no row edits. `publishSchedule()` calls `generate()` again, discarding the plan the organizer saw.
- Regenerate bracket uses the same seed order as initial generation. The button says "regenerate" but produces the same matchups.
- Cancelling the remaining games of a decided series leaves their match slots behind, so the calendar keeps showing games that will never be played.
- The box score page has no series context. It shows one game with no way to move between Game 1, Game 2, and Game 3.

## Goals

1. Give every series game an accurate action set: organizer controls plus View box score; coaches and fans see View box score only.
2. Let the organizer choose a specific game before entering in-game scoring.
3. Make the box score accurate for the selected game and labelled correctly for final, draft, scheduled, conditional, and cancelled states.
4. Pre-schedule every series game during schedule generation: one game per eligible day, on one court and one tip-off lane.
5. Make schedule preview editable, publish the exact preview, and add a rebuild wizard with start date, end date, and preferred weekdays.
6. Validate every schedule write against the tournament window, court conflicts, team rest, and series order.
7. Make Regenerate bracket shuffle all approved teams and clear the published schedule.
8. Release match slots when a series ends early so the calendar only shows playable games.
9. Cover the behavior with unit, feature, and demo-seeder tests.

## Non-goals

- No drag-and-drop bracket editing.
- No new competition formats or changes to bracket structure.
- No separate series table; the anchor-match model stays.
- No full schedule rebuild after results exist. Results lock the schedule; per-game moves remain the mid-tournament tool.
- No automatic simulation of outcomes and no automatic decision of which conditional games become necessary.
- No automatic court blackout handling beyond the existing court and rest checks.
- No change to round-robin playoff scheduling after group results; the existing results lock remains.
- No changes to the prototype under `prototype/`.
- No public API changes.

## Relationship to prior specs

The bracket engine spec (`2026-11-11-bracket-engine-playoff-series-design.md`) states that newly activated series games appear unscheduled. That behavior is intentionally superseded: all series games are scheduled up front, and activation reuses the existing slot. Activation only creates a replacement slot in the correction edge case described below.

## Decisions

### 1. Series action matrix

Every game in the series table shows **View box score** for every role. Organizer-only actions appear beside it according to the game state.

| Game state | Organizer actions | Coach / fan actions |
|---|---|---|
| Scheduled and active | Open scoring, Enter final score, View box score | View box score |
| In progress | Resume scoring, View box score | View box score |
| Final or forfeit | Correct score, View box score | View box score |
| Conditional (if needed) | View box score | View box score |
| Cancelled (not needed) | View box score | View box score |

- The per-row action cell is role-gated in Blade with `$isOrganizer`. Server-side `Gate::authorize('correctScore')`, `enterStats`, and `generateBracket` checks remain authoritative.
- "Open scoring" navigates to `gameManage` with the **game's own ID**, never the series anchor.
- "Enter final score" keeps the existing quick-score dialog for the selected game.
- "View box score" links to `gameStats` with the game's own ID.
- The match-detail footer keeps Close and View box score for everyone. "In-game score & player stats" becomes a chooser trigger for series and a direct link for single games. Correct score stays organizer-only.
- The bracket page continues to open the match-detail dialog for all roles, but organizer controls inside it are hidden for coaches and fans.

### 2. Choose game flow

`BracketShow` gains an explicit chooser dialog:

- Method: `openGameChooser(int|string|null $matchId)`.
- Opens for a series (`best_of > 1`); single games link directly to `gameManage`.
- Dialog title includes the matchup and series score, for example "Choose game · Aces vs Tigers · Series tied 1–1".
- Each row shows Game N of M, score, schedule, status, and one action:
  - Scheduled and active → Open scoring.
  - In progress → Resume scoring.
  - Final or forfeit → View box score.
  - Conditional → disabled, "Available after Game {N} is final."
  - Cancelled → disabled, "Series ended {home}–{away}. This game is not needed."
- Only the active game can open GameManage. Final games open the box score; corrections remain in the bracket action cell.
- Selecting a game closes the chooser and navigates with the child game ID.
- The chooser is available to organizers only because it starts the scoring flow. Coaches and fans keep the direct View box score action.

`GameManage` changes:

- `resolveActiveSeriesMatch()` continues to redirect an **anchor** request to the active game, preserving the existing regression test.
- An explicit child ID is kept as requested when its status is `scheduled`, `in_progress`, `final`, or `forfeit`.
- A conditional or cancelled child redirects to the anchor's active game when one exists; otherwise it falls back to the anchor and shows a warning toast.
- A Game 1..N switcher appears above the editor for series games. The current game is highlighted with `aria-current="true"`. Scheduled and in-progress games are links; final and forfeit games link to the box score; conditional and cancelled games are disabled with a reason.
- The existing locked-state panel, next-game link, publish confirmation, and correction behavior stay unchanged.

### 3. Box score page

`GameStatsShow` loads the exact match ID; it never resolves an anchor to a different game.

- A Game 1..N strip appears for every role when the game belongs to a series.
- Each tab shows the game number, score, and status. The current game is highlighted. Final tabs show official totals; unplayed tabs show an empty state.
- The page header shows series context: "Game 2 of 3 · Series tied 1–1" or "Aces lead 2–1".
- Organizer actions (Open scoring, Correct score) stay hidden from coaches and fans.

Box score states:

| State | Display |
|---|---|
| Final or forfeit | Official stats, final score, series summary. |
| Stats exist but the game is not final | Stats plus a warning banner: "Draft stats — not official until the game is published." |
| Scheduled, no stats | "No stats yet. Scheduled {date} at {time} on {court}." Organizer sees Open scoring when the game is active. |
| Conditional, no stats | "This game is only played if needed." |
| Cancelled | "This game was cancelled when the series ended early." |
| Unknown game ID | 404. |

The route stays `gameStats?match={id}`. Links from the bracket always use a match from the current tournament.

### 4. Random bracket regeneration

`BracketService::generate()` gains a third parameter:

```php
public function generate(
    Tournament $tournament,
    string $format = 'single-elimination',
    bool $shuffle = false,
): array
```

- Initial generation keeps the current seed ordering and writes `seeding_method = 'manual'`.
- Regeneration passes `$shuffle = true`, calls `shuffle()` on the approved registrations, and writes `seeding_method = 'random'`.
- No seed values are read or written in random mode. Seeds remain on registrations for future manual generation.
- The audit entry records `shuffled` and `seedingMethod`.
- Regenerate remains blocked when any match is `final`, `forfeit`, or `in_progress`.
- Regeneration already deletes matches and slots through `deleteBracketData()`. The confirmation must say so explicitly: "This shuffles all approved teams, ignores seeding, and clears the published bracket and schedule."
- Initial Generate keeps its existing confirmation text about seeded teams.

### 5. Scheduling model

A series is one scheduling unit. All games in the unit are placed on consecutive **eligible match days**, on the same court and at the same tip-off time.

Eligibility:

- A day is eligible when its ISO weekday is selected in the plan options and it falls inside the tournament window.
- If a preferred day is excluded, the series resumes on the next eligible day. A best-of-3 with Sunday excluded plays Friday, Saturday, Monday.
- A series never plays two games on one day, regardless of court capacity.

Placement:

1. Build units: each series anchor plus its `conditional` child games, sorted by `game_number`; each non-series match is its own unit.
2. Exclude byes, cancelled matches, and matches with results.
3. Order round groups with the existing `roundSequence()` rules: round-robin matchdays, interleaved winners and losers rounds, grand final, reset. Round groups that share a sequence share the same earliest day.
4. The first sequence starts on the plan start date. Each following sequence starts on the first eligible day after the previous sequence's last game.
5. Assign each unit to the earliest start day at or after its sequence's earliest day where every required game day has a free lane. A lane is a court plus a start time.
6. Prefer lanes deterministically: court order, then time waves starting from `roundStartHour(unitCount)` and stepping by `slotMinutes + restMinutes`.
7. Enforce court double-booking and team rest during assignment.
8. If any unit would finish after the tournament end date, abort the plan with the projected finish date and the series label. No partial plan is returned and no database write occurs.

Example: a best-of-3 semifinal on Court 1 at 3:00 PM plays Nov 20, Nov 21, and Nov 22. The final cannot start before Nov 23. Another semifinal can use Court 2 at 3:00 PM on the same three days.

Only conditional games with `series_id != null` are included. Conditional anchors such as a grand-final reset stay unscheduled until they become necessary.

### 6. `plan()`, `publish()`, and `validatePlan()`

`plan()` keeps its public shape and gains options:

| Option | Default | Validation |
|---|---|---|
| `startDate` | tournament `start_date` | required, not before tournament start |
| `endDate` | tournament `end_date` | not before start, not after tournament end |
| `weekdays` | all ISO days 1–7 | at least one |
| `slotMinutes` | rules or 30 | at least 10 |
| `restMinutes` | rules or 15 | zero or more |

When the tournament has no `end_date`, `endDate` is optional, there is no upper-bound error, and the UI shows "No end date set".

The returned plan adds:

- `endDate`
- `weekdays`
- `summary` with `games`, `matchDays`, `firstDate`, `lastDate`, and `seriesCount`
- `assignments` as today: `matchId`, `courtId`, `scheduledAt`

New service methods:

```php
public function publish(Tournament $tournament, array $plan): int;
public function validatePlan(Tournament $tournament, array $plan): array;
```

`publish()`:

- Runs in a database transaction.
- Deletes all match slots for the tournament and recreates the exact assignments in the plan.
- Uses the plan's `slotMinutes` for `end_at`; never re-plans.
- Writes `schedule.publish` (or `schedule.rebuild`) to the audit log with options, match count, first date, and last date.
- Returns the number of assignments.

`generate()` becomes `plan()` plus `publish()` and stays backward compatible for seeders and tests.

`validatePlan()` returns:

```php
[
    'errors' => [matchId => 'message'],
    'warnings' => [matchId => 'message'],
    'valid' => true|false,
]
```

It rejects:

- missing required plan keys (`assignments`, `courts`, `slotMinutes`, `restMinutes`, `startDate`)
- match IDs that do not belong to the tournament
- matches that are byes, cancelled, or already have results
- unknown courts
- unparseable or out-of-window start times
- duplicate match IDs
- a plan that omits a schedulable match
- court double-booking
- team rest violations

A manual row edit that falls on an unselected weekday is a **warning**, not an error: the organizer explicitly overrode the preference. All other checks are blockers. Publish re-runs validation and refuses to write when errors exist.

### 7. Schedule preview and rebuild wizard

Generate and Rebuild share one preview panel. There is no new database table; the draft lives in Livewire state until Publish.

`previewPlan` stores the plan returned by `plan()`: `assignments`, `courts`, `slotMinutes`, `restMinutes`, `startDate`, `endDate`, `weekdays`, and `summary`. Row edits update assignments by `matchId`, not by array index, so filtering or reordering rows cannot write to the wrong game.

Panel layout:

- **Settings:** start date, end date, weekday chips (Mon–Sun), slot length, rest minutes.
- **Actions:** Build preview / Rebuild preview, Reset to generated plan, Cancel, Publish schedule.
- **Summary:** X games · Y match days · ends {date} · N warnings · N conflicts.
- **Rows:** series or match label, Game N of M, court select, date-time input, status indicator, inline message.

Behavior:

- Generate starts with tournament dates, all weekdays, and rules slot/rest.
- Rebuild prefills the current schedule's first date, the weekdays actually used, and rules slot/rest.
- Changing settings after a preview marks it stale: Publish is disabled and a hint says "Settings changed — rebuild the preview."
- Editing a court or time updates the same assignment in `previewPlan`; the row status and summary recalculate.
- Publish is disabled while any error exists or the preview is stale.
- Reset restores the plan returned by the last successful build.
- Generate stays the action when no schedule exists. Rebuild schedule appears once a schedule exists.
- Rebuild shows a warning that the current schedule will be replaced, then asks for confirmation before publishing.
- Both modes are refused when results exist, with a toast telling the organizer to use Reschedule for individual games.
- On success, the calendar jumps to the first match day and a toast reports games, match days, and the final date.

`ScheduleShow` state adds preview options and removes the old behavior where `publishSchedule()` re-planned silently. The one-click Regenerate action and the placeholder Manual manage action are removed; Rebuild schedule is the only bulk scheduling action.

### 8. Per-game moves

`SchedulingService::reschedule()` keeps its signature and adds:

- Only `scheduled` and `conditional` matches may be moved.
- The new start must be on or after the tournament start and on or before the tournament end.
- Court conflict and team-rest checks stay.
- For a series game, the new start must be after the previous game's start and before the next game's start when those games have slots. The error names the blocking game and its time.
- The dialog shows a read-only Game 1..N strip with current slots and a note that moving one game does not move the rest.
- Moving a game does not rewrite the rest of the series or the round.

### 9. Slot release and calendar consistency

`BracketService` gains a small helper:

```php
protected function releaseSlots(iterable $matchIds): void
```

It deletes `match_slots` rows for the given matches.

Call it from:

- `cancelRemainingSeriesGames()`, before marking children cancelled
- `applyBye()`, for children cancelled when a series becomes a bye
- `cancelMatch()`, for children cancelled with the anchor

The schedule page filters out cancelled matches defensively, even if a slot survives:

- `matchSlots()` joins or filters on `matches.status != 'cancelled'`
- `scheduledSlots()` and calendar counts use the same filtered collection
- `unscheduledCount()` ignores cancelled matches

Bracket rows keep showing cancelled games as "Not needed" without a time.

Correction edge case: when a correction makes a series undecided again, the next game is reactivated. If its slot was released, `scheduleSeriesGame()` tries to create it on the same court and tip-off lane on the next available day after the previous game. It infers preferred weekdays from the series' existing game dates; when it cannot, every day is eligible. If no room exists before the end date, the game stays unscheduled, the organizer sees a warning toast, and the normal unscheduled indicator appears on the schedule page. Corrections are never blocked solely because the follow-up slot cannot be created.

### 10. Error handling and feedback

Failures are specific and actionable. Single-operation failures use a toast plus an inline field error when a field exists. Preview problems use the summary bar and per-row markers.

| Situation | Feedback |
|---|---|
| Regenerate with results | "Results already exist. Regenerating is locked — use Correct score instead." |
| Fewer than two approved teams | "At least two approved teams are needed to generate a bracket." |
| No bracket before scheduling | "Generate a bracket before scheduling." |
| No courts | "Choose at least one court for this tournament." |
| End before start | "The end date must be on or after the start date." |
| Start outside tournament | "Start date must be within the tournament dates ({start} – {end})." |
| No weekdays | "Select at least one match day." |
| Not enough days | "The tournament ends {end}, but this plan needs through {last}. Add weekdays, shorten slots, or extend the end date." |
| Court conflict | "{Court} is already booked at {time}. Pick another court or time." |
| Rest violation | "{Team} would play twice within {minutes} minutes. Move one game." |
| Series order | "Game {N} must start after Game {N-1} ({date}, {time})." |
| Stale preview | "Settings changed — rebuild the preview before publishing." |
| Publish with no plan | "Build a preview before publishing." |
| Move past end date | "The tournament ends {date}. Choose an earlier date." |
| Move final/cancelled game | "Only scheduled or if-needed games can be moved." |
| Inactive series game | "Game {N} is available after Game {N-1} is final." |
| Series already decided | "The series ended {home}–{away}. Game {N} is not needed." |

Interaction rules:

- Long actions disable their button and show a spinner. Duplicate submits are impossible.
- Destructive actions use the existing confirmation dialog: regenerate bracket, regenerate schedule, rebuild publish, correct score.
- Empty states always state the next action for organizers and a read-only explanation for coaches and fans.
- Inline errors use `aria-invalid` and `aria-describedby`; focus moves to the first invalid control.
- Dialogs close on Escape and restore focus to the trigger.
- Action buttons carry descriptive labels such as "View box score for Game 2".
- Layouts are checked at 375, 768, and 1280 px. Tables stack into cards below `md` without losing actions.

### 11. Authorization

- Bracket generation and regeneration: `generateBracket`.
- Correct score and score entry: `correctScore`.
- In-game stat entry: `enterStats`.
- Schedule generation, rebuild, publish, and per-game move: `generateSchedule`.

Blade hides organizer-only actions for coaches and fans. Policies remain the source of truth. Coach and fan roles may view the bracket, the series detail, and every game's box score, but not organizer controls.

## Architecture

| File | Change |
|---|---|
| `app/Livewire/Organizer/BracketShow.php` | Game chooser state/methods, role-aware series actions, random regenerate flag, updated confirmation copy. |
| `resources/views/livewire/organizer/bracket-show.blade.php` | Action matrix, chooser dialog, series context, organizer gating, loading states. |
| `app/Livewire/Organizer/GameManage.php` | Explicit child-game selection, series switcher data, anchor redirect preservation, unavailable-game feedback. |
| `resources/views/livewire/organizer/game-manage.blade.php` | Game 1..N switcher and disabled reasons. |
| `app/Livewire/Organizer/GameStatsShow.php` | Exact-match loading, series strip data, state banners, role-aware actions. |
| `resources/views/livewire/organizer/game-stats-show.blade.php` | Series strip, draft/scheduled/conditional/cancelled states. |
| `app/Services/BracketService.php` | Shuffle option, seeding method, audit fields, slot release helper and calls. |
| `app/Services/SchedulingService.php` | Series units and lanes, weekday/end-date options, plan summary, exact publish, plan validation, reschedule checks. |
| `app/Livewire/Organizer/ScheduleShow.php` | Preview options, stale state, row edits, exact publish, rebuild mode, validation summary. |
| `resources/views/livewire/organizer/schedule-show.blade.php` | Editable preview panel, rebuild wizard, error states, loading states. |
| `database/seeders/BracketDemoSeeder.php` | Extend the double-elimination end date so best-of-3 series fit. |
| `tests/Unit/Services/SchedulingServiceTest.php` | New scheduling model and validation coverage. |
| `tests/Feature/Pages/BracketTest.php` | Action matrix, chooser, box score links, random regeneration. |
| `tests/Feature/Pages/GameManageTest.php` | Explicit game selection and anchor redirect. |
| `tests/Feature/Pages/GameStatsTest.php` | Per-game box score states and series strip. |
| `tests/Feature/Pages/ScheduleTest.php` | Preview edits, exact publish, rebuild, end-date errors, slot release. |
| `tests/Feature/Database/BracketDemoSeederTest.php` | Series games are scheduled; demo dates fit. |
| `tests/Unit/Services/Bracket/SeriesLifecycleTest.php` | Cancelled games release slots; reactivation fallback. |

No database migration is expected. `brackets.seeding_method` already accepts a string; the new value is `random`. `match_slots` already supports multiple games per tournament.

## Data flow

1. **Score a series game:** bracket detail → chooser → child game ID → `GameManage` → `GameStatsService::publish()` → series lifecycle updates the anchor and activates the next game.
2. **View a box score:** bracket row or series tab → `gameStats?match={childId}` → exact match and series strip.
3. **Generate schedule:** `ScheduleShow` options → `SchedulingService::plan()` → editable `previewPlan` → `publish()` exact assignments.
4. **Regenerate bracket:** confirmation → `BracketService::generate(..., shuffle: true)` → delete old matches/slots → random order → new bracket, unscheduled.
5. **Series ends early:** `BracketService` cancels children and releases their slots → schedule queries exclude cancelled matches → calendar shows only playable games.

## Testing

| File | Cases |
|---|---|
| `SchedulingServiceTest` | Series Game 1..N pre-scheduled one per eligible day on one court/time lane; weekday skipping; lane spillover; next round starts after the previous round's last game; end-date failure writes nothing; exact `publish()` persists edited court/time; `validatePlan()` rejects cross-tournament, out-of-window, duplicate, double-booked, and rest-violating rows; weekday override is a warning; `reschedule()` rejects past-end, wrong-status, and out-of-order series moves. |
| `BracketTest` | Organizer sees Correct/Open/Score plus View box score; coach and fan see only View box score; each box score link uses the game ID; chooser lists all games with correct enabled/disabled states; regenerate shuffles team order, writes `seeding_method = random`, and clears slots; regenerate blocked with results. |
| `GameManageTest` | Explicit Game 2 selection stays on Game 2; anchor request still redirects to the active game; conditional/cancelled requests redirect with a warning; switcher marks the current game. |
| `GameStatsTest` | Final, draft, scheduled, conditional, and cancelled states; series strip navigation for all roles; organizer actions hidden from coach/fan; unknown match 404. |
| `ScheduleTest` | Preview settings regenerate; per-row court/time edits persist exactly; Publish disabled on conflict or stale preview; rebuild wizard prefills and replaces; end-date and weekday messages; early series end removes slots; cancelled games disappear from calendar counts. |
| `BracketDemoSeederTest` | Every series game has a slot; the double-elimination schedule fits before its end date. |
| `SeriesLifecycleTest` | 2–0 cancellation releases Game 3's slot; correction back to 1–1 reactivates and schedules Game 3 when room exists. |

Run the full suite and `composer quality` in `src` before completion, and report the actual output.

## Acceptance criteria

1. Organizer sees Correct score, Open scoring, Enter final score, and View box score as appropriate per game; coach and fan see only View box score.
2. View box score opens the exact game and shows official, draft, scheduled, conditional, or cancelled state accurately.
3. In-game score opens a Game 1..N chooser for a series; only the active game starts scoring; final games open the box score.
4. `GameManage` and `GameStatsShow` both provide a working Game 1..N series strip with correct status and navigation.
5. Generating a schedule pre-schedules every series game, one game per eligible day, on one court and tip-off time.
6. A plan that cannot finish by the end date is rejected with the projected date and writes nothing.
7. Schedule preview settings and per-row court/time are editable, and Publish persists exactly the preview shown.
8. Rebuild schedule prefills from the current schedule, validates weekdays and end date, warns about replacement, and is refused once results exist.
9. Moving a game past the end date, outside the tournament, or out of series order is rejected with a specific message.
10. A 2–0 series cancels remaining games, releases their slots, and removes them from the calendar.
11. Regenerate bracket shuffles all approved teams, ignores seeds, records random seeding, clears the published schedule, and rebuilds the bracket after confirmation.
12. All destructive actions require confirmation; all long actions show loading; all failures show actionable feedback.
13. The demo double-elimination tournament can schedule every best-of-3 series before its end date.
14. The full test suite and `composer quality` pass.
