# Sub-project 3: Payment destination + teams nav filtering

- **Date:** 2026-11-10
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Parent:** [[2026-10-30-prototype-ui-interactivity-design]]
- **Scope:** prototype only (`prototype/`, `docs/superpowers/`).

## Problem

1. Tournaments have no payment destination. Coaches registering a team don't know where to send the entry fee (e.g., a GCash number). The payment instructions are tribal knowledge.
2. The Teams page shows all teams across all tournaments. When an organizer or coach clicks "Teams" in the nav, they see a flat list with no tournament scoping, making it hard to find the right team.

## Goals

1. **Payment destination:** Add a required payment method + account number to tournament creation. Display it in tournament details and in the coach registration/payment flow.
2. **Teams nav filtering:** When clicking "Teams", prompt the user to select a tournament first (or auto-select for coaches who own a team in one). Then show only teams in that tournament.

## Non-goals

- No real payment gateway integration.
- No change to `src/`.
- No multi-method payment (one destination per tournament).

## Decisions

### 1. Data model

Tournament gains:
- `paymentMethod: 'GCash' | 'Maya' | 'Bank transfer' | 'Cash'` (default `'GCash'`).
- `paymentAccount: string` (required, e.g., `09489666136`).

Validation in `createTournament`: both required, account 6–24 chars (letters, numbers, dashes).

### 2. Tournament creation wizard

- Add "Payment method" select and "Payment account" input to the Format & courts step (or a new step).
- Required; validation blocks publish if empty.

### 3. Tournament details (tournament-show page)

- Add a "Payment" entry to the stat strip: method + account (e.g., "GCash · 09489666136").

### 4. Coach registration wizard

- In the payment step, display the tournament's payment destination prominently: "Send ₱1,500 via GCash to 09489666136".
- The coach's payment ref input remains (they enter their transaction reference).

### 5. My Team page

- Show the payment destination in the payment/registration timeline section.

### 6. Teams nav filtering

- `teams/index.html` / `pages/teams.js`: if no `?tournament=` param, render a tournament picker first (list of tournaments with name/division/status). After selecting, navigate to `teams/index.html?tournament=<id>` (or update state + re-render).
- For coaches: auto-select the tournament where they own a team (if exactly one); otherwise show the picker.
- For fans: show all approved teams across tournaments (existing behavior) OR prompt — recommend prompt for consistency.
- Team list, KPIs, and filters all scope to the selected tournament.

### 7. Acceptance criteria

- Tournament creation requires payment method + account.
- Tournament details show payment destination.
- Coach registration shows payment destination prominently.
- Teams page prompts for tournament selection when no `?tournament=` param.
- All existing tests pass; `node --test` green; static sweeps clean.

## Open questions (resolved)

- **Payment destination on organizer pages?** Yes — shown in the payments ledger header. Resolved.
- **Fans see payment destinations?** No — payment details stay private to organizers/coaches. Resolved.

---

Spec written for review. Confirm or request changes before I write the implementation plan.