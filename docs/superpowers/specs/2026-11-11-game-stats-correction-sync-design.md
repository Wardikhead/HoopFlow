# Game Stats Correction & Box Score Sync — Design

**Date:** 2026-11-11
**Status:** Approved for planning
**Owner:** HoopFlow

## Context

`/game-stats` (`App\Livewire\Organizer\GameStatsShow`) and `/game-manage`
(`App\Livewire\Organizer\GameManage`) already share the same data through
`GameStatsService`:

- `quarters_json` holds the team quarter scores.
- `player_stats` holds each player's aggregate stat line.
- `player_stat_quarters` holds each player's per-quarter stat line.

Saving or publishing from `/game-manage` writes all three inside one transaction. The period
navigator added to `/game-stats` reads those same rows, so the box score and the scoreboard agree
when a game is scored through In-game scoring.

Two score-only controls on `/game-stats` still bypass that data:

- **Enter final score** finalizes a scheduled game through `BracketService::recordScore()`.
- **Correct score** changes a final game through `BracketService::correctScore()`.

Neither writes `quarters_json`, `player_stats`, or `player_stat_quarters`. As a result:

1. A scheduled game with a draft box score can be finalized with a score that does not match the
   recorded player stats.
2. A final score correction leaves the old box score and quarter breakdown attached to the new
   score. Final games are locked in `/game-manage`, so the stats cannot be brought back in line.

The prototype behaves the same way, and the earlier period-navigation spec deliberately kept these
controls unchanged. This design closes the gap for games with an editable box score while
preserving the score-only flows for games that have no per-quarter detail.

## Goal

A final score correction on a game with an editable box score must update the score and the box
score together, with a required reason, one database transaction, and audit records. Finalizing a
scheduled game with draft quarter stats must go through the same editor. Mismatches that remain, or
that already exist in seeded data, must be visible but must not block the organizer.

## Non-goals

- Changing the prototype files.
- Adding a database migration, model, seeder, or route.
- Editing aggregate-only box scores that have no `player_stat_quarters` rows.
- Hard-blocking a save when player points do not equal the final score.
- Changing role access, authentication, or the public read-only view.
- Changing the normal locking of final games outside correction mode.
- Backfilling quarter rows for historical games.

## Definitions

- **Editable box score** — the match has at least one `PlayerStat` row *and* at least one
  `PlayerStatQuarter` row.
- **Aggregate-only box score** — the match has `PlayerStat` rows but no `PlayerStatQuarter` rows.
  Match 9 is the seeded example.
- **Score-only flow** — the existing `/game-stats` score console and correction dialog backed by
  `BracketService`.
- **Combined editor** — `/game-manage` in correction mode, backed by
  `GameStatsService::correct()`.

## UX design

### Routing rules

| Entry point | Condition | Behaviour |
|---|---|---|
| `/game-stats` **Enter final score** | Scheduled + editable box score | Redirect to `/game-manage?match={id}` |
| `/game-stats` **Enter final score** | Otherwise | Existing score console |
| `/game-stats` **Correct score** | Final + editable box score | Redirect to `/game-manage?match={id}&correct=1` |
| `/game-stats` **Correct score** | Otherwise | Existing correction dialog |
| `/game-stats?correct=1` | Final + editable box score | Redirect to `/game-manage?match={id}&correct=1` |
| `/game-stats?correct=1` | Otherwise | Existing correction dialog, or the existing refusal when the game is not final |
| `/game-manage?correct=1` | Final + editable box score | Correction mode |
| `/game-manage?correct=1` | Final + aggregate-only or no stats | Redirect to `/game-stats?match={id}&correct=1` |
| `/game-manage?correct=1` | Scheduled | Ignore the flag and render the normal editor |
| `/game-manage` locked **Correct score** | Editable box score | Link to `/game-manage?match={id}&correct=1` |
| `/game-manage` locked **Correct score** | Aggregate-only or no stats | Existing link to `/game-stats?correct=1` |

The `/game-stats` action methods perform the redirects, not only the buttons. Calling
`openScoreConsole`, `requestFinalizeScore`, `performFinalize`, `openCorrection`,
`requestCorrection`, or `performCorrection` directly for an editable box score must follow the same
routing rules. This prevents a Livewire call from bypassing the combined editor.

### Correction mode in `/game-manage`

When `?correct=1` opens a final game with an editable box score:

- The normal editor is rendered instead of the locked result card.
- A correction banner appears at the top of the editor:

  > Correction mode — you are editing a recorded result. A reason is required, and changes are
  > written to the audit trail.

  `data-testid="ingame-correction-banner"`
- A **Correction reason** textarea appears above the action row, required, 5–500 characters:

  `data-testid="ingame-correction-reason"`

