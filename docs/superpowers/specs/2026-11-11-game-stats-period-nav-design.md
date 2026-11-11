# Game Stats Period Navigation — Design

**Date:** 2026-11-11
**Status:** Approved for planning
**Owner:** HoopFlow

## Context

The read-only Game Stats page (`/game-stats`, `App\Livewire\Organizer\GameStatsShow`) is the
Laravel equivalent of `prototype/games/stats.html`. The page already covers the prototype's header,
scoreboard, correction banner, result controls, quarter breakdown, side filters, player search, team
filter, sortable PTS/AST/REB table, and score/correction dialogs.

Two gaps remain:

1. The Game Stats player table only shows PTS, AST, and REB, while the in-game
   score & player stats editor (`/game-manage`) records and displays the full stat line.
2. There is no way to move between team periods the way NBA 2K26 box scores do
   (`All`, `Q1`, `Q2`, `Q3`, `Q4`, `OT1`, …).

The in-game editor already has quarter tabs. This work adds period navigation only to the
read-only Game Stats page.

## Goal

Make `/game-stats` match the prototype's page structure and controls while displaying the same full
player stat line as `/game-manage`, with a 2K-style period navigator that switches the table between
full-game totals and any recorded quarter or overtime.

## Non-goals

- Changing the prototype files.
- Changing `/game-manage` quarter tabs, stat inputs, save/publish behaviour, or score dialogs.
- Adding player-quarter data that was never recorded.
- A database migration or new page route.
- Changing authentication or role access to `/game-stats`.

## UX design

### Period navigator

Place a new horizontal period bar directly below the **Player stats** heading and above the side
filters/search controls.

- Labels: `All`, `Q1`, `Q2`, `Q3`, `Q4`, then `OT1`, `OT2`, … for recorded overtimes (maximum 9
  periods total).
- Only periods with at least one `PlayerStatQuarter` row for this match are shown. `All` is always
  shown.
- Games with no per-player quarter rows render only `All` and a muted note:
  “Per-quarter player stats were not recorded for this game.”
- Active period uses the primary filled state. Inactive periods use the muted segmented state.
- The bar is a `<nav aria-label="Player stat period">` containing buttons with `aria-pressed`.
  Active state must not rely on colour alone: the selected button also carries `aria-current="true"`.
- On narrow screens the bar scrolls horizontally in one row rather than wrapping into many lines.
- `data-testid` hooks:
  - `stats-period-nav`
  - `stats-period-all`
  - `stats-period-{index}` (0-based quarter index, so Q1 = `stats-period-0`)

### Player stats table

Keep the existing per-team sections, team shield/title, player-count chip, global search, team select,
and side chips. Replace the table contents with the full stat line used by the in-game editor:

| # | Player | FG (M/A) | 3PT (M/A) | FT (M/A) | REB | AST | STL | BLK | TO | PF | PTS |
|---|--------|----------|-----------|----------|-----|-----|-----|-----|----|----|-----|

- PTS/AST/REB remain clickable sort headers. The other columns are not sortable.
- The player stats card keeps the existing eyebrow (`Official box score` / `Draft box score`), the
  `Player stats` heading, and gains a slate chip showing the selected period, e.g. `All` or `Q2`
  (`data-testid="stats-period-label"`).
- Team totals in `<tfoot>` sum the visible rows for the selected period. Made/attempted totals are
  displayed as `M/A`.
- Search filters player names only; it does not change the selected period.
- Side chips and the team `<select>` continue to control which team section is visible.
- The per-player “by quarter” `<details>` accordions are removed. The period bar replaces them.
- Existing empty states remain: “No players match”, “No player stats”, and “Teams still pending”.

### Unchanged controls

These stay functionally and visually unchanged:

- Page header actions: Bracket, Schedule, Matchups, and In-game scoring (organizer only).
- Scoreboard and correction banner.
- Official result card: Enter final score / Correct score, and read-only role chip.
- Team quarter breakdown table, including dynamic OT columns.
- Score console, correction dialog, and confirm dialog.

## Data and state design

### Period state

Add to `GameStatsShow`:

```php
#[Url(as: 'period', history: true, except: 'all')]
public string $period = 'all';
```

- Values are `all` or a 0-based quarter index as a string (`0`–`8`).
- A `setPeriod(string $period)` action validates the value against the available periods before
  assigning it.
- After mount, if the URL supplied a period that is not available for the match, reset to `all`.
- Changing `side`, `search`, `sortKey`, or `sortDirection` never resets `period`.

### Available periods

Add a `periods` computed property returning a list of:

```php
array{key: string, label: string, index: int|null}
```

Rules:

1. `All` is always first.
2. Determine the game's quarter count from `quarters_json`, clamped to 4–9, exactly as the existing
   `quarters` computed property does.
3. For each quarter index, include it only when at least one `PlayerStatQuarter` row for this match
   has that `quarter_index`.
4. Labels: index `< 4` → `Q{index + 1}`; otherwise `OT{index - 3}`.

If no quarter rows exist, `periods` contains only `All`.

### Row building

