# Per-quarter player stats — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add per-player, per-quarter full stat lines to the in-game management page (editable, with auto-summed totals) and display them on the game-stats page, while staying backward compatible with seeded/old data.

**Architecture:** Extend each player entry in `match.boxScore.team1/team2` with an optional `quarters` array of full stat-line objects. Totals are derived (sum of quarters) when quarters are present, otherwise stand alone. The in-game page gains a collapsible "By quarter" panel per player (compact points grid + expandable full stats). The game-stats page gains an expandable per-player quarter breakdown. `saveGameStats` persists quarters; publish uses derived totals.

**Tech Stack:** Static HTML, vanilla ES modules, Node's built-in test runner. No new dependencies.

## Global Constraints

- Work only in `prototype/`, `tools/`, and `docs/superpowers/`. Do not touch `src/`, `docs/wireframes/`, `HoopFlow/`, `logo/`, `video/`, or `_archive/`.
- Runtime must work offline. No `https://` asset references in prototype HTML or CSS.
- No `alert()`, `confirm()`, or `prompt()`. Use `ui.toast()` and `ui.confirm()`.
- No client-side framework, no build step, no runtime npm dependencies.
- All existing prototype routes stay valid. No new routes.
- Node tests: `cd prototype` then `node --test`.
- Never stage `docs/wireframes/`.

---

### Task 1: Extend data model and store for per-quarter player stats

**Files:**
- Modify: `prototype/assets/js/store.js` (`buildBoxScore`, `saveGameStats`)
- Modify: `prototype/assets/js/seed.js` (boxScore entries — add empty `quarters: []`)

**Interfaces:**
- Produces: player entry shape `{ playerId, points, fgm, fga, tpm, tpa, ftm, fta, reb, ast, stl, blk, to, pf, quarters: [{fgm, fga, tpm, tpa, ftm, fta, reb, ast, stl, blk, to, pf, points}] }`. `quarters` optional; totals derived when present.

- [ ] **Step 1: Update buildBoxScore** to include `quarters: []`:

```js
function buildBoxScore(team, total, quarters = null) {
  const count = team.players.length || 1;
  const base = Math.floor(total / count);
  const remainder = total % count;
  return team.players.map((player, index) => ({
    playerId: player.id,
    points: base + (index < remainder ? 1 : 0),
    fgm: 0, fga: 0, tpm: 0, tpa: 0, ftm: 0, fta: 0,
    reb: (index * 2 + 2) % 9,
    ast: (index * 3 + 1) % 7,
    stl: 0, blk: 0, to: 0, pf: 0,
    quarters: [],
  }));
}
```

- [ ] **Step 2: Update saveGameStats** to accept per-player `quarters` in the stats payload and derive totals. When a player entry has a non-empty `quarters` array, recompute that player's totals as the sum of quarters (points, fgm, fga, tpm, tpa, ftm, fta, reb, ast, stl, blk, to, pf). Store the `quarters` array on the entry. If `quarters` is empty/absent, keep totals as provided (manual override).

Add a helper:
```js
function sumPlayerQuarters(qs) {
  const fields = ['fgm','fga','tpm','tpa','ftm','fta','reb','ast','stl','blk','to','pf'];
  const totals = { points: 0 };
  for (const f of fields) totals[f] = 0;
  for (const q of qs) {
    for (const f of fields) totals[f] += Number(q?.[f]) || 0;
    totals.points += Number(q?.points) || (Number(q?.fgm||0)*2 + Number(q?.tpm||0)*3 + Number(q?.ftm||0));
  }
  return totals;
}
```

In saveGameStats, after validating, for each side and each player index:
```js
const entry = stats1[i];
if (Array.isArray(entry?.quarters) && entry.quarters.length) {
  const derived = sumPlayerQuarters(entry.quarters);
  Object.assign(entry, derived);
}
```

- [ ] **Step 3: Update seed.js** — add `quarters: []` to each buildBoxScore entry (already handled by buildBoxScore change) and to seeded match boxScores. No per-quarter data in seed (backward compat).

- [ ] **Step 4: Run tests**

