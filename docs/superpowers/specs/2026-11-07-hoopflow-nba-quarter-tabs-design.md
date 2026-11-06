# NBA-style quarter tabs for in-game score

- **Date:** 2026-11-07
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Parent:** [[2026-11-06-hoopflow-perquarter-player-stats-design]]
- **Scope:** prototype only (`prototype/`, `docs/superpowers/`).

## Problem

The current in-game page shows per-player quarter stats in collapsible "By quarter" panels below each totals row, with all quarters visible at once. This is cluttered and not NBA-like. The user wants: select a quarter (tabs), edit player stats for that quarter, and have the team's per-quarter score sync automatically.

## Goals

1. Replace the collapsible per-player quarter panels with **quarter tabs** at the top of the page (Q1, Q2, Q3, Q4, OT1…).
2. Selecting a tab shows **only that quarter's** player stat inputs for both teams.
3. Editing a player stat in the active quarter re-derives that player's PTS and re-syncs the team's per-quarter score + running total + final score.
4. Keep totals (auto-derived from quarters) displayed as a read-only summary; manual total edits clear per-quarter data (manual override, existing rule).
5. Keep the game-stats page per-player quarter breakdown as-is.

## Non-goals

- No change to data model (already supports per-player quarters).
- No change to `src/`.
- No new routes.

## Decisions

### 1. Quarter tabs

- Rendered at the top of the management form (below the page header, above the team quarter panels).
- Tabs: Q1, Q2, Q3, Q4, then OT1, OT2, … (up to the current quarter count).
- Active tab highlighted; clicking sets `activeQuarter` (index) and re-renders the player stats tables for that quarter.
- "Add overtime" / "Remove last OT" buttons remain; they add/remove a quarter tab + team quarter input + per-player quarter column.

### 2. Team quarter panels

- Remain visible above the tabs (Q1..OTn score inputs + final score + running total).
- When a player stat changes in the active quarter, the corresponding team quarter input + running total + final score auto-update (sync from player stats).
- Manual edit of a team quarter input does NOT clear player stats (team score is a derived display that can be overridden).

### 3. Player stats tables

- Show only the active quarter's inputs (FG M/A, 3PT M/A, FT M/A, REB, AST, STL, BLK, TO, PF, PTS auto-derived).
- The totals row/column is shown as read-only derived values (sum of all quarters) below or beside each player.
- Search + team filter (from Task 4) still apply.
- Editing a total stat input clears per-quarter data for that player (manual override).

### 4. Data flow

- State: `activeQuarter` index. Default 0 (Q1).
- On tab change: re-render stats tables for the new quarter (inputs bound to `entry.quarters[activeQuarter]`).
- On player stat input: update `entry.quarters[activeQuarter][field]`, recompute that quarter's PTS, re-sum player totals, re-sync team quarter score + final.
- Save/Publish: collect per-player quarters (all quarters, not just active) and persist. Publish validates quarter sums == finals.

### 5. Acceptance criteria

- Quarter tabs switch the displayed player stats.
- Editing a player stat in a quarter updates the team's per-quarter score and final score.
- OT can be added/removed; tabs update.
- Search + team filter still work.
- Backward compat: players without per-quarter data show empty inputs.
- All existing tests pass.
- `node --test` green; static sweeps clean.

## Open questions (resolved)

- **Totals editable?** Editable with manual override (editing a total clears per-quarter data). Resolved: yes.
- **Active quarter persists?** Yes — `activeQuarter` is preserved across Save/Publish re-renders. Resolved: yes.

---

Spec written for review. Confirm or request changes before I write the implementation plan.