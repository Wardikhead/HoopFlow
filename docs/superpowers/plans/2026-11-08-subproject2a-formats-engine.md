# Sub-project 2A: Bracket formats engine — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add double-elimination and round-robin bracket generation (plus custom slot counts) to the store engine, with a `format` field on tournaments and a `bracket` field on matches. SE remains the default. Includes a `regenerateBracket` action.

**Architecture:** `generateBracket(tournamentId)` dispatches by `tournament.format`. SE reuses the existing algorithm. DE builds winners + losers + grand final (with bracket reset). RR builds all pairings across matchdays. Slot validation: powers of 2 for SE/DE (2–32); 2–16 for RR. `regenerateBracket` wipes and rebuilds (guarded by no results).

**Tech Stack:** Static HTML, vanilla ES modules, Node's built-in test runner. No new dependencies.

## Global Constraints

- Work only in `prototype/`, `tools/`, and `docs/superpowers/`. Do not touch `src/`, `docs/wireframes/`, `HoopFlow/`, `logo/`, `video/`, or `_archive/`.
- Runtime must work offline. No `https://` asset references in prototype HTML or CSS.
- No `alert()`, `confirm()`, or `prompt()`. Use `ui.toast()` and `ui.confirm()`.
- No client-side framework, no build step, no runtime npm dependencies.
- All existing prototype routes stay valid. No new routes in this plan.
- Node tests: `cd prototype` then `node --test`.
- Never stage `docs/wireframes/`.

---

### Task 1: Add format field to tournament data model and seed data

**Files:**
- Modify: `prototype/assets/js/store.js` (tournament validation in `createTournament`, findTournament)
- Modify: `prototype/assets/js/seed.js` (add `format` to seeded tournaments)

**Interfaces:**
- Produces: `tournament.format: 'single-elimination' | 'double-elimination' | 'round-robin'` (default `'single-elimination'`).

- [ ] **Step 1: Update createTournament validation** — accept `format` param, validate against the three values, default to `'single-elimination'`. Store on the tournament object.

- [ ] **Step 2: Update seed.js** — add `format: 'single-elimination'` to all three seeded tournaments (tournament-1, tournament-2, tournament-3).

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --test
git add prototype/assets/js/store.js prototype/assets/js/seed.js
git commit -m "feat(prototype): add format field to tournament data model"
```

---

### Task 2: Add bracket field to match data model

**Files:**
- Modify: `prototype/assets/js/store.js` (match construction in generateBracket, makeMatch in seed.js)

**Interfaces:**
- Produces: `match.bracket: 'winners' | 'losers' | 'grand-final' | null` (null for SE/RR, which have no bracket split).

- [ ] **Step 1: Add `bracket` field** to match objects created in `generateBracket` (default `null` for SE, set appropriately for DE).

- [ ] **Step 2: Update seed.js makeMatch** — add `bracket: null` to seeded matches.

- [ ] **Step 3: Update makeMatch default** in store.js (the template match object used for byes/placeWinner).

- [ ] **Step 4: Run tests + commit**

```powershell
cd prototype
node --test
git add prototype/assets/js/store.js prototype/assets/js/seed.js
git commit -m "feat(prototype): add bracket field to match data model"
```

---

### Task 3: Refactor generateBracket to dispatch by format

**Files:**
- Modify: `prototype/assets/js/store.js` (generateBracket function)

**Interfaces:**
- Consumes: `tournament.format`, `tournament.slots`.
- Produces: `generateBracket(tournamentId)` → `{ matches, byes }` for all formats.

- [ ] **Step 1: Add format validation** — for SE/DE, slots must be in `[2, 4, 8, 16, 32]`; for RR, slots must be 2–16. Throw `VALIDATION` StoreError otherwise.

- [ ] **Step 2: Extract SE logic** into `generateSingleElimination(tournament, approved, next, created, byRound)`.

- [ ] **Step 3: Add DE generation** — `generateDoubleElimination(tournament, approved, next, created, byRound)`:
  - Winners bracket: SE-style for the approved teams.
  - Losers bracket: for each winners round, create losers matches that receive the losers of the corresponding winners matches. Number of losers matches = half the winners matches in that round.
  - Grand final: 1 match, receives winners-bracket champion and losers-bracket champion.
  - Bracket reset: if losers champion wins grand final, create a reset match (grand-final-2).
  - Set `bracket` field on each match: 'winners', 'losers', or 'grand-final'.

- [ ] **Step 4: Add RR generation** — `generateRoundRobin(tournament, approved, next, created)`:
  - Generate all pairings (round-robin) across matchdays. Use circle method for even/odd team counts.
  - Each match has `round` = matchday number, `roundLabel` = `Matchday N`.
  - No advancement (nextMatchId = null). No byes (if odd number of teams, one team gets a bye per round).
  - Set `bracket: null`.

- [ ] **Step 5: Dispatch** — at the top of `generateBracket`, read `tournament.format` and call the appropriate generator.

- [ ] **Step 6: Run tests + commit**

```powershell
cd prototype
node --test
git add prototype/assets/js/store.js
git commit -m "feat(prototype): dispatch generateBracket by format (SE/DE/RR)"
```

---

### Task 4: Add regenerateBracket action

**Files:**
- Modify: `prototype/assets/js/store.js` (new action + export)

**Interfaces:**
- Produces: `store.actions.regenerateBracket(tournamentId)` → wipes matches and regenerates. Guarded by no recorded results. Requires `bracket.generate` permission.

- [ ] **Step 1: Implement regenerateBracket** — check permission, check no results (throw `BRACKET_LOCKED` if any final/forfeit), wipe matches for tournament, call generateBracket logic, audit entry `bracket.regenerate`.

- [ ] **Step 2: Export** `regenerateBracket` in actions.

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --test
git add prototype/assets/js/store.js
git commit -m "feat(prototype): add regenerateBracket action"
```

