# Landing Page Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the landing page dynamic, interactive, and accurate to the real app UI: calendar preview, developer section, splash screen, enhanced motion.

**Architecture:** Incremental enhancement of existing landing partials. Add `LandingShowcase::calendar()`, new `calendar-panel`, `developers`, and `splash` partials; rename schedule tab to calendar; restyle bracket/standings panels; add motion layer. Frontend only.

**Tech Stack:** Laravel 13, Blade, SCSS (Vite), vanilla JS, existing `--hf-*` tokens.

## Global Constraints

- Frontend only; `LandingShowcase` (support class) is the only backend touch; no domain service changes.
- Reuse existing tokens/components (`hf-calendar`, `hf-calendar-cell`, `hf-card`, `hf-avatar`).
- Respect `prefers-reduced-motion`.
- No new dependencies.
- Preserve existing `data-testid`s relied on by tests; rename schedule→calendar testids explicitly.

---

### Task 1: `LandingShowcase::calendar()` method

**Files:**
- Modify: `src/app/Support/LandingShowcase.php`
- Test: `src/tests/Unit/Support/LandingShowcaseTest.php`

**Interfaces:**
- Produces: `calendar(): array{month: string, weeks: list<list<array{day:int|null, hasGames:bool, matches:list}>>}`

- [ ] **Step 1:** Add `calendar()` method returning a November 2026 month grid (5 weeks × 7 days). Days 14, 21, 22 have games (from schedule data). Each cell: `['day' => int|null, 'hasGames' => bool, 'matches' => list]`.
- [ ] **Step 2:** Run `php artisan test tests/Unit/Support/LandingShowcaseTest.php` — expect pass.
- [ ] **Step 3:** Commit `feat(landing): add calendar() showcase data`.

### Task 2: Calendar panel partial + lab tab rename

**Files:**
- Create: `src/resources/views/landing/calendar-panel.blade.php`
- Modify: `src/resources/views/landing/lab.blade.php` (rename schedule tab → calendar)
- Modify: `src/tests/Feature/Pages/LandingTest.php` (testid renames)

**Interfaces:**
- Consumes: `$calendar` (from Task 1), `$teams`
- Produces: `demo-tab-calendar`, `demo-panel-calendar` testids.

- [ ] **Step 1:** Write failing test asserting `demo-tab-calendar` and `demo-panel-calendar` exist (rename from schedule).
- [ ] **Step 2:** Run test — expect fail.
- [ ] **Step 3:** Create calendar-panel with month grid + day game list. Update lab.blade.php. Update home.blade.php to pass `$calendar`.
- [ ] **Step 4:** Run test — expect pass.
- [ ] **Step 5:** Commit.

### Task 3: Accurate bracket & standings panels

**Files:**
- Modify: `src/resources/views/landing/bracket-panel.blade.php`
- Modify: `src/resources/views/landing/standings-panel.blade.php`

- [ ] **Step 1:** Restyle bracket panel to use arena card tokens (dark board, connector lines, featured final).
- [ ] **Step 2:** Restyle standings panel to use `hf-table` tokens with qualification line.
- [ ] **Step 3:** Run `LandingTest` — expect pass.
- [ ] **Step 4:** Commit.

### Task 4: Developer section

**Files:**
- Create: `src/resources/views/landing/developers.blade.php`
- Modify: `src/resources/views/landing/closing.blade.php`

- [ ] **Step 1:** Create developers partial with two cards (Jeward Mahinay, Rusty Japos; avatar placeholders, role, links).
- [ ] **Step 2:** Include in closing.
- [ ] **Step 3:** Commit.

### Task 5: Splash screen

**Files:**
- Create: `src/resources/views/landing/splash.blade.php`
- Modify: `src/resources/views/pages/home.blade.php`
- Create/modify: `src/resources/js/ui/landing.js` (splash timing, sessionStorage skip, reduced-motion)

- [ ] **Step 1:** Create splash partial with HoopFlow logo animation.
- [ ] **Step 2:** Include at top of home.blade.php.
- [ ] **Step 3:** Add JS for fade-out + skip-on-repeat.
- [ ] **Step 4:** Commit.

### Task 6: Motion enhancements

**Files:**
- Modify: `src/resources/css/landing/_base.scss`
- Create: `src/resources/css/landing/_motion.scss` (import in landing.scss)
- Modify: `src/resources/css/landing.scss` (import motion)

- [ ] **Step 1:** Add hero entrance, court draw-in, ball bounce, tab cross-fade, flow reveal, calendar pop-in, developer reveal.
- [ ] **Step 2:** Respect `prefers-reduced-motion`.
- [ ] **Step 3:** Commit.

### Task 7: Verification

- [ ] **Step 1:** `npm run build` pass.
- [ ] **Step 2:** `composer quality` pass (Pint, PHPStan, full suite).
- [ ] **Step 3:** Browser check 1440/1024/390, light/dark, reduced motion, splash skip.
- [ ] **Step 4:** Commit any final tweaks.

## Execution

Invoke writing-plans execution via subagent-driven-development or executing-plans.