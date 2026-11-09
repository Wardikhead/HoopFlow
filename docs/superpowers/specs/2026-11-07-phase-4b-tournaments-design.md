# Phase 4b — Tournaments Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Port the prototype tournaments feature (list with filters, show, create) to Livewire + Blade.

## Section 1 — Livewire components

- `app/Livewire/Organizer/TournamentsIndex.php` (or Public) — filterable list (search/status/division).
- `app/Livewire/Organizer/TournamentCreate.php` — validated form, creates Tournament+Division+GameRule+Venue+Courts, redirects to show.
- `app/Livewire/Organizer/TournamentShow.php` — detail.

Views in `resources/views/livewire/organizer/` and `resources/views/pages/tournaments/`.

## Section 2 — Routes & policies

- `/tournaments` -> TournamentsIndex (auth).
- `/tournaments/create` -> TournamentCreate (auth + role:organizer).
- `/tournaments/{tournament}` -> TournamentShow (auth).
- `#[Layout('layouts.public')]`. TournamentPolicy (viewAny, create, update) exists.

## Section 3 — Tests

Feature tests for index (per role), create (validation + creation, forbidden for non-organizer), show. composer quality must pass.

## Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.
