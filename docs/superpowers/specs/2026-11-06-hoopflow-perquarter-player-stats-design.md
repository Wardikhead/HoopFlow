# Per-quarter player stats in in-game management

- **Date:** 2026-11-06
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Parent:** [[2026-11-05-hoopflow-ingame-fullpage-search-design]]
- **Scope:** prototype only (`prototype/`, `docs/superpowers/`).

## Problem

The in-game score & player stats page captures only per-player **totals**. There is no way to record or review which quarter a player's points (or other stats) came in, so "identify its score for first quarter and so on" is impossible. The team-level quarter breakdown exists, but per-player quarter data does not.

## Goals

1. Capture a **full stat line per player per quarter** (FG/3PT/FT M/A, REB, AST, STL, BLK, TO, PF, PTS) in the in-game management page.
2. Auto-sum per-quarter player stats into the player's totals (and into team quarter totals).
3. Display per-player per-quarter breakdown on the game-stats page.
4. Stay backward compatible: matches without per-quarter player data keep working (totals only).

## Non-goals

- No play-by-play, shot charts, or per-possession data.
- No change to `src/`.
- No change to bracket/schedule logic.

## Decisions

### 1. Data model

Extend each player entry in `match.boxScore.team1/team2`:

```js
{
  playerId,
  // totals (kept for backward compat; derived when quarters present)
  points, fgm, fga, tpm, tpa, ftm, fta, reb, ast, stl, blk, to, pf,
  // NEW: per-quarter full stat lines
  quarters: [
    { fgm, fga, tpm, tpa, ftm, fta, reb, ast, stl, blk, to, pf, points }, // Q1
    ... // Q2, Q3, Q4, OT...
  ],
}
```

- `quarters` length matches the team's quarter count (Q1–Q4 + OTs).
- When `quarters` is present and non-empty, **totals are derived** (sum of quarters) and the stored totals are overwritten on Save/Publish.
- When `quarters` is absent/empty, totals stand alone (backward compat with seeded/old data).
- `buildBoxScore` produces entries with `quarters: []` (totals only) so generated matches behave like before.

### 2. In-game page UI

- Each player row keeps the totals row (editable) **plus** a collapsible "By quarter" panel (collapsed by default) showing a compact table: rows = quarters (Q1, Q2, Q3, Q4, OT1…), columns = the full stat line.
- Editing any per-quarter cell re-derives that quarter's PTS and re-sums the player's totals + team quarter totals.
- "Add overtime" (team level) appends an empty quarter column to every player's quarters array.
- Save (draft) persists per-quarter + totals. Publish validates that per-quarter sums == finals (when per-quarter data is filled) and finalizes.

### 3. Game-stats page UI

- Each player's row gains an expandable "quarters" toggle showing per-quarter points (Q1, Q2, Q3, Q4, OT) and, on expand, the full per-quarter stat line.
- If a player has no per-quarter data, show "—" (totals only).

### 4. Validation

- Per-quarter stat inputs: non-negative integers; made ≤ attempted.
- If any per-quarter field is filled for a player, all quarters for that player must be consistent (sums of made/attempted etc. ≤ totals). Simpler rule: per-quarter sums must equal the player's totals when per-quarter data is present; otherwise totals stand.
- Publish validates team quarter sums == final scores (already in place).

### 5. Data flow

- Load: read `entry.quarters` (or `[]`).
- Edit totals directly → clears per-quarter data (treat as manual override) OR keeps per-quarter and warns. Recommend: editing a total clears per-quarter for that player (manual override takes precedence, matches "totals stand alone" fallback).
- Edit per-quarter → recompute totals.
- Save draft → persist quarters + (recomputed) totals.
- Publish → finalize using totals.

### 6. Acceptance criteria

- In-game page can capture per-player per-quarter full stat line; totals auto-sum; publish advances bracket.
- Game-stats page shows per-quarter breakdown per player.
- Backward compat: seeded matches (no per-quarter) render totals only.
- All existing tests pass; new tests for per-quarter summing pass.
- `node --test` green; static sweeps clean.

## Open questions (resolved)

- **UI density:** Compact per-quarter points grid (Q1..OT columns) in the main table per player, plus an expandable "full stats" toggle for the detailed per-quarter stat line. (Resolved: option 1.)
- **Required or optional:** Per-quarter stats are **optional** — totals suffice; per-quarter is bonus data. (Resolved: option a.)

---

Spec written for review. Confirm or request changes before I write the implementation plan.