---

### Task 5: Add rescheduleMatch action

**Files:**
- Modify: `prototype/assets/js/store.js` (new action + export)

**Interfaces:**
- Consumes: `matchId`, `{ courtId, scheduledAt }`.
- Produces: `store.actions.rescheduleMatch(matchId, { courtId, scheduledAt })` → updates court/day/time with conflict validation. Requires `schedule.generate` (or `match.reschedule`).

- [ ] **Step 1: Implement rescheduleMatch** — validate court exists, date format, no double-booking (court + time overlap), rest minutes. Update match.courtId/scheduledAt. Audit entry `schedule.reschedule`.

- [ ] **Step 2: Export** `rescheduleMatch`.

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --test
git add prototype/assets/js/store.js
git commit -m "feat(prototype): add rescheduleMatch action with conflict validation"
```

---

### Task 6: Add setBracketSlots action (manual reassignment)

**Files:**
- Modify: `prototype/assets/js/store.js` (new action + export)

**Interfaces:**
- Consumes: `tournamentId`, `assignments` (array of `{ matchId, slot: 1|2, teamId }`).
- Produces: `store.actions.setBracketSlots(tournamentId, assignments)` → reassigns teams in first-round (and DE losers) slots. Guarded by no results. Requires `bracket.generate`.

- [ ] **Step 1: Implement setBracketSlots** — validate tournament, no results, unique teams, valid matchIds. Update match.team1Id/team2Id. Audit entry `bracket.slots`.

- [ ] **Step 2: Export** `setBracketSlots`.

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --test
git add prototype/assets/js/store.js
git commit -m "feat(prototype): add setBracketSlots action for manual reassignment"
```

---

### Task 7: Update seed data for format field and bracket field

**Files:**
- Modify: `prototype/assets/js/seed.js`

- [ ] **Step 1: Add format to tournaments** — all three seeded tournaments get `format: 'single-elimination'`.

- [ ] **Step 2: Add bracket field to matches** — all seeded matches get `bracket: null` (SE).

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --test
git add prototype/assets/js/seed.js
git commit -m "feat(prototype): add format and bracket fields to seed data"
```

---

### Task 8: Add tests for DE/RR generation and new actions

**Files:**
- Modify: `prototype/tests/store.test.mjs` (add tests)

**Interfaces:**
- Produces: tests for DE bracket generation, RR bracket generation, regenerateBracket, rescheduleMatch, setBracketSlots.

- [ ] **Step 1: Add tests** — at least:
  - DE: generate bracket for tournament-3 (8 teams), verify winners + losers + grand final matches exist, correct counts.
  - RR: generate bracket for a tournament with 4 approved teams, verify all 6 pairings exist across matchdays.
  - regenerateBracket: wipes and rebuilds; throws BRACKET_LOCKED if results exist.
  - rescheduleMatch: updates court/time; throws on conflict.
  - setBracketSlots: reassigns teams; throws if results exist.

- [ ] **Step 2: Run tests**

```powershell
cd prototype
node --test
```
Expected: all pass.

- [ ] **Step 3: Commit**

```powershell
git add prototype/tests/store.test.mjs
git commit -m "test(prototype): add DE/RR generation and management action tests"
```

---

### Task 9: Static gates and verification

**Files:**
- Create: `docs/superpowers/verification/2026-11-08-subproject2a-formats-engine.md`

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
git add docs/superpowers/verification/2026-11-08-subproject2a-formats-engine.md
git commit -m "docs: record sub-project 2A verification"
```

---

## Self-Review

1. **Spec coverage:** format field (Task 1), bracket field (Task 2), SE/DE/RR dispatch (Task 3), regenerate (Task 4), reschedule (Task 5), setBracketSlots (Task 6), seed (Task 7), tests (Task 8), verification (Task 9). All spec sections for the engine covered.
2. **Placeholders:** Task 3 DE/RR algorithms are described at a high level; the implementer will need to write the actual generation logic. This is acceptable because the algorithms are complex and the implementer has the existing SE code as a template.
3. **Type consistency:** `generateBracket`, `regenerateBracket`, `rescheduleMatch`, `setBracketSlots` signatures consistent. `format` and `bracket` field names consistent.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-08-subproject2a-formats-engine.md`.

Two execution options:
1. Subagent-Driven (recommended)
2. Inline Execution

Which approach? (I recommend Subagent-Driven.)