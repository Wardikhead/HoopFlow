# Phase 2 — Auth & Policies Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Add authorization policies for all models with domain actions and ownership,
plus Pest authorization tests.

## Section 1 — BasePolicy & ownership

`app/Policies/BasePolicy.php` with defaults: `viewAny` (any authed), `view` (any authed),
`create` (organizer), `update` (organizer or owner), `delete` (organizer),
`restore`/`forceDelete` (organizer). Owner check via `isOwner(User, Model)` checking
`coach_user_id`/`created_by`/`actor_user_id`. Model policies extend BasePolicy and
override domain actions (approve, verify, correctScore, generateBracket, generateSchedule,
publish, etc.) with organizer-only or organizer+coach-own.

## Section 2 — Policy registration & gates

Laravel 13 auto-discovers policies by convention. Add `Gate::define` for non-model actions:
`manage-users`, `reset-demo`, `view-audit`. Policies used via `$this->authorize()` and `@can`.

## Section 3 — Authorization tests

Pest tests in `tests/Feature/Policies/` asserting role-based allow/deny per policy and
domain actions. Use `Gate::forUser($user)->allows(...)`.

## Constraints

- No Bootstrap JS; composer quality must pass.
- Ownership: coach manages only own team.