- The action row replaces **Save** and **Publish result** with:
  - **Save correction** — primary, `data-testid="ingame-save-correction"`
  - **Cancel correction** — `data-testid="ingame-cancel-correction"`
- Each team panel shows a live, non-blocking mismatch line below the final score input:
  - When different: `Player points 78 · Final 80 · 2 unassigned`
  - When equal: `Player points match the final score`
  - `data-testid="ingame-mismatch-team1"` / `data-testid="ingame-mismatch-team2"`
- **Save correction** validates the reason, final score, and stat rules, then opens the existing
  confirmation dialog with the message:

  > Save correction? This updates the score and box score, may rewrite the downstream bracket if
  > the winner changes, and is written to the audit trail.

- Confirming saves the correction, redirects to `/game-stats?match={id}`, and shows the corrected
  read-only view. The existing correction banner on `/game-stats` displays the new score and the
  reason.
- **Cancel correction** redirects to `/game-stats?match={id}` without writing anything.
- Quarter tabs, add/remove overtime, side chips, team filter, player search, and stat inputs keep
  working exactly as they do in the normal editor.

### Mismatch warning on `/game-stats`

A warning appears below the scoreboard and above the result controls when all of the following are
true:

- The match is final or a forfeit.
- `home_score` and `away_score` are not null.
- At least one `PlayerStat` row exists.
- The sum of `player_stats.points` for a team differs from that team's final score.

Copy:

> Box score doesn’t match the final score: {team} player points {points} vs final {score}.

When the game is aggregate-only, append:

> Per-quarter stats were not recorded, so only the score can be corrected.

The warning is informational for read-only roles. It never blocks a save.

`data-testid="stats-score-mismatch"`

No new action button is added. The existing **Correct score** button is nearby and already follows
the routing rules above.

### Preserved behaviour

- `/game-stats` header, scoreboard, correction banner, period navigator, full stat-line table,
  totals, side chips, team select, search, and PTS/AST/REB sorting.
- `/game-manage` normal scheduled editor, quarter tabs, save draft, publish flow, and locked final
  state.
- Score-only console and correction dialog for games without an editable box score.
- Bracket, Schedule, Matchups, and In-game scoring links.
- Read-only role experience on `/game-stats`.

## Data and state

### `GameStatsShow`

Add two computed properties:

```php
#[Computed]
public function hasEditableBoxScore(): bool
{
    $match = $this->match;

    return $match instanceof MatchModel
        && $match->playerStats->isNotEmpty()
        && $match->playerQuarterStats->isNotEmpty();
}
```

```php
#[Computed]
public function scoreMismatch(): ?array
{
    // Returns null when there is no mismatch.
    // Otherwise a list of:
    // ['side' => 'team1', 'team' => string, 'points' => int, 'score' => int, 'editable' => bool]
}
```

`scoreMismatch` groups the eager-loaded `playerStats` by `team_id`, sums `points`, and compares the
result to `home_score` / `away_score`. It skips null scores, forfeits without stats, and teams whose
totals match. `editable` is `hasEditableBoxScore`.

Routing changes:

- `openScoreConsole()` — after the existing status and team checks, redirect to
  `route('gameManage', ['match' => $this->matchId])` when `hasEditableBoxScore` is true.
- `openCorrection()` — when the match is final and `hasEditableBoxScore` is true, redirect to
  `route('gameManage', ['match' => $this->matchId, 'correct' => 1])` before setting dialog state.
- `requestFinalizeScore()` and `performFinalize()` — re-check `hasEditableBoxScore` for scheduled
  games and redirect instead of finalizing score-only.
- `requestCorrection()` and `performCorrection()` — re-check `hasEditableBoxScore` for final games
  and redirect instead of applying a score-only correction.

No other public state is added to `GameStatsShow`.

### `GameManage`

Add:

```php
public bool $correctionMode = false;

public string $correctionReason = '';
```

Mount and mode handling:

- After `selectMatch($found)`, if `request()->boolean('correct')`, call `enterCorrectionMode()`.
- `enterCorrectionMode()` allows only an organizer, a final match, and an editable box score.
  - Final + editable box score → set `correctionMode = true` and reset the reason.
  - Final + aggregate-only or no stats → redirect to `/game-stats?match={id}&correct=1`.
  - Scheduled → ignore the flag and keep the normal editor.
- `isLocked()` returns true for final/forfeit only when `correctionMode` is false.
- `editableMatch()`:
  - In correction mode, require `status === 'final'`, authorize `correctScore`, and require both
    teams.
  - Otherwise keep the current scheduled-only rules and `enterStats` authorization.
- `saveDraft()` and `requestPublish()` are no-ops in correction mode. The UI hides their buttons,
  and the guards prevent direct calls.
