# In-game score as a full page + OT + search/filter

- **Date:** 2026-11-05
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Parent:** [[2026-11-04-hoopflow-subproject1-role-consolidation-ingame-scoring-design]]
- **Scope:** prototype only (`prototype/`, `docs/superpowers/`).

## Problem

The in-game score & player stats management surface is currently a wide dialog opened from the bracket. It is hard to use on a phone, cannot be deep-linked, and only supports Q1–Q4 (no overtime). The game-stats page has no search or team filter over its player table. The user wants a full-page, linkable, searchable experience with OT support.

## Goals

1. Convert in-game score & player stats management from a dialog into a full page (`games/manage.html`), reachable by link from the bracket (and from matchups/schedule).
2. Support overtime: Q1–Q4 plus a dynamic "Add overtime" button (up to 5 OT periods), each quarter an input; quarters auto-sum into the final.
3. Add a match picker at the top of the in-game page: a filterable list of matches (by tournament, team, status) so the page works standalone (not only via `?match=`).
4. Add player search + team filter (+ existing column sort) to the player stats tables on **both** the in-game page and the game-stats page.

## Non-goals

- No real-time/live clock, play-by-play, or multi-user concurrency.
- No change to `src/`.
- No drag-and-drop or bracket reordering (Sub-project 2).
- OT is capped at 5 periods in v1.

## Decisions

### 1. Route and page

- New route `gameManage: 'games/manage.html'` in `routes.js`.
- New files: `prototype/games/manage.html`, `prototype/assets/js/pages/game-manage.js`.
- `games/manage.html?match=<id>` opens that match directly; with no `match` param, the page shows the match picker.
- Guarded by `guardRole({ roles: ['organizer'] })` — non-organizers see the restricted empty state and redirect.

### 2. Bracket integration

- In `pages/bracket.js`, replace the "In-game score & player stats" dialog button with a link to `route('gameManage', { match: match.id })` (label unchanged, `data-testid="match-in-game"`). Remove the `openInGameManagement` import and the dialog call.
- Remove `openInGameManagement` from `score-dialogs.js` (no longer used). `openScoreConsole` and `openCorrectionDialog` remain.

### 3. OT support in the data model

- `match.boxScore.quarters.team1/team2` become variable-length arrays of length ≥ 4 (Q1–Q4) and up to 9 (Q1–Q4 + 5 OT).
- `normalizeQuarters` in the new page pads to at least 4 and trims to 9.
- `saveGameStats` validates each quarter is a non-negative integer; no fixed length check (min 4 enforced in the UI).
- `saveScore` (publish) sums all quarters (Q1..OTn) into the final score.
- Seed data: existing boxScores keep 4 quarters (no OT). `buildBoxScore` unchanged (quarters stored separately).

### 4. In-game page layout

- Page header ("In-game score & player stats").
- **Match picker** section: tournament select + status filter (scheduled/final/all) + team text search → filtered list of match cards (reuse `components.matchCard` or a compact list). Clicking a match selects it (sets `?match=` via `history.replaceState` or just updates internal state + re-renders the management form below).
- **Management form** (when a match is selected): team quarter panels (Q1–Q4 + dynamic OT inputs with "Add overtime" / "Remove last OT"), running total, final score inputs; per-player stats tables (one per team) with search + team filter + sortable columns; Save (draft) and Publish result buttons.
- OT button: "Add overtime" appends a 0 quarter to both teams (up to 5 OTs); "Remove last OT" removes the last OT quarter if its value is 0 (or confirm).

### 5. Player search + team filter on stats tables

- Applies to both `game-manage.js` and `game-stats.js`.
- Each stats table gets a search input (by player name) and a team filter (Team 1 / Team 2 / All) — on game-stats, team filter switches which team's table is shown (or filters rows). On in-game page, each team has its own table but search filters rows within.
- Column headers remain sortable (existing `sortBoxScore`).
- Debounce search by 120ms (use `ui.debounce`).

### 6. Game-stats page changes

- Add search input + team filter above the box score table (filter rows by name; team filter shows one team or both).
- Keep existing columns and sort.

### 7. Data flow

- In-game page loads match from `?match=` or picker selection.
- Save draft → `saveGameStats` (quarters + stats). Publish → validate quarter sums == finals → saveGameStats then saveScore (passing boxScore via options to preserve stats).
- onDone → re-render (update KPIs/lists).

### 8. Acceptance criteria

- `games/manage.html` renders standalone (match picker) and with `?match=`.
- OT can be added/removed; quarters sum to final; publish advances bracket.
- Player search + team filter work on both pages.
- Organizer-only; non-organizers redirected.
- All existing tests pass; new tests for OT summing and search/filter logic (if testable) pass.
- `node --test` green; static sweeps clean.

## Open questions

- Should the in-game page allow editing a **final** match's stats (correction flow), or only scheduled matches? (Recommend: scheduled only for publish; allow Save draft on scheduled; final matches show read-only + "Correct score" link to existing correction dialog.)
- Should OT count toward the quarter sum validation (yes, recommend).

---

Spec written for review. Confirm or request changes before I write the implementation plan.