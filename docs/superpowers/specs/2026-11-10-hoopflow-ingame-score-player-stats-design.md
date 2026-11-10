# In-game score & player stats — Laravel implementation

- **Date:** 2026-11-10
- **Owner:** Jeward T. Mahinay
- **Status:** Approved for planning
- **Scope:** `src/` (Laravel application), `docs/superpowers/`
- **Prototype reference:** `prototype/assets/js/pages/game-manage.js`, `prototype/games/manage.html`

## Problem

The prototype has a complete organizer workflow for a live game: pick a match, enter quarter
scores, add overtime, enter a full per-player box score for the selected quarter, save a draft,
and publish the result. The Laravel application only has a read-only game-stats page and a final
score dialog. It stores only PTS/AST/REB/STL/BLK/FOULS per player, so per-quarter player stats and
the full FG/3PT/FT line cannot be captured at all.

Organizers cannot currently run a game from the application, and the game-stats page cannot show
the per-quarter player breakdown that the prototype supports.

## Goals

1. Port the prototype in-game screen to Livewire at `/game-manage` with the same layout, controls,
   filters, score inputs, overtime controls, and player-stat tables.
2. Persist team quarter scores and a full per-player, per-quarter box score.
3. Keep `player_stats` totals derived from the quarter rows so points and shooting totals stay
   consistent.
4. Save drafts without finalizing; publish to finalize the game, advance the winner, and update
   standings through the existing bracket service.
5. Show per-player quarter breakdowns on the read-only game-stats page.
6. Link the new page from the bracket match detail, the organizer workspace nav, and the game-stats
   page.
7. Enforce organizer-only access through routes and policies.

## Non-goals

- No play-by-play, shot charts, or possession tracking.
- No changes to bracket generation or schedule generation.
- No editing of a finalized result from the in-game page. Corrections remain on the game-stats page
  and keep the existing reason + audit workflow.
- No new client-side framework. Livewire handles state; Alpine is used only for dialogs/toasts as
  it is elsewhere in the application.
- No Bootstrap JavaScript.

## Decisions

### 1. Route and access

- Add `GET /game-manage` with route name `gameManage`, implemented by
  `App\Livewire\Organizer\GameManage`.
- The route uses `auth` and `role:organizer`.
- `?match={id}` selects a game. Without it, the component renders the match picker.
- `mount()` also aborts with 403 for non-organizers so direct component usage cannot bypass the
  route.
- Every mutating action (`saveDraft`, `requestPublish`, `confirmPublish`, `addOvertime`,
  `removeOvertime`) re-checks the match with the `enterStats` policy. A final or forfeit match is
  never mutated by this page: the editor renders a locked summary and links to the existing
  correction workflow.
- The route is registered in a feature file, `routes/features/game-management.php`. `routes/web.php`
  gains a small loop that requires every `routes/features/*.php` file so the table-of-contents
  convention is real. The feature file declares its own `auth` + `role:organizer` middleware.

### 2. Data model

#### `player_stats` — totals row (existing table, extended)

Add:

| Column | Type | Default | Meaning |
|---|---|---|---|
| `field_goals_made` | unsigned integer | 0 | Total FG made, including 3PT |
| `field_goals_attempted` | unsigned integer | 0 | Total FG attempts |
| `three_points_made` | unsigned integer | 0 | 3PT made |
| `three_points_attempted` | unsigned integer | 0 | 3PT attempts |
| `free_throws_made` | unsigned integer | 0 | FT made |
| `free_throws_attempted` | unsigned integer | 0 | FT attempts |
| `turnovers` | unsigned integer | 0 | Turnovers |

Existing columns remain the totals: `points`, `assists`, `rebounds`, `steals`, `blocks`, `fouls`.

Migration: `2026_11_10_000001_add_box_score_columns_to_player_stats_table.php`.

#### `player_stat_quarters` — new table

