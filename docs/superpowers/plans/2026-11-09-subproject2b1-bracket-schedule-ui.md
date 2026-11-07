# Sub-project 2B1: Bracket & schedule management UI — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add bracket management (regenerate, edit slots, format-aware rendering) and schedule management (regenerate, re-schedule dialog, manual manage) UI to the prototype. Excludes the create-wizard format step (Sub-project 2B2).

**Architecture:** Modify `pages/bracket.js` (regenerate + edit slots modal + DE/RR rendering), `pages/schedule.js` (regenerate + reschedule dialog + manual manage), `pages/tournament-show.js` (format badge). Reuse existing store actions (`regenerateBracket`, `setBracketSlots`, `rescheduleMatch`, `generateSchedule`). No new routes.

**Tech Stack:** Static HTML, vanilla ES modules, Node's built-in test runner, Bootstrap 5.3 (vendored). No new dependencies.

## Global Constraints

- Work only in `prototype/`, `tools/`, and `docs/superpowers/`. Do not touch `src/`, `docs/wireframes/`, `HoopFlow/`, `logo/`, `video/`, or `_archive/`.
- Runtime must work offline. No `https://` asset references in prototype HTML or CSS.
- No `alert()`, `confirm()`, or `prompt()`. Use `ui.toast()` and `ui.confirm()`.
- No client-side framework, no build step, no runtime npm dependencies.
- All existing prototype routes stay valid. No new routes.
- Node tests: `cd prototype` then `node --test`.
- Never stage `docs/wireframes/`.

---

### Task 1: Tournament show page — format badge

**Files:**
- Modify: `prototype/assets/js/pages/tournament-show.js`

**Interfaces:**
- Consumes: `tournament.format`.

- [ ] **Step 1: Add format badge** in the page header (next to tournament name). Display human-readable format: "Single elimination", "Double elimination", "Round robin".

- [ ] **Step 2: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/tournament-show.js
git add prototype/assets/js/pages/tournament-show.js
git commit -m "feat(prototype): add format badge to tournament show page"
```

---

### Task 2: Bracket page — Regenerate button

**Files:**
- Modify: `prototype/assets/js/pages/bracket.js`

**Interfaces:**
- Consumes: `store.actions.regenerateBracket`, `ui.confirm`, `ui.setBusy`, `ui.toast`.

- [ ] **Step 1: Add regenerate button** — organizer-only, shown when bracket exists and no results recorded. Placement: in the page header actions (next to existing buttons). Confirmation dialog: "Regenerate bracket? This wipes the current bracket and rebuilds from approved teams." On confirm, call `regenerateBracket(tournamentId)`, toast success, re-render (`draw()`).

- [ ] **Step 2: Disable when results exist** — if any match is final/forfeit, hide the button (or disable with tooltip).

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/bracket.js
git add prototype/assets/js/pages/bracket.js
git commit -m "feat(prototype): add regenerate bracket button"
```

---

### Task 3: Bracket page — Edit slots modal

**Files:**
- Modify: `prototype/assets/js/pages/bracket.js`

**Interfaces:**
- Consumes: `store.actions.setBracketSlots`, `ui.dialog`, `ui.confirm`, `ui.toast`.

- [ ] **Step 1: Add "Edit slots" button** — organizer-only, shown when bracket exists and no results. Opens a modal with a list of first-round slots (and DE losers round 1 slots), each with a team dropdown (approved teams + "Bye/empty"). 

