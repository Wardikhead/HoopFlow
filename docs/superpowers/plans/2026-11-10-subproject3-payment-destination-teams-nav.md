# Sub-project 3: Payment destination + teams nav filtering — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a required payment destination (method + account) to tournaments, surface it in tournament details, coach registration, My Team, and the payments ledger; and make the Teams page prompt for a tournament selection (auto-select for coaches) before showing teams.

**Architecture:** Extend the tournament model with `paymentMethod` + `paymentAccount`. Update `createTournament` validation. Update the create wizard, tournament-show, payments, my-team, and team-register pages. Rework `pages/teams.js` to show a tournament picker when no `?tournament=` param.

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

### Task 1: Add payment destination to tournament data model

**Files:**
- Modify: `prototype/assets/js/store.js` (`createTournament`)
- Modify: `prototype/assets/js/seed.js` (seeded tournaments)

**Interfaces:**
- Produces: `tournament.paymentMethod: 'GCash'|'Maya'|'Bank transfer'|'Cash'`, `tournament.paymentAccount: string` (required).

- [ ] **Step 1: Update createTournament** — accept `paymentMethod` and `paymentAccount`, validate both required, account 6–24 chars. Store on tournament.

```js
const paymentMethod = requireChoice(input.paymentMethod || 'GCash', 'paymentMethod', 'Payment method', PAYMENT_METHODS);
const paymentAccount = requireText(input.paymentAccount, 'paymentAccount', 'Payment account', 6);
// ... in tournament object:
paymentMethod,
paymentAccount,
```

Also update `requireText` min for account is 6, max 24 — add a max check or use a regex. Simplest: `requireText` then check length <= 24.

- [ ] **Step 2: Update seed.js** — add `paymentMethod` and `paymentAccount` to all three seeded tournaments. E.g., tournament-1: `{ paymentMethod: 'GCash', paymentAccount: '09489666136' }`.

- [ ] **Step 3: Run tests + commit**

```powershell
cd prototype
node --test
git add prototype/assets/js/store.js prototype/assets/js/seed.js
git commit -m "feat(prototype): add payment destination to tournament model"
```

---

### Task 2: Create wizard — payment destination inputs

**Files:**
- Modify: `prototype/assets/js/pages/tournament-create.js`

**Interfaces:**
- Consumes: `PAYMENT_METHODS` (already imported? check).

- [ ] **Step 1: Add payment method select + account input** to the Format & courts step. Required.

- [ ] **Step 2: Update validateFormat** to validate payment method + account.

- [ ] **Step 3: Payload already passes?** Check the publish payload — add `paymentMethod` and `paymentAccount`.

- [ ] **Step 4: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/tournament-create.js
git add prototype/assets/js/pages/tournament-create.js
git commit -m "feat(prototype): add payment destination to create wizard"
```

---

### Task 3: Tournament show page — payment destination in stat strip

**Files:**
- Modify: `prototype/assets/js/pages/tournament-show.js`

- [ ] **Step 1: Add "Payment" entry** to the stat strip: `${tournament.paymentMethod} · ${tournament.paymentAccount}`.

- [ ] **Step 2: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/tournament-show.js
git add prototype/assets/js/pages/tournament-show.js
git commit -m "feat(prototype): show payment destination on tournament show page"
```

---

### Task 4: Coach registration wizard — show payment destination

**Files:**
- Modify: `prototype/assets/js/pages/team-register.js`

- [ ] **Step 1: In the payment step**, display the tournament's payment destination prominently: "Send ₱{fee} via {method} to {account}". Show above the payment ref input.

- [ ] **Step 2: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/team-register.js
git add prototype/assets/js/pages/team-register.js
git commit -m "feat(prototype): show payment destination in coach registration"
```

---

### Task 5: My Team page — show payment destination

**Files:**
- Modify: `prototype/assets/js/pages/my-team.js`

- [ ] **Step 1: In the payment/registration section**, show the tournament's payment destination (method + account) alongside the team's payment status.

- [ ] **Step 2: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/my-team.js
git add prototype/assets/js/pages/my-team.js
git commit -m "feat(prototype): show payment destination on My Team page"
```

---

### Task 6: Payments ledger — show payment destination in header

**Files:**
- Modify: `prototype/assets/js/pages/payments.js`

- [ ] **Step 1: Show payment destination** in the page header or a card at the top of the payments ledger (organizer view).

- [ ] **Step 2: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/payments.js
git add prototype/assets/js/pages/payments.js
git commit -m "feat(prototype): show payment destination on payments ledger"
```

---

### Task 7: Teams page — tournament picker + filtering

**Files:**
- Modify: `prototype/assets/js/pages/teams.js`

**Interfaces:**
- Consumes: `store.select.tournaments`, `route`.
- Produces: tournament picker when no `?tournament=` param; scoped team list.

- [ ] **Step 1: Add tournament picker** — if no `tournament` query param, render a picker: list of tournaments (name, division, status). For coaches, auto-select if they own a team in exactly one tournament. Clicking a tournament navigates to `teams/index.html?tournament=<id>`.

- [ ] **Step 2: Scope team list** — when `?tournament=` param present, filter `visibleTeams()` to that tournament. Update KPIs and filters to scope.

- [ ] **Step 3: Add "All tournaments" option** for organizers (optional, shows all).

- [ ] **Step 4: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/teams.js
git add prototype/assets/js/pages/teams.js
git commit -m "feat(prototype): add tournament picker and filtering to teams page"
```

---

### Task 8: Static gates and verification

**Files:**
- Create: `docs/superpowers/verification/2026-11-10-subproject3-payment-destination-teams-nav.md`

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
git add docs/superpowers/verification/2026-11-10-subproject3-payment-destination-teams-nav.md
git commit -m "docs: record sub-project 3 verification"
```

---

## Self-Review

1. **Spec coverage:** Payment destination (Tasks 1-6), teams nav filtering (Task 7), verification (Task 8). All spec sections covered.
2. **Placeholders:** Tasks 4-6 are UI changes; implementers should model on existing patterns.
3. **Type consistency:** `paymentMethod` + `paymentAccount` field names consistent across store, seed, create wizard, tournament-show, payments, my-team, team-register.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-10-subproject3-payment-destination-teams-nav.md`.

Two execution options:
1. Subagent-Driven (recommended)
2. Inline Execution

Which approach? (I recommend Subagent-Driven.)