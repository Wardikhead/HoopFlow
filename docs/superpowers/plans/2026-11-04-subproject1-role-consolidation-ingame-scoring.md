# Sub-project 1: Role consolidation and in-game scoring — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the `scorekeeper` role (merge into organizer), and add an organizer-only "In-game score & player stats management" dialog on matchups that captures Q1–Q4 team scores plus a full per-player box score, with Save (draft) and Publish result (finalize) actions.

**Architecture:** Reuse the existing store (`saveScore` for finalize, new `saveGameStats` for draft), extend `match.boxScore` with a `quarters` array (backward compatible), and add a wide dialog in `score-dialogs.js`. Bracket page gains an organizer-only trigger; game-stats page renders quarters. Seed, demo script, tests, and README are updated for the role removal.

**Tech Stack:** Static HTML, vanilla ES modules, Node's built-in test runner, Bootstrap 5.3 (vendored). No new dependencies.

## Global Constraints

- Work only in `prototype/`, `tools/`, and `docs/superpowers/`. Do not touch `src/`, `docs/wireframes/`, `HoopFlow/`, `logo/`, `video/`, or `_archive/`.
- Runtime must work offline. No `https://` asset references in prototype HTML or CSS.
- No `alert()`, `confirm()`, or `prompt()`. Use `ui.toast()` and `ui.confirm()`.
- No client-side framework, no build step, no runtime npm dependencies.
- All existing prototype routes stay valid. No new routes added in this sub-project.
- Node tests: `cd prototype` then `node --test` (not `node --test tests`, which is obsolete on Node v24).
- Never stage `docs/wireframes/`.

---

### Task 1: Remove the scorekeeper role from store, seed, guards, shell, and README

**Files:**
- Modify: `prototype/assets/js/store.js` (ROLES, PERMISSIONS, AUDIENCE_BY_ROLE)
- Modify: `prototype/assets/js/seed.js` (remove `user-scorekeeper`)
- Modify: `prototype/assets/js/guards.js` (ROLE_LABELS)
- Modify: `prototype/assets/js/shell.js` (`roleTools`, demo-menu role list)
- Modify: `prototype/README.md` (demo accounts, permissions table)
- Modify: `prototype/tests/seed.test.mjs`
- Modify: `prototype/tests/store.test.mjs`

**Interfaces:**
- Produces: role set `['organizer', 'coach', 'fan']`; organizer permission set includes `score.save`, `score.correct`, `stats.view`.

- [ ] **Step 1: Edit store.js**

```js
// ROLES (line ~15)
const ROLES = ['organizer', 'coach', 'fan'];

// PERMISSIONS (lines ~16-21)
const PERMISSIONS = {
  organizer: new Set(['*', 'score.save', 'score.correct', 'stats.view']),
  coach: new Set(['team.register', 'team.edit', 'payment.submit', 'team.view.own']),
  fan: new Set(['public.view']),
};

// AUDIENCE_BY_ROLE (lines ~27-32)
export const AUDIENCE_BY_ROLE = {
  organizer: ['all', 'coaches', 'organizers'],
  coach: ['all', 'coaches'],
  fan: ['all'],
};
```

- [ ] **Step 2: Edit seed.js** — remove the `user-scorekeeper` entry from the `users` array.

- [ ] **Step 3: Edit guards.js** — remove `scorekeeper: 'scorekeeper'` from `ROLE_LABELS`.

- [ ] **Step 4: Edit shell.js** — remove the `if (role === 'scorekeeper') return ['gameStats'];` branch; add `'gameStats'` to the organizer's `roleTools` return (so organizers can open game stats from the rail): `if (role === 'organizer') return ['submissions', 'payments', 'audit', 'createTournament', 'gameStats'];`. Remove `'scorekeeper'` from the demo-menu role list array.

- [ ] **Step 5: Edit README.md** — remove the scorekeeper row from the demo accounts table and the scorekeeper row from the roles/permissions table.

- [ ] **Step 6: Update tests**

In `prototype/tests/seed.test.mjs`:
```js
assert.deepEqual(
  SEED.users.map((u) => u.role).sort(),
  ['coach', 'fan', 'organizer']
);
```

