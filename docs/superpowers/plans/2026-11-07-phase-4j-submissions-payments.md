# Phase 4j — Submissions & Payments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the prototype submissions (review queue) and payments (verify) features to Livewire.

**Architecture:** Two Livewire components: SubmissionsIndex (review queue) + PaymentsIndex (verify). Uses RegistrationPolicy/PaymentPolicy.

**Tech Stack:** Laravel 13, Livewire 4, Blade, Pest.

## Global Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.

## File Structure

- Create: `src/app/Livewire/Organizer/SubmissionsIndex.php`
- Create: `src/resources/views/livewire/organizer/submissions-index.blade.php`
- Create: `src/app/Livewire/Organizer/PaymentsIndex.php`
- Create: `src/resources/views/livewire/organizer/payments-index.blade.php`
- Modify: `src/routes/web.php`
- Create: `src/tests/Feature/Pages/SubmissionsPaymentsTest.php`

---

### Task 1: SubmissionsIndex Livewire

**Files:** Create component + view.

- [ ] **Step 1:** Livewire component with filters (status). Renders registration cards with approve/reject actions. Uses RegistrationPolicy.
- [ ] **Step 2:** Commit.

---

### Task 2: PaymentsIndex Livewire

**Files:** Create component + view.

- [ ] **Step 1:** Livewire component with filters (status). Renders payment cards with verify/refund actions. Uses PaymentPolicy.
- [ ] **Step 2:** Commit.

---

### Task 3: Routes

**Files:** Modify routes/web.php.

- [ ] **Step 1:** Add /submissions -> SubmissionsIndex (auth+role:organizer), /payments -> PaymentsIndex (auth+role:organizer). Remove placeholders.
- [ ] **Step 2:** Commit.

---

### Task 4: Tests + quality

**Files:** Create tests/Feature/Pages/SubmissionsPaymentsTest.php.

- [ ] **Step 1:** Tests: pages load for organizer (200), forbidden for coach/fan, guest redirected.
- [ ] **Step 2:** Run composer quality. Fix. Commit.

## Self-Review Notes

- Spec coverage: submissions (T1), payments (T2), routes (T3), tests (T4).
- No placeholders.
- Type consistency: SubmissionsIndex uses Registration model; PaymentsIndex uses Payment model.
