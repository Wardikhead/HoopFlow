# Phase 4d — Bracket Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the prototype bracket feature (view, generate, edit slots, score entry, correction) to Livewire.

**Architecture:** Single `BracketShow` Livewire full-page component using BracketService. View renders rounds/matches with generate/edit/score/correct actions.

**Tech Stack:** Laravel 13, Livewire 4, Blade, Pest.

## Global Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.
- Uses existing BracketService.

## File Structure

- Create: `src/app/Livewire/Organizer/BracketShow.php`
- Create: `src/resources/views/livewire/organizer/bracket-show.blade.php`
- Modify: `src/routes/web.php`
- Create: `src/tests/Feature/Pages/BracketTest.php`

---

### Task 1: BracketShow Livewire component

**Files:** Create component.

- [ ] **Step 1:** Livewire component with properties tournamentId, selectedMatch, showEditSlots, showCorrectionDialog, score1, score2, reason. Methods: generateBracket, regenerateBracket, editSlots, saveSlots, openScoreConsole, submitScore, openCorrection, submitCorrection. Uses BracketService. #[Layout('layouts.public')].
- [ ] **Step 2:** Commit.

---

### Task 2: Bracket view

**Files:** Create Blade view.

- [ ] **Step 1:** Port bracket markup from prototype bracket.js: rounds/matches, generate button, edit slots modal, score console, correction dialog.
- [ ] **Step 2:** Commit.

---

### Task 3: Routes

**Files:** Modify routes/web.php.

- [ ] **Step 1:** Add /bracket route -> BracketShow (auth). Remove 'bracket' from placeholder foreach.
- [ ] **Step 2:** Commit.

---

### Task 4: Tests + quality

**Files:** Create tests/Feature/Pages/BracketTest.php.

- [ ] **Step 1:** Tests: page loads per role, generate bracket (organizer), non-organizer forbidden.
- [ ] **Step 2:** Run composer quality. Fix. Commit.

## Self-Review Notes

- Spec coverage: component (T1), view (T2), routes (T3), tests (T4).
- No placeholders.
- Type consistency: BracketShow methods use BracketService.
