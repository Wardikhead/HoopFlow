# Bracket engine and playoff series design

- **Date:** 2026-11-11
- **Owner:** Jeward T. Mahinay
- **Status:** Approved for planning
- **Scope:** `src/` bracket generation, score propagation, standings, scheduling integration, bracket UI, tournament creation UI, tests.

## Problem

The current bracket engine advertises single elimination, double elimination, and round robin, but only single elimination is reliable.

- Double elimination generates a hard-coded losers bracket that only resembles the 8-team shape. Play-through probes leave matches permanently unfilled for 4, 8, and 16 teams. The winners final and grand final also share the same `round_number`/`match_number` identity.
- Round robin works for even team counts but creates fake final matches for odd-team byes, ignores the configured slot count, and has no configurable number of meetings.
- The bracket page labels losers rounds as “Round 1001”, sorts them ahead of the winners bracket, and can crown the winners-bracket final winner as champion before the grand final.
- Playoff rounds are always single games. There is no best-of series support.
- Round robin has no playoff stage.

## Goals

1. Make double elimination fully playable and accurate for 4–32 team brackets, including byes, loser routing, grand final, and reset.
2. Make round robin accurate for even and odd team counts, with configurable meetings per pair and no phantom matches.
3. Add best-of series for quarterfinals, semifinals, and finals in elimination formats.
4. Add an optional round-robin playoff stage seeded from final standings.
5. Fix the bracket UI, champion detection, edit-slots targeting, and scheduling order for all formats.
6. Add head-to-head as the first standings tiebreaker.

## Non-goals

- No drag-and-drop bracket editing.
- No new competition formats beyond the three existing ones.
- No change to the prototype under `prototype/`.
- No live in-game scorekeeping changes beyond selecting the active series game.
- No automatic court assignment for newly activated series games; they appear as unscheduled for the organizer to place.

## Decisions

### 1. Stage and phase schema

Add explicit bracket identity instead of magic round numbers.

- `matches.stage`: `winners`, `losers`, `grand_final`, `reset`, `round_robin`.
- `bracket_slots.stage`: same values, so winners round 1 and losers round 1 do not collide.
- `brackets.phase`: `main`, `group`, `playoffs`.
  - Single elimination and double elimination use `main`.
  - Round robin group stage uses `group`.
  - Round-robin playoffs use `playoffs`.

Existing rows are backfilled:

- `single_elim` → `winners` / `main`.
- `round_robin` → `round_robin` / `group`.
- Existing double-elimination rows are mapped best-effort (`round_number >= 1000` → `losers`). Existing DE brackets are already structurally wrong and must be regenerated; no automatic data deletion is performed.

### 2. Double-elimination structure

For bracket size `N = 2^k`:

- Winners bracket: `k` rounds. Round 1 is the first round, round `k` is the winners final.
- Losers bracket: `2(k-1)` rounds.
  - Round 1 receives losers of winners round 1.
  - Even losers rounds receive winners of the previous losers round plus losers of winners round `j/2 + 1`.
  - Odd losers rounds from 3 onward pair winners of the previous losers round.
  - The last losers round receives the winners-final loser.
- Grand final: winners champion vs losers champion.
- Reset: a conditional best-of-1 series, created only when the grand final is best-of-1.

Expected totals including reset:

| Teams | Winners | Losers | Grand final | Reset | Total |
|---|---|---|---|---|---|
| 4 | 3 | 2 | 1 | 1 | 7 |
| 8 | 7 | 6 | 1 | 1 | 15 |
| 16 | 15 | 14 | 1 | 1 | 31 |
| 32 | 31 | 30 | 1 | 1 | 63 |

Byes are resolved generically:

- A matchup with one resolved team and no possible opponent becomes a bye: `final`, winner set, no scores.
- A matchup with no possible teams becomes `cancelled`.
- Resolution repeats until stable so byes cascade through both brackets.
- `cancelled` and `conditional` matches are hidden from normal bracket play and excluded from scheduling.

### 3. Round-robin structure

- Circle method for `n` teams.
- Odd `n`: one bye per matchday, with no fake match row.
- `rules_json.round_robin_legs`: integer 1–10, default 1.
  - Leg 1 uses the base pairings.
  - Even legs swap home and away.
  - Matchdays are numbered continuously across legs.
- Respect `rules_json.slots`: reject approved teams greater than slots. Round robin accepts 2–16 teams.
- Return `byes = 0`; byes are not matches.

### 4. Series anchor model

Every matchup is a series. The anchor match is game 1. This avoids a separate series table and preserves existing single-game rows as best-of-1 series.

