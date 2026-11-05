# In-game score as a full page + OT + search/filter — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert in-game score & player stats management from a dialog to a full page (`games/manage.html`), add overtime support (up to 5 OT periods), add a filterable match picker, and add player search + team filter to stats tables on both the in-game page and the game-stats page.

**Architecture:** New route `gameManage` and page module `pages/game-manage.js`. The page reads `?match=` to open a specific match, otherwise shows a filterable match picker. Management form reuses the quarter/stats logic from the dialog but adds dynamic OT inputs. The bracket page's trigger becomes a link. `saveGameStats`/`saveScore` already support variable-length quarters (no store changes needed). Player search/filter is added to both `game-manage.js` and `game-stats.js`.

**Tech Stack:** Static HTML, vanilla ES modules, Node's built-in test runner, Bootstrap 5.3 (vendored). No new dependencies.

## Global Constraints

- Work only in `prototype/`, `tools/`, and `docs/superpowers/`. Do not touch `src/`, `docs/wireframes/`, `HoopFlow/`, `logo/`, `video/`, or `_archive/`.
- Runtime must work offline. No `https://` asset references in prototype HTML or CSS.
- No `alert()`, `confirm()`, or `prompt()`. Use `ui.toast()` and `ui.confirm()`.
- No client-side framework, no build step, no runtime npm dependencies.
- All existing prototype routes stay valid. Add only `games/manage.html`.
- Node tests: `cd prototype` then `node --test`.
- Never stage `docs/wireframes/`.

---

### Task 1: Add route and HTML skeleton for the in-game page

**Files:**
- Modify: `prototype/assets/js/routes.js` (add `gameManage`)
- Create: `prototype/games/manage.html`

**Interfaces:**
- Produces: route `gameManage` → `games/manage.html`; HTML skeleton with `data-page="game-manage"`, `data-shell="app"`, `#hfShell`, `#main`.

- [ ] **Step 1: Add route to routes.js**

In `ROUTES` object, add:
```js
gameManage: 'games/manage.html',
```

- [ ] **Step 2: Create manage.html** (mirroring other app-shell pages):

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>In-game score — HoopFlow</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/hoopflow.css">
</head>
<body data-page="game-manage" data-shell="app">
  <a class="hf-skip-link" href="#main">Skip to content</a>
  <div class="hf-app">
    <div id="hfShell"></div>
    <main id="main" class="hf-main" tabindex="-1">
      <div class="hf-page-loading" role="status" aria-live="polite">Loading in-game management...</div>
    </main>
  </div>
  <script type="module" src="../assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 3: Commit**

```powershell
git add prototype/assets/js/routes.js prototype/games/manage.html
git commit -m "feat(prototype): add in-game management page route and skeleton"
```

---

### Task 2: Implement the in-game management page module

**Files:**
- Create: `prototype/assets/js/pages/game-manage.js`

**Interfaces:**
- Consumes: `route`, `escapeHtml`, `formatDate`, `formatTime`, `ui`, `components`, `store`, `guardRole`, `memoryStorage` (not needed).
- Produces: `render({ store, ui, components, root, theme })`.

- [ ] **Step 1: Scaffold the page module** with guardRole check (organizer only), URL param reading (`match`), and a `draw()` function. If no match selected, render the match picker (Task 3). If match selected, render the management form.

- [ ] **Step 2: Implement the management form** — reuse the quarter/stats logic from `openInGameManagement` (dialog code). Convert to full-page layout:
  - Quarter panels: Q1–Q4 inputs + dynamic OT inputs (up to 5) with "Add overtime" / "Remove last OT" buttons.
  - Running total per team + final score inputs; quarter inputs auto-sync final.
  - Per-player stats tables (one per team) with search input + team filter (team1/team2/all — but each table is per-team, so search filters rows within that table) + sortable columns.
  - Save (draft) and Publish result buttons.

- [ ] **Step 3: Implement Save and Publish** — Save calls `saveGameStats` (draft, toast "Saved", no finalize). Publish validates quarter sums == finals, calls saveGameStats then saveScore with `options.boxScore`, advances bracket, toast success, re-renders.

- [ ] **Step 4: Implement OT logic** — "Add overtime" appends a 0 quarter to both teams (max 5 OTs beyond Q4 = 9 quarters total). "Remove last OT" removes the last quarter if its value is 0 for both teams (or confirm if non-zero).

