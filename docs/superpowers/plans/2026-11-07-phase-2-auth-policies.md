# Phase 2 — Auth & Policies Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add authorization policies for all models with domain actions and ownership, plus Pest authorization tests.

**Architecture:** `BasePolicy` with role-based defaults (organizer full, coach read+own, fan read-only). Model policies extend it and override domain actions. Laravel auto-discovers by convention. Gates for non-model actions. Tests use `Gate::forUser`.

**Tech Stack:** Laravel 13, Pest 4, Larastan L6.

## Global Constraints

- No Bootstrap JS; composer quality must pass.
- Ownership: coach manages only own team (coach_user_id/created_by/actor_user_id).
- No domain logic in controllers; policies only.

## File Structure

- Create: `src/app/Policies/BasePolicy.php`.
- Create: `src/app/Policies/{Model}Policy.php` (25 files).
- Modify: `src/app/Providers/AppServiceProvider.php` (gates).
- Create: `src/tests/Feature/Policies/*.php` (policy tests).

---

### Task 1: BasePolicy

**Files:** Create `src/app/Policies/BasePolicy.php`.

- [ ] **Step 1: Write BasePolicy** with viewAny, view, create, update, delete, restore, forceDelete, and protected isOwner(). Domain action stubs: approve, reject, verify, correctScore, generateBracket, generateSchedule, publish, resetDemo returning false by default (organizer overrides).

- [ ] **Step 2: Run lint. Commit "feat(policies): BasePolicy with role defaults and ownership".**

---

### Task 2: Generate model policies

**Files:** Create `src/app/Policies/{Model}Policy.php` for all 25 models extending BasePolicy.

- [ ] **Step 1: Create 25 policy files** (artisan make:policy or manual) extending BasePolicy. Override `update`/`delete` for ownership where applicable (Team, Registration, Tournament, Announcement, MatchModel, Player, Payment).

- [ ] **Step 2: Run lint + analyse. Commit "feat(policies): model policies extending BasePolicy".**

---

### Task 3: Domain actions

**Files:** Modify relevant policies (TournamentPolicy, TeamPolicy, RegistrationPolicy, PaymentPolicy, MatchModelPolicy, AnnouncementPolicy, BracketPolicy).

- [ ] **Step 1: Add domain methods**: TournamentPolicy (generateBracket, generateSchedule, publish), TeamPolicy (approve), RegistrationPolicy (approve, reject), PaymentPolicy (verify, refund), MatchModelPolicy (correctScore, enterStats), AnnouncementPolicy (publish), BracketPolicy (generate). Organizer-only (or organizer + coach-own for team).

- [ ] **Step 2: Run lint + analyse. Commit "feat(policies): domain actions".**

---

### Task 4: Gates

**Files:** Modify `src/app/Providers/AppServiceProvider.php`.

- [ ] **Step 1: Define gates** `manage-users`, `reset-demo`, `view-audit` (organizer only).

- [ ] **Step 2: Run lint. Commit "feat(policies): gates for non-model actions".**

---

### Task 5: Authorization tests

**Files:** Create `src/tests/Feature/Policies/AuthorizationTest.php`.

- [ ] **Step 1: Write failing tests** asserting fan/coach/organizer allow/deny for viewAny, create, update, delete on Team/Tournament/Registration/Payment/Match/Announcement, plus domain actions and ownership (coach manages own team only).

- [ ] **Step 2: Run tests to verify pass. Commit "test(policies): authorization tests".**

---

### Task 6: Quality gate

- [ ] **Step 1: Run `composer quality`. Fix. Commit.**

## Self-Review Notes

- Spec coverage: BasePolicy (Task 1), 25 policies (Task 2), domain actions (Task 3), gates (Task 4), tests (Task 5), quality (Task 6).
- No placeholders.
- Type consistency: isOwner(User, Model), hasRole(string): bool.