One row per player, per match, per quarter.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `match_id` | FK matches | cascade delete |
| `player_id` | FK players | cascade delete |
| `team_id` | FK teams | cascade delete |
| `quarter_index` | unsigned tiny integer | 0-based; 0–3 = Q1–Q4, 4–8 = OT1–OT5 |
| `field_goals_made` | unsigned integer | default 0; includes 3PT makes |
| `field_goals_attempted` | unsigned integer | default 0 |
| `three_points_made` | unsigned integer | default 0 |
| `three_points_attempted` | unsigned integer | default 0 |
| `free_throws_made` | unsigned integer | default 0 |
| `free_throws_attempted` | unsigned integer | default 0 |
| `rebounds` | unsigned integer | default 0 |
| `assists` | unsigned integer | default 0 |
| `steals` | unsigned integer | default 0 |
| `blocks` | unsigned integer | default 0 |
| `turnovers` | unsigned integer | default 0 |
| `fouls` | unsigned integer | default 0 |
| `points` | unsigned integer | derived, default 0 |
| timestamps | | |

Migration: `2026_11_10_000002_create_player_stat_quarters_table.php`.

Constraints/indexes:

- Unique `(match_id, player_id, quarter_index)`.
- Index `(match_id, team_id)`.

The table intentionally does **not** foreign-key to `player_stats.id`: `player_stats` is hash
partitioned on Postgres and has no unique key to reference. `(match_id, player_id)` is the natural
key shared by both tables.

#### Models and relations

- New `App\Models\PlayerStatQuarter` with fillable columns and `match()`, `player()`, `team()`
  relations.
- `MatchModel::playerQuarterStats(): HasMany<PlayerStatQuarter>` ordered by `player_id` then
  `quarter_index`.
- No `PlayerStat::quarters()` relation: the natural key is composite `(match_id, player_id)` and
  `player_stats` is partitioned. Consumers load `playerQuarterStats` for the match and group by
  `player_id`.
- `PlayerStat` and `PlayerStatQuarter` share long-form column names; `GameStatsService` maps them to
  the short box-score keys (`fgm`, `fga`, `tpm`, `tpa`, `ftm`, `fta`, `reb`, `ast`, `stl`, `blk`,
  `to`, `pf`) in the Livewire payload.

#### Scoring math

`points` is never accepted from the client. It is derived as:

```
points = (field_goals_made - three_points_made) * 2 + three_points_made * 3 + free_throws_made
       = field_goals_made * 2 + three_points_made + free_throws_made
```

This is standard box-score math where `field_goals_made` includes 3PT makes. It differs from the
prototype helper, which computes `fgm * 2 + tpm * 3 + ftm` and therefore double-counts 3PT makes.
The correct formula is used everywhere, including the live editor's auto-sync of a team quarter
score from player stats, because this page writes official results and standings. Input validation
enforces `three_points_made <= field_goals_made` and
`three_points_attempted <= field_goals_attempted` so the formula always makes sense.

Team quarter scores remain independent inputs stored in `matches.quarters_json`
(`['team1' => [], 'team2' => []]`). They are not derived from player rows, so a scorekeeper can
record a team quarter before entering the detailed box score. Publish requires the team finals to
equal the quarter sums, as in the prototype.

### 3. Domain service — `App\Services\GameStatsService`

Plain service class in `App\Services`, consistent with `BracketService` and `StandingsService`.
No HTTP or Livewire dependencies.

Constants:

- `MIN_QUARTERS = 4`
- `MAX_QUARTERS = 9` (Q1–Q4 + 5 overtime periods)
- A field map from short UI keys to long column names:
  `fgm → field_goals_made`, `fga → field_goals_attempted`, `tpm → three_points_made`,
  `tpa → three_points_attempted`, `ftm → free_throws_made`, `fta → free_throws_attempted`,
  `reb → rebounds`, `ast → assists`, `stl → steals`, `blk → blocks`, `to → turnovers`,
  `pf → fouls`.

Public API:

```php
/**
 * @return array{
 *   quarters: array{team1: list<int>, team2: list<int>},
 *   finals: array{team1: int, team2: int},
 *   stats: array{
 *     team1: array<int, array{quarters: list<array<string, int>>}>,
 *     team2: array<int, array{quarters: list<array<string, int>>}>
 *   }
 * }
 */
public function load(MatchModel $match): array;

/**
 * @param array{quarters: array{team1: list<int>, team2: list<int>}, stats: array{team1: array<int, array>, team2: array<int, array>}} $payload
 */
public function saveDraft(MatchModel $match, array $payload): void;

/**
 * @param array{quarters: array{team1: list<int>, team2: list<int>}, stats: array{team1: array<int, array>, team2: array<int, array>}, finals: array{team1: int, team2: int}} $payload
 */
public function publish(MatchModel $match, array $payload): MatchModel;
```

