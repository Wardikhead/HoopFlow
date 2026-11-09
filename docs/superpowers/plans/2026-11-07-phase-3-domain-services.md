# Phase 3 — Domain Services Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port prototype domain logic into Laravel services: Eligibility, Standings, Scheduling, Bracket (all three formats).

**Architecture:** Pure service classes in `app/Services/`, no HTTP. Faithful port of `prototype/assets/js/store.js` algorithms. DB transactions for bracket generation. Tests with seeded data.

**Tech Stack:** Laravel 13, Eloquent, Pest 4.

## Global Constraints

- No domain logic in controllers/Livewire; services only.
- composer quality must pass.
- All three bracket formats supported.

## File Structure

- Create: `src/app/Services/EligibilityService.php`
- Create: `src/app/Services/StandingsService.php`
- Create: `src/app/Services/SchedulingService.php`
- Create: `src/app/Services/BracketService.php`
- Create: `src/tests/Unit/Services/*.php`
- Create: `src/tests/Feature/Services/*.php`

---

### Task 1: EligibilityService

**Files:** Create `src/app/Services/EligibilityService.php`.

- [ ] **Step 1: Implement** `refresh(Team $team): void` (recompute documentsComplete/consentComplete based on players; set eligibility_status) and `validatePlayer(array $input, Division $division, ?int $ignoreNumber): array` returning errors. Port from store.js refreshEligibility/validatePlayer.

- [ ] **Step 2: Unit tests. Commit "feat(services): EligibilityService".**

---

### Task 2: StandingsService

**Files:** Create `src/app/Services/StandingsService.php`.

- [ ] **Step 1: Implement** `compute(Tournament|Division): Collection` — wins/losses/points/for/against, tiebreakers (head-to-head, point diff), sorted. Port from prototype standings logic.

- [ ] **Step 2: Unit tests with seeded data. Commit "feat(services): StandingsService".**

---

### Task 3: SchedulingService

**Files:** Create `src/app/Services/SchedulingService.php`.

- [ ] **Step 1: Implement** `generate(Tournament $tournament, array $options = []): void` — assigns courts/dates/times to matches, writes match_slots, rest minutes. Port from store.js generateSchedule.

- [ ] **Step 2: Unit tests. Commit "feat(services): SchedulingService".**

---

### Task 4: BracketService — single-elim + round-robin

**Files:** Create `src/app/Services/BracketService.php`.

- [ ] **Step 1: Implement** `generate(Tournament, string $format)` for single-elimination and round-robin: delete existing matches, create matches + bracket_slots, handle byes, propagate bye winners to next round. Port from generateSingleElimination/generateRoundRobin.

- [ ] **Step 2: Unit tests (16-team single-elim byes, 8-team RR). Commit.**

---

### Task 5: BracketService — double-elimination

- [ ] **Step 1: Implement** generateDoubleElimination port (WB/LB/GF, loser routing).

- [ ] **Step 2: Unit tests. Commit "feat(services): double-elimination bracket".**

---

### Task 6: correctScore + finalizeMatch

- [ ] **Step 1: Implement** `correctScore(MatchModel, int $s1, int $s2, string $reason)` and `finalizeMatch`. Propagate winner to next match via nextMatchId/nextSlot. Record correction. Append audit.

- [ ] **Step 2: Unit tests (propagation). Commit.**

---

### Task 7: Integration tests

- [ ] **Step 1: Integration tests** against seeded tournament: generate bracket on a fresh tournament (create via factory), assert match counts; generate schedule; compute standings.

- [ ] **Step 2: Commit.**

---

### Task 8: Quality gate

- [ ] **Step 1: Run composer quality + tests. Fix. Commit.**

## Self-Review Notes

- Spec coverage: Eligibility (T1), Standings (T2), Scheduling (T3), Bracket single/RR (T4), double-elim (T5), correctScore (T6), integration (T7), quality (T8).
- No placeholders.
- Type consistency: generate(Tournament, string): array; compute(Tournament|Division): Collection.