Rewrite `rowsFor(?Team $team)` so each row contains:

```php
array{
  playerId: int,
  name: string,
  number: int|string|null,
  position: string|null,
  fgm: int, fga: int,
  tpm: int, tpa: int,
  ftm: int, fta: int,
  reb: int, ast: int, stl: int, blk: int, to: int, pf: int,
  points: int
}
```

Source by period:

- `all`: map from the player's `PlayerStat` aggregate (`field_goals_made`,
  `field_goals_attempted`, `three_points_made`, `three_points_attempted`, `free_throws_made`,
  `free_throws_attempted`, `rebounds`, `assists`, `steals`, `blocks`, `turnovers`, `fouls`,
  `points`).
- quarter index: map from that player's `PlayerStatQuarter` for the selected index; a missing row
  becomes all zeroes. `points` uses the stored row value, falling back to
  `fgm * 2 + tpm * 3 + ftm` when needed.

Sorting uses the selected period's `points`, `assists`, or `rebounds`, then falls back to
case-insensitive name order. Search filtering stays name-only.

### Team totals

Add a helper that sums the visible rows into the same shape:

- Numeric totals for `reb`, `ast`, `stl`, `blk`, `to`, `pf`, `points`.
- Separate made/attempted sums for `fgm/fga`, `tpm/tpa`, `ftm/fta`.

The footer must reflect the selected period and the current search/side filtering.

## Routes

`/game-stats` is already registered as `gameStats` in `routes/web.php`. No new route is required.

The selected period is carried by the existing route as a query parameter:

- `/game-stats?match=12&period=2`
- Omitted when `All` is selected.

`correct=1` continues to work alongside `period`.

## Files

| File | Change |
|------|--------|
| `src/app/Livewire/Organizer/GameStatsShow.php` | Add period state, available periods, period-aware row building, totals helper, validation/fallback. |
| `src/resources/views/livewire/organizer/game-stats-show.blade.php` | Add period nav, full stat-line table and totals, remove per-player quarter accordions, add missing-data note. |
| `src/resources/css/_hoopflow.scss` | Add the period navigator styles using existing design tokens. |
| `src/tests/Feature/Pages/GameStatsTest.php` | Update existing assertions; add period behaviour coverage. |

No model, migration, seeder, route, or service changes are expected. `GameStatsService` field
mapping can be reused for short field names.

## Edge cases

- **No match:** existing “Game not found” state; no period nav.
- **Teams pending:** existing “Teams still pending” state; no player tables or period nav.
- **No player stats:** existing per-team empty state; `All` remains available.
- **No quarter rows:** only `All`; muted note explains the missing detail.
- **Some players missing a quarter row:** they appear with zeroes for that period.
- **Invalid URL period:** fall back to `All` without an error.
- **Selected period becomes invalid after a Livewire update:** reset to `All`.
- **Search returns no players:** same “No players match” empty state per team.
- **Long OT games:** labels continue as `OT1` through `OT5` (nine total periods).
- **Mobile:** the period bar scrolls horizontally; the table keeps its existing horizontal scroll
  wrapper.

## Accessibility

- Use a labelled `<nav>` and real `<button type="button">` elements.
- `aria-pressed` and `aria-current` reflect the active period.
- Active/inactive states are visible in both light and dark themes via existing CSS variables.
- Table headers keep `scope="col"`; numeric cells keep `is-num`.
- Sort buttons keep their existing accessible names and caret/arrow icons.

## Testing

Extend `src/tests/Feature/Pages/GameStatsTest.php`:

1. The period nav renders `All`, `Q1`–`Q4`, and any OT tabs for a match with player-quarter rows.
2. A game with only aggregate stats renders only `All` and the “not recorded” note.
3. `setPeriod('0')` shows Q1 values in FG/3PT/FT/REB/AST/STL/BLK/TO/PF/PTS columns.
4. Team totals in the footer change when the period changes.
5. Sorting by PTS/AST/REB uses the selected period's values.
6. `?period=0` selects Q1 on initial load; an invalid `?period=99` falls back to `All`.
7. Search and side filtering continue to work while a quarter is selected.
8. Existing score, correction, role, quarter-breakdown, and empty-state tests still pass.
9. Replace the removed per-player accordion assertion with period-nav assertions.

Manual verification:

- Run the app and compare `/game-stats?match=…` against `prototype/games/stats.html` for header,
  scoreboard, controls, filters, table structure, and dialogs.
- Check light and dark themes and a narrow viewport.

## Quality gates

Before reporting completion, run from `src/`:

```powershell
composer quality
```

This covers Pint, PHPStan, and the full Pest suite. Report the actual output.

## Acceptance criteria

- `/game-stats` shows the full in-game stat line for every player.
- A 2K-style period bar switches between `All`, recorded quarters, and recorded overtimes.
- Period selection updates player rows, points, sort results, and team totals.
- Games without player-quarter rows show only `All` and an explanatory note.
- The selected period is deep-linkable and invalid values fall back to `All`.
- Existing page actions, score/correction dialogs, filters, and empty states keep working.
- All quality gates pass.
