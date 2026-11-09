# Phase 4e — Schedule Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the prototype schedule feature (calendar + generate) to Livewire.

**Architecture:** Single `ScheduleShow` Livewire full-page component using SchedulingService.

**Tech Stack:** Laravel 13, Livewire 4, Blade, Pest.

## Global Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.
- Uses existing SchedulingService.

## File Structure

- Create: `src/app/Livewire/Organizer/ScheduleShow.php`
- Create: `src/resources/views/livewire/organizer/schedule-show.blade.php`
- Modify: `src/routes/web.php`
- Create: `src/tests/Feature/Pages/ScheduleTest.php`

---

### Task 1: ScheduleShow Livewire component

**Files:** Create component.

- [ ] **Step 1:** Livewire component with properties tournamentId, viewMode, currentMonth, selectedDay. Methods: generateSchedule, navigateMonth, selectDay. Uses SchedulingService. #[Layout('layouts.public')].
- [ ] **Step 2:** Commit.

---

### Task 2: Schedule view

**Files:** Create Blade view.

- [ ] **Step 1:** Port calendar grid + day matches + generate button from prototype schedule.js.
- [ ] **Step 2:** Commit.

---

### Task 3: Routes

**Files:** Modify routes/web.php.

- [ ] **Step 1:** Add /schedule -> ScheduleShow (auth). Remove 'schedule' from placeholder foreach.
- [ ] **Step 2:** Commit.

---

### Task 4: Tests + quality

**Files:** Create tests/Feature/Pages/ScheduleTest.php.

- [ ] **Step 1:** Tests: page loads per role, generate (organizer).
- [ ] **Step 2:** Run composer quality. Fix. Commit.

## Self-Review Notes

- Spec coverage: component (T1), view (T2), routes (T3), tests (T4).
- No placeholders.
- Type consistency: ScheduleShow uses SchedulingService.