- [ ] **Step 2: Implement the modal** — use `ui.dialog({ size: 'wide', ... })`. Body: table of slots with team selects. Footer: Save / Cancel. On Save, collect assignments and call `setBracketSlots(tournamentId, assignments)`. On conflict (duplicate team), show field error. On success, toast + close + re-render.

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/bracket.js
git add prototype/assets/js/pages/bracket.js
git commit -m "feat(prototype): add edit slots modal to bracket page"
```

---

### Task 4: Bracket page — format-aware rendering (DE + RR)

**Files:**
- Modify: `prototype/assets/js/pages/bracket.js`

**Interfaces:**
- Consumes: `tournament.format`, `bracket.rounds` (each round has matches with `bracket` field).

- [ ] **Step 1: DE rendering** — when `tournament.format === 'double-elimination'`, render two bracket trees: "Winners bracket" (rounds where bracket==='winners') and "Losers bracket" (rounds where bracket==='losers'), plus a "Grand Final" card (bracket==='grand-final'). Reuse `components.bracketMatch`.

- [ ] **Step 2: RR rendering** — when `tournament.format === 'round-robin'`, render a matchday grid (rows = matchdays/rounds, each showing matches with scores) plus a link to standings. No tree.

- [ ] **Step 3: SE rendering** — unchanged (existing tree).

- [ ] **Step 4: Adapt header description** — mention format name.

- [ ] **Step 5: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/bracket.js
git add prototype/assets/js/pages/bracket.js
git commit -m "feat(prototype): add format-aware bracket rendering (DE/RR)"
```

---

### Task 5: Schedule page — Regenerate button

**Files:**
- Modify: `prototype/assets/js/pages/schedule.js`

**Interfaces:**
- Consumes: `store.actions.generateSchedule`, `ui.confirm`, `ui.setBusy`, `ui.toast`.

- [ ] **Step 1: Add regenerate button** — organizer-only, shown when schedule exists (any match has courtId+scheduledAt) and no results recorded. Confirmation dialog. On confirm, call `generateSchedule(tournamentId)`, toast success, re-render.

- [ ] **Step 2: Disable when results exist or no bracket** — if results recorded or no bracket, hide/disable.

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/schedule.js
git add prototype/assets/js/pages/schedule.js
git commit -m "feat(prototype): add regenerate schedule button"
```

---

### Task 6: Schedule page — Re-schedule match dialog

**Files:**
- Modify: `prototype/assets/js/pages/schedule.js`

**Interfaces:**
- Consumes: `store.actions.rescheduleMatch`, `ui.dialog`, `ui.setFieldError`, `ui.toast`.

- [ ] **Step 1: Add "Reschedule" button** on each match card (organizer-only). Opens a dialog with court select + date/time inputs (pre-filled with current values).

- [ ] **Step 2: Implement the dialog** — on Save, call `rescheduleMatch(matchId, { courtId, scheduledAt })`. On conflict (`SCHEDULE_CONFLICT`), show field error. On success, toast + close + re-render.

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/schedule.js
git add prototype/assets/js/pages/schedule.js
git commit -m "feat(prototype): add re-schedule match dialog"
```

---

### Task 7: Schedule page — Manual manage mode

**Files:**
- Modify: `prototype/assets/js/pages/schedule.js`

**Interfaces:**
- Consumes: `store.actions.rescheduleMatch`, `ui.debounce`.

- [ ] **Step 1: Add "Manual manage" toggle** — organizer-only. When on, each match card shows court and day dropdowns. Changes call `rescheduleMatch` per edit (debounced 300ms). On conflict, show inline error.

- [ ] **Step 2: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/schedule.js
git add prototype/assets/js/pages/schedule.js
git commit -m "feat(prototype): add manual manage mode to schedule page"
```

---

### Task 8: Static gates and verification

**Files:**
- Create: `docs/superpowers/verification/2026-11-09-subproject2b1-bracket-schedule-ui.md`

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
git add docs/superpowers/verification/2026-11-09-subproject2b1-bracket-schedule-ui.md
git commit -m "docs: record sub-project 2B1 verification"
```

---

## Self-Review

1. **Spec coverage:** Regenerate bracket (Task 2), edit slots (Task 3), format-aware rendering (Task 4), format badge (Task 1), regenerate schedule (Task 5), reschedule dialog (Task 6), manual manage (Task 7), verification (Task 8). All spec sections covered.
2. **Placeholders:** Tasks 3, 4, 6, 7 are large; implementers should model on existing dialog/card patterns.
3. **Type consistency:** `regenerateBracket`, `setBracketSlots`, `rescheduleMatch` signatures consistent with store. `route('gameManage', ...)` pattern consistent.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-09-subproject2b1-bracket-schedule-ui.md`.

Two execution options:
1. Subagent-Driven (recommended)
2. Inline Execution

Which approach? (I recommend Subagent-Driven.)