- `requestSaveCorrection()`:
  - Guard correction mode.
  - Validate `correctionReason` as required, string, min 5, max 500.
  - Validate both final scores equal the sum of their quarters and that the game is not tied.
  - Open the confirmation dialog with action `saveCorrection`.
- `confirmSaveCorrection()`:
  - Call `GameStatsService::correct($match, $this->payload(), $this->correctionReason)`.
  - Catch `InvalidArgumentException`, keep the editor open, and show `errorMessage` plus a toast.
  - On success, redirect to `route('gameStats', ['match' => $match->id])`.
- `cancelCorrection()`:
  - Redirect to `route('gameStats', ['match' => $match->id])` without writing.
- Add `statPointsForSide(string $side): int` for the view. It sums
  `GameStatsService::pointsFor()` over every player's quarters in `$this->stats[$side]`, independent
  of the player search filter.

### `GameStatsService`

Add:

```php
public function correct(MatchModel $match, array $payload, string $reason): MatchModel
```

Rules:

1. Require `status === 'final'` and both teams known.
2. Trim the reason. Require 5–500 characters; otherwise throw
   `InvalidArgumentException('Enter at least 5 characters for the correction reason.')`.
3. Normalize the payload with the existing `normalize()` method.
4. Read the final scores with the existing `intValue()` rules and require them to equal the sum of
   the normalized quarters.
5. Reject a tie with the existing elimination-game message.
6. Capture `$previousQuarters = is_array($match->quarters_json) ? $match->quarters_json : null`
   before writing.
7. Run one transaction:
   - `write($match, $normalized, false)` — updates `quarters_json`, `player_stats`, and
     `player_stat_quarters`.
   - `$this->bracketService->correctScore($match->refresh(), $final1, $final2, $reason)` — updates
     the score, winner, status, completion data, and downstream bracket.
   - `writeAudit($match, 'stats.correct', $previousQuarters, [...])` — records the quarter scores,
     finals, player count, and reason.
8. Return `$match->refresh()`.

Update `load()` so the quarter count includes the highest recorded quarter row:

```php
$maxQuarterIndex = (int) $match->playerQuarterStats->max('quarter_index');
$count = min(
    self::MAX_QUARTERS,
    max(self::MIN_QUARTERS, count($team1), count($team2), $maxQuarterIndex + 1),
);
```

This prevents an OT row that exists in `player_stat_quarters` but not in `quarters_json` from being
hidden by the editor and then deleted by the next save.

### Audit

`BracketService::correctScore()` continues to write `score.correct` with the previous and new
scores, winner, and reason. This keeps the `/game-stats` correction banner working.

`GameStatsService::correct()` adds `stats.correct` with:

```php
[
    'quarters' => $normalized['quarters'],
    'finals' => ['team1' => $final1, 'team2' => $final2],
    'players' => count($normalized['stats']['team1']) + count($normalized['stats']['team2']),
    'reason' => $reason,
]
```

`AuditLogIndex::ACTION_LABELS` gains:

```php
'stats.correct' => 'Box score corrected',
```

`AuditLogIndex::summary()` gains:

```php
'stats.correct' => 'Corrected the '.$entity.' box score',
```

The default `changes()` chips already skip `reason`. No other audit page changes are required.

## Validation and guardrails

- Correction reason: required, 5–500 characters after trimming.
- Final score: integer 0–199, equal to the sum of the quarters.
- No ties in elimination games.
- Maximum 9 quarters.
- Made shots cannot exceed attempts.
- Players must belong to the correct roster.
- Only organizers can enter correction mode or save a correction.
- Only final games with an editable box score use the combined editor.
- Aggregate-only and no-stat games keep the score-only flows so existing totals are never zeroed.
- A player-point mismatch warns but does not block.

## Error handling

- Service validation failures throw `InvalidArgumentException`; the component catches them, keeps
  the editor open, and shows the message.
- Any failure inside the transaction rolls back the score, stats, bracket propagation, and audit
  entries together.
- Ineligible `?correct=1` requests redirect instead of rendering a broken editor.
- Non-organizers receive 403 on `/game-manage` and see no correction controls on `/game-stats`.

## Edge cases

- **Aggregate-only final (match 9):** Correct score keeps the existing dialog. The warning explains
  that per-quarter stats were not recorded. The combined editor is never opened, so the aggregates
  cannot be zeroed.
- **No stats at all:** Enter final score and Correct score keep their current console/dialog.
- **Scheduled game with an aggregate-only draft:** Enter final score keeps the console. A mismatch
  warning can appear after finalization.