In `prototype/tests/store.test.mjs` (permissions test):
```js
test('permissions are role-based', () => {
  const store = makeStore();
  assert.equal(store.can('team.approve'), true);
  store.actions.setRole('coach');
  assert.equal(store.can('score.save'), false);
  assert.equal(store.can('team.approve'), false);
  store.actions.setRole('fan');
  assert.equal(store.can('score.save'), false);
  assert.equal(store.can('public.view'), true);
  assert.equal(store.canRole(['organizer', 'coach']), true);
  assert.equal(store.canRole(['coach']), false);
});
```

- [ ] **Step 7: Run tests**

```powershell
cd prototype
node --test
```
Expected: seed + store tests pass (role assertions updated).

- [ ] **Step 8: Commit**

```powershell
git add prototype/assets/js/store.js prototype/assets/js/seed.js prototype/assets/js/guards.js prototype/assets/js/shell.js prototype/README.md prototype/tests/seed.test.mjs prototype/tests/store.test.mjs
git commit -m "feat(prototype): remove scorekeeper role and merge into organizer"
```

---

### Task 2: Normalize boxScore data model (quarters + full stat line)

**Files:**
- Modify: `prototype/assets/js/store.js` (`buildBoxScore`, `saveScore`, `correctScore`)
- Modify: `prototype/assets/js/seed.js` (match boxScores)

**Interfaces:**
- Produces: `match.boxScore = { team1: [...], team2: [...], quarters: { team1: [q1,q2,q3,q4], team2: [q1,q2,q3,q4] } }`. Per-player stats include `fgm, fga, tpm, tpa, ftm, fta, reb, ast, stl, blk, to, pf, points`.

- [ ] **Step 1: Update buildBoxScore in store.js**

```js
function buildBoxScore(team, total, quarters = null) {
  const count = team.players.length || 1;
  const base = Math.floor(total / count);
  const remainder = total % count;
  const q = quarters || [0, 0, 0, 0];
  return team.players.map((player, index) => ({
    playerId: player.id,
    points: base + (index < remainder ? 1 : 0),
    fgm: 0, fga: 0, tpm: 0, tpa: 0, ftm: 0, fta: 0,
    reb: (index * 2 + 2) % 9,
    ast: (index * 3 + 1) % 7,
    stl: 0, blk: 0, to: 0, pf: 0,
  }));
}
```

Keep `quarters` optional; when not provided, buildBoxScore ignores it (quarters stored separately on boxScore).

- [ ] **Step 2: Update saveScore and correctScore boxScore construction**

In `saveScore` (around line 919):
```js
target.boxScore = {
  team1: buildBoxScore(team1, first),
  team2: buildBoxScore(team2, second),
  quarters: { team1: [0, 0, 0, 0], team2: [0, 0, 0, 0] },
};
```

In `correctScore` (around line 968): same shape.

- [ ] **Step 3: Update seed.js match boxScores** — ensure each seeded `boxScore.team1/team2` entry includes the full stat fields (points, fgm, fga, tpm, tpa, ftm, fta, reb, ast, stl, blk, to, pf) and add `quarters` with the seeded final split (or zeros). The `qf-1` corrected match (78–70, previously 74–70) should have quarters summing to 78/70.

- [ ] **Step 4: Run tests**

```powershell
cd prototype
node --test
```
Expected: all pass (backward compatible).

- [ ] **Step 5: Commit**

```powershell
git add prototype/assets/js/store.js prototype/assets/js/seed.js
git commit -m "feat(prototype): normalize boxScore with quarters and full stat line"
```

---

### Task 3: Add `saveGameStats` draft action to the store

**Files:**
- Modify: `prototype/assets/js/store.js` (new action + export)

**Interfaces:**
- Consumes: `matchId`, `{ quarters: {team1, team2}, stats: {team1: [...], team2: [...]}, score1?, score2? }`.
- Produces: `store.actions.saveGameStats(matchId, payload)` → persists draft stats/quarters without finalizing; throws `StoreError` on validation failure or permission denial. Requires `score.save`.

- [ ] **Step 1: Implement saveGameStats** (insert after `correctScore`, before `commit` export block):

