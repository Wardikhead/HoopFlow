# Phase 5 — Parity & Quality Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Final validation: port prototype test scenarios to Pest, run parity checks, final quality gate, code review, branch finishing.

**Architecture:** Parity tests in `tests/Feature/Parity/`. Final quality gate + review + finish.

**Tech Stack:** Laravel 13, Pest 4, Larastan L6.

## Global Constraints

- composer quality must pass.
- No Bootstrap JS.
- No `#` placeholders.

## File Structure

- Create: `src/tests/Feature/Parity/*.php`
- Modify: various (fixes from review).

---

### Task 1: Parity tests

**Files:** Create `src/tests/Feature/Parity/ParityTest.php`.

- [ ] **Step 1:** Port prototype test scenarios: dashboard loads, tournament CRUD, team registration flow, bracket generation, score entry, standings, announcements, auth/role access, seed reset. Use RefreshDatabase + PrototypeSeeder.
- [ ] **Step 2:** Run tests. Commit "test(parity): port prototype scenarios".

---

### Task 2: Revert accidental change

**Files:** Revert `.obsidian/graph.json`.

- [ ] **Step 1:** `git checkout -- .obsidian/graph.json`.
- [ ] **Step 2:** Commit (if needed) or leave.

---

### Task 3: Final quality gate

**Files:** src/.

- [ ] **Step 1:** Run `composer quality` + `npm run build`. Fix issues. Commit "chore: final quality fixes".

---

### Task 4: Final code review

- [ ] **Step 1:** Invoke requesting-code-review (final whole-branch review). Fix findings (one fix wave + re-review).

---

### Task 5: Finish

- [ ] **Step 1:** Invoke finishing-a-development-branch to present merge options.

## Self-Review Notes

- Spec coverage: parity (T1), revert (T2), quality (T3), review (T4), finish (T5).
- No placeholders.
- Type consistency: N/A.