`stats` is keyed by player ID. Each entry contains a `quarters` list indexed 0–8 using the short
box-score keys; missing quarter entries are zero-filled by `load()`. All other payload values are
ignored unless listed above; `points` is always derived.

`load()`:

- Normalizes `matches.quarters_json` to two arrays of 4–9 integers, padding missing quarters with 0.
- Loads every roster player for both teams, ordered by jersey number then name.
- Loads existing `player_stats` and `player_stat_quarters` rows.
- Returns zero-filled quarter arrays for players without data.
- Uses `match.home_score` / `match.away_score` when present; otherwise returns the quarter sums as
  finals.

`saveDraft()`:

- Rejects a match whose status is `final` or `forfeit`:
  `This result is already final. Use correction to change it.`
- Rejects a match with a missing home or away team:
  `Both teams must be known before entering stats.`
- Validates and normalizes the payload (see validation below).
- Runs in a transaction:
  1. Writes `matches.quarters_json`.
  2. Upserts one `player_stats` totals row per roster player.
  3. Upserts one `player_stat_quarters` row per player per quarter.
  4. Deletes quarter rows above the current quarter count.
  5. Writes an audit entry `stats.save` with the quarter totals and player count.
- Leaves status, scores, winner, and bracket untouched.

`publish()`:

- Applies the same match-state checks and payload validation.
- Requires `finals.team1 === array_sum(quarters.team1)` and the same for team2:
  `Final score must equal the sum of the quarters.`
- Requires `finals.team1 !== finals.team2`:
  `Elimination games cannot end in a tie.`
- Runs in a transaction:
  1. Persists stats through the same internal writer used by `saveDraft()`.
  2. Calls `BracketService::recordScore($match, $final1, $final2)` so the result, winner, bracket
     propagation, and `score.save` audit entry use the existing source of truth.
  3. Writes an audit entry `stats.publish` with the quarter totals.
- Returns the refreshed match.

Validation performed by both write paths:

- Both teams are set on the match.
- Each team's quarter array is normalized to a shared length: pad the shorter array with zeros to
  `max(4, longest input)`. Reject an input longer than 9 quarters.
- Quarter scores are integers from 0 to 199.
- Finals, when supplied, are integers from 0 to 199.
- Stats are keyed by player ID; every key must belong to that team's roster. Unknown player keys are
  rejected; roster players missing from the payload are zero-filled.
- Each player's `quarters` list is padded with zero entries to the shared quarter count; a longer
  list is rejected.
- Every stat field is a non-negative integer from 0 to 199.
- `fgm <= fga`, `tpm <= tpa`, `ftm <= fta`, `tpm <= fgm`, `tpa <= fga`.

Postgres partition handling:

- `player_stats.tournament_id` is NOT NULL on Postgres. Every created totals row sets it from
  `match.tournament_id`. The service only sets the column when the database driver is `pgsql`, matching
  the existing seeder pattern, because local SQLite does not have that column.

### 4. Livewire page — `App\Livewire\Organizer\GameManage`

`#[Layout('layouts.public')]`, `#[Title('In-game score & player stats')]`. View:
`resources/views/livewire/organizer/game-manage.blade.php`.

State:

- `matchId`, `tournamentFilter`, `statusFilter`, `teamSearch` for the picker.
- `quarters: array{team1: list<int>, team2: list<int>}`
- `finals: array{team1: int, team2: int}`
- `stats: array{team1: array<int, array{quarters: list<array<string, int>>}>, team2: array<int, array{quarters: list<array<string, int>>}>}`
  keyed by player ID; each `quarters` entry uses the short box-score keys.
- `activeQuarter: int`
- `side: string` (`all|team1|team2`)
- `search1`, `search2`
- Confirm-dialog state (`showConfirm`, `confirmTitle`, `confirmMessage`, `confirmLabel`,
  `confirmTone`, `confirmAction`).

Picker (no match selected):

- Header eyebrow `In-game management`; title `Select a match to manage`; description adapted from
  the prototype.
- Tournament select (all tournaments), status select (all/scheduled/final), team search input.
- Match cards using `<x-match-card>` with `href="{{ route('gameManage', ['match' => $match->id]) }}"`
  and `data-testid="ingame-match-card-{id}"`.
