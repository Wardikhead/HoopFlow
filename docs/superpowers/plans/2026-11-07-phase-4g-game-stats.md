# Phase 4g — Game Stats Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the prototype game stats feature (matchup list + box score with score entry/correction) to Livewire.

**Architecture:** Two Livewire components: MatchesIndex (list) and GameStatsShow (box score + score console + correction). Uses BracketService::correctScore.

**Tech Stack:** Laravel 13, Livewire 4, Blade, Pest.

## Global Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.

## File Structure

- Create: `src/app/Livewire/Organizer/MatchesIndex.php`
- Create: `src/resources/views/livewire/organizer/matches-index.blade.php`
- Create: `src/app/Livewire/Organizer/GameStatsShow.php`
- Create: `src/resources/views/livewire/organizer/game-stats-show.blade.php`
- Modify: `src/routes/web.php`
- Create: `src/tests/Feature/Pages/GameStatsTest.php`

---

### Task 1: MatchesIndex Livewire

**Files:** Create component + view.

- [ ] **Step 1:** Livewire component listing matches (filterable by tournament/round), links to game stats. #[Layout('layouts.public')].
- [ ] **Step 2:** Commit.

---

### Task 2: GameStatsShow Livewire

**Files:** Create component + view.

- [ ] **Step 1:** Livewire component showing box score (player stats table, sortable), score console, correction dialog. Uses BracketService::correctScore. #[Layout('layouts.public')].
- [ ] **Step 2:** Commit.

---

### Task 3: Routes

**Files:** Modify routes/web.php.

- [ ] **Step 1:** Add /matches -> MatchesIndex, /game-stats -> GameStatsShow (auth). Remove 'gameStats' placeholder.
- [ ] **Step 2:** Commit.

---

### Task 4: Tests + quality

**Files:** Create tests/Feature/Pages/GameStatsTest.php.

- [ ] **Step 1:** Tests: pages load per role.
- [ ] **Step 2:** Run composer quality. Fix. Commit.

## Self-Review Notes

- Spec coverage: list (T1), detail (T2), routes (T3), tests (T4).
- No placeholders.
- Type consistency: GameStatsShow uses BracketService.