- [ ] **Step 5: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/game-manage.js
node --test
git add prototype/assets/js/pages/game-manage.js
git commit -m "feat(prototype): implement in-game management full page with OT"
```

---

### Task 3: Add the filterable match picker to the in-game page

**Files:**
- Modify: `prototype/assets/js/pages/game-manage.js`

**Interfaces:**
- Consumes: `store.select.matches`, `store.select.tournament`, `components.matchCard`.

- [ ] **Step 1: Implement match picker** — when no `?match=` param (or user clicks "Change match"), render:
  - Tournament select filter.
  - Status filter (scheduled / final / all).
  - Team text search.
  - Filtered list of match cards (reuse `components.matchCard` with href to `route('gameManage', { match: match.id })`).
  - Clicking a card navigates to `games/manage.html?match=<id>`.

- [ ] **Step 2: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/game-manage.js
git add prototype/assets/js/pages/game-manage.js
git commit -m "feat(prototype): add filterable match picker to in-game page"
```

---

### Task 4: Add player search + team filter to in-game page stats tables

**Files:**
- Modify: `prototype/assets/js/pages/game-manage.js`

- [ ] **Step 1: Add search input + team filter** above each team's stats table. Search filters rows by player name (debounced 120ms). Team filter toggles between showing team1's table, team2's table, or both (default both). Column headers remain sortable via `sortBoxScore`.

- [ ] **Step 2: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/game-manage.js
git add prototype/assets/js/pages/game-manage.js
git commit -m "feat(prototype): add player search and team filter to in-game page"
```

---

### Task 5: Add player search + team filter to the game-stats page

**Files:**
- Modify: `prototype/assets/js/pages/game-stats.js`

- [ ] **Step 1: Add search input + team filter** above the box score table. Search filters rows by player name (debounced 120ms). Team filter switches between team1, team2, or both (default both, showing both tables stacked). Column headers remain sortable.

- [ ] **Step 2: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/game-stats.js
git add prototype/assets/js/pages/game-stats.js
git commit -m "feat(prototype): add player search and team filter to game stats"
```

---

### Task 6: Convert bracket button to link and remove the in-game dialog

**Files:**
- Modify: `prototype/assets/js/pages/bracket.js`
- Modify: `prototype/assets/js/score-dialogs.js`

- [ ] **Step 1: In bracket.js**, replace the "In-game score & player stats" button + dialog wiring with a link: `<a class="hf-btn" href="${route('gameManage', { match: match.id })}" data-testid="match-in-game">In-game score & player stats</a>`. Remove the `openInGameManagement` import.

- [ ] **Step 2: In score-dialogs.js**, remove the `openInGameManagement` function and its export (no longer used). Keep `openScoreConsole` and `openCorrectionDialog`.

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/bracket.js
node --check assets/js/score-dialogs.js
node --test
git add prototype/assets/js/pages/bracket.js prototype/assets/js/score-dialogs.js
git commit -m "refactor(prototype): replace in-game dialog with full-page link"
```

---

### Task 7: Static gates and verification

**Files:**
- Create: `docs/superpowers/verification/2026-11-05-ingame-fullpage-search.md`

- [ ] **Step 1: Run static gates**

```powershell
cd prototype
Get-ChildItem assets/js -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }
node --test
```
Expected: no syntax output; all tests pass.

- [ ] **Step 2: Run sweeps** (legacy, CDN, native dialogs, https, scorekeeper) — expect no first-party matches.

- [ ] **Step 3: Write verification record** with environment, automated checks, deviations.

- [ ] **Step 4: Commit**

```powershell
git add docs/superpowers/verification/2026-11-05-ingame-fullpage-search.md
git commit -m "docs: record in-game full page and search verification"
```

---

## Self-Review

1. **Spec coverage:** OT support (Task 2), full page (Tasks 1-2), match picker (Task 3), player search/filter on in-game page (Task 4), player search/filter on game-stats page (Task 5), dialog removal (Task 6), verification (Task 7). All spec sections covered.
2. **Placeholders:** Task 2 is large; the implementer should model the management form on the existing dialog code (now in git history / score-dialogs.js before removal — Task 6 removes it, so Task 2 must be done before Task 6). Order matters: Tasks 2-4 before Task 6.
3. **Type consistency:** `openInGameManagement` removed in Task 6; bracket uses `route('gameManage', { match })`. Consistent.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-05-hoopflow-ingame-fullpage-search.md`.

Two execution options:
1. Subagent-Driven (recommended)
2. Inline Execution

Which approach? (I recommend Subagent-Driven.)