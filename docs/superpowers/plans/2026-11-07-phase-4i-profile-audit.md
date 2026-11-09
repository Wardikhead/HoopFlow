# Phase 4i — Profile & Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the prototype profile and audit features (profile Blade + AuditLogIndex Livewire).

**Architecture:** Profile as Blade (static user info + role links). Audit as Livewire (filterable log, organizer only).

**Tech Stack:** Laravel 13, Livewire 4, Blade, Pest.

## Global Constraints

- Livewire for audit filters; no Bootstrap JS.
- composer quality must pass.
- AuditLogPolicy/view-audit gate (organizer only).

## File Structure

- Create: `src/resources/views/pages/profile.blade.php`
- Create: `src/app/Livewire/Organizer/AuditLogIndex.php`
- Create: `src/resources/views/livewire/organizer/audit-log-index.blade.php`
- Modify: `src/routes/web.php`
- Create: `src/tests/Feature/Pages/ProfileAuditTest.php`

---

### Task 1: Profile Blade view

**Files:** Create view.

- [ ] **Step 1:** Blade view extending layouts.public: user info (name, email, role), role links, theme preference.
- [ ] **Step 2:** Commit.

---

### Task 2: AuditLogIndex Livewire

**Files:** Create component + view.

- [ ] **Step 1:** Livewire component with filters (action, entity type, date range). Renders audit log entries. Organizer only.
- [ ] **Step 2:** Commit.

---

### Task 3: Routes

**Files:** Modify routes/web.php.

- [ ] **Step 1:** Add /profile -> profile (auth), /audit -> AuditLogIndex (auth+role:organizer). Remove 'profile'/'audit' placeholders.
- [ ] **Step 2:** Commit.

---

### Task 4: Tests + quality

**Files:** Create tests/Feature/Pages/ProfileAuditTest.php.

- [ ] **Step 1:** Tests: profile loads for authed user; audit loads for organizer (200), forbidden for others; guest redirected.
- [ ] **Step 2:** Run composer quality. Fix. Commit.

## Self-Review Notes

- Spec coverage: profile (T1), audit (T2), routes (T3), tests (T4).
- No placeholders.
- Type consistency: AuditLogIndex filters by action/entity/date.
