# Phase 4c — Teams Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Port the prototype teams feature (list/filter, show, register, my-team) to Livewire + Blade.

## Section 1 — Livewire components

- `app/Livewire/Organizer/TeamsIndex.php` (or Public) — filterable list (tournament/status/division/search).
- `app/Livewire/Coach/TeamRegister.php` — validated registration form.

## Section 2 — Blade views & routes

- `pages/teams/show.blade.php`, `pages/teams/my-team.blade.php`.
- Routes: `/teams`, `/teams/{team}`, `/my-team`, `/team-register`.
- TeamPolicy (view, update own).

## Section 3 — Tests

Feature tests per role + ownership + quality gate.

## Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.
