# Phase 4a — Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the prototype dashboard (organizer/coach/fan variants) to a Blade view backed by DashboardService.

**Architecture:** Single `pages/dashboard.blade.php` with role-based sections. `DashboardService` aggregates KPIs/selectors. Route `/dashboard` renders the view.

**Tech Stack:** Laravel 13, Blade, StandingsService, Eloquent.

## Global Constraints

- Single Blade view; no Livewire.
- Bootstrap for variables/icons only; custom CSS for layout.
- composer quality must pass.

## File Structure

- Create: `src/app/Services/DashboardService.php`
- Modify: `src/resources/views/pages/dashboard.blade.php`
- Modify: `src/routes/web.php` (dashboard route uses controller)
- Create: `src/app/Http/Controllers/DashboardController.php`
- Create: `src/tests/Feature/Pages/DashboardTest.php`

---

### Task 1: DashboardService

**Files:** Create `src/app/Services/DashboardService.php`.

- [ ] **Step 1: Implement** organizerKpis, coachTeams, teamRecord, upcomingMatches, recentResults, announcements. Use StandingsService for records. Port from prototype dashboard.js selectors (kpis, paymentSummary, recentResults, upcomingMatches, coachTeams, teamRecord).

- [ ] **Step 2: Unit tests. Commit "feat(services): DashboardService".**

---

### Task 2: DashboardController + route

**Files:** Create controller; modify routes/web.php.

- [ ] **Step 1: DashboardController::index** builds view data via DashboardService based on auth user role; passes active tournament (tournament-1 seeded). 

- [ ] **Step 2: Route** `/dashboard` -> DashboardController@index (auth middleware).

- [ ] **Step 3: Commit.**

---

### Task 3: Dashboard Blade view

**Files:** Modify `src/resources/views/pages/dashboard.blade.php`.

- [ ] **Step 1: Port organizer/coach/fan dashboard markup** from prototype dashboard.js using hf-* classes and Bootstrap variables. Role sections via @if(hasRole).

- [ ] **Step 2: Commit.**

---

### Task 4: Tests + quality

**Files:** Create `src/tests/Feature/Pages/DashboardTest.php`.

- [ ] **Step 1: Tests** — dashboard loads for each role (200), contains expected headings.

- [ ] **Step 2: Run composer quality. Fix. Commit.**

## Self-Review Notes

- Spec coverage: service (T1), controller/route (T2), view (T3), tests/quality (T4).
- No placeholders.
- Type consistency: DashboardService methods accept Tournament/User/Team.