- Matches are sorted by scheduled start, then match number.
- Empty state `No matches match` with a clear-filters action.
- No match found for `?match=` shows the prototype empty state with a link back to the picker.

Editor (match selected):

- If a team is missing: `Teams still pending` empty state.
- If status is `final` or `forfeit`: locked state card with the final score, status, `View box
  score` link to `gameStats`, `Correct score` link to `gameStats` with `correct=1`, and `Change
  match` link back to the picker. No editable controls and no Save/Publish buttons.
- Otherwise the full prototype editor:
  - Header: eyebrow `{home name} vs {away name}`, title `In-game score & player stats`,
    description with round and slot, actions Bracket / Matchups / Change match.
  - Quarter-tabs card with buttons `Q1`…`Q4`, `OT1`…`OT5`; active tab uses primary styling and
    `aria-pressed`.
  - Two-column team panels with one score input per quarter, a running total, and a final-score
    input.
  - Add overtime and Remove last OT buttons. Add is capped at 9 quarters with a warning toast.
  - Filter card with All / Team 1 / Team 2 chips and a team-filter select.
  - One section per team with its own player search, editable table, active-quarter PTS and derived
    totals, exactly following the prototype table order:
    Player, FG (M/A), 3PT (M/A), FT (M/A), REB, AST, STL, BLK, TO, PF, PTS (active quarter), Totals.
  - Error line and Save / Publish result / Cancel buttons. Cancel links back to the bracket.
- Inputs use Bootstrap/`hf-*` classes; no inline styles beyond what the prototype already uses.

Behaviour:

- `updated($name)` watches:
  - `quarters.team1.*` / `quarters.team2.*` → re-sync that team’s final score to the quarter sum.
  - `stats.team1.*` / `stats.team2.*` → recompute that team’s active-quarter score from all roster
    players’ FG/3PT/FT for the active quarter, write it into `quarters`, and re-sync the final.
  - `finals.*` → no side effect; publish validation catches mismatches.
- `setActiveQuarter($index)` clamps to the available range.
- `addOvertime()` appends a zero to both team quarter arrays and every player’s quarter list.
- `removeOvertime()` removes the last quarter only when the count is above 4. If the last quarter
  has a non-zero score or any player data, it opens the confirm dialog first.
- `saveDraft()` calls `GameStatsService::saveDraft()`, catches `InvalidArgumentException`, shows the
  message in the error line, and dispatches a danger toast. Success dispatches `Saved`.
- `requestPublish()` validates locally (tie, final/quarter mismatch) then opens a confirm dialog
  with the prototype message. `confirmPublish()` calls `GameStatsService::publish()`, then reloads
  the match, resets the editor, and dispatches `Result published.`
- `changeMatch()` / the header link returns to `route('gameManage')`.
- All mutations are guarded by `Gate::authorize('enterStats', $match)`.

### 5. Read-only game-stats page changes

`App\Livewire\Organizer\GameStatsShow`:

- Eager-load `playerQuarterStats`, `playerStats.player`, and `playerStats.team`.
- Group the match's quarter rows by `player_id` and include them in each `rowsFor()` row.
- Extend the `quarters()` computed to return 4–9 values instead of exactly four, and render dynamic
  `Q1`…`OT5` headers on the team quarter breakdown.
- Render the prototype's per-player expandable block under each team table:

  ```
  <details data-testid="stats-player-quarters-{side}-{playerId}">
    <summary><strong>{player}</strong> — by quarter</summary>
    Q | PTS | FG | 3PT | FT | REB | AST | STL | BLK | TO | PF
  </details>
  ```

  - Rows use Q1–Q4, then OT1…OT5.
  - PTS is read from the stored quarter row.
  - Fields render as made/attempted for FG, 3PT, and FT.
  - The whole details block is omitted for players with no quarter rows.
- Add an organizer action `In-game scoring` in the page header when the match is scheduled and both
  teams are set, linking to `route('gameManage', ['match' => $match->id])`.
- When a scheduled game already has saved stats, label the box score `Draft box score` instead of
  `Official box score`. Final/forfeit games keep `Official box score`.
- In `mount()`, open the correction dialog automatically when the request has `correct=1`, the user
  is an organizer, and the game is final/forfeit, so the locked in-game screen can deep-link to it.

### 6. Links and navigation

