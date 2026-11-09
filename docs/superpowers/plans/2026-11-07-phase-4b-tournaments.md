# Phase 4b — Tournaments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the prototype tournaments feature (filterable list, show, create) to Livewire + Blade.

**Architecture:** Livewire components for interactive list and create form; Blade view for show. Routes under /tournaments. Policy gates already exist.

**Tech Stack:** Laravel 13, Livewire 4, Blade, Pest.

## Global Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.

## File Structure

- Create: `src/app/Livewire/Organizer/TournamentsIndex.php`
- Create: `src/resources/views/livewire/organizer/tournaments-index.blade.php`
- Create: `src/app/Livewire/Organizer/TournamentCreate.php`
- Create: `src/resources/views/livewire/organizer/tournament-create.blade.php`
- Create: `src/resources/views/pages/tournaments/show.blade.php`
- Modify: `src/routes/web.php`
- Create: `src/tests/Feature/Pages/TournamentsTest.php`

---

### Task 1: TournamentsIndex Livewire

**Files:** Create component + view.

- [ ] **Step 1:** Livewire component with public properties search, status, division; computed filtered tournaments; render view with cards.
- [ ] **Step 2:** Commit.

---

### Task 2: TournamentCreate Livewire

**Files:** Create component + view.

- [ ] **Step 1:** Form properties (name, slug, description, start_date, end_date, status, slots, fee, division name, age_max, venue name, courts). Validation. On submit create Tournament, Division, GameRule, Venue, Courts. Redirect to show.
- [ ] **Step 2:** Commit.

---

### Task 3: TournamentShow view

**Files:** Create `pages/tournaments/show.blade.php`.

- [ ] **Step 1:** Blade view extending layouts.public showing tournament details, divisions, courts, teams count, links to bracket/schedule/standings.
- [ ] **Step 2:** Route + controller or Livewire. Use a simple Blade view with data from route model binding controller.
- [ ] **Step 3:** Commit.

---

### Task 4: Routes

**Files:** Modify routes/web.php.

- [ ] **Step 1:** Add /tournaments (Livewire index), /tournaments/create (Livewire create, role:organizer), /tournaments/{tournament} (show). Use `Route::livewire` or view with component.
- [ ] **Step 2:** Commit.

---

### Task 5: Tests + quality

**Files:** Create tests/Feature/Pages/TournamentsTest.php.

- [ ] **Step 1:** Tests for index (per role 200), create (organizer creates, non-organizer 403), show loads.
- [ ] **Step 2:** Run composer quality. Fix. Commit.

## Self-Review Notes

- Spec coverage: index (T1), create (T2), show (T3), routes (T4), tests (T5).
- No placeholders.
- Type consistency: TournamentCreate validates and creates.
