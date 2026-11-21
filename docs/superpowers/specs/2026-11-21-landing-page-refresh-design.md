# Landing Page Refresh — Design Spec

**Date:** 2026-11-21
**Status:** Approved (brainstorming)
**Scope:** Frontend (Blade + SCSS + JS). Backend untouched except `LandingShowcase` support class.

## Goal

Make the HoopFlow landing page dynamic, interactive, and accurate to the real app UI:
- Feature lab previews (bracket, **calendar**, standings) match the real app's UI language.
- Add a month-grid calendar preview (replacing the schedule list tab).
- Add a "Built by" developer hero section (Jeward Mahinay, Rusty Japos; avatars later).
- Add a HoopFlow logo splash screen on first load.
- Enhance transitions, effects, and animations (respecting `prefers-reduced-motion`).

## Approach

Incremental enhancement of existing landing partials — lowest risk, preserves tests.

## Architecture

- `pages/home.blade.php`: render `landing.splash` first (full-screen overlay), then existing sections.
- `landing/lab.blade.php`: rename "Schedule" tab → "Calendar"; include `landing.calendar-panel` instead of `landing.schedule-panel`. Keep tab testids updated (`demo-tab-calendar`, `demo-panel-calendar`).
- `landing/bracket-panel.blade.php`, `landing/standings-panel.blade.php`: restyle to match arena card + standings table tokens.
- `landing/closing.blade.php`: include new `landing.developers` partial (Built by section).
- `landing/calendar-panel.blade.php` (new): month grid with match-day highlighting + day game list.
- `landing/developers.blade.php` (new): two developer cards with avatar placeholder, name, role, links.
- `landing/splash.blade.php` (new): full-screen logo overlay, fades out after ~1.2s, skipped on repeat visits (sessionStorage), instant under reduced motion.
- Motion: hero entrance, court draw-in, ball bounce, lab tab cross-fade, flow reveal, calendar pop-in, developer reveal. CSS in `landing/_base.scss` + new `landing/_motion.scss`; JS in new `landing.js` module.

## Data

`LandingShowcase` gains `calendar()` returning a month grid (Nov 2026) with match-day cells populated from existing `schedule()` day data. No other data changes.

## Tests

- Update `LandingTest` testids: `demo-tab-schedule` → `demo-tab-calendar`, `demo-panel-schedule` → `demo-panel-calendar`.
- Add assertions for calendar panel and developer section where straightforward.
- Keep all existing `data-testid`s relied on by tests.

## Constraints

- Frontend only. Reuse `--hf-*` tokens and existing components (`hf-calendar`, `hf-calendar-cell`, `hf-card`, `hf-avatar`).
- Respect `prefers-reduced-motion`.
- No new dependencies.

## Verification

- `composer quality` (Pint, PHPStan, full suite).
- `npm run build`.
- Browser: 1440×900 / 1024×768 / 390×844, light/dark, reduced motion, splash skip-on-repeat.