- Bracket match detail dialog: for organizers, add `In-game score & player stats` linking to
  `route('gameManage', ['match' => $detailMatch->id])`, `data-testid="bracket-in-game"`.
- Organizer workspace nav: add a `Game scoring` item with icon `clipboard-pulse`, route `gameManage`,
  `data-testid="nav-gameManage"`. Add `gameManage` to the organizer `$roleTools` list and to the
  `schedule` active group.
- Game-stats header: add `In-game scoring` for organizers as described above.
- Matches page keeps linking cards to the read-only game stats; the nav and bracket cover the
  management entry point.

### 7. Error handling and audit

- Domain validation throws `InvalidArgumentException` with user-facing messages.
- The component catches and surfaces the message in the existing `hf-field-error` line plus a
  danger toast. It never leaves a partially written draft.
- Publish confirmation is required; cancelling leaves all inputs untouched.
- Audit entries:
  - `stats.save` on a draft, entity type `match`, with `before_json` = previous quarters (if any)
    and `after_json` = new quarters + player count.
  - `stats.publish` on publish, entity type `match`, with the published quarters.
  - `BracketService::recordScore` continues to write `score.save` and propagate the winner.
- Audit writes use `AuditLogger` (same shape as `BracketService::writeAudit`), with organization ID
  from the tournament.
- All write operations run inside `DB::transaction`, including the nested bracket call.
- `publish()` must reject a final/forfeit match before calling `BracketService::recordScore()`, which
  would also reject it; this keeps the error message consistent between save and publish.

### 8. Data flow

1. Organizer opens `/game-manage`, sees the picker.
2. Chooses a scheduled game; `GameManage::mount()` loads quarter scores, finals, roster, and existing
   stats through `GameStatsService::load()`.
3. Edits quarter scores and active-quarter player stats. Each stat edit recomputes that team’s
   active-quarter score and final on the server.
4. Save → `saveDraft()` → `matches.quarters_json`, `player_stats`, `player_stat_quarters`,
   `stats.save` audit. Match remains scheduled.
5. Publish → confirm → `publish()` → persist stats → `BracketService::recordScore()` → match final,
   winner advanced, standings recalculated, `score.save` + `stats.publish` audit.
6. The page reloads into the locked state; the read-only game-stats page shows the official box
   score with per-player quarter details.

## Testing

Feature tests (`tests/Feature/Pages/GameManageTest.php`):

- Guest is redirected; coach and fan receive 403; organizer receives 200 on the picker.
- Picker renders tournament/status/team filters and match cards.
- Editor renders the prototype structure and test IDs: quarter tabs, team panels, overtime buttons,
  side filters, team searches, save/publish/cancel.
- Save draft persists quarters, totals, and quarter rows; status stays scheduled; audit exists.
- Stat edits recompute the active-quarter team score and final.
- Quarter edits re-sync finals.
- Add overtime adds a quarter to both teams and every player; remove works; cap at 9.
- Publish finalizes, sets winner, advances the bracket, writes audits, and renders the locked state.
- Publish rejects a tie and a finals/quarters mismatch.
- Final and forfeit matches render locked and cannot be mutated.
- Coaches/fans cannot call save or publish.
- Game-stats page renders per-player quarter details and the organizer entry link.

Service tests (`tests/Feature/Services/GameStatsServiceTest.php`):

- Derived points use the standard formula.
- Totals sum the quarter rows.
- Unknown players and made > attempted are rejected.
- Quarter padding and maximum are enforced.
- Postgres partition key handling is covered by the existing driver-conditional seeder pattern; the
  SQLite test suite verifies the same code path with the conditional omitted.

Existing tests must keep passing. `composer quality` must pass (Pint, Larastan level 6, Pest).

## Acceptance criteria

- `/game-manage` exists, is organizer-only, and renders the prototype picker and editor.
- An organizer can enter quarter scores, add/remove overtime, edit per-quarter player stats, save a
  draft, and publish a result.
- Publishing advances the winner and updates standings through the existing bracket service.
- Totals are derived from quarter data and match the standard box-score formula.
- Final games are locked from further in-game edits and point to the correction workflow.
- The read-only game-stats page shows per-player quarter breakdowns.
- Links exist from the bracket match detail, organizer nav, and game-stats header.
- `composer quality` passes with actual output reported.