```js
function saveGameStats(matchId, payload = {}) {
  requirePermission(state, 'score.save');
  const match = findMatch(state, matchId);
  if (!match.team1Id || !match.team2Id) {
    throw new StoreError('TEAMS_PENDING', 'Both teams must be known before entering stats.', 'match');
  }
  const quarters1 = Array.isArray(payload.quarters?.team1) ? payload.quarters.team1 : [0,0,0,0];
  const quarters2 = Array.isArray(payload.quarters?.team2) ? payload.quarters.team2 : [0,0,0,0];
  for (const q of [...quarters1, ...quarters2]) {
    if (!Number.isInteger(q) || q < 0) throw new StoreError('VALIDATION', 'Quarter scores must be whole numbers.', 'quarters');
  }
  const stats1 = Array.isArray(payload.stats?.team1) ? payload.stats.team1 : [];
  const stats2 = Array.isArray(payload.stats?.team2) ? payload.stats.team2 : [];
  // validate made <= attempted
  const validateStat = (s, side, index) => {
    for (const k of ['fgm','fga','tpm','tpa','ftm','fta','reb','ast','stl','blk','to','pf']) {
      const v = Number(s?.[k] ?? 0);
      if (!Number.isInteger(v) || v < 0) throw new StoreError('VALIDATION', 'Stats must be whole numbers.', `${side}.${index}.${k}`);
    }
    if ((s.fgm||0) > (s.fga||0)) throw new StoreError('VALIDATION', 'Made cannot exceed attempted (FG).', `${side}.${index}.fgm`);
    if ((s.tpm||0) > (s.tpa||0)) throw new StoreError('VALIDATION', 'Made cannot exceed attempted (3PT).', `${side}.${index}.tpm`);
    if ((s.ftm||0) > (s.fta||0)) throw new StoreError('VALIDATION', 'Made cannot exceed attempted (FT).', `${side}.${index}.ftm`);
  };
  stats1.forEach((s, i) => validateStat(s, 'team1', i));
  stats2.forEach((s, i) => validateStat(s, 'team2', i));

  const sum1 = quarters1.reduce((a,b)=>a+b,0);
  const sum2 = quarters2.reduce((a,b)=>a+b,0);

  const next = clone(state);
  const target = findMatch(next, matchId);
  target.boxScore = {
    team1: stats1.length ? stats1 : target.boxScore?.team1 || [],
    team2: stats2.length ? stats2 : target.boxScore?.team2 || [],
    quarters: { team1: quarters1, team2: quarters2 },
  };
  // draft only — do not change status/winner/score
  if (payload.score1 != null) target.score1 = Number(payload.score1);
  if (payload.score2 != null) target.score2 = Number(payload.score2);
  appendAudit(next, {
    action: 'stats.save',
    entityType: 'match',
    entityId: matchId,
    summary: `Saved game stats for ${target.roundLabel}`,
    changes: { quarters: { team1: quarters1, team2: quarters2 } },
  });
  commit(next, 'stats.save');
  return clone(target);
}
```

Add `saveGameStats` to the actions export block.

- [ ] **Step 2: Export it** (add `saveGameStats,` next to `saveScore,`).

- [ ] **Step 3: Run tests**

```powershell
cd prototype
node --test
```
Expected: pass.

- [ ] **Step 4: Commit**

```powershell
git add prototype/assets/js/store.js
git commit -m "feat(prototype): add saveGameStats draft action"
```

---

### Task 4: In-game management dialog

**Files:**
- Modify: `prototype/assets/js/score-dialogs.js` (add `openInGameManagement`)

**Interfaces:**
- Consumes: `{ store, ui, matchId, onDone }`.
- Produces: dialog with Save (draft) and Publish result (finalize).

- [ ] **Step 1: Append the dialog** to score-dialogs.js. Implementation mirrors the score console but adds quarter tabs and a stats table. Key behaviors:
  - Load existing `match.boxScore.quarters` (or zeros) and per-player stats (or buildBoxScore defaults).
  - Quarter inputs update running totals and final score fields.
  - Save calls `store.actions.saveGameStats(matchId, { quarters, stats })` and toasts "Saved".
  - Publish validates quarter sums == final scores, calls `store.actions.saveScore(matchId, sum1, sum2)` to finalize/advance, and the stats are already persisted via a prior saveGameStats OR publish also calls saveGameStats first. To keep it simple: Publish first calls `saveGameStats` (draft) then `saveScore`.
  - Use `ui.setBusy` on the publish button.

