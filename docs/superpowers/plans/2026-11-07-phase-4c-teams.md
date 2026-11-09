# Phase 4c — Teams Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the prototype teams feature (filterable list, show, register, my-team) to Livewire + Blade.

**Architecture:** Livewire for interactive list + register; Blade for show + my-team. Routes under /teams, /my-team, /team-register.

**Tech Stack:** Laravel 13, Livewire 4, Blade, Pest.

## Global Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.

## File Structure

- Create: `src/app/Livewire/Organizer/TeamsIndex.php`
- Create: `src/resources/views/livewire/organizer/teams-index.blade.php`
- Create: `src/app/Livewire/Coach/TeamRegister.php`
- Create: `src/resources/views/livewire/coach/team-register.blade.php`
- Create: `src/app/Http/Controllers/TeamController.php`
- Create: `src/resources/views/pages/teams/show.blade.php`
- Create: `src/resources/views/pages/teams/my-team.blade.php`
- Modify: `src/routes/web.php`
- Create: `src/tests/Feature/Pages/TeamsTest.php`

---

### Task 1: TeamsIndex Livewire

**Files:** Create component + view.

- [ ] **Step 1:** Livewire component with filters (tournament, status, division, search); renders team cards with KPIs. Use #[Layout('layouts.public')].
- [ ] **Step 2:** Commit.

---

### Task 2: TeamRegister Livewire

**Files:** Create component + view.

- [ ] **Step 1:** Validated registration form creating Team + Registration + Payment. Redirect to my-team.
- [ ] **Step 2:** Commit.

---

### Task 3: TeamController + show/my-team views

**Files:** Create controller + 2 Blade views.

- [ ] **Step 1:** Controller show(Team) and myTeam(Request). Views: show.blade.php (roster, status, payment), my-team.blade.php (coach's team).
- [ ] **Step 2:** Commit.

---

### Task 4: Routes

**Files:** Modify routes/web.php.

- [ ] **Step 1:** Add /teams, /teams/{team}, /my-team, /team-register routes.
- [ ] **Step 2:** Commit.

---

### Task 5: Tests + quality

**Files:** Create tests/Feature/Pages/TeamsTest.php.

- [ ] **Step 1:** Tests for index (per role), show, my-team (coach 200, others 403), register (coach creates).
- [ ] **Step 2:** Run composer quality. Fix. Commit.

## Self-Review Notes

- Spec coverage: index (T1), register (T2), show/my-team (T3), routes (T4), tests (T5).
- No placeholders.
- Type consistency: TeamController::show(Team): View.
