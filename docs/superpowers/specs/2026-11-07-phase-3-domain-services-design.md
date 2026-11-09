# Phase 3 — Domain Services Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Port prototype domain logic into Laravel services: Bracket, Scheduling, Standings, Eligibility.

## Section 1 — BracketService

`app/Services/BracketService.php`: `generate(Tournament, format)` creates matches + bracket_slots (single-elim, round-robin, double-elim), handles byes and loser routing; `correctScore(Match, s1, s2, reason)` updates and propagates winner; `finalizeMatch`. DB transactions. Faithful port of store.js algorithms.

## Section 2 — Scheduling/Standings/Eligibility

- `SchedulingService::generate(Tournament, options)` assigns courts/dates/times to match_slots.
- `StandingsService::compute(Tournament|Division)` returns sorted standings with tiebreakers.
- `EligibilityService::refresh(Team)`, `validatePlayer(input, division)`.

## Section 3 — Tests

Pest unit tests in `tests/Unit/Services/` plus integration tests against seeded data.

## Constraints

- No domain logic in controllers/Livewire; services only.
- composer quality must pass.
- All three bracket formats supported.