```powershell
cd prototype
node --test
```
Expected: all pass (51).

- [ ] **Step 5: Commit**

```powershell
git add prototype/assets/js/store.js prototype/assets/js/seed.js
git commit -m "feat(prototype): add per-player quarters to boxScore model"
```

---

### Task 2: In-game page — per-player "By quarter" panel

**Files:**
- Modify: `prototype/assets/js/pages/game-manage.js`

**Interfaces:**
- Consumes: `store.actions.saveGameStats`, `ui.dialog`/`ui.toast`/`ui.setBusy`.
- Produces: collapsible per-player quarter panel with compact points grid (Q1..OT) + expandable full stats.

- [ ] **Step 1: Add per-player quarter panel** inside `teamStatsTable` (or as an expandable row). For each player row, add a toggle button "By quarter" that reveals a panel containing:
  - A compact table: rows = quarters (Q1, Q2, Q3, Q4, OT1…), columns = PTS (and total). Each quarter cell is an input for points? No — per-quarter full stat line. To keep UI manageable, show a compact points grid (Q1..OT inputs for points) PLUS an expandable "full stats" toggle that reveals FG/3PT/FT/REB/AST/STL/BLK/TO/PF inputs per quarter.
  - Actually simpler: per quarter, a row of inputs: PTS, FG (M/A), 3PT (M/A), FT (M/A), REB, AST, STL, BLK, TO, PF. Auto-derive PTS = FGM*2 + TPM*3 + FTM.
  - "Add quarter" / "Remove last quarter" buttons at the team level (synced with team quarters count — already exists).

- [ ] **Step 2: Wire Save/Publish** — collect per-player quarters from the panel inputs, pass in stats payload. If a player has no per-quarter data entered (all zeros/empty), send `quarters: []` (totals-only fallback).

- [ ] **Step 3: Auto-sum** — editing a per-quarter cell recomputes that quarter's PTS and re-sums the player's totals + team quarter totals + final score.

- [ ] **Step 4: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/game-manage.js
node --test
git add prototype/assets/js/pages/game-manage.js
git commit -m "feat(prototype): add per-player per-quarter stats panel to in-game page"
```

---

### Task 3: Game-stats page — per-player quarter breakdown

**Files:**
- Modify: `prototype/assets/js/pages/game-stats.js`

**Interfaces:**
- Consumes: `match.boxScore.team1/team2` entries with `quarters`.

- [ ] **Step 1: Add expandable per-player quarter breakdown** — each player row gains a toggle "quarters" that reveals a compact grid: Q1, Q2, Q3, Q4, OT points (and on further expand, full stat line per quarter). Show "—" when no per-quarter data.

- [ ] **Step 2: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/game-stats.js
node --test
git add prototype/assets/js/pages/game-stats.js
git commit -m "feat(prototype): add per-player quarter breakdown to game stats"
```

---

### Task 4: Static gates and verification

**Files:**
- Create: `docs/superpowers/verification/2026-11-06-perquarter-player-stats.md`

- [ ] **Step 1: Run static gates**

```powershell
cd prototype
Get-ChildItem assets/js -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }
node --test
```

- [ ] **Step 2: Run sweeps** (legacy, CDN, native dialogs, https, scorekeeper).

- [ ] **Step 3: Write verification record.**

- [ ] **Step 4: Commit**

```powershell
git add docs/superpowers/verification/2026-11-06-perquarter-player-stats.md
git commit -m "docs: record per-quarter player stats verification"
```

---

## Self-Review

1. **Spec coverage:** Data model (Task 1), in-game per-quarter panel (Task 2), game-stats display (Task 3), verification (Task 4). All spec sections covered.
2. **Placeholders:** Task 2 and 3 are large; implementers should model on existing quarter panel and stats table patterns. Full code not provided due to size, but behaviors are specified.
3. **Type consistency:** `quarters` array on player entry consistent across store, in-game page, game-stats page. `sumPlayerQuarters` helper in store.

## Execution Handoff

Plan complete. Two execution options:
1. Subagent-Driven (recommended)
2. Inline Execution

Which approach?