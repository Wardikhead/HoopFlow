# Phase 4a — Dashboard Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Port the prototype dashboard (organizer/coach/fan variants) to a Blade view.

## Section 1 — DashboardService

`app/Services/DashboardService.php` aggregating KPIs/selectors: organizerKpis, coachTeams, teamRecord, upcomingMatches, recentResults, announcements.

## Section 2 — Blade view

`pages/dashboard.blade.php` single view with role-based sections via `@if(hasRole)`. Ported markup from prototype dashboard.js.

## Section 3 — Route & tests

`/dashboard` renders view via controller using DashboardService. Feature tests per role. composer quality must pass.

## Constraints

- Single Blade view; no Livewire.
- Bootstrap for variables/icons only; custom CSS for layout.
- composer quality must pass.
