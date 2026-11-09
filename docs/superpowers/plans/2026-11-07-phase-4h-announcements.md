# Phase 4h — Announcements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the prototype announcements feature (filterable list + create) to Livewire.

**Architecture:** Two Livewire components: AnnouncementsIndex (list) and AnnouncementCreate (form). Uses AnnouncementPolicy.

**Tech Stack:** Laravel 13, Livewire 4, Blade, Pest.

## Global Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.

## File Structure

- Create: `src/app/Livewire/Organizer/AnnouncementsIndex.php`
- Create: `src/resources/views/livewire/organizer/announcements-index.blade.php`
- Create: `src/app/Livewire/Organizer/AnnouncementCreate.php`
- Create: `src/resources/views/livewire/organizer/announcement-create.blade.php`
- Modify: `src/routes/web.php`
- Create: `src/tests/Feature/Pages/AnnouncementsTest.php`

---

### Task 1: AnnouncementsIndex Livewire

**Files:** Create component + view.

- [ ] **Step 1:** Livewire component with filters (tournament, category, search). Role-based audience filtering. Renders announcement cards.
- [ ] **Step 2:** Commit.

---

### Task 2: AnnouncementCreate Livewire

**Files:** Create component + view.

- [ ] **Step 1:** Validated form creating Announcement (organizer only). Redirect to announcements index.
- [ ] **Step 2:** Commit.

---

### Task 3: Routes

**Files:** Modify routes/web.php.

- [ ] **Step 1:** Add /announcements -> AnnouncementsIndex (auth), /announcements/create -> AnnouncementCreate (auth+role:organizer). Remove 'announcements' placeholder.
- [ ] **Step 2:** Commit.

---

### Task 4: Tests + quality

**Files:** Create tests/Feature/Pages/AnnouncementsTest.php.

- [ ] **Step 1:** Tests: pages load per role, create (organizer).
- [ ] **Step 2:** Run composer quality. Fix. Commit.

## Self-Review Notes

- Spec coverage: index (T1), create (T2), routes (T3), tests (T4).
- No placeholders.
- Type consistency: AnnouncementCreate validates and creates.