- [ ] **Step 2: Export** `openInGameManagement`.

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --check assets/js/score-dialogs.js
git add prototype/assets/js/score-dialogs.js
git commit -m "feat(prototype): add in-game score and player stats dialog"
```

---

### Task 5: Wire the in-game button into the bracket page

**Files:**
- Modify: `prototype/assets/js/pages/bracket.js`

**Interfaces:**
- Consumes: `openInGameManagement` from `../score-dialogs.js`.

- [ ] **Step 1: Import** `openInGameManagement` alongside `openScoreConsole`.

- [ ] **Step 2: Add the button** in the match detail dialog footer for organizers, with `data-testid="match-in-game"` and label "In-game score & player stats". Wire click to `openInGameManagement({ store, ui, matchId: match.id, onDone: draw })`.

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/bracket.js
git add prototype/assets/js/pages/bracket.js
git commit -m "feat(prototype): add in-game management button to bracket"
```

---

### Task 6: Render quarters on the game-stats page

**Files:**
- Modify: `prototype/assets/js/pages/game-stats.js`

- [ ] **Step 1: If `match.boxScore.quarters` exists**, render a quarter-by-quarter breakdown table (Q1–Q4 + total) for both teams above the box score.

- [ ] **Step 2: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/game-stats.js
git add prototype/assets/js/pages/game-stats.js
git commit -m "feat(prototype): render quarter breakdown on game stats"
```

---

### Task 7: Update seed data and demo script test for role removal

**Files:**
- Modify: `prototype/assets/js/seed.js`
- Modify: `prototype/tests/demo-script.test.mjs`

- [ ] **Step 1: seed.js** — remove `user-scorekeeper` (already done in Task 1 if not). Ensure boxScore entries have full stat fields + quarters (done in Task 2).

- [ ] **Step 2: demo-script.test.mjs** — reword step 8: instead of setting role to scorekeeper, set role to coach and assert `store.can('score.save') === false` and `store.can('team.approve') === false`; then set role to fan and assert `store.can('score.save') === false`. Remove the `setRole('scorekeeper')` line. Also remove any reference to scorekeeper user in assertions.

- [ ] **Step 3: Run tests**

```powershell
cd prototype
node --test
```
Expected: all pass.

- [ ] **Step 4: Commit**

```powershell
git add prototype/assets/js/seed.js prototype/tests/demo-script.test.mjs
git commit -m "test(prototype): update demo script for removed scorekeeper role"
```

---

### Task 8: Final static gates and verification

**Files:**
- Modify: `docs/superpowers/verification/2026-11-04-subproject1.md` (create)

- [ ] **Step 1: Run static gates**

```powershell
cd prototype
Get-ChildItem assets/js -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }
node --test
```
Expected: no syntax output; all tests pass.

- [ ] **Step 2: Run sweeps** (legacy, CDN, native dialogs, https) — expect no first-party matches.

- [ ] **Step 3: Write verification record** `docs/superpowers/verification/2026-11-04-subproject1.md` with environment, automated checks, deviations.

- [ ] **Step 4: Commit**

```powershell
git add docs/superpowers/verification/2026-11-04-subproject1.md
git commit -m "docs: record sub-project 1 verification"
```

---

## Self-Review

1. **Spec coverage:** Role removal (Task 1), boxScore normalization (Task 2), draft action (Task 3), in-game dialog (Task 4), bracket wiring (Task 5), game-stats quarters (Task 6), seed/demo-script updates (Task 7), verification (Task 8). All spec sections covered.
2. **Placeholders:** Code snippets provided for store changes and permissions. The in-game dialog (Task 4) is described with behaviors rather than full code — the implementer should model it on the existing score console in `score-dialogs.js`. This is acceptable because the dialog is large and the pattern is established; the implementer has the existing file as a template. If the reviewer wants full code, I can expand.
3. **Type consistency:** `saveGameStats(matchId, payload)` signature consistent. `openInGameManagement({ store, ui, matchId, onDone })` consistent with `openScoreConsole`.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-04-subproject1-role-consolidation-ingame-scoring.md`.

Two execution options:
1. Subagent-Driven (recommended) — fresh subagent per task + two-stage review
2. Inline Execution — execute in this session with checkpoints

Which approach? (I recommend Subagent-Driven to match the prior 24-task workflow.)