New `matches` columns:

- `series_id`: nullable self-reference to the anchor match. `null` means the row is an anchor.
- `game_number`: unsigned small integer, default 1.
- `best_of`: unsigned small integer, default 1. Valid values: 1, 3, 5, 7, 9.
- `series_home_wins`: unsigned small integer, default 0.
- `series_away_wins`: unsigned small integer, default 0.
- `series_winner_team_id`: nullable team reference.
- `game_winner_team_id`: nullable team reference, always the winner of that individual game.

Anchor semantics:

- `winner_team_id` on the anchor is the series winner once decided; before that it is null.
- `game_winner_team_id` on the anchor is the game 1 winner.
- Child games (`series_id = anchor.id`, `game_number = 2..best_of`) store their own game winner in both `winner_team_id` and `game_winner_team_id`.
- For best-of-1, the anchor is the only game and both winner columns match.
- Bracket advancement uses the anchor's `winner_team_id`. Standings, team records, and per-game displays use `game_winner_team_id` for individual games.

Statuses:

- Match/series statuses: `scheduled`, `in_progress`, `final`, `forfeit`, `conditional`, `cancelled`.
- `conditional`: a future series game that has not been activated.
- `in_progress`: a series with at least one game played but not decided.
- `cancelled`: a structurally empty matchup or an unused series game.

### 5. Series lifecycle

- All potential games are created up front.
- Game 1 is `scheduled`; games 2..N are `conditional`.
- Recording a game:
  - Store the game score and `game_winner_team_id`.
  - Recompute `series_home_wins` and `series_away_wins` from all completed games.
  - If a side reaches `floor(best_of / 2) + 1` wins, the series is decided:
    - Set the anchor winner, `series_winner_team_id`, status `final` (or `forfeit` when the deciding game was a forfeit), and completion time.
    - Cancel remaining conditional games.
    - Propagate the anchor winner and loser to downstream bracket slots.
  - Otherwise:
    - Set the anchor status to `in_progress`.
    - Activate the next conditional game with both teams and status `scheduled`.
- Correcting a game recomputes the series:
  - If the series winner changes and a downstream matchup already has a result, reject the correction with a clear domain error.
  - If the series becomes undecided, clear the anchor winner and downstream scheduled teams, then reactivate the next game.
- Forfeits are recorded per game and count as a game win for the opponent.

### 6. Series round mapping

Configured independently in tournament rules:

- `best_of_quarterfinal`
- `best_of_semifinal`
- `best_of_final`

Each is one of 1, 3, 5, 7, 9; default 1.

Mapping:

- Single elimination: final = last round, semifinal = second-to-last, quarterfinal = third-to-last.
- Double elimination:
  - winners-bracket final/semifinal/quarterfinal use the matching setting
  - losers bracket is always best-of-1
  - grand final uses `best_of_final`
  - if `best_of_final = 1`, a conditional reset is created
  - if `best_of_final > 1`, the grand-final series decides the champion and no reset is created
- Round-robin playoffs: same mapping as single elimination.

Rounds that do not exist (for example, a 4-team bracket has no quarterfinal) simply ignore the unused setting.

### 7. Round-robin playoffs

- `rules_json.playoff_teams`: `0`, `2`, `4`, `8`, or `16`; default `0` (disabled).
- Playoff qualification uses final standings, including head-to-head tiebreakers.
- The organizer clicks “Generate playoffs” only after every group game is `final`, `forfeit`, or `cancelled`.
- Generation creates a separate `Bracket` row:
  - `format = single_elim`
  - `phase = playoffs`
  - name = “{Tournament} Playoffs”
- Seeds are the top `playoff_teams` teams by rank.
- The playoff bracket uses the same single-elimination builder and series settings.
- Regenerating the group stage removes or invalidates the playoff bracket only when no playoff result exists; otherwise the action is rejected.

### 8. Standings

Ranking order:

1. Wins.
2. Head-to-head wins among teams tied on wins.
3. Point differential.
4. Points scored.
5. Seed.

`playoffCut` uses `playoff_teams` when configured; otherwise it keeps the existing top-half behavior for display.

### 9. UI

Tournament creation:

- “Meetings per pair” number input for round robin (1–10).
- “Playoff teams” select for round robin (0, 2, 4, 8, 16).
- Best-of selects for quarterfinal, semifinal, and final (1, 3, 5, 7, 9).
- Values stored in `rules_json`.

Bracket page:

- Stage-aware labels:
  - “Winners Round 1”, “Winners Semifinal”, “Winners Final”
  - “Losers Round 1”, “Losers Semifinal”, “Losers Final”
  - “Grand Final”, “Grand Final Reset (if needed)”
  - “Matchday N” for round robin
- Separate Winners, Losers, and Grand Final sections for double elimination.
- Group-stage and playoff sections for round robin.
- Series cards show `Best of N`, series score, and the active game number.
- Series detail dialog lists every game, with score entry for the active game and correction for completed games.
- Champion comes from:
  - the playoff final series for round robin with playoffs
  - for double elimination with a best-of-1 grand final: the reset series if played, otherwise the grand-final winner when the winners champion wins, otherwise no champion until the reset is played
  - for double elimination with a best-of-3+ grand final: the grand-final series winner
- `TournamentShow` uses the same champion resolution as the bracket page.
- `MatchModel::roundLabel()` becomes stage-aware so schedule, team, and match pages use the same labels.
- “Edit slots” targets the first playable round:
  - winners round 1 for double elimination
  - matchday 1 for round robin
  - first round for single elimination
- Cancelled matchups are hidden. Conditional reset shows “if needed”.

Scheduling:

- Stage-aware round ordering: winners rounds, interleaved losers rounds, grand final, reset.
- Conditional and cancelled games are excluded.
- Newly activated series games appear as unscheduled matches so the organizer can assign a court and time.

## Data model summary

`matches` additions:

| Column | Type | Default | Notes |
|---|---|---|---|
| `stage` | string(20) | `winners` | indexed with `bracket_id` |
| `series_id` | FK matches, nullable | null | anchor self-reference |
| `game_number` | unsigned small int | 1 | 1-based |
| `best_of` | unsigned small int | 1 | 1, 3, 5, 7, 9 |
| `series_home_wins` | unsigned small int | 0 | anchor |
| `series_away_wins` | unsigned small int | 0 | anchor |
| `series_winner_team_id` | FK teams, nullable | null | anchor |
| `game_winner_team_id` | FK teams, nullable | null | every game |

`bracket_slots` addition:

| Column | Type | Default | Notes |
|---|---|---|---|
| `stage` | string(20) | `winners` | indexed with `bracket_id` |

`brackets` addition:

| Column | Type | Default | Notes |
|---|---|---|---|
| `phase` | string(20) | `main` | `main`, `group`, `playoffs` |

## Architecture

- `app/Services/Bracket/DoubleEliminationBuilder.php`: pure-PHP plan for winners, losers, grand final, reset, byes.
- `app/Services/Bracket/RoundRobinBuilder.php`: pure-PHP plan for matchdays and legs.
- `app/Services/Bracket/SingleEliminationBuilder.php`: shared by single elimination and round-robin playoffs; applies series settings.
- `BracketService`: persistence, bye resolution, series lifecycle, propagation, champion resolution, playoff generation.
- `StandingsService`: head-to-head ranking and playoff cut.
- `SchedulingService`: stage-aware ordering and conditional filtering.
- Livewire and Blade: bracket page, series dialogs, tournament creation fields.

## Testing

- Builder unit tests:
  - DE sizes 4, 8, 16, 32 with full fields.
  - DE byes for 5, 6, 7, 9, and 15 approved teams.
  - Every matchup has unique stage/round/match identity.
  - Each non-champion team exits with exactly two series losses.
  - RR even and odd fields, legs 1/2/3, unique pairings, no phantom matches.
- Play-through integration:
  - Simulate every game; exactly one champion.
  - Champion has at most one series loss in DE.
  - Reset is played only when the losers champion wins a best-of-1 grand final.
  - Grand-final series with `best_of > 1` never creates a reset.
- Series tests:
  - 2–0 and 2–1 outcomes.
  - 3–2 and 4–3 outcomes.
  - Forfeit-decided series.
  - Corrections that change or un-decide a series.
- Round-robin playoff tests:
  - Qualification cut and seeding.
  - Generation blocked until group stage completes.
  - Playoff bracket uses series settings.
- Standings head-to-head tests.
- Bracket page tests for labels, sections, champion, edit-slots target, and series display.
- `composer quality` must pass.

## Acceptance criteria

- A generated DE bracket can be played to completion for 4, 8, 16, and 32 teams, including bye cases.
- A generated RR bracket is accurate for even and odd fields and any configured legs.
- Quarterfinal, semifinal, and final series work in single elimination, DE winners bracket, DE grand final, and RR playoffs.
- The bracket page shows correct sections, labels, series scores, and champion.
- Scheduling and edit-slots use the correct first playable round.
- Existing single-elimination behavior and tests remain green.
- `composer quality` passes.
