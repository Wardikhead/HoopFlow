# Phase 4g — Game Stats Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Port the prototype game stats feature (matchup list + box score with score entry/correction) to Livewire.

## Section 1 — Livewire components

- `app/Livewire/Organizer/MatchesIndex.php` — matchup list (filterable).
- `app/Livewire/Organizer/GameStatsShow.php` — box score + score console + correction dialog. Uses BracketService::correctScore.

Views in `livewire/organizer/`. #[Layout('layouts.public')].

## Section 2 — Routes & tests

Routes `/matches`, `/game-stats`. Tests: pages load per role. composer quality must pass.

## Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.
- Uses existing BracketService + PlayerStat model.