- **Final game with quarter rows but no `quarters_json`:** The editor loads four zero quarters. The
  organizer must re-enter quarter scores before saving; player stat rows are preserved.
- **OT rows beyond `quarters_json`:** The updated `load()` includes them, and `correct()` preserves
  them.
- **Forfeit:** Correction stays score-only through `/game-stats`; the combined editor is final-only.
- **Mismatched player points after correction:** The correction saves and the warning remains on
  `/game-stats`.
- **Winner change:** `BracketService::correctScore()` propagates the downstream bracket and records
  the previous and new winner.
- **Whitespace-only reason:** Trimmed and rejected as too short.

## Files

| File | Change |
|---|---|
| `src/app/Services/GameStatsService.php` | Add `correct()`, include the highest quarter row in `load()`. |
| `src/app/Livewire/Organizer/GameManage.php` | Correction mode, reason, save/cancel actions, mismatch helper, guards. |
| `src/resources/views/livewire/organizer/game-manage.blade.php` | Correction banner, reason field, save/cancel buttons, mismatch lines, locked-state link. |
| `src/app/Livewire/Organizer/GameStatsShow.php` | Editable-box-score detection, mismatch data, routing guards. |
| `src/resources/views/livewire/organizer/game-stats-show.blade.php` | Mismatch warning. |
| `src/app/Livewire/Organizer/AuditLogIndex.php` | Label and summary for `stats.correct`. |
| `src/tests/Feature/Services/GameStatsServiceTest.php` | Correction service and OT-loading coverage. |
| `src/tests/Feature/Pages/GameManageTest.php` | Correction mode, validation, redirects, mismatch coverage. |
| `src/tests/Feature/Pages/GameStatsTest.php` | Routing, warning, and preserved score-only coverage. |

No migration, model, seeder, or route changes.

## Testing

### `GameStatsServiceTest`

1. `correct()` updates the score, `quarters_json`, aggregate totals, quarter rows, status, and
   winner, and writes both `score.correct` and `stats.correct`.
2. `correct()` rejects a short reason, a tie, and finals that do not equal the quarter sums, with no
   partial writes.
3. `correct()` propagates the bracket when the winner changes and leaves it alone when the winner
   does not change.
4. `load()` includes OT quarter rows that are missing from `quarters_json`, and `correct()` keeps
   them.
5. `correct()` rejects a scheduled match.

### `GameManageTest`

1. `?correct=1` on a final game with an editable box score unlocks the editor and renders the
   correction banner, reason field, and Save correction button.
2. The same final game without `?correct=1` stays locked.
3. Saving a correction requires a reason, updates the score and box score, writes both audits, and
   redirects to `/game-stats`.
4. The mismatch line renders when player points differ, and the save still succeeds.
5. Cancel redirects without writing.
6. Non-organizers receive 403.
7. A final aggregate-only or no-stat game with `?correct=1` redirects to
   `/game-stats?correct=1`.
8. A scheduled game with `?correct=1` renders the normal editor without the correction banner.

### `GameStatsTest`

1. A final editable game routes **Correct score** to `/game-manage?correct=1`.
2. The existing aggregate-only match 9 still opens the score-only correction dialog.
3. The `?correct=1` deep link on a final editable game redirects to the combined editor.
4. A scheduled game with a draft editable box score routes **Enter final score** to `/game-manage`.
5. A scheduled game without stats still opens the score console.
6. The mismatch warning renders when totals differ and is hidden when they match.
7. Coach and fan views show no correction controls.
8. The existing period-navigation, sorting, filtering, and role tests remain green.

### Manual verification

- Correct a final game's score and stats; verify `/game-stats` rows, totals, quarter breakdown,
  correction banner, audit log, and bracket all reflect the change.
- Correct only the score; verify the warning appears and does not block.
- Verify match 9 still uses the dialog and its totals are unchanged after the run.
- Verify a scheduled game without stats still uses the console.
- Check the correction banner, reason field, and warning in light and dark themes and at a narrow
  viewport.

### Quality gates

From `src/`:

```powershell
composer quality
```

Report the actual output.

## Acceptance criteria

- A final game with an editable box score is score-corrected only through the combined editor.
- Saving a correction updates the score, quarter scores, player totals, and per-quarter rows in one
  transaction, with a required reason and both audit entries.
- Finalizing a scheduled game with draft quarter stats routes through the editor.
- No-stat and aggregate-only games keep the current score-only flows.
- Player-point mismatches are visible in the editor and on `/game-stats` but do not block a save.
- Normal final games remain locked; non-organizers cannot correct.
- All tests and `composer quality` pass.

## Out of scope / follow-ups

- Backfilling quarter rows for historical aggregate-only games.
- A separate admin data-repair tool.
- Enforcing exact equality between player points and the final score.
