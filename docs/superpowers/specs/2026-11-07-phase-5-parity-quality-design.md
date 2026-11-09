# Phase 5 — Parity & Quality Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Final validation pass: port prototype test scenarios to Pest, run parity checks, final quality gate, code review, branch finishing.

## Section 1 — Parity tests

Port prototype test scenarios to `tests/Feature/Parity/`. Cover: dashboard, tournaments, teams, bracket, schedule, standings, announcements, auth/role, seed reset. Use RefreshDatabase + PrototypeSeeder.

## Section 2 — Final quality, review, finish

- Run `composer quality` + `npm run build` (must pass).
- Invoke requesting-code-review for final whole-branch review.
- Fix findings (one fix wave + re-review).
- Invoke finishing-a-development-branch for merge options.
- Revert accidental `.obsidian/graph.json` change.

## Constraints

- composer quality must pass.
- No Bootstrap JS.
- No `#` placeholders.
