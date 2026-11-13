# HoopFlow UI/UX Enhancement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the HoopFlow interface as a bold courtside-broadcast product with complete navigation, one feedback system, accessible components, and product-ready copy — without changing backend behavior.

**Architecture:** Split the single SCSS file into token-driven partials, then rebuild the shell (full rail, compact rail, More sheet), add global feedback (toast host, progress, validation, offline, errors), and restyle matchday and desk surfaces in phases. All new behavior lives in Blade, CSS, and small JS modules; existing Pest tests and `data-testid` contracts are the regression gate.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, Livewire 4, Alpine 3, Bootstrap 5.3 SCSS, Vite 8, Pest 4, Barlow Condensed + Inter.

## Global Constraints

- **UI-only:** no routes, controllers, middleware, services, models, migrations, seeders, config, `.env`, or domain logic. Allowed paths: `src/resources/views/**`, `src/resources/css/**`, `src/resources/js/**`, `src/public/**`, and presentation-only hooks in `src/app/Livewire/**`.
- **No test edits.** Preserve every test-asserted string and every `data-testid`. If a copy change would require a test edit, stop and ask.
- **Staging:** Never `git add` a directory (`src/resources/views`, `src/resources/views/livewire`, `src/resources/views/pages`, `src/resources/views/components`, `src/resources/views/auth`, `src/resources/css/hf`). Enumerate the exact files changed in the current task. Directory adds sweep in the user's in-progress edits.
- **Working tree:** Before Task 1, run `git status --short` and ask the user to commit or stash their in-progress work. Never revert, overwrite, or stage a file you did not change for the current task. Known user work at plan time: `.obsidian/graph.json`, `src/app/Models/Tournament.php`, `src/resources/views/livewire/organizer/{bracket-show,tournament-create,tournament-show}.blade.php`, `src/tests/Feature/Pages/{BracketTest,TournamentsTest}.php`, and `.kilo/`. If those files are still dirty when a task touches them, stop and confirm before staging.
- **No new dependencies.** No Tailwind, Bootstrap JS, SPA, npm UI library, confetti, sound, or looping decoration.
- **Navigation contract:** `data-testid="nav-{key}"` stays on rail links; `data-testid="mobile-nav-{key}"` stays on bottom-nav tabs; `data-testid="theme-toggle"` stays on the theme control; `data-testid="logout"` stays on logout.
- **Copy contract (non-exhaustive):** `Quick-fill accounts`, `Create account`, `Finalize score?`, `Record forfeit?`, `Apply correction?`, `Correct score`, `Open scoring`, `8 of 8 entries`, `Schedule published`, `Tournament created`, `Team list`, `Schedule preview`, `Registration timeline`, `Send to:`, `Public profile`, `Pending and rejected registrations are visible only to the organizer and the team coach.`
- **One `<h1>` per page.** The topbar title becomes a `<p>`.
- **Motion:** 120ms micro, 180ms standard, 280ms expressive; transform/opacity only; reduced motion disables transforms, count-ups, drawing, shimmer, and looping transitions.
- **Touch targets:** at least 44px for controls; focus rings visible in both themes and forced-colors mode.
- **Verification:** `composer quality` and `npm run build` must pass; existing Pest suite must pass unchanged.

## File Structure

**CSS (new, imported by `_hoopflow.scss`):**
- `src/resources/css/hf/_tokens.scss` — design tokens, themes, density, motion prefs.
- `src/resources/css/hf/_base.scss` — reset, typography, links, focus, skip link, utilities.
- `src/resources/css/hf/_shell.scss` — rail, topbar, compact rail, bottom nav, More sheet, page headers.
- `src/resources/css/hf/_components.scss` — buttons, cards, chips, callouts, forms, tables, dialogs, toasts, empty, skeleton.
- `src/resources/css/hf/_data.scss` — match cards, bracket, schedule, standings, timeline.
- `src/resources/css/hf/_surfaces.scss` — auth, hero, dashboard, profile, public, error pages.
- `src/resources/css/hf/_motion.scss` — page enter, score pop, path draw, shimmer.
- `src/resources/css/hf/_print.scss` — bracket, schedule, match list print rules.

**JS (new modules imported by `app.js`):**
- `src/resources/js/ui/preferences.js` — theme (light/system/dark), density, motion.
- `src/resources/js/ui/toasts.js` — one Alpine toast store.
- `src/resources/js/ui/feedback.js` — progress bar, offline banner, request failures.
- `src/resources/js/ui/validation.js` — error sync, summary, focus first invalid.
- `src/resources/js/ui/shell.js` - More sheet, user menu, theme sync.
- `src/resources/js/ui/score-console.js` - score keyboard shortcuts.
- `src/resources/js/ui/bracket.js` — bracket zoom/fit.
- `src/resources/js/ui/scorekeeper.js` — scorekeeper mode and unsaved warning.
- `src/resources/js/ui/countup.js` — one-time KPI count-up.

**Blade components (new):**
- `src/resources/views/components/matchday-strip.blade.php`
- `src/resources/views/components/breadcrumbs.blade.php`
- `src/resources/views/components/user-menu.blade.php`
- `src/resources/views/components/callout.blade.php`
- `src/resources/views/components/form-errors.blade.php`
- `src/resources/views/components/error-page.blade.php`

**Views:**
- `src/resources/views/layouts/public.blade.php` — shell, rail, topbar, bottom nav, More sheet, toast host, progress, offline banner.
- `src/resources/views/layouts/landing.blade.php` — boot script and shared tokens.
- `src/resources/views/errors/{403,404,419,429,500,503}.blade.php` — branded error pages.
- All `src/resources/views/livewire/**` and `src/resources/views/pages/**` — restyled in Phases 3–4.

---

# Phase 0 — Foundation

### Task 1: Split the stylesheet without visual change

**Files:**
- Create: `src/resources/css/hf/_tokens.scss`
- Create: `src/resources/css/hf/_base.scss`
- Create: `src/resources/css/hf/_shell.scss`
- Create: `src/resources/css/hf/_components.scss`
- Create: `src/resources/css/hf/_data.scss`
- Create: `src/resources/css/hf/_surfaces.scss`
- Create: `src/resources/css/hf/_motion.scss`
- Create: `src/resources/css/hf/_print.scss`
- Modify: `src/resources/css/_hoopflow.scss:1-2607`

**Interfaces:**
- Consumes: nothing.
- Produces: the eight partials imported by `_hoopflow.scss`; every later task edits one of these files instead of the monolith.

- [ ] **Step 1: Create the eight empty partials**

Run:

```powershell
New-Item -ItemType Directory -Force src/resources/css/hf | Out-Null
1..1 | ForEach-Object { New-Item -ItemType File -Force src/resources/css/hf/_tokens.scss, src/resources/css/hf/_base.scss, src/resources/css/hf/_shell.scss, src/resources/css/hf/_components.scss, src/resources/css/hf/_data.scss, src/resources/css/hf/_surfaces.scss, src/resources/css/hf/_motion.scss, src/resources/css/hf/_print.scss | Out-Null }
```

- [ ] **Step 2: Replace `_hoopflow.scss` with the import wrapper**

```scss
// Aggregates the HoopFlow design system. Order matters: tokens first, then
// base, shell, components, data surfaces, page surfaces, motion, and print.
@use "hf/tokens";
@use "hf/base";
@use "hf/shell";
@use "hf/components";
@use "hf/data";
@use "hf/surfaces";
@use "hf/motion";
@use "hf/print";
```

- [ ] **Step 3: Move the existing blocks verbatim**

Cut the current `_hoopflow.scss` into the partials using this exact map. Do not rewrite declarations in this task.

| Partial | Current lines / blocks |
|---|---|
| `_tokens.scss` | lines 1–53 (`:root`, `[data-theme='dark']`) |
| `_base.scss` | lines 55-117; utility block 2373-2426; `.hf-sr-only` 2439-2448; public/mobile hardening 2532-2607 |
| `_shell.scss` | brand 118–186; app/main 187–211; rail 212–290; topbar 291–421; demo menu 422–526; mobile nav 527–610; page headers 611–686 |
| `_components.scss` | buttons 687–760; cards/grids 761–856; KPI 857–930; chips 1035–1084; forms 1591–1672; status dropdown 1673–1933; stepper 1934–1977; dialogs 1978–2041; toasts 2042–2119; empty 2120–2151; new primitives in later tasks |
| `_data.scss` | match cards 931–1034; bracket 1086–1234; schedule 1235–1444; standings 1445–1590 |
| `_surfaces.scss` | timeline/audit 2152–2212; auth 2213–2304; hero/stat 2305–2372; announcements 2451–2456; profile 2457–2529 |
| `_motion.scss` | motion block 2427-2437 |
| `_print.scss` | empty; filled in Task 13 |

- [ ] **Step 4: Build and compare**

Run: `npm run build`
Expected: build succeeds; CSS output contains `.hf-rail`, `.hf-topbar`, `.hf-bracket`, `.hf-dialog`, and `.hf-auth`.

Run: `php artisan test --filter=DashboardTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/resources/css/_hoopflow.scss src/resources/css/hf/_tokens.scss src/resources/css/hf/_base.scss src/resources/css/hf/_shell.scss src/resources/css/hf/_components.scss src/resources/css/hf/_data.scss src/resources/css/hf/_surfaces.scss src/resources/css/hf/_motion.scss src/resources/css/hf/_print.scss
git commit -m "refactor: split the HoopFlow stylesheet into design-system partials"
```

---

### Task 2: Tokens, contrast, density, and motion foundation

**Files:**
- Modify: `src/resources/css/hf/_tokens.scss`
- Modify: `src/resources/css/hf/_base.scss`
- Modify: `src/resources/css/hf/_shell.scss`
- Modify: `src/resources/css/hf/_components.scss`
- Modify: `src/resources/css/hf/_surfaces.scss`
- Modify: `src/resources/css/hf/_motion.scss`

**Interfaces:**
- Consumes: the partials from Task 1.
- Produces tokens used by every later task: `--hf-action`, `--hf-action-hover`, `--hf-on-action`, `--hf-primary-ink`, `--hf-gold-ink`, `--hf-success-ink`, `--hf-warning-ink`, `--hf-danger-ink`, `--hf-info-ink`, `--hf-content-max`, `--hf-radius-xs/md/lg/xl`, `--hf-space-1..8`, `--hf-shadow-rest/hover/overlay`, `--hf-dur-micro/standard/expressive`, `--hf-ease-out`.

- [ ] **Step 1: Add the new tokens**

In `_tokens.scss`, append inside `:root`:

```scss
  /* Action colors (meet 4.5:1 with their text) */
  --hf-action: #c2410c;
  --hf-action-hover: #9a3412;
  --hf-on-action: #ffffff;
  --hf-primary-ink: #b93d0a;
  --hf-gold-ink: #7a5200;
  --hf-success-ink: #0b5f49;
  --hf-warning-ink: #8a3d07;
  --hf-danger-ink: #b31632;
  --hf-info: #1d4f91;
  --hf-info-ink: #1d4f91;

  /* Layout */
  --hf-content-max: 1520px;

  /* Radius */
  --hf-radius-xs: 4px;
  --hf-radius-md: 8px;
  --hf-radius-lg: 12px;
  --hf-radius-xl: 16px;

  /* Spacing */
  --hf-space-1: 4px;
  --hf-space-2: 8px;
  --hf-space-3: 12px;
  --hf-space-4: 16px;
  --hf-space-5: 24px;
  --hf-space-6: 32px;
  --hf-space-7: 48px;
  --hf-space-8: 64px;

  /* Depth */
  --hf-shadow-rest: var(--hf-shadow);
  --hf-shadow-hover: 0 2px 4px rgba(11, 18, 32, 0.08), 0 14px 32px rgba(11, 18, 32, 0.10);
  --hf-shadow-overlay: 0 28px 90px rgba(0, 0, 0, 0.38);

  /* Motion */
  --hf-dur-micro: 120ms;
  --hf-dur-standard: 180ms;
  --hf-dur-expressive: 280ms;
  --hf-ease-out: cubic-bezier(0.22, 1, 0.36, 1);
```

In `[data-theme='dark']`, append:

```scss
  --hf-action: #fb7a1e;
  --hf-action-hover: #ff8f3f;
  --hf-on-action: #101827;
  --hf-primary-ink: #fb7a1e;
  --hf-gold-ink: #f5b301;
  --hf-success-ink: #34d399;
  --hf-warning-ink: #fbbf24;
  --hf-danger-ink: #ff5c7a;
  --hf-info: #7dd3fc;
  --hf-info-ink: #7dd3fc;
  --hf-shadow-hover: 0 2px 4px rgba(0, 0, 0, 0.45), 0 14px 32px rgba(0, 0, 0, 0.38);
```

Add density and motion preference blocks at the end of `_tokens.scss`:

```scss
[data-density='compact'] {
  --hf-card-pad: 14px;
  --hf-row-pad: 8px;
  --hf-section-gap: 12px;
}

[data-motion='reduced'] *,
[data-motion='reduced'] *::before,
[data-motion='reduced'] *::after {
  scroll-behavior: auto !important;
  animation-duration: 0.01ms !important;
  animation-iteration-count: 1 !important;
  transition-duration: 0.01ms !important;
}
```

- [ ] **Step 2: Apply the action tokens**

In `_components.scss`, change the primary button block:

```scss
.hf-btn {
  border: 1px solid var(--hf-border-strong);
  background: var(--hf-surface-2);
  box-shadow: none;
}

.hf-btn:hover {
  background: color-mix(in srgb, var(--hf-primary) 8%, var(--hf-surface-2));
  transform: translateY(-1px);
}

.hf-btn--primary {
  border-color: var(--hf-action);
  background: var(--hf-action);
  box-shadow: 0 2px 0 color-mix(in srgb, var(--hf-action) 72%, #000);
  color: var(--hf-on-action);
}

.hf-btn--primary:hover {
  background: var(--hf-action-hover);
  border-color: var(--hf-action-hover);
}

.hf-btn--danger {
  border-color: var(--hf-danger-ink);
  background: var(--hf-danger-ink);
  box-shadow: 0 2px 0 color-mix(in srgb, var(--hf-danger-ink) 70%, #000);
  color: #fff;
}

.hf-btn.is-loading {
  pointer-events: none;
}

.hf-btn.is-loading .bi {
  display: none;
}

.hf-btn.is-loading::after {
  width: 14px;
  height: 14px;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  content: '';
  animation: hf-spin 700ms linear infinite;
}

@keyframes hf-spin {
  to { transform: rotate(360deg); }
}
```

In `_shell.scss`, change the active rail link and active tab:

```scss
.hf-rail-link.is-active {
  background: color-mix(in srgb, var(--hf-primary) 22%, transparent);
  box-shadow: inset 3px 0 0 var(--hf-primary);
  color: #fff;
}

.hf-nav-tab.is-active {
  color: var(--hf-primary-ink);
}

.hf-nav-tab.is-active .bi::after {
  background: var(--hf-primary-ink);
}
```

In `_components.scss`, replace every chip color with its ink token:

```scss
.hf-chip--success { color: var(--hf-success-ink); }
.hf-chip--warning { color: var(--hf-warning-ink); }
.hf-chip--danger { color: var(--hf-danger-ink); }
.hf-chip--primary { color: var(--hf-primary-ink); }
.hf-chip--gold { color: var(--hf-gold-ink); }
.hf-chip--info { color: var(--hf-info-ink); }
```

Add the info chip:

```scss
.hf-chip--info {
  border-color: color-mix(in srgb, var(--hf-info) 30%, transparent);
  background: color-mix(in srgb, var(--hf-info) 12%, transparent);
  color: var(--hf-info-ink);
}
```

In `_base.scss`, change link hover and focus:

```scss
a:hover {
  text-decoration-color: var(--hf-primary-ink);
}

:focus-visible {
  outline: 3px solid var(--hf-action);
  outline-offset: 2px;
}
```

In `_surfaces.scss`, change the avatar background to `var(--hf-action)` and the auth card top border to `var(--hf-action)`.

- [ ] **Step 3: Add density rules**

At the end of `_components.scss`:

```scss
[data-density='compact'] .hf-card { padding: 14px; }
[data-density='compact'] .hf-table th,
[data-density='compact'] .hf-table td { padding-block: 8px; }
[data-density='compact'] .hf-section-head { margin-block: 12px 8px; }
[data-density='compact'] .hf-kpi { padding: 14px; }
[data-density='compact'] .hf-main { padding-top: 18px; }
```

- [ ] **Step 4: Add reduced-motion safeguards**

In `_motion.scss`, add:

```scss
@media (prefers-reduced-motion: reduce) {
  .hf-kpi-value[data-countup],
  .hf-page-enter,
  .hf-bracket-path,
  .hf-skeleton {
    animation: none !important;
  }
}

@media (forced-colors: active) {
  .hf-btn,
  .hf-card,
  .hf-chip,
  .hf-input,
  .hf-select,
  .hf-textarea,
  .hf-dialog {
    border: 1px solid CanvasText;
  }

  .hf-rail-link.is-active {
    outline: 2px solid Highlight;
  }
}
```

- [ ] **Step 5: Build and run the contrast-sensitive tests**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --filter="DashboardTest|LoginTest|BracketTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/resources/css/hf/_tokens.scss src/resources/css/hf/_base.scss src/resources/css/hf/_shell.scss src/resources/css/hf/_components.scss src/resources/css/hf/_surfaces.scss src/resources/css/hf/_motion.scss
git commit -m "feat: add accessible action tokens, density, and motion preferences"
```

---

### Task 3: Shared component states and primitives

**Files:**
- Modify: `src/resources/css/hf/_components.scss`
- Modify: `src/resources/css/hf/_data.scss`
- Modify: `src/resources/css/hf/_base.scss`

**Interfaces:**
- Consumes: tokens from Task 2.
- Produces classes used by every page task: `.hf-card--link`, `.hf-card__arrow`, `.hf-callout`, `.hf-callout--{info,warning,danger,success}`, `.hf-error-summary`, `.hf-skeleton`, `.hf-skeleton--text/row/card`, `.hf-is-updating`, `.hf-btn-group`, `.hf-segmented`, `.hf-segmented__item`, `.hf-tooltip`.

- [ ] **Step 1: Add interactive card and button-group styles**

Append to `_components.scss`:

```scss
a.hf-card,
.hf-card--link {
  position: relative;
  transition:
    transform var(--hf-dur-standard) var(--hf-ease-out),
    box-shadow var(--hf-dur-standard) var(--hf-ease-out),
    border-color var(--hf-dur-standard) var(--hf-ease-out);
}

a.hf-card:hover,
.hf-card--link:hover {
  border-color: color-mix(in srgb, var(--hf-primary) 45%, var(--hf-border));
  box-shadow: var(--hf-shadow-hover);
  transform: translateY(-2px);
}

.hf-card__arrow {
  margin-left: auto;
  color: var(--hf-primary-ink);
  transition: transform var(--hf-dur-standard) var(--hf-ease-out);
}

a.hf-card:hover .hf-card__arrow,
.hf-card--link:hover .hf-card__arrow {
  transform: translateX(4px);
}

.hf-btn-group {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 4px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-radius-lg);
  background: color-mix(in srgb, var(--hf-surface-2) 82%, var(--hf-ground));
}

.hf-btn-group .hf-btn {
  box-shadow: none;
}
```

- [ ] **Step 2: Add callouts and error summary**

Append to `_components.scss`:

```scss
.hf-callout {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 10px;
  padding: 12px 14px;
  border: 1px solid var(--hf-border);
  border-left: 4px solid var(--hf-info);
  border-radius: var(--hf-radius-lg);
  background: color-mix(in srgb, var(--hf-info) 7%, var(--hf-surface));
}

.hf-callout > .bi { color: var(--hf-info-ink); font-size: 18px; }
.hf-callout--warning { border-left-color: var(--hf-warning); background: color-mix(in srgb, var(--hf-warning) 8%, var(--hf-surface)); }
.hf-callout--warning > .bi { color: var(--hf-warning-ink); }
.hf-callout--danger { border-left-color: var(--hf-danger-ink); background: color-mix(in srgb, var(--hf-danger-ink) 7%, var(--hf-surface)); }
.hf-callout--danger > .bi { color: var(--hf-danger-ink); }
.hf-callout--success { border-left-color: var(--hf-success); background: color-mix(in srgb, var(--hf-success) 7%, var(--hf-surface)); }
.hf-callout--success > .bi { color: var(--hf-success-ink); }
.hf-callout strong { display: block; font-size: 13px; }
.hf-callout p { margin: 2px 0 0; color: var(--hf-muted); font-size: 12px; }

.hf-error-summary {
  margin-bottom: 14px;
}

.hf-error-summary ul {
  margin: 6px 0 0;
  padding-left: 18px;
  font-size: 12px;
}

.hf-error-summary a {
  color: var(--hf-danger-ink);
  text-decoration: underline;
  text-underline-offset: 2px;
}
```

- [ ] **Step 3: Add skeletons and updating states**

Append to `_components.scss`:

```scss
.hf-skeleton {
  position: relative;
  overflow: hidden;
  border-radius: 6px;
  background: color-mix(in srgb, var(--hf-ink) 8%, var(--hf-surface-2));
}

.hf-skeleton::after {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--hf-surface-2) 60%, transparent), transparent);
  content: '';
  transform: translateX(-100%);
  animation: hf-shimmer 1.2s ease-out 1;
}

.hf-skeleton--text { width: 100%; height: 12px; }
.hf-skeleton--text-short { width: 60%; height: 12px; }
.hf-skeleton--row { width: 100%; height: 44px; }
.hf-skeleton--card { width: 100%; height: 140px; border-radius: var(--hf-radius); }

.is-updating {
  opacity: 0.6;
  transition: opacity var(--hf-dur-standard) var(--hf-ease-out);
}

@keyframes hf-shimmer {
  to { transform: translateX(100%); }
}
```

- [ ] **Step 4: Add table selected/hover and mobile stack polish**

In `_data.scss`, extend the table block:

```scss
.hf-table th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--hf-surface-2);
}

.hf-table tbody tr {
  transition: background var(--hf-dur-micro) linear;
}

.hf-table tbody tr:hover {
  background: color-mix(in srgb, var(--hf-primary) 6%, transparent);
}

.hf-table tbody tr.is-active {
  box-shadow: inset 3px 0 0 var(--hf-primary);
  background: color-mix(in srgb, var(--hf-primary) 9%, transparent);
}

.hf-table .is-num {
  font-variant-numeric: tabular-nums;
  text-align: right;
}
```

- [ ] **Step 5: Add segmented control and tooltip**

Append to `_components.scss`:

```scss
.hf-segmented {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 4px;
  padding: 4px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-radius-lg);
  background: var(--hf-ground);
}

.hf-segmented__item {
  min-height: 36px;
  padding: 0 12px;
  border: 0;
  border-radius: var(--hf-radius-md);
  background: transparent;
  color: var(--hf-muted);
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
}

.hf-segmented__item[aria-pressed='true'] {
  background: var(--hf-surface-2);
  box-shadow: var(--hf-shadow-rest);
  color: var(--hf-ink);
}

[data-tooltip] {
  position: relative;
}

[data-tooltip]:hover::after,
[data-tooltip]:focus-visible::after {
  position: absolute;
  bottom: calc(100% + 8px);
  left: 50%;
  z-index: 80;
  max-width: 240px;
  padding: 6px 8px;
  border-radius: 6px;
  background: var(--hf-ink);
  color: var(--hf-ground);
  content: attr(data-tooltip);
  font-size: 11px;
  line-height: 1.3;
  transform: translateX(-50%);
  white-space: normal;
}
```

- [ ] **Step 6: Build and commit**

Run: `npm run build`
Expected: build succeeds.

```bash
git add src/resources/css/hf/_components.scss src/resources/css/hf/_data.scss src/resources/css/hf/_base.scss
git commit -m "feat: add shared component states, callouts, skeletons, and tables"
```

---

### Task 4: Shell components, single H1, breadcrumbs, and content width

**Files:**
- Create: `src/resources/views/components/matchday-strip.blade.php`
- Create: `src/resources/views/components/breadcrumbs.blade.php`
- Create: `src/resources/views/components/user-menu.blade.php`
- Create: `src/resources/views/components/callout.blade.php`
- Create: `src/resources/views/components/form-errors.blade.php`
- Modify: `src/resources/views/layouts/public.blade.php`
- Modify: `src/resources/views/pages/dashboard.blade.php`
- Modify: `src/resources/views/pages/teams/show.blade.php`
- Modify: `src/resources/views/pages/tournaments/show.blade.php`
- Modify: `src/resources/views/livewire/organizer/bracket-show.blade.php`
- Modify: `src/resources/views/livewire/organizer/schedule-show.blade.php`
- Modify: `src/resources/views/livewire/organizer/game-stats-show.blade.php`
- Modify: `src/resources/views/livewire/organizer/game-manage.blade.php`
- Modify: `src/resources/css/hf/_shell.scss`
- Modify: `src/resources/css/hf/_surfaces.scss`

**Interfaces:**
- Consumes: CSS tokens and classes from Tasks 2–3.
- Produces: `<x-matchday-strip>`, `<x-breadcrumbs :items>`, `<x-user-menu>`, `<x-callout>`, `<x-form-errors>`, and a topbar without `<h1>`.

- [ ] **Step 1: Create the matchday strip**

`components/matchday-strip.blade.php`:

```blade
@props([
    'eyebrow',
    'title',
    'status' => null,
    'statusTone' => 'primary',
    'description' => null,
    'meta' => [],
    'nextUp' => null,
])

<section {{ $attributes->merge(['class' => 'hf-matchday']) }}>
    <span class="hf-matchday__court" aria-hidden="true"></span>
    <div class="hf-matchday__body">
        <span class="hf-eyebrow hf-eyebrow--on-dark">{{ $eyebrow }}</span>
        <div class="hf-matchday__title-row">
            <h1 class="hf-matchday__title">{{ $title }}</h1>
            @if ($status)
                <span class="hf-chip hf-chip--{{ $statusTone }}">{{ $status }}</span>
            @endif
        </div>
        @if ($description)
            <p class="hf-matchday__desc">{{ $description }}</p>
        @endif
        @if (count($meta))
            <div class="hf-matchday__meta">
                @foreach ($meta as $item)
                    <span><i class="bi bi-{{ $item['icon'] ?? 'circle' }}" aria-hidden="true"></i>{{ $item['label'] }}</span>
                @endforeach
            </div>
        @endif
        @if ($nextUp)
            <p class="hf-matchday__next"><i class="bi bi-broadcast" aria-hidden="true"></i> Next up · {{ $nextUp }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div class="hf-matchday__actions">{{ $slot }}</div>
    @endif
</section>
```

Add to `_surfaces.scss`:

```scss
.hf-matchday {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 20px;
  overflow: hidden;
  margin-bottom: 18px;
  padding: 24px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: var(--hf-radius-xl);
  background:
    radial-gradient(circle at 88% 18%, color-mix(in srgb, var(--hf-primary) 28%, transparent), transparent 38%),
    var(--hf-rail);
  color: var(--hf-rail-ink);
  box-shadow: var(--hf-shadow-rest);
}

.hf-matchday__court {
  position: absolute;
  right: -80px;
  bottom: -120px;
  width: 340px;
  height: 340px;
  border: 2px solid rgba(255, 255, 255, 0.10);
  border-radius: 50%;
  pointer-events: none;
}

.hf-matchday__court::before {
  position: absolute;
  inset: 70px;
  border: 2px solid rgba(255, 255, 255, 0.08);
  border-radius: 50%;
  content: '';
}

.hf-matchday__court::after {
  position: absolute;
  top: 50%;
  left: -20%;
  width: 140%;
  border-top: 2px solid rgba(255, 255, 255, 0.08);
  content: '';
}

.hf-matchday__body { position: relative; z-index: 1; min-width: 0; }
.hf-eyebrow--on-dark { color: var(--hf-gold-ink); }
.hf-matchday__title-row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.hf-matchday__title {
  margin: 4px 0 0;
  font-family: var(--hf-font-display);
  font-size: clamp(2.5rem, 4vw, 4rem);
  font-weight: 800;
  line-height: 0.92;
  letter-spacing: 0.01em;
  overflow-wrap: anywhere;
}
.hf-matchday__desc { max-width: 68ch; margin: 8px 0 0; color: var(--hf-rail-muted); }
.hf-matchday__meta { display: flex; flex-wrap: wrap; gap: 8px 18px; margin-top: 12px; color: var(--hf-rail-muted); font-size: 13px; }
.hf-matchday__meta span { display: inline-flex; align-items: center; gap: 6px; }
.hf-matchday__next { margin: 14px 0 0; padding-top: 12px; border-top: 1px solid rgba(255, 255, 255, 0.14); color: var(--hf-rail-ink); font-size: 13px; }
.hf-matchday__actions { position: relative; z-index: 1; display: flex; flex-wrap: wrap; align-items: flex-end; gap: 8px; }

@media (max-width: 900px) {
  .hf-matchday { grid-template-columns: 1fr; padding: 18px; }
  .hf-matchday__actions { align-items: stretch; }
}
```

- [ ] **Step 2: Create breadcrumbs, user menu, callout, and form errors**

`components/breadcrumbs.blade.php`:

```blade
@props(['items' => []])

@if (count($items))
    <nav class="hf-breadcrumbs" aria-label="Breadcrumb">
        <ol>
            @foreach ($items as $item)
                <li>
                    @if (! $loop->last && ! empty($item['href']))
                        <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                    @else
                        <span @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
```

`components/user-menu.blade.php`:

```blade
@php
    $user = auth()->user();
    $role = $user?->hasRole('organizer') ? 'organizer' : ($user?->hasRole('coach') ? 'coach' : 'fan');
    $initials = collect(preg_split('/\s+/', $user?->name ?? ''))
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->slice(0, 2)
        ->implode('');
@endphp

<details class="hf-user-menu">
    <summary class="hf-user-chip" aria-label="Account menu for {{ $user?->name }}">
        <span class="hf-avatar">{{ $initials }}</span>
        <span class="hf-user-meta">
            <strong>{{ $user?->name }}</strong>
            <span>{{ $role }}</span>
        </span>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </summary>
    <div class="hf-user-menu__panel">
        <a href="{{ route('profile') }}" data-testid="topbar-profile"><i class="bi bi-person" aria-hidden="true"></i> Profile</a>
        <a href="{{ route('profile') }}#appearance"><i class="bi bi-palette" aria-hidden="true"></i> Appearance</a>
        <a href="{{ route('profile') }}#shortcuts"><i class="bi bi-keyboard" aria-hidden="true"></i> Keyboard shortcuts</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" data-testid="logout"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log out</button>
        </form>
    </div>
</details>
```

`components/callout.blade.php`:

```blade
@props(['tone' => 'info', 'icon' => null, 'title' => null])

@php
    $icons = ['info' => 'info-circle-fill', 'warning' => 'exclamation-triangle-fill', 'danger' => 'exclamation-octagon-fill', 'success' => 'check-circle-fill'];
@endphp

<div {{ $attributes->merge(['class' => 'hf-callout hf-callout--'.$tone]) }} role="{{ $tone === 'danger' ? 'alert' : 'status' }}">
    <i class="bi bi-{{ $icon ?? ($icons[$tone] ?? 'info-circle-fill') }}" aria-hidden="true"></i>
    <div>
        @if ($title)<strong>{{ $title }}</strong>@endif
        {{ $slot }}
    </div>
</div>
```

`components/form-errors.blade.php`:

```blade
@props(['targets' => []])

@if ($errors->any())
    <div class="hf-callout hf-callout--danger hf-error-summary" role="alert" tabindex="-1" data-hf-error-summary>
        <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
        <div>
            <strong>Please fix {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('field', $errors->count()) }}</strong>
            <ul>
                @foreach ($errors->keys() as $field)
                    <li><a href="#{{ $targets[$field] ?? $field }}">{{ $errors->first($field) }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
```

- [ ] **Step 3: Wire the topbar, breadcrumbs, and content width into the layout**

In `layouts/public.blade.php`, replace the topbar `<h1>` with a paragraph and add the breadcrumb slot:

```blade
<header class="hf-topbar">
    <div class="hf-topbar-context">
        @hasSection('breadcrumbs')
            @yield('breadcrumbs')
        @else
            <p class="hf-topbar-title">@yield('title', $title ?? 'HoopFlow')</p>
            <div class="hf-topbar-sub">{{ $activeTournament?->name ?? 'No active tournament' }} · {{ $role }}</div>
        @endif
    </div>
    <div class="hf-topbar-actions">
        <button type="button" class="hf-icon-btn" data-theme-toggle aria-label="Toggle color theme" data-testid="theme-toggle">
            <i class="bi bi-moon-stars" aria-hidden="true"></i>
        </button>
        <a class="hf-icon-btn" href="{{ route('announcements') }}" aria-label="Announcements" data-testid="topbar-announcements">
            <i class="bi bi-bell" aria-hidden="true"></i>
        </a>
    </div>
</header>
```

The account menu lives once, in the rail footer (and in the More sheet on mobile). Do not mount
`<x-user-menu>` in both the topbar and the rail: that duplicates `data-testid="logout"`.

Replace the rail footer user block with:

```blade
<div class="hf-rail-footer">
    <x-user-menu />
</div>
```

Add breadcrumb styles to `_shell.scss`:

```scss
.hf-breadcrumbs ol {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 12px;
}

.hf-breadcrumbs li + li::before {
  margin-right: 6px;
  color: var(--hf-muted);
  content: '/';
}

.hf-breadcrumbs a { color: var(--hf-muted); }
.hf-breadcrumbs [aria-current='page'] { color: var(--hf-ink); font-weight: 700; }

.hf-user-menu { position: relative; }
.hf-user-menu summary { cursor: pointer; list-style: none; }
.hf-user-menu summary::-webkit-details-marker { display: none; }
.hf-user-menu[open] summary { border-color: var(--hf-primary); }
.hf-user-menu__panel {
  position: absolute;
  right: 0;
  bottom: calc(100% + 8px);
  z-index: 80;
  display: grid;
  min-width: 220px;
  padding: 6px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-radius-lg);
  background: var(--hf-surface-2);
  box-shadow: var(--hf-shadow-overlay);
}
.hf-rail-footer .hf-user-menu__panel { top: auto; bottom: calc(100% + 8px); }
.hf-user-menu__panel a,
.hf-user-menu__panel button {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 40px;
  padding: 8px 10px;
  border: 0;
  border-radius: var(--hf-radius-md);
  background: transparent;
  color: var(--hf-ink);
  cursor: pointer;
  font-size: 13px;
  text-align: left;
}
.hf-user-menu__panel a:hover,
.hf-user-menu__panel button:hover { background: var(--hf-ground); text-decoration: none; }
```

Add `max-width` to `.hf-main` in `_shell.scss`:

```scss
.hf-main {
  width: 100%;
  max-width: var(--hf-content-max);
  margin-inline: auto;
}
```

- [ ] **Step 4: Add breadcrumbs to detail pages**

In `pages/teams/show.blade.php`, after `@section('title', $team->name)` add:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Teams', 'href' => route('teams')],
        ['label' => $team->name],
    ]" />
@endsection
```

In `pages/tournaments/show.blade.php`:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Tournaments', 'href' => route('tournaments')],
        ['label' => $tournament->name],
    ]" />
@endsection
```

In `livewire/organizer/bracket-show.blade.php`, inside the `@else` branch before the header:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Tournaments', 'href' => route('tournaments')],
        ['label' => $tournament->name, 'href' => route('tournamentShow', ['tournament' => $tournament->id])],
        ['label' => 'Bracket'],
    ]" />
@endsection
```

Repeat the same pattern for `schedule-show.blade.php` (last label `Schedule`), `game-stats-show.blade.php` (last label `Game stats`), and `game-manage.blade.php` (last label `Game scoring`).

- [ ] **Step 5: Fix the duplicate H1s in page headers**

In `pages/dashboard.blade.php`:

- Line 219: change `<h1>{{ $team->name }}</h1>` to `<h2 class="hf-hero__name">{{ $team->name }}</h2>`.
- Line 311: change `<h1>{{ $tournament?->name ?? 'HoopFlow' }}</h1>` to `<h2 class="hf-hero__name">{{ $tournament?->name ?? 'HoopFlow' }}</h2>`.

The organizer header's `<h1>` at line 62 stays for now; Task 11 replaces that header with `<x-matchday-strip>`.

In `pages/teams/show.blade.php`, line 43: change `<h1>{{ $team->name }}</h1>` to `<h2 class="hf-hero__name">{{ $team->name }}</h2>`.

In `livewire/organizer/bracket-show.blade.php`, line 122: change `<h1>{{ $bracket['champion']->name }}</h1>` to `<h2 class="hf-hero__name">{{ $bracket['champion']->name }}</h2>`.

In `livewire/organizer/game-stats-show.blade.php`, lines 99 and 111: change `<strong class="hf-page-title ...">` to `<strong class="hf-team-name ...">`.

Add to `_surfaces.scss`:

```scss
.hf-hero__name {
  position: relative;
  margin: 4px 0;
  font-family: var(--hf-font-display);
  font-size: clamp(34px, 4vw, 54px);
  font-weight: 800;
  line-height: 0.92;
}

.hf-team-name {
  font-family: var(--hf-font-body);
  font-size: clamp(18px, 2.4vw, 28px);
  font-weight: 700;
  letter-spacing: -0.02em;
}
```

- [ ] **Step 6: Verify one H1 and build**

Run: `npm run build`
Expected: build succeeds.

Run:

```powershell
Select-String -Path src/resources/views/**/*.blade.php -Pattern "<h1" | Select-Object Path,LineNumber,Line
```

Expected: no `layouts/public.blade.php` h1; each page has at most one h1; dashboard coach/fan hero names are h2.

Run: `php artisan test --filter="DashboardTest|TeamsTest|BracketTest|GameStatsTest"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/resources/views/components/matchday-strip.blade.php src/resources/views/components/breadcrumbs.blade.php src/resources/views/components/user-menu.blade.php src/resources/views/components/callout.blade.php src/resources/views/components/form-errors.blade.php src/resources/views/layouts/public.blade.php src/resources/views/pages/dashboard.blade.php src/resources/views/pages/teams/show.blade.php src/resources/views/pages/tournaments/show.blade.php src/resources/views/livewire/organizer/bracket-show.blade.php src/resources/views/livewire/organizer/schedule-show.blade.php src/resources/views/livewire/organizer/game-stats-show.blade.php src/resources/views/livewire/organizer/game-manage.blade.php src/resources/css/hf/_shell.scss src/resources/css/hf/_surfaces.scss
git commit -m "feat: add broadcast shell components, breadcrumbs, and one-h1 headers"
```

---

# Phase 1 — Shell and navigation

### Task 5: Theme, density, and motion preferences

**Files:**
- Create: `src/resources/js/ui/preferences.js`
- Modify: `src/resources/js/app.js`
- Modify: `src/resources/views/layouts/public.blade.php:12-23`
- Modify: `src/resources/views/layouts/landing.blade.php:13-25`
- Modify: `src/resources/views/pages/profile.blade.php:77-81`
- Modify: `src/resources/css/hf/_components.scss`
- Modify: `src/resources/css/hf/_surfaces.scss`

**Interfaces:**
- Consumes: `data-theme`, `data-density`, `data-motion` tokens from Task 2.
- Produces: `initPreferences()` in `preferences.js`; preference buttons use `data-hf-pref="theme|density|motion"` and `data-hf-value="light|system|dark|comfortable|compact|reduced"`.

- [ ] **Step 1: Create the preferences module**

`src/resources/js/ui/preferences.js`:

```js
const THEME_KEY = 'hf-theme';
const DENSITY_KEY = 'hf-density';
const MOTION_KEY = 'hf-motion';
const media = window.matchMedia('(prefers-color-scheme: dark)');

export function themePreference() {
    return localStorage.getItem(THEME_KEY) || 'system';
}

export function resolvedTheme() {
    const pref = themePreference();
    if (pref === 'system') return media.matches ? 'dark' : 'light';
    return pref === 'dark' ? 'dark' : 'light';
}

export function applyPreferences() {
    const theme = resolvedTheme();
    document.documentElement.dataset.theme = theme;
    document.documentElement.dataset.themePreference = themePreference();
    document.documentElement.dataset.density = localStorage.getItem(DENSITY_KEY) || 'comfortable';
    document.documentElement.dataset.motion = localStorage.getItem(MOTION_KEY) || 'system';
    document.documentElement.style.colorScheme = theme;
}

export function setPreference(name, value) {
    if (name === 'theme') localStorage.setItem(THEME_KEY, value);
    if (name === 'density') localStorage.setItem(DENSITY_KEY, value);
    if (name === 'motion') localStorage.setItem(MOTION_KEY, value);
    applyPreferences();
    syncPreferenceButtons();
    document.dispatchEvent(new CustomEvent('hf:theme-changed', { detail: { theme: resolvedTheme() } }));
}

export function toggleTheme() {
    setPreference('theme', resolvedTheme() === 'dark' ? 'light' : 'dark');
}

export function syncPreferenceButtons() {
    document.querySelectorAll('[data-hf-pref]').forEach((button) => {
        const name = button.dataset.hfPref;
        const current = name === 'theme'
            ? themePreference()
            : (name === 'density' ? (localStorage.getItem(DENSITY_KEY) || 'comfortable') : (localStorage.getItem(MOTION_KEY) || 'system'));
        button.setAttribute('aria-pressed', String(button.dataset.hfValue === current));
    });
    document.querySelectorAll('[data-theme-toggle] i').forEach((icon) => {
        icon.className = `bi bi-${resolvedTheme() === 'dark' ? 'sun' : 'moon-stars'}`;
    });
}

export function initPreferences() {
    applyPreferences();

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-theme-toggle]');
        if (toggle) {
            toggleTheme();
            return;
        }

        const pref = event.target.closest('[data-hf-pref]');
        if (pref) {
            setPreference(pref.dataset.hfPref, pref.dataset.hfValue);
        }
    });

    media.addEventListener('change', () => {
        if (themePreference() === 'system') {
            applyPreferences();
            syncPreferenceButtons();
        }
    });

    syncPreferenceButtons();
    document.addEventListener('hf:theme-changed', syncPreferenceButtons);
}
```

- [ ] **Step 2: Replace the theme code in `app.js`**

Remove the existing `THEME_KEY`, `applyTheme`, `theme`, `syncTheme`, theme click listener, and `hf:theme-changed` listener. Add:

```js
import { initPreferences, syncPreferenceButtons } from './ui/preferences';

initPreferences();
document.addEventListener('hf:theme-changed', syncPreferenceButtons);
```

Keep the Bootstrap Icons import at the top of `app.js`.

- [ ] **Step 3: Update the inline boot script in both layouts**

Replace the existing theme IIFE in `layouts/public.blade.php` and `layouts/landing.blade.php` with:

```blade
<script>
    (function () {
        try {
            var theme = localStorage.getItem('hf-theme') || 'system';
            var dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
            document.documentElement.dataset.themePreference = theme;
            document.documentElement.dataset.density = localStorage.getItem('hf-density') || 'comfortable';
            document.documentElement.dataset.motion = localStorage.getItem('hf-motion') || 'system';
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
</script>
```

- [ ] **Step 4: Add the appearance panel to Profile**

Replace the existing appearance card in `pages/profile.blade.php` with:

```blade
<section id="appearance" class="hf-card" data-testid="profile-appearance">
    <span class="hf-kpi-label">Appearance</span>
    <h2 class="hf-section-title hf-mt-1">Theme</h2>
    <p class="hf-small hf-muted">Choose how the workspace looks. System follows your device setting.</p>
    <div class="hf-segmented" role="group" aria-label="Theme">
        @foreach (['light' => 'Light', 'system' => 'System', 'dark' => 'Dark'] as $value => $label)
            <button type="button" class="hf-segmented__item" data-hf-pref="theme" data-hf-value="{{ $value }}" aria-pressed="false">{{ $label }}</button>
        @endforeach
    </div>

    <h2 class="hf-section-title hf-mt-3">Density</h2>
    <div class="hf-segmented" role="group" aria-label="Density">
        @foreach (['comfortable' => 'Comfortable', 'compact' => 'Compact'] as $value => $label)
            <button type="button" class="hf-segmented__item" data-hf-pref="density" data-hf-value="{{ $value }}" aria-pressed="false">{{ $label }}</button>
        @endforeach
    </div>

    <h2 class="hf-section-title hf-mt-3">Motion</h2>
    <div class="hf-segmented" role="group" aria-label="Motion">
        @foreach (['system' => 'System', 'reduced' => 'Reduced'] as $value => $label)
            <button type="button" class="hf-segmented__item" data-hf-pref="motion" data-hf-value="{{ $value }}" aria-pressed="false">{{ $label }}</button>
        @endforeach
    </div>
</section>
```

- [ ] **Step 5: Add the keyboard-shortcut reference**

After the appearance card, add:

```blade
<section id="shortcuts" class="hf-card hf-mt-3">
    <span class="hf-kpi-label">Keyboard</span>
    <h2 class="hf-section-title hf-mt-1">Shortcuts</h2>
    <dl class="hf-shortcuts">
        <div><dt><kbd>1</kbd> <kbd>2</kbd> <kbd>3</kbd></dt><dd>Add points to the active team in a score console</dd></div>
        <div><dt><kbd>U</kbd></dt><dd>Undo the last score</dd></div>
        <div><dt><kbd>Enter</kbd></dt><dd>Finalize the score dialog</dd></div>
        <div><dt><kbd>←</kbd> <kbd>→</kbd></dt><dd>Switch the active team in a score console</dd></div>
        <div><dt><kbd>Esc</kbd></dt><dd>Close a dialog or the More sheet</dd></div>
    </dl>
</section>
```

Add to `_surfaces.scss`:

```scss
.hf-shortcuts { display: grid; gap: 8px; margin: 10px 0 0; }
.hf-shortcuts div { display: grid; grid-template-columns: 130px minmax(0, 1fr); gap: 12px; }
.hf-shortcuts dt { margin: 0; }
.hf-shortcuts dd { margin: 0; color: var(--hf-muted); font-size: 13px; }
.hf-shortcuts kbd {
  display: inline-block;
  min-width: 24px;
  padding: 2px 6px;
  border: 1px solid var(--hf-border-strong);
  border-radius: 5px;
  background: var(--hf-surface-2);
  font-family: var(--hf-font-body);
  font-size: 11px;
  text-align: center;
}
```

- [ ] **Step 6: Verify theme and profile**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --filter="ProfileAuditTest|LoginTest"`
Expected: PASS.

Manual: open `/profile`, switch Light/System/Dark, Comfortable/Compact, System/Reduced; reload and confirm the choices persist.

- [ ] **Step 7: Commit**

```bash
git add src/resources/js/app.js src/resources/js/ui/preferences.js src/resources/views/layouts/public.blade.php src/resources/views/layouts/landing.blade.php src/resources/views/pages/profile.blade.php src/resources/css/hf/_components.scss src/resources/css/hf/_surfaces.scss
git commit -m "feat: add theme, density, and motion preferences"
```

---

### Task 6: Full rail, compact rail, and topbar

**Files:**
- Modify: `src/resources/views/layouts/public.blade.php:113-171`
- Modify: `src/resources/css/hf/_shell.scss`
- Modify: `src/resources/js/ui/shell.js`

**Interfaces:**
- Consumes: `<x-user-menu>` and breadcrumbs from Task 4; preferences from Task 5.
- Produces: responsive rail destinations at every width; `data-label` on rail links for compact tooltips.

- [ ] **Step 1: Rewrite the rail markup**

In `layouts/public.blade.php`, replace the `<aside class="hf-rail">...</aside>` block with:

```blade
<aside class="hf-rail" aria-label="Primary navigation">
    <a class="hf-rail-brand" href="{{ route('dashboard') }}" aria-label="HoopFlow dashboard">
        <x-brand size="md" on-dark tagline="Tournament ops" />
    </a>

    <span class="hf-rail-label">Matchday</span>
    @foreach ($matchdayKeys as $key)
        @php $item = $navItems[$key]; @endphp
        <a class="hf-rail-link{{ $isActive($key) ? ' is-active' : '' }}"
           href="{{ route($item['route']) }}"
           data-testid="nav-{{ $key }}"
           data-label="{{ $item['label'] }}"
           @if ($isActive($key)) aria-current="page" @endif>
            <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach

    @if (count($roleTools))
        <span class="hf-rail-label">Workspace</span>
        @foreach ($roleTools as $key)
            @php $item = $navItems[$key]; @endphp
            <a class="hf-rail-link{{ $isActive($key) ? ' is-active' : '' }}"
               href="{{ route($item['route']) }}"
               data-testid="nav-{{ $key }}"
               data-label="{{ $item['label'] }}"
               @if ($isActive($key)) aria-current="page" @endif>
                <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    @endif

    <div class="hf-rail-footer">
        <x-user-menu />
    </div>
</aside>
```

- [ ] **Step 2: Add the compact rail CSS**

Replace the existing `@media (max-width: 991.98px)` rail rules in `_shell.scss` with:

```scss
@media (min-width: 768px) and (max-width: 1199.98px) {
  .hf-app {
    grid-template-columns: 76px minmax(0, 1fr);
  }

  .hf-rail {
    align-items: center;
    padding: 18px 8px;
  }

  .hf-rail-brand {
    justify-content: center;
    padding: 4px 0 14px;
  }

  .hf-rail-brand .hf-brand__text,
  .hf-rail-label,
  .hf-rail-link span {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  }

  .hf-rail-link {
    justify-content: center;
    width: 52px;
    padding: 10px 0;
  }

  .hf-rail-link::after {
    position: absolute;
    left: calc(100% + 10px);
    z-index: 90;
    padding: 6px 8px;
    border-radius: 6px;
    background: var(--hf-ink);
    color: var(--hf-ground);
    content: attr(data-label);
    font-size: 11px;
    opacity: 0;
    pointer-events: none;
    transition: opacity var(--hf-dur-micro) linear;
    white-space: nowrap;
  }

  .hf-rail-link:hover::after,
  .hf-rail-link:focus-visible::after {
    opacity: 1;
  }

  .hf-rail-footer {
    width: 100%;
  }

  .hf-rail-footer .hf-user-chip {
    justify-content: center;
    width: 52px;
    padding: 4px;
  }

  .hf-rail-footer .hf-user-meta,
  .hf-rail-footer .hf-user-chip > .bi {
    display: none;
  }
}

@media (max-width: 767.98px) {
  .hf-app {
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: auto 1fr;
  }

  .hf-rail {
    display: none;
  }

  .hf-topbar {
    grid-column: 1;
    min-height: 58px;
    padding: 8px 14px;
  }

  .hf-main {
    grid-column: 1;
    padding: 16px 14px 92px;
  }

  .hf-bottom-nav {
    display: grid;
  }
}
```

Remove the old `@media (max-width: 991.98px)` block that hides the rail and shows the bottom nav at 992px. Replace it with the two blocks above.

After the rail CSS is in place, delete the dead `.hf-demo-menu`, `.hf-demo-row`, and `.hf-demo-roles`
selectors from `_shell.scss` (the old 422-526 block). Keep `.hf-demo-title`, which the login
quick-fill panel still uses.

- [ ] **Step 3: Add user-menu behavior**

Create `src/resources/js/ui/shell.js`:

```js
export function initShell() {
    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.hf-user-menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('details.hf-user-menu[open]').forEach((menu) => {
            menu.removeAttribute('open');
            menu.querySelector('summary')?.focus();
        });
    });
}
```

Import and call it in `app.js`:

```js
import { initShell } from './ui/shell';
initShell();
```

- [ ] **Step 4: Fix the topbar grid placement**

In `_shell.scss`, ensure `.hf-topbar` uses `grid-column: 2` above 768px:

```scss
.hf-topbar {
  grid-column: 2;
  min-width: 0;
}
```

At 768–1199px the rail is still column 1, so the topbar stays in column 2. At below 768px the media query resets it to column 1.

- [ ] **Step 5: Verify the rail at three widths**

Run: `npm run build`
Expected: build succeeds.

Manual: open the dashboard at 1440px, 1024px, and 390px. Confirm:
- 1440px: 248px rail with labels and the user menu at the bottom.
- 1024px: 76px rail with icon-only links, hover tooltips, and an avatar.
- 390px: no rail, bottom nav visible.

Run: `php artisan test --filter="GameManageTest"`
Expected: PASS, including `data-testid="nav-gameManage"`.

- [ ] **Step 6: Commit**

```bash
git add src/resources/views/layouts/public.blade.php src/resources/css/hf/_shell.scss src/resources/js/app.js src/resources/js/ui/shell.js
git commit -m "feat: add full and compact navigation rails"
```

---

### Task 7: Mobile bottom bar and More sheet

**Files:**
- Modify: `src/resources/views/layouts/public.blade.php:79-87,173-180`
- Modify: `src/resources/css/hf/_shell.scss`
- Modify: `src/resources/js/ui/shell.js`

**Interfaces:**
- Consumes: `$navItems`, `$roleTools`, `$isActive` from the layout.
- Produces: `<dialog data-more-sheet>` with every workspace and account destination; bottom nav keys `dashboard`, `bracket`, `schedule`, `standings`, `more`.

- [ ] **Step 1: Replace the mobile tab list and add the More sheet**

In the layout, replace `$roleTabs` with:

```php
$roleTabs = [
    ['key' => 'dashboard', 'label' => 'Home', 'icon' => 'house-door', 'route' => 'dashboard'],
    ['key' => 'bracket', 'label' => 'Bracket', 'icon' => 'diagram-3', 'route' => 'bracket'],
    ['key' => 'schedule', 'label' => 'Schedule', 'icon' => 'calendar3', 'route' => 'schedule'],
    ['key' => 'standings', 'label' => 'Standings', 'icon' => 'bar-chart', 'route' => 'standings'],
];
```

Delete the `$roleTabs[] = $role === 'coach' ? ... : ...;` line that currently appends My Team or
Profile. Those destinations move into the More sheet; leaving the append creates a sixth tab that
overflows the bar.

Replace the bottom nav markup with:

```blade
<nav class="hf-bottom-nav" aria-label="Mobile navigation">
    @foreach ($roleTabs as $tab)
        <a class="hf-nav-tab{{ $isActive($tab['key']) ? ' is-active' : '' }}"
           href="{{ route($tab['route']) }}"
           data-testid="mobile-nav-{{ $tab['key'] }}"
           @if ($isActive($tab['key'])) aria-current="page" @endif>
            <i class="bi bi-{{ $tab['icon'] }}" aria-hidden="true"></i>
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
    <button type="button" class="hf-nav-tab" data-more-sheet-open aria-haspopup="dialog" aria-controls="hf-more-sheet" data-testid="mobile-nav-more">
        <i class="bi bi-grid" aria-hidden="true"></i>
        <span>More</span>
    </button>
</nav>

<dialog id="hf-more-sheet" class="hf-sheet" data-more-sheet aria-labelledby="hf-more-title">
    <div class="hf-sheet__handle" aria-hidden="true"></div>
    <div class="hf-sheet__head">
        <h2 class="hf-dialog__title" id="hf-more-title">More</h2>
        <button type="button" class="hf-icon-btn" data-more-sheet-close aria-label="Close menu">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="hf-sheet__body">
        <details class="hf-user-menu hf-sheet__account" open>
            <summary class="hf-user-chip" aria-label="Account menu for {{ $authUser->name }}">
                <span class="hf-avatar">{{ $initials }}</span>
                <span class="hf-user-meta">
                    <strong>{{ $authUser->name }}</strong>
                    <span>{{ $role }}</span>
                </span>
            </summary>
            <div class="hf-user-menu__panel">
                <a href="{{ route('profile') }}"><i class="bi bi-person" aria-hidden="true"></i> Profile</a>
                <a href="{{ route('profile') }}#appearance"><i class="bi bi-palette" aria-hidden="true"></i> Appearance</a>
                <a href="{{ route('profile') }}#shortcuts"><i class="bi bi-keyboard" aria-hidden="true"></i> Keyboard shortcuts</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log out</button>
                </form>
            </div>
        </details>

        <div class="hf-sheet__group">
            <span class="hf-rail-label">Matchday</span>
            @foreach ($matchdayKeys as $key)
                @php $item = $navItems[$key]; @endphp
                <a href="{{ route($item['route']) }}" data-more-link="{{ $key }}">
                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>{{ $item['label'] }}
                </a>
            @endforeach
        </div>

        @if (count($roleTools))
            <div class="hf-sheet__group">
                <span class="hf-rail-label">Workspace</span>
                @foreach ($roleTools as $key)
                    @php $item = $navItems[$key]; @endphp
                    <a href="{{ route($item['route']) }}" data-more-link="{{ $key }}">
                        <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>{{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="hf-sheet__group">
            <span class="hf-rail-label">Account</span>
            <a href="{{ route('myTeam') }}" data-more-link="myTeam"><i class="bi bi-people" aria-hidden="true"></i> My team</a>
            <a href="{{ route('profile') }}" data-more-link="profile"><i class="bi bi-person" aria-hidden="true"></i> Profile</a>
        </div>
    </div>
</dialog>
```

- [ ] **Step 2: Add the sheet CSS**

Append to `_shell.scss`:

```scss
.hf-sheet {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  width: 100%;
  max-width: none;
  max-height: min(86dvh, 760px);
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: 18px 18px 0 0;
  background: var(--hf-surface-2);
  color: var(--hf-ink);
  box-shadow: var(--hf-shadow-overlay);
}

.hf-sheet::backdrop {
  background: rgba(8, 13, 23, 0.62);
  backdrop-filter: blur(3px);
}

.hf-sheet__handle {
  width: 44px;
  height: 4px;
  margin: 8px auto 0;
  border-radius: 999px;
  background: var(--hf-border-strong);
}

.hf-sheet__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 18px 8px;
}

.hf-sheet__body {
  max-height: calc(min(86dvh, 760px) - 76px);
  overflow-y: auto;
  padding: 0 18px calc(24px + env(safe-area-inset-bottom));
}

.hf-sheet__group {
  display: grid;
  gap: 2px;
  padding: 10px 0;
  border-top: 1px solid var(--hf-border);
}

.hf-sheet__group a {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 8px 10px;
  border-radius: var(--hf-radius-md);
  font-size: 14px;
  font-weight: 600;
}

.hf-sheet__group a:hover {
  background: var(--hf-ground);
  text-decoration: none;
}

.hf-sheet__account .hf-user-menu__panel {
  position: static;
  min-width: 0;
  margin-top: 6px;
  box-shadow: none;
}

.hf-nav-tab[data-more-sheet-open] {
  border: 0;
  background: transparent;
  cursor: pointer;
}
```

- [ ] **Step 3: Add the sheet controller to `shell.js`**

Append:

```js
export function initMoreSheet() {
    const sheet = document.querySelector('[data-more-sheet]');
    if (!sheet) return;

    let trigger = null;

    document.querySelectorAll('[data-more-sheet-open]').forEach((button) => {
        button.addEventListener('click', () => {
            trigger = button;
            sheet.showModal();
        });
    });

    document.querySelectorAll('[data-more-sheet-close]').forEach((button) => {
        button.addEventListener('click', () => sheet.close());
    });

    sheet.addEventListener('click', (event) => {
        if (event.target === sheet) sheet.close();
    });

    sheet.addEventListener('close', () => {
        trigger?.focus({ preventScroll: true });
    });

    sheet.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => sheet.close());
    });
}
```

Import and call in `app.js`:

```js
import { initMoreSheet } from './ui/shell';
initMoreSheet();
```

- [ ] **Step 4: Verify mobile navigation**

Run: `npm run build`
Expected: build succeeds.

Manual at 390px: tap More; confirm every workspace link, Profile, My Team, and Logout are reachable; press Escape and confirm focus returns to the More tab; tap a link and confirm the sheet closes.

Run: `php artisan test --filter="GameManageTest|DashboardTest"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/resources/views/layouts/public.blade.php src/resources/css/hf/_shell.scss src/resources/js/app.js src/resources/js/ui/shell.js
git commit -m "feat: add a complete mobile More sheet and bottom navigation"
```

---

# Phase 2 — Feedback and error handling

### Task 8: One global toast host

**Files:**
- Create: `src/resources/js/ui/toasts.js`
- Modify: `src/resources/js/app.js`
- Modify: `src/resources/views/layouts/public.blade.php`
- Modify: `src/resources/css/hf/_components.scss`
- Modify: `src/resources/views/livewire/**` (ten views; remove duplicated toast code)

**Interfaces:**
- Consumes: the existing `hf-toast` browser event with `{title, message, tone}`.
- Produces: Alpine data `hfToasts` with `push(detail)`, `dismiss(id)`, `pause(id)`, `resume(id)`; one `.hf-toast-region` host mounted in the layout.

- [ ] **Step 1: Create the toast module**

`src/resources/js/ui/toasts.js`:

```js
const MAX_TOASTS = 3;
const DEFAULT_DURATION = 4200;

export function registerToasts() {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('hfToasts', () => ({
            toasts: [],

            push(detail = {}) {
                const toast = {
                    id: `${Date.now()}-${Math.random()}`,
                    title: detail.title || '',
                    message: detail.message || '',
                    tone: ['success', 'warning', 'danger', 'info'].includes(detail.tone) ? detail.tone : 'info',
                    actionLabel: detail.actionLabel || '',
                    actionHref: detail.actionHref || '',
                    duration: Number(detail.duration) || DEFAULT_DURATION,
                    paused: false,
                    remaining: Number(detail.duration) || DEFAULT_DURATION,
                    startedAt: performance.now(),
                    timer: null,
                };

                this.toasts.push(toast);
                if (this.toasts.length > MAX_TOASTS) this.toasts.shift();
                this.schedule(toast);
            },

            schedule(toast) {
                toast.startedAt = performance.now();
                toast.timer = window.setTimeout(() => this.dismiss(toast.id), toast.remaining);
            },

            dismiss(id) {
                const toast = this.toasts.find((item) => item.id === id);
                if (toast?.timer) window.clearTimeout(toast.timer);
                this.toasts = this.toasts.filter((item) => item.id !== id);
            },

            pause(id) {
                const toast = this.toasts.find((item) => item.id === id);
                if (!toast || toast.paused) return;
                toast.paused = true;
                window.clearTimeout(toast.timer);
                toast.remaining = Math.max(0, toast.remaining - (performance.now() - toast.startedAt));
            },

            resume(id) {
                const toast = this.toasts.find((item) => item.id === id);
                if (!toast || !toast.paused) return;
                toast.paused = false;
                this.schedule(toast);
            },
        }));
    });
}
```

- [ ] **Step 2: Mount the host in the layout**

Immediately after the opening `<body>` tag in `layouts/public.blade.php`, add:

```blade
@php
    $flashToast = session('announcement_toast');
@endphp

<div class="hf-toast-region"
     aria-live="polite"
     aria-atomic="false"
     x-data="hfToasts"
     @hf-toast.window="push($event.detail)"
     @if ($flashToast) x-init="push(@js($flashToast))" @endif>
    <template x-for="toast in toasts" :key="toast.id">
        <div class="hf-toast"
             :class="`hf-toast--${toast.tone}${toast.paused ? ' is-paused' : ''}`"
             :role="toast.tone === 'danger' ? 'alert' : 'status'"
             @mouseenter="pause(toast.id)"
             @mouseleave="resume(toast.id)"
             @focusin="pause(toast.id)"
             @focusout="resume(toast.id)">
            <i class="bi"
               :class="toast.tone === 'danger' ? 'bi-x-circle-fill' : toast.tone === 'warning' ? 'bi-exclamation-triangle-fill' : toast.tone === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill'"
               aria-hidden="true"></i>
            <div>
                <strong x-show="toast.title" x-text="toast.title"></strong>
                <p x-text="toast.message"></p>
                <a x-show="toast.actionLabel" class="hf-toast__action" :href="toast.actionHref" x-text="toast.actionLabel"></a>
            </div>
            <button type="button" aria-label="Dismiss notification" data-testid="toast-dismiss" @click="dismiss(toast.id)">&times;</button>
            <span class="hf-toast__timer" :style="`animation-duration:${toast.duration}ms`" aria-hidden="true"></span>
        </div>
    </template>
</div>
```

- [ ] **Step 3: Import the toast module**

In `app.js`:

```js
import { registerToasts } from './ui/toasts';
registerToasts();
```

- [ ] **Step 4: Remove the ten duplicate toast blocks**

Run:

```powershell
Select-String -Path src/resources/views/livewire/**/*.blade.php -Pattern "pushToast|hf-toast-region|toasts: \[\]|flashToast" | Select-Object Path,LineNumber
```

For each of the ten files (`coach/my-team`, `coach/team-register`, `organizer/announcements-index`, `organizer/bracket-show`, `organizer/game-stats-show`, `organizer/payments-index`, `organizer/schedule-show`, `organizer/submissions-index`, `organizer/tournament-create`, `organizer/tournament-show`):

1. Remove `toasts: []`, `pushToast(detail) { ... }`, `dismissToast(id) { ... }`, and `toastIcon(tone) { ... }` from the root `x-data` object. Keep any other properties such as `mobileRound`.
2. Remove `x-on:hf-toast.window="pushToast($event.detail)"`.
3. Remove the `@if ($flashToast) x-init="pushToast(@js($flashToast))" @endif` block.
4. Remove the trailing `<div class="hf-toast-region">...</div>` block.
5. If the root `x-data` object is now empty, remove the `x-data` attribute.

Remove the `flashToast` property and mount assignment from `AnnouncementsIndex.php` only if it is no longer referenced anywhere. Keep the `session()->flash('announcement_toast', ...)` in `AnnouncementCreate.php`; the layout now consumes it.

- [ ] **Step 5: Add toast styles**

Replace the existing `.hf-toast` rules in `_components.scss` with:

```scss
.hf-toast-region {
  position: fixed;
  right: 18px;
  bottom: 18px;
  z-index: 120;
  display: grid;
  gap: 8px;
  width: min(380px, calc(100vw - 24px));
}

.hf-toast {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 10px;
  align-items: start;
  overflow: hidden;
  padding: 12px 12px 14px;
  border: 1px solid var(--hf-border);
  border-left: 4px solid var(--hf-primary);
  border-radius: var(--hf-radius-lg);
  background: var(--hf-surface-2);
  box-shadow: var(--hf-shadow-overlay);
  animation: hf-toast-in var(--hf-dur-standard) var(--hf-ease-out);
}

.hf-toast > .bi { margin-top: 1px; color: var(--hf-primary-ink); font-size: 18px; }
.hf-toast--success { border-left-color: var(--hf-success); }
.hf-toast--success > .bi { color: var(--hf-success-ink); }
.hf-toast--warning { border-left-color: var(--hf-warning); }
.hf-toast--warning > .bi { color: var(--hf-warning-ink); }
.hf-toast--danger { border-left-color: var(--hf-danger-ink); }
.hf-toast--danger > .bi { color: var(--hf-danger-ink); }
.hf-toast strong { display: block; font-size: 13px; }
.hf-toast p { margin: 3px 0 0; color: var(--hf-muted); font-size: 12px; }
.hf-toast button {
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--hf-muted);
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
}
.hf-toast__action {
  display: inline-block;
  margin-top: 6px;
  color: var(--hf-primary-ink);
  font-size: 12px;
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 2px;
}
.hf-toast__timer {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  height: 2px;
  background: currentColor;
  opacity: 0.35;
  transform-origin: left;
  animation: hf-toast-timer linear forwards;
}
.hf-toast.is-paused .hf-toast__timer { animation-play-state: paused; }

@keyframes hf-toast-timer {
  from { transform: scaleX(1); }
  to { transform: scaleX(0); }
}

@media (max-width: 767.98px) {
  .hf-toast-region {
    right: 12px;
    bottom: calc(76px + env(safe-area-inset-bottom));
    left: 12px;
    width: auto;
  }
}
```

- [ ] **Step 6: Verify all toast paths**

Run: `npm run build`
Expected: build succeeds.

Manual: approve a submission, verify a payment, publish an announcement, and trigger a roster-full warning. Confirm each shows exactly one toast, not a stack, and the announcement flash survives the redirect.

Run: `php artisan test --filter="SubmissionsPaymentsTest|AnnouncementsTest|MyTeamTest|BracketTest"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/resources/js/app.js src/resources/js/ui/toasts.js src/resources/views/layouts/public.blade.php src/resources/views/livewire/coach/my-team.blade.php src/resources/views/livewire/coach/team-register.blade.php src/resources/views/livewire/organizer/announcements-index.blade.php src/resources/views/livewire/organizer/bracket-show.blade.php src/resources/views/livewire/organizer/game-stats-show.blade.php src/resources/views/livewire/organizer/payments-index.blade.php src/resources/views/livewire/organizer/schedule-show.blade.php src/resources/views/livewire/organizer/submissions-index.blade.php src/resources/views/livewire/organizer/tournament-create.blade.php src/resources/views/livewire/organizer/tournament-show.blade.php src/resources/css/hf/_components.scss
git commit -m "feat: replace duplicated toasts with one global host"
```

Do not stage `src/resources/views/livewire` as a directory: three of those files carry the user's
in-progress work. Stage only the list above, and only after the Step 0 working-tree check is settled.

---

### Task 9: Progress, loading states, request failures, and offline banner

**Files:**
- Create: `src/resources/js/ui/feedback.js`
- Modify: `src/resources/js/app.js`
- Modify: `src/resources/views/layouts/public.blade.php`
- Modify: `src/resources/css/hf/_components.scss`
- Modify: `src/resources/views/livewire/**` (primary actions and result regions)

**Interfaces:**
- Consumes: `Livewire.interceptRequest`; the toast event from Task 8.
- Produces: `initFeedback()`; CSS `.hf-progress`, `.hf-offline`, `.hf-btn.is-loading`, `.is-updating`, `.hf-updating-pill`.

- [ ] **Step 1: Create the feedback module**

`src/resources/js/ui/feedback.js`:

```js
let activeRequests = 0;
let startedAt = 0;
let showTimer = null;
let hideTimer = null;

function progressEl() {
    return document.querySelector('[data-hf-progress]');
}

function showProgress() {
    const bar = progressEl();
    if (!bar) return;
    bar.classList.add('is-active');
    bar.setAttribute('aria-hidden', 'false');
}

function hideProgress() {
    const bar = progressEl();
    if (!bar) return;
    bar.classList.remove('is-active');
    bar.setAttribute('aria-hidden', 'true');
}

function startProgress() {
    activeRequests += 1;
    if (activeRequests !== 1) return;
    startedAt = performance.now();
    window.clearTimeout(hideTimer);
    showTimer = window.setTimeout(showProgress, 150);
}

function finishProgress() {
    activeRequests = Math.max(0, activeRequests - 1);
    if (activeRequests !== 0) return;
    window.clearTimeout(showTimer);
    const elapsed = performance.now() - startedAt;
    hideTimer = window.setTimeout(hideProgress, Math.max(0, 300 - elapsed));
}

function toast(detail) {
    window.dispatchEvent(new CustomEvent('hf-toast', { detail }));
}

function initOffline() {
    const banner = document.querySelector('[data-hf-offline]');
    if (!banner) return;

    let wasOffline = !navigator.onLine;

    const sync = () => {
        banner.hidden = navigator.onLine;
        if (!navigator.onLine) {
            wasOffline = true;
        } else if (wasOffline) {
            wasOffline = false;
            toast({ tone: 'success', title: 'Back online', message: 'Changes will save again.' });
        }
    };

    window.addEventListener('online', sync);
    window.addEventListener('offline', sync);
    sync();
}

function initRequests() {
    if (!window.Livewire) return;

    window.Livewire.interceptRequest(({ onSend, onFinish, onError, onFailure }) => {
        onSend(() => startProgress());
        onFinish(() => finishProgress());

        onError(({ response, preventDefault }) => {
            const status = response?.status ?? 0;
            if (status === 422) return;
            preventDefault?.();

            if (status === 419) {
                toast({
                    tone: 'danger',
                    title: 'Your session expired',
                    message: 'Reload the page and sign in again.',
                    actionLabel: 'Reload',
                    actionHref: window.location.href,
                });
                return;
            }

            toast({
                tone: 'danger',
                title: 'That action didn’t go through',
                message: 'Try again. Your form state is still here.',
                actionLabel: 'Retry',
                actionHref: window.location.href,
            });
        });

        onFailure(() => {
            toast({
                tone: 'danger',
                title: 'Connection lost',
                message: 'Your change may not have saved.',
                actionLabel: 'Retry',
                actionHref: window.location.href,
            });
        });
    });
}

export function initFeedback() {
    initOffline();
    initRequests();
}
```

- [ ] **Step 2: Mount the progress and offline markup, then import the module**

In `layouts/public.blade.php`, immediately after the opening `<body>` tag and before the toast host, add:

```blade
<div class="hf-progress" data-hf-progress aria-hidden="true"><span></span></div>

<div class="hf-offline" data-hf-offline role="status" hidden>
    <i class="bi bi-wifi-off" aria-hidden="true"></i>
    You're offline. Changes won't save until you reconnect.
</div>
```

In `app.js`:

```js
import { initFeedback } from './ui/feedback';
initFeedback();
```

- [ ] **Step 3: Add progress and offline styles**

Append to `_components.scss`:

```scss
.hf-progress {
  position: fixed;
  top: 0;
  right: 0;
  left: 0;
  z-index: 200;
  height: 2px;
  overflow: hidden;
  pointer-events: none;
  opacity: 0;
  transition: opacity var(--hf-dur-micro) linear;
}

.hf-progress.is-active { opacity: 1; }

.hf-progress span {
  display: block;
  width: 40%;
  height: 100%;
  background: var(--hf-primary);
  animation: hf-progress 1.1s ease-in-out infinite;
}

@keyframes hf-progress {
  from { transform: translateX(-100%); }
  to { transform: translateX(350%); }
}

.hf-offline {
  position: fixed;
  top: 0;
  right: 0;
  left: 0;
  z-index: 190;
  padding: 8px 14px;
  background: var(--hf-warning);
  color: #1a1300;
  font-size: 12px;
  font-weight: 700;
  text-align: center;
}

.hf-updating-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 8px;
  border-radius: var(--hf-pill);
  background: var(--hf-ground);
  color: var(--hf-muted);
  font-size: 11px;
  font-weight: 700;
}

[data-motion='reduced'] .hf-progress span {
  animation: none;
  width: 100%;
}
```

- [ ] **Step 4: Add loading states to primary actions and result regions**

In each Livewire view, add `wire:loading.class="is-loading"` and `wire:loading.attr="aria-busy"` to the primary submit/save/publish/generate/approve buttons. Examples:

`organizer/payments-index.blade.php`:

```blade
<button type="button" class="hf-btn hf-btn--primary" wire:click="verifyPayment({{ $payment->id }})" wire:loading.class="is-loading" wire:loading.attr="aria-busy" data-testid="payment-verify-{{ $payment->id }}">
    Verify payment
</button>
```

`organizer/submissions-index.blade.php`:

```blade
<button type="button" class="hf-btn hf-btn--primary" wire:click="approve({{ $review->id }})" wire:loading.class="is-loading" wire:loading.attr="aria-busy" data-testid="submission-approve-{{ $review->id }}">
    Approve
</button>
```

`organizer/bracket-show.blade.php`:

```blade
<button type="button" class="hf-btn hf-btn--primary" wire:click="requestGeneratePlayoffs" wire:loading.class="is-loading" wire:loading.attr="aria-busy" data-testid="bracket-generate-playoffs">
    Generate playoffs
</button>
```

`organizer/schedule-show.blade.php`:

```blade
<button type="button" class="hf-btn hf-btn--primary" wire:click="publish" wire:loading.class="is-loading" wire:loading.attr="aria-busy" data-testid="schedule-publish">
    Publish schedule
</button>
```

Add `wire:loading.class="is-updating"` and `wire:loading.attr="aria-busy"` to these result regions:

- `#tournamentList` in `tournaments-index.blade.php`
- `#matchList` in `matches-index.blade.php`
- `#scheduleList` in `schedule-show.blade.php`
- `#standingsBody` in `standings-show.blade.php`
- the payments table wrapper in `payments-index.blade.php`
- the submissions queue wrapper in `submissions-index.blade.php`
- the bracket tree wrapper in `bracket-show.blade.php`

Add an updating pill inside each region:

```blade
<div class="hf-updating-pill" wire:loading wire:target="search,status,division">
    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Updating…
</div>
```

Use the appropriate `wire:target` list for each page.

- [ ] **Step 5: Verify feedback**

Run: `npm run build`
Expected: build succeeds.

Manual: throttle the network in DevTools, trigger a Livewire action, confirm the top bar appears after ~150ms and the button shows a spinner. Go offline and confirm the banner appears; go online and confirm “Back online”.

Run: `php artisan test --filter="TournamentsTest|SubmissionsPaymentsTest|ScheduleTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/resources/js/app.js src/resources/js/ui/feedback.js src/resources/views/layouts/public.blade.php src/resources/css/hf/_components.scss src/resources/views/livewire/organizer/tournaments-index.blade.php src/resources/views/livewire/organizer/matches-index.blade.php src/resources/views/livewire/organizer/schedule-show.blade.php src/resources/views/livewire/public/standings-show.blade.php src/resources/views/livewire/organizer/payments-index.blade.php src/resources/views/livewire/organizer/submissions-index.blade.php src/resources/views/livewire/organizer/bracket-show.blade.php
git commit -m "feat: add global progress, loading states, and offline feedback"
```

---

### Task 10: Validation summaries, field errors, and branded error pages

**Files:**
- Create: `src/resources/js/ui/validation.js`
- Create: `src/resources/views/components/error-page.blade.php`
- Create: `src/resources/views/errors/403.blade.php`
- Create: `src/resources/views/errors/404.blade.php`
- Create: `src/resources/views/errors/419.blade.php`
- Create: `src/resources/views/errors/429.blade.php`
- Create: `src/resources/views/errors/500.blade.php`
- Create: `src/resources/views/errors/503.blade.php`
- Modify: `src/resources/js/app.js`
- Modify: `src/resources/views/auth/login.blade.php`
- Modify: `src/resources/views/auth/register.blade.php`
- Modify: `src/resources/css/hf/_surfaces.scss`

**Interfaces:**
- Consumes: `<x-form-errors>` from Task 4; `.hf-callout` from Task 3.
- Produces: `initValidation()`; every `.hf-field-error` gets `role="alert"`, `aria-invalid`, and `aria-describedby`; error pages at `/errors/{code}`.

- [ ] **Step 1: Create the validation module**

`src/resources/js/ui/validation.js`:

```js
function fieldFor(error) {
    const field = error.closest('.hf-field');
    return field?.querySelector('input, select, textarea') ?? null;
}

function ensureErrorIds() {
    document.querySelectorAll('.hf-field-error').forEach((error, index) => {
        if (!error.id) error.id = `hf-error-${index}-${Math.random().toString(36).slice(2, 8)}`;
        error.setAttribute('role', 'alert');

        const field = fieldFor(error);
        if (!field) return;
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', error.id);
    });
}

function buildSummary() {
    const errors = [...document.querySelectorAll('.hf-field-error')].filter((error) => error.textContent.trim() !== '');
    if (!errors.length || document.querySelector('[data-hf-error-summary]')) return;

    const host = document.querySelector('[data-hf-form]') ?? document.querySelector('form') ?? document.querySelector('[wire\\:id]');
    if (!host) return;

    const summary = document.createElement('div');
    summary.className = 'hf-callout hf-callout--danger hf-error-summary';
    summary.setAttribute('role', 'alert');
    summary.setAttribute('tabindex', '-1');
    summary.setAttribute('data-hf-error-summary', '');

    const count = errors.length;
    const list = errors.map((error) => {
        const field = fieldFor(error);
        const id = field?.id || error.id;
        return `<li><a href="#${id}">${error.textContent.trim()}</a></li>`;
    }).join('');

    summary.innerHTML = `
        <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
        <div>
            <strong>Please fix ${count} ${count === 1 ? 'field' : 'fields'}</strong>
            <ul>${list}</ul>
        </div>
    `;

    host.prepend(summary);
}

function focusFirstInvalid() {
    const summary = document.querySelector('[data-hf-error-summary]');
    if (!summary) return;
    const field = document.querySelector('[aria-invalid="true"]');
    if (field) {
        field.focus({ preventScroll: false });
        field.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
}

function sync() {
    ensureErrorIds();
    buildSummary();
}

export function initValidation() {
    sync();
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morphed', () => {
            sync();
            focusFirstInvalid();
        });
    });

    document.addEventListener('submit', () => {
        window.setTimeout(sync, 0);
    });
}
```

- [ ] **Step 2: Import and call the module**

In `app.js`:

```js
import { initValidation } from './ui/validation';
initValidation();
```

- [ ] **Step 3: Add the summary to auth forms**

In `auth/login.blade.php`, immediately inside the `<form>` after `@csrf`:

```blade
<x-form-errors :targets="['email' => 'loginEmail', 'password' => 'loginPassword']" />
```

In `auth/register.blade.php`, immediately inside the `<form id="registerForm">` after `@csrf`:

```blade
<x-form-errors :targets="[
    'name' => 'registerName',
    'email' => 'registerEmail',
    'password' => 'registerPassword',
    'role' => 'registerRole',
    'organization' => 'registerOrganization',
]" />
```

- [ ] **Step 4: Add the form-error summary hook to the tournament wizard**

In `livewire/organizer/tournament-create.blade.php`, inside the form wrapper (the element that contains the step fields), add:

```blade
<x-form-errors />
```

Keep the existing field-level `@error` blocks; the JS sync fills in missing ARIA.

- [ ] **Step 5: Create the branded error page component**

`components/error-page.blade.php`:

```blade
@props([
    'code',
    'title',
    'message',
    'detail' => null,
    'primaryLabel' => 'Go home',
    'primaryHref' => null,
    'secondaryLabel' => 'Go back',
    'secondaryHref' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} · {{ $title }} · HoopFlow</title>
    <link rel="icon" type="image/png" href="{{ asset('img/hoopflow icon without background.png') }}">
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('hf-theme') || 'system';
                var dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
                document.documentElement.dataset.density = localStorage.getItem('hf-density') || 'comfortable';
                document.documentElement.dataset.motion = localStorage.getItem('hf-motion') || 'system';
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
    @vite(['resources/css/app.scss'])
</head>
<body class="hf-error-page">
    <main class="hf-error-shell">
        <span class="hf-error-court" aria-hidden="true"></span>
        <div class="hf-error-brand">
            <x-brand size="md" />
        </div>
        <p class="hf-eyebrow">{{ $code }}</p>
        <h1 class="hf-error-title">{{ $title }}</h1>
        <p class="hf-error-message">{{ $message }}</p>
        @if ($detail)
            <p class="hf-error-detail">{{ $detail }}</p>
        @endif
        <div class="hf-btn-row hf-error-actions">
            <a class="hf-btn hf-btn--primary" href="{{ $primaryHref ?? route('home') }}">{{ $primaryLabel }}</a>
            <a class="hf-btn" href="{{ $secondaryHref ?? url()->previous() }}">{{ $secondaryLabel }}</a>
        </div>
    </main>
</body>
</html>
```

- [ ] **Step 6: Create the six error views**

`errors/404.blade.php`:

```blade
<x-error-page
    code="404"
    title="Page not found"
    message="That page moved, was renamed, or never existed. Check the address or head back to the dashboard."
    primary-label="Go to dashboard"
    :primary-href="auth()->check() ? route('dashboard') : route('home')"
/>
```

`errors/403.blade.php`:

```blade
<x-error-page
    code="403"
    title="You don’t have access"
    message="This area is limited to a different role. If you think this is a mistake, ask the tournament organizer."
    primary-label="Go to dashboard"
    :primary-href="auth()->check() ? route('dashboard') : route('home')"
/>
```

`errors/419.blade.php`:

```blade
<x-error-page
    code="419"
    title="Your session expired"
    message="Your sign-in timed out while the page was open. Reload and sign in again."
    primary-label="Sign in again"
    :primary-href="route('login')"
    secondary-label="Go home"
    :secondary-href="route('home')"
/>
```

`errors/429.blade.php`:

```blade
<x-error-page
    code="429"
    title="Too many attempts"
    message="The server is rate-limiting requests. Wait a moment, then try again."
    primary-label="Go home"
    :primary-href="route('home')"
    secondary-label="Go back"
/>
```

`errors/500.blade.php`:

```blade
<x-error-page
    code="500"
    title="Something went wrong"
    message="The page hit an unexpected error. Your data is safe; try the action again or return to the dashboard."
    primary-label="Go to dashboard"
    :primary-href="auth()->check() ? route('dashboard') : route('home')"
/>
```

`errors/503.blade.php`:

```blade
<x-error-page
    code="503"
    title="HoopFlow is briefly offline"
    message="The service is restarting or under maintenance. Try again in a minute."
    primary-label="Try again"
    :primary-href="url()->current()"
    secondary-label="Go home"
    :secondary-href="route('home')"
/>
```

- [ ] **Step 7: Add error page styles**

Append to `_surfaces.scss`:

```scss
.hf-error-page {
  min-height: 100vh;
  background:
    radial-gradient(circle at 18% 20%, color-mix(in srgb, var(--hf-primary) 16%, transparent), transparent 42%),
    var(--hf-ground);
}

.hf-error-shell {
  position: relative;
  display: grid;
  align-content: center;
  min-height: 100vh;
  max-width: 720px;
  margin: 0 auto;
  padding: 48px 24px;
}

.hf-error-court {
  position: absolute;
  top: 50%;
  right: -180px;
  width: 420px;
  height: 420px;
  border: 2px solid color-mix(in srgb, var(--hf-ink) 8%, transparent);
  border-radius: 50%;
  transform: translateY(-50%);
  pointer-events: none;
}

.hf-error-brand { margin-bottom: 32px; }
.hf-error-title {
  margin: 6px 0 10px;
  font-family: var(--hf-font-display);
  font-size: clamp(2.5rem, 6vw, 4.5rem);
  font-weight: 800;
  line-height: 0.92;
  text-transform: uppercase;
}
.hf-error-message { max-width: 58ch; margin: 0; color: var(--hf-muted); font-size: 16px; }
.hf-error-detail { max-width: 58ch; margin: 10px 0 0; color: var(--hf-muted); font-size: 13px; }
.hf-error-actions { margin-top: 24px; }
```

- [ ] **Step 8: Verify error pages and validation**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --filter="LoginTest|RegistrationTest|TournamentLifecycleTest"`
Expected: PASS.

Manual: visit `/not-a-real-page` and confirm the branded 404. Submit the login form empty and confirm the summary appears and focus moves to the first invalid field.

- [ ] **Step 9: Commit**

```bash
git add src/resources/js/app.js src/resources/js/ui/validation.js src/resources/views/components/error-page.blade.php src/resources/views/errors/403.blade.php src/resources/views/errors/404.blade.php src/resources/views/errors/419.blade.php src/resources/views/errors/429.blade.php src/resources/views/errors/500.blade.php src/resources/views/errors/503.blade.php src/resources/views/auth/login.blade.php src/resources/views/auth/register.blade.php src/resources/views/livewire/organizer/tournament-create.blade.php src/resources/css/hf/_surfaces.scss
git commit -m "feat: add validation summaries and branded error pages"
```

---

# Phase 3 — Matchday surfaces

### Task 11: Dashboard for organizer, coach, and fan

**Files:**
- Modify: `src/resources/views/pages/dashboard.blade.php`
- Modify: `src/resources/views/components/kpi-tile.blade.php`
- Create: `src/resources/js/ui/countup.js`
- Modify: `src/resources/js/app.js`
- Modify: `src/resources/css/hf/_surfaces.scss`
- Modify: `src/resources/css/hf/_data.scss`

**Interfaces:**
- Consumes: `<x-matchday-strip>` from Task 4; `.hf-card--link` from Task 3.
- Produces: `data-countup` support on KPI tiles; one-time count-up for dashboard KPIs; one `<h1>` per role branch.

- [ ] **Step 1: Add count-up support to the KPI component**

In `components/kpi-tile.blade.php`, add the prop:

```blade
@props([
    'label',
    'value',
    'hint' => null,
    'tone' => null,
    'href' => null,
    'testid' => null,
    'countup' => false,
])
```

Change the value element:

```blade
<span class="hf-kpi-value" @if ($countup) data-countup @endif>{{ $value }}</span>
```

- [ ] **Step 2: Add the count-up module**

`src/resources/js/ui/countup.js`:

```js
function animateValue(element) {
    if (element.dataset.counted === 'true') return;
    element.dataset.counted = 'true';

    const original = element.textContent.trim();
    const match = original.match(/-?\d[\d,]*/);
    if (!match || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const start = 0;
    const end = Number(match[0].replace(/,/g, ''));
    if (!Number.isFinite(end) || end === 0) return;

    const prefix = original.slice(0, match.index);
    const suffix = original.slice((match.index ?? 0) + match[0].length);
    const duration = 520;
    const started = performance.now();

    const tick = (now) => {
        const progress = Math.min(1, (now - started) / duration);
        const eased = 1 - Math.pow(1 - progress, 3);
        const value = Math.round(start + (end - start) * eased);
        element.textContent = `${prefix}${value.toLocaleString('en-PH')}${suffix}`;
        if (progress < 1) requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
}

export function initCountUp() {
    document.querySelectorAll('[data-countup]').forEach(animateValue);
}
```

Import and call in `app.js`:

```js
import { initCountUp } from './ui/countup';
initCountUp();
```

- [ ] **Step 3: Replace the organizer header with the matchday strip**

In `pages/dashboard.blade.php`, replace lines 59–76 with:

```blade
@php
    $nextUp = $nextMatch
        ? trim(($nextMatch->roundLabel() ?? 'Next game')
            .' · '.($nextMatch->matchSlots->first()?->start_at?->format('M j, g:i A') ?? 'Time TBD')
            .' · '.($nextMatch->homeTeam?->shortLabel() ?? 'TBD').' vs '.($nextMatch->awayTeam?->shortLabel() ?? 'TBD'))
        : null;
@endphp

<x-matchday-strip
    eyebrow="Matchday desk · organizer"
    title="Run the tournament"
    :status="$tournament?->statusLabel()"
    :description="$tournament ? $tournament->name : 'No active tournament yet. Create one to open the matchday desk.'"
    :meta="$tournament ? [
        ['icon' => 'calendar3', 'label' => $tournament->start_date?->format('M d, Y').' – '.$tournament->end_date?->format('M d, Y')],
        ['icon' => 'geo-alt', 'label' => $tournament->venueName()],
        ['icon' => 'people', 'label' => $tournament->slotCount().' slots · '.$tournament->courtCount().' courts'],
    ] : []"
    :next-up="$nextUp"
>
    <a class="hf-btn hf-btn--primary" href="{{ route('tournamentCreate') }}" data-testid="dashboard-create-tournament"><i class="bi bi-plus-lg" aria-hidden="true"></i> New tournament</a>
    <a class="hf-btn" href="{{ route('submissions') }}" data-testid="dashboard-open-submissions">Review queue</a>
</x-matchday-strip>
```

- [ ] **Step 4: Update the KPI row and remove demo narration**

Replace the KPI block with:

```blade
<div class="hf-grid hf-grid--4 hf-mb-3">
    <x-kpi-tile label="Tournaments" :value="$tournamentsCount" :hint="$tournamentsCount.' in the workspace'" tone="primary" :href="route('tournaments')" testid="kpi-tournaments" :countup="true" />
    <x-kpi-tile label="Pending teams" :value="$pending" hint="Waiting for review" tone="gold" :href="route('submissions', ['status' => 'pending'])" testid="kpi-pending" :countup="true" />
    <x-kpi-tile label="Uncollected" :value="$money($uncollected)" :hint="$unpaidTeams.' teams unpaid'" tone="alert" :href="route('payments', ['status' => 'pending'])" testid="kpi-uncollected" :countup="true" />
    <x-kpi-tile label="Approved teams" :value="$approved" :hint="$registered.' registered'" tone="success" :href="route('teams')" testid="kpi-approved" :countup="true" />
</div>
```

The string `6 teams unpaid` and `₱9,000` remain unchanged for `DashboardTest`.

- [ ] **Step 5: Add the milestone strip and link cards**

Replace the active-tournament progress block (`<div class="hf-round-progress ...">...</div>`) with:

```blade
<div class="hf-milestones hf-mt-2" aria-label="Tournament progress">
    @foreach (['Round of 16', 'Quarterfinals', 'Semifinals', 'Final'] as $index => $milestone)
        @php
            $threshold = ($index + 1) * 25;
            $state = $progress >= $threshold ? 'is-done' : ($progress >= $threshold - 25 ? 'is-active' : '');
        @endphp
        <span class="hf-milestone {{ $state }}">
            <i class="bi bi-{{ $state === 'is-done' ? 'check-circle-fill' : 'circle' }}" aria-hidden="true"></i>
            {{ $milestone }}
        </span>
    @endforeach
</div>
```

Add `hf-card--link` to the three next-action cards:

```blade
<a class="hf-card hf-card--flat hf-card--link" href="..." data-testid="dashboard-action-review">
```

Add to each card after the paragraph:

```blade
<span class="hf-card__arrow" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>
```

- [ ] **Step 6: Replace the coach and fan headers**

For the coach branch, replace lines 191–207 with:

```blade
<x-matchday-strip
    eyebrow="Matchday desk · coach"
    title="Coach corner"
    :status="$registrationLabel"
    :description="$team ? $team->name.' · '.($divisionName ?? 'Division').' · '.$team->city : 'Register a team to start the coach journey.'"
    :meta="$tournament ? [
        ['icon' => 'calendar3', 'label' => $tournament->start_date?->format('M d, Y').' – '.$tournament->end_date?->format('M d, Y')],
        ['icon' => 'geo-alt', 'label' => $tournament->venueName()],
        ['icon' => 'people', 'label' => $tournament->slotCount().' slots · '.$tournament->courtCount().' courts'],
    ] : []"
    :next-up="$coachNextMatch?->roundLabel()"
>
    <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}" data-testid="dashboard-register-cta"><i class="bi bi-trophy" aria-hidden="true"></i> Find open tournament</a>
</x-matchday-strip>
```

For the fan branch, replace lines 291–307 with:

```blade
<x-matchday-strip
    eyebrow="Courtside pass · fan"
    title="Courtside pass"
    :status="$statusLabel($tournament?->status)"
    :description="$tournament?->name ?? 'HoopFlow'"
    :meta="$tournament ? [
        ['icon' => 'calendar3', 'label' => $tournament->start_date?->format('M d, Y').' – '.$tournament->end_date?->format('M d, Y')],
        ['icon' => 'geo-alt', 'label' => $tournament->venueName()],
        ['icon' => 'people', 'label' => $tournament->slotCount().' slots · '.$tournament->courtCount().' courts'],
    ] : []"
    :next-up="$upcomingMatches->first()?->roundLabel()"
>
    <a class="hf-btn hf-btn--primary" href="{{ route('bracket', $tournament ? ['id' => $tournament->id] : []) }}" data-testid="dashboard-open-bracket"><i class="bi bi-diagram-3" aria-hidden="true"></i> Open bracket</a>
</x-matchday-strip>
```

The coach hero and fan hero remain, but their `<h1>` was changed to `<h2 class="hf-hero__name">` in Task 4.

- [ ] **Step 7: Add empty states for announcements**

Replace the organizer announcements block with:

```blade
<div class="hf-grid">
    @forelse ($announcements as $announcement)
        <x-announcement-card :announcement="$announcement" :compact="true" />
    @empty
        <div class="hf-empty hf-empty--compact">
            <i class="bi bi-megaphone" aria-hidden="true"></i>
            <h3>No announcements yet</h3>
            <p>Post a venue, schedule, or eligibility notice for the tournament.</p>
            @if ($role === 'organizer')
                <a class="hf-btn hf-btn--sm" href="{{ route('announcementCreate') }}">New announcement</a>
            @endif
        </div>
    @endforelse
</div>
```

Apply the same `@forelse` pattern to the fan sidebar announcements.

- [ ] **Step 8: Add dashboard styles**

Append to `_surfaces.scss`:

```scss
.hf-milestones {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}

.hf-milestone {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 7px 10px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-radius-md);
  color: var(--hf-muted);
  font-size: 11px;
  font-weight: 700;
}

.hf-milestone.is-active {
  border-color: color-mix(in srgb, var(--hf-primary) 40%, var(--hf-border));
  background: color-mix(in srgb, var(--hf-primary) 7%, transparent);
  color: var(--hf-primary-ink);
}

.hf-milestone.is-done {
  border-color: color-mix(in srgb, var(--hf-success) 40%, var(--hf-border));
  color: var(--hf-success-ink);
}

@media (max-width: 767.98px) {
  .hf-milestones { grid-template-columns: 1fr 1fr; }
}
```

Add to `_components.scss`:

```scss
.hf-empty--compact {
  min-height: 140px;
  padding: 18px 14px;
}

.hf-empty--compact .bi { font-size: 24px; }
.hf-empty--compact h3 { font-size: 18px; }
```

- [ ] **Step 9: Verify dashboard**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --filter=DashboardTest`
Expected: PASS, including `6 teams unpaid`, `₱9,000`, and the coach register/my-team test IDs.

Manual: sign in as organizer, coach, and fan; confirm each dashboard has exactly one `h1`, the matchday strip reads clearly, and KPI numbers count up once.

- [ ] **Step 10: Commit**

```bash
git add src/resources/views/pages/dashboard.blade.php src/resources/views/components/kpi-tile.blade.php src/resources/js/ui/countup.js src/resources/js/app.js src/resources/css/hf/_surfaces.scss src/resources/css/hf/_data.scss
git commit -m "feat: rebuild the dashboard around the matchday strip"
```

---

### Task 12: Bracket page

**Files:**
- Modify: `src/resources/views/livewire/organizer/bracket-show.blade.php`
- Create: `src/resources/js/ui/bracket.js`
- Create: `src/resources/js/ui/score-console.js`
- Modify: `src/resources/js/app.js`
- Modify: `src/resources/css/hf/_data.scss`
- Modify: `src/resources/css/hf/_components.scss`

**Interfaces:**
- Consumes: `<x-matchday-strip>`; existing bracket data `$sections`, `$mobileRounds`, `$totalRounds`; existing score dialog test IDs.
- Produces: `initBracketZoom()`; `initScoreConsole()`; `data-score-console` and `data-score-team` hooks.

- [ ] **Step 1: Re-read the user's uncommitted bracket changes**

Run: `git diff -- src/resources/views/livewire/organizer/bracket-show.blade.php`

Expected: note any local edits. Preserve them; do not revert the file to HEAD.

- [ ] **Step 2: Replace the bracket header**

Replace lines 48–74 with:

```blade
<x-matchday-strip
    eyebrow="{{ $tournament->divisions?->first()?->name ?? $tournament->name }} · {{ $tournament->statusLabel() }}"
    :title="$bracket['hasBracket'] ? 'Bracket' : 'Build the bracket'"
    :description="$bracket['hasBracket']
        ? $tournament->name.' · '.$tournament->formatLabel().' · '.$totalRounds.' rounds'
        : $tournament->name.' · '.$approved->count().' approved teams ready to seed into a '.$tournamentSlots.'-slot '.$tournament->formatLabel().' bracket.'"
    :status="$tournament->statusLabel()"
    :meta="[
        ['icon' => 'geo-alt', 'label' => $tournament->venueName()],
        ['icon' => 'diagram-3', 'label' => $tournament->formatLabel()],
        ['icon' => 'people', 'label' => $approved->count().' approved teams'],
    ]"
>
    <a class="hf-btn" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}" data-testid="bracket-tournament"><i class="bi bi-arrow-left" aria-hidden="true"></i> Tournament</a>
    <a class="hf-btn" href="{{ route('schedule', ['id' => $tournament->id]) }}" data-testid="bracket-schedule"><i class="bi bi-calendar3" aria-hidden="true"></i> Schedule</a>
    <a class="hf-btn" href="{{ route('standings', ['id' => $tournament->id]) }}" data-testid="bracket-standings"><i class="bi bi-bar-chart" aria-hidden="true"></i> Standings</a>
    @if ($isOrganizer && $bracket['hasBracket'] && $kpis['matchesPlayed'] === 0)
        <div class="hf-btn-group">
            <button type="button" class="hf-btn hf-btn--sm" wire:click="requestRegenerate" data-testid="bracket-regenerate">Regenerate bracket</button>
            <button type="button" class="hf-btn hf-btn--sm" wire:click="openEditSlots" data-testid="bracket-edit-slots">Edit slots</button>
        </div>
    @endif
    @if ($isOrganizer && $format === 'round-robin' && $this->canGeneratePlayoffs)
        <button type="button" class="hf-btn hf-btn--primary" wire:click="requestGeneratePlayoffs" data-testid="bracket-generate-playoffs" wire:loading.class="is-loading" wire:loading.attr="aria-busy">
            Generate playoffs
        </button>
    @endif
</x-matchday-strip>
```

- [ ] **Step 3: Add zoom controls and champion treatment**

Before the desktop tree wrapper, add:

```blade
<div class="hf-bracket-toolbar">
    <span class="hf-small hf-muted">{{ $totalRounds }} rounds · {{ $approved->count() }} teams</span>
    <div class="hf-btn-group" aria-label="Bracket zoom">
        <button type="button" class="hf-btn hf-btn--sm" data-bracket-zoom="out" aria-label="Zoom out"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
        <button type="button" class="hf-btn hf-btn--sm" data-bracket-zoom="reset">Fit</button>
        <button type="button" class="hf-btn hf-btn--sm" data-bracket-zoom="in" aria-label="Zoom in"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
    </div>
</div>
```

Add `data-bracket-canvas` to the `[data-testid="bracket-tree"]` container.

In the champion card, add the class `hf-champion` and the road line:

```blade
<section class="hf-hero hf-champion hf-mb-3" data-testid="bracket-champion">
    <span class="hf-eyebrow hf-eyebrow--on-dark">Champion</span>
    <h2 class="hf-hero__name">{{ $bracket['champion']->name }}</h2>
    <p>Road to the title · {{ $totalRounds }} rounds · {{ $tournament->name }}</p>
</section>
```

Keep the existing champion markup and test ID; only add the class and the road line.

- [ ] **Step 4: Mark the score console for keyboard shortcuts**

In both score dialogs (`bracket-show.blade.php` and `game-stats-show.blade.php`), add to the `<dialog>`:

```blade
data-score-console
```

Add to each team panel:

```blade
data-score-team="1"
```

and

```blade
data-score-team="2"
```

Add `tabindex="-1"` to the dialog.

- [ ] **Step 5: Create the bracket zoom module**

`src/resources/js/ui/bracket.js`:

```js
export function initBracketZoom() {
    const canvas = document.querySelector('[data-bracket-canvas]');
    if (!canvas) return;

    let scale = 1;
    const apply = () => {
        canvas.style.transform = `scale(${scale})`;
        canvas.style.transformOrigin = 'top left';
    };

    document.querySelectorAll('[data-bracket-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.bracketZoom;
            if (action === 'in') scale = Math.min(1.6, scale + 0.1);
            if (action === 'out') scale = Math.max(0.6, scale - 0.1);
            if (action === 'reset') scale = 1;
            apply();
        });
    });
}
```

- [ ] **Step 6: Create the score console module**

`src/resources/js/ui/score-console.js`:

```js
export function initScoreConsole() {
    document.addEventListener('keydown', (event) => {
        const dialog = document.querySelector('dialog[data-score-console][open]');
        if (!dialog) return;
        if (event.target.matches('input, select, textarea')) return;

        const active = dialog.querySelector('[data-score-team].is-active') ?? dialog.querySelector('[data-score-team="1"]');

        if (['1', '2', '3'].includes(event.key) && active) {
            event.preventDefault();
            active.querySelector(`[data-points="${event.key}"]`)?.click();
        }

        if (event.key.toLowerCase() === 'u') {
            event.preventDefault();
            dialog.querySelector('[data-testid="score-undo"]')?.click();
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            dialog.querySelector('[data-testid="score-finalize"]')?.click();
        }

        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            const teams = [...dialog.querySelectorAll('[data-score-team]')];
            const index = teams.indexOf(active);
            const next = event.key === 'ArrowRight' ? (index + 1) % teams.length : (index - 1 + teams.length) % teams.length;
            teams.forEach((team) => team.classList.remove('is-active'));
            teams[next]?.classList.add('is-active');
        }
    });

    document.addEventListener('click', (event) => {
        const panel = event.target.closest('dialog[data-score-console] [data-score-team]');
        if (!panel) return;
        panel.closest('dialog').querySelectorAll('[data-score-team]').forEach((team) => team.classList.remove('is-active'));
        panel.classList.add('is-active');
    });
}
```

- [ ] **Step 7: Add bracket and score console styles**

Append to `_data.scss`:

```scss
.hf-bracket-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}

.hf-bracket [data-bracket-canvas] {
  transition: transform var(--hf-dur-standard) var(--hf-ease-out);
}

.hf-bracket-round-head {
  position: sticky;
  top: 72px;
  z-index: 5;
  padding: 6px 0;
  background: var(--hf-ground);
}

.hf-bracket-path,
.hf-bracket-connector {
  stroke: color-mix(in srgb, var(--hf-ink) 45%, transparent);
  stroke-width: 1.5;
}

.hf-champion {
  border-color: color-mix(in srgb, var(--hf-gold) 55%, transparent);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--hf-gold) 25%, transparent), 0 12px 40px color-mix(in srgb, var(--hf-gold) 16%, transparent);
}
```

Append to `_components.scss`:

```scss
[data-score-team] {
  cursor: pointer;
  transition: border-color var(--hf-dur-micro) linear, box-shadow var(--hf-dur-micro) linear;
}

[data-score-team].is-active {
  border-color: var(--hf-primary);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--hf-primary) 28%, transparent);
}

[data-score-team].is-active [data-score-display] {
  animation: hf-score-pop var(--hf-dur-expressive) var(--hf-ease-out);
}

@keyframes hf-score-pop {
  0% { transform: scale(1); }
  45% { transform: scale(1.12); }
  100% { transform: scale(1); }
}
```

- [ ] **Step 8: Import the modules**

In `app.js`:

```js
import { initBracketZoom } from './ui/bracket';
import { initScoreConsole } from './ui/score-console';
initBracketZoom();
initScoreConsole();
```

- [ ] **Step 9: Verify bracket**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --filter=BracketTest`
Expected: PASS. Pay special attention to `Finalize score?`, `Record forfeit?`, `Apply correction?`, `Generate bracket?`, `Correct score`, `Open scoring`, `View box score`, round labels, and every `bracket-*` test ID.

Manual: open a score dialog, press `1`, `2`, `3`, `U`, arrow keys, and `Enter`; confirm the same actions fire as clicking.

- [ ] **Step 10: Commit**

```bash
git add src/resources/views/livewire/organizer/bracket-show.blade.php src/resources/views/livewire/organizer/game-stats-show.blade.php src/resources/js/ui/bracket.js src/resources/js/ui/score-console.js src/resources/js/app.js src/resources/css/hf/_data.scss src/resources/css/hf/_components.scss
git commit -m "feat: redesign the bracket and add score console shortcuts"
```

---

### Task 13: Schedule and matchups

**Files:**
- Modify: `src/resources/views/livewire/organizer/schedule-show.blade.php`
- Modify: `src/resources/views/livewire/organizer/matches-index.blade.php`
- Create: `src/resources/js/ui/calendar.js`
- Modify: `src/resources/js/app.js`
- Modify: `src/resources/css/hf/_data.scss`
- Modify: `src/resources/css/hf/_print.scss`

**Interfaces:**
- Consumes: `<x-matchday-strip>`; the existing calendar cells and `wire:click="selectDay"`.
- Produces: `initCalendar()`; `is-today` and `is-legend` calendar styles; print action.

- [ ] **Step 1: Replace the schedule header**

Replace the header block (lines 40–59) with:

```blade
<x-matchday-strip
    eyebrow="{{ $tournament->divisions?->first()?->name ?? 'Tournament' }} · {{ $this->venueName }}"
    title="Court schedule"
    :status="$this->hasScheduledGames ? 'Published' : 'Not published'"
    :description="$tournament->start_date?->format('M d, Y').' to '.$tournament->end_date?->format('M d, Y').' · '.$courts->count().' courts · '.$this->slotMinutes.'-minute slots with '.$this->restMinutes.' minutes of rest.'"
    :meta="[
        ['icon' => 'calendar3', 'label' => $this->matchDayCount.' matchdays'],
        ['icon' => 'clock', 'label' => $this->slotMinutes.'-minute slots'],
        ['icon' => 'geo-alt', 'label' => $this->venueName],
    ]"
>
    <a class="hf-btn" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}" data-testid="schedule-tournament"><i class="bi bi-arrow-left" aria-hidden="true"></i> Tournament</a>
    <a class="hf-btn" href="{{ route('bracket', ['id' => $tournament->id]) }}" data-testid="schedule-bracket"><i class="bi bi-diagram-3" aria-hidden="true"></i> Bracket</a>
    <a class="hf-btn" href="{{ route('matches', ['id' => $tournament->id]) }}" data-testid="schedule-matches"><i class="bi bi-list-ul" aria-hidden="true"></i> Matchups</a>
    <button type="button" class="hf-btn" data-print-page><i class="bi bi-printer" aria-hidden="true"></i> Print</button>
    @if ($this->isOrganizer && $this->hasScheduledGames && $this->resultsCount === 0)
        <button type="button" class="hf-btn" wire:click="openRebuild" data-testid="schedule-rebuild" wire:loading.attr="disabled" wire:loading.class="is-loading">Rebuild schedule</button>
    @endif
    @if ($this->isOrganizer && $this->hasScheduledGames)
        <button type="button" class="hf-btn" wire:click="openReschedule" data-testid="schedule-reschedule" wire:loading.attr="disabled" wire:loading.class="is-loading">Reschedule match</button>
    @endif
</x-matchday-strip>
```

- [ ] **Step 2: Add today and hover preview to calendar cells**

In the calendar cell class list, add `{{ $cell['key'] === now()->toDateString() ? ' is-today' : '' }}`.

Add a `title` attribute:

```blade
title="{{ \Carbon\Carbon::parse($cell['key'])->format('l, F j') }}{{ $cell['count'] ? ' · '.$cell['count'].' games' : ' · no games' }}{{ $cell['reserved'] ? ' · reserved for playoffs' : '' }}"
```

Add `tabindex="0"` to each cell and `data-calendar-day` for arrow-key navigation.

- [ ] **Step 3: Add the calendar legend**

After the calendar grid and before the week strip, add:

```blade
<div class="hf-calendar-legend" aria-label="Calendar legend">
    <span><i class="hf-legend-dot hf-legend-dot--today" aria-hidden="true"></i> Today</span>
    <span><i class="hf-legend-dot hf-legend-dot--event" aria-hidden="true"></i> Games</span>
    <span><i class="hf-legend-dot hf-legend-dot--reserved" aria-hidden="true"></i> Playoffs reserved</span>
</div>
```

- [ ] **Step 4: Add the matchday strip to matchups**

Replace the matchups header (lines 20–30) with:

```blade
<x-matchday-strip
    eyebrow="{{ $tournament->divisions->first()?->name ?? $tournament->name }} · {{ $tournament->name }}"
    title="Matchups"
    :status="$tournament->statusLabel()"
    description="Every bracket game grouped by matchday, with court, status, and a link to the official box score."
    :meta="[
        ['icon' => 'calendar3', 'label' => $groups->count().' matchdays'],
        ['icon' => 'list-ul', 'label' => $matches->count().' games'],
    ]"
>
    <a class="hf-btn" href="{{ route('schedule', ['id' => $tournament->id]) }}" data-testid="matches-schedule"><i class="bi bi-calendar3" aria-hidden="true"></i> Schedule</a>
    <a class="hf-btn" href="{{ route('bracket', ['id' => $tournament->id]) }}" data-testid="matches-bracket"><i class="bi bi-diagram-3" aria-hidden="true"></i> Bracket</a>
    <button type="button" class="hf-btn" data-print-page><i class="bi bi-printer" aria-hidden="true"></i> Print</button>
</x-matchday-strip>
```

Keep the exact description string `Every bracket game grouped by matchday, with court, status, and a link to the official box score.`; `MatchesTest` asserts it.

- [ ] **Step 5: Create the calendar keyboard module**

`src/resources/js/ui/calendar.js`:

```js
export function initCalendar() {
    document.querySelectorAll('[data-testid="schedule-calendar"]').forEach((calendar) => {
        const cells = [...calendar.querySelectorAll('[data-calendar-day]')];
        if (!cells.length) return;

        calendar.addEventListener('keydown', (event) => {
            const index = cells.indexOf(document.activeElement);
            if (index === -1) return;

            const columns = 7;
            let next = index;
            if (event.key === 'ArrowRight') next = index + 1;
            if (event.key === 'ArrowLeft') next = index - 1;
            if (event.key === 'ArrowDown') next = index + columns;
            if (event.key === 'ArrowUp') next = index - columns;
            if (next < 0 || next >= cells.length) return;

            event.preventDefault();
            cells[next].focus();
        });
    });

    document.querySelectorAll('[data-print-page]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });
}
```

- [ ] **Step 6: Add schedule styles**

Append to `_data.scss`:

```scss
.hf-calendar-cell.is-today {
  border-color: var(--hf-primary);
  box-shadow: inset 0 0 0 1px var(--hf-primary);
}

.hf-calendar-cell.is-today > span:first-child {
  color: var(--hf-primary-ink);
  font-weight: 800;
}

.hf-calendar-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  margin-top: 10px;
  color: var(--hf-muted);
  font-size: 11px;
}

.hf-calendar-legend span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.hf-legend-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
}

.hf-legend-dot--today { background: var(--hf-primary); }
.hf-legend-dot--event { background: var(--hf-success); }
.hf-legend-dot--reserved { background: var(--hf-gold); }
```

- [ ] **Step 7: Add print rules**

Write `_print.scss`:

```scss
@media print {
  .hf-rail,
  .hf-topbar,
  .hf-bottom-nav,
  .hf-toast-region,
  .hf-progress,
  .hf-offline,
  .hf-page-actions,
  .hf-matchday__actions,
  .hf-btn,
  .hf-calendar,
  .hf-week-strip,
  .hf-filter-row {
    display: none !important;
  }

  .hf-app,
  .hf-main {
    display: block !important;
    max-width: none !important;
    padding: 0 !important;
  }

  body {
    background: #fff !important;
    color: #000 !important;
  }

  .hf-card,
  .hf-matchday,
  .hf-bracket,
  .hf-table {
    border-color: #bbb !important;
    box-shadow: none !important;
    break-inside: avoid;
  }

  .hf-bracket {
    overflow: visible !important;
  }

  a[href]::after {
    content: '';
  }
}
```

- [ ] **Step 8: Verify schedule and matchups**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --filter="ScheduleTest|MatchesTest"`
Expected: PASS. Keep `Court filters`, `Reschedule match`, `Matchups`, `Manual manage` absence, `Schedule remaining games`, `Build preview`, `Settings changed - rebuild the preview before publishing.`, `Rebuild schedule`, `replace the current schedule`, and every `schedule-*` test ID.

Manual: arrow-key through the calendar; print preview shows only the bracket/schedule content.

- [ ] **Step 9: Commit**

```bash
git add src/resources/views/livewire/organizer/schedule-show.blade.php src/resources/views/livewire/organizer/matches-index.blade.php src/resources/js/ui/calendar.js src/resources/js/app.js src/resources/css/hf/_data.scss src/resources/css/hf/_print.scss
git commit -m "feat: redesign the schedule and matchups with calendar controls"
```

---

### Task 14: Game stats and game management

**Files:**
- Modify: `src/resources/views/livewire/organizer/game-stats-show.blade.php`
- Modify: `src/resources/views/livewire/organizer/game-manage.blade.php`
- Create: `src/resources/js/ui/scorekeeper.js`
- Modify: `src/resources/js/app.js`
- Modify: `src/resources/css/hf/_components.scss`
- Modify: `src/resources/css/hf/_data.scss`

**Interfaces:**
- Consumes: `<x-matchday-strip>`; score console hooks from Task 12.
- Produces: `initScorekeeper()`; `data-scorekeeper-toggle`; `data-scorekeeper` on `<html>`; sticky action bar; unsaved-change warning.

- [ ] **Step 1: Replace the game-stats header with a broadcast scoreboard**

Replace the header block (lines 48–120) with a matchday strip plus a scoreboard card. Keep the exact asserted summary string intact:

```blade
<x-matchday-strip
    eyebrow="{{ $match->roundLabel() }} · {{ $match->statusLabel() }}"
    title="Game stats"
    :status="$match->statusLabel()"
    :description="$match->matchSlots->first()?->court?->name ?? $tournament->name"
    :meta="[
        ['icon' => 'calendar3', 'label' => $match->matchSlots->first()?->start_at?->format('M d, Y · g:i A') ?? 'Time TBD'],
        ['icon' => 'geo-alt', 'label' => $match->matchSlots->first()?->court?->name ?? 'Court TBD'],
        ['icon' => 'trophy', 'label' => $tournament->name],
    ]"
>
    <a class="hf-btn" href="{{ route('bracket', ['id' => $match->tournament_id, 'match' => $match->id]) }}" data-testid="stats-bracket"><i class="bi bi-diagram-3" aria-hidden="true"></i> Bracket</a>
    <a class="hf-btn" href="{{ route('schedule', ['id' => $match->tournament_id]) }}" data-testid="stats-schedule"><i class="bi bi-calendar3" aria-hidden="true"></i> Schedule</a>
    <a class="hf-btn" href="{{ route('matches', ['id' => $match->tournament_id]) }}" data-testid="stats-matches"><i class="bi bi-list-ul" aria-hidden="true"></i> Matchups</a>
</x-matchday-strip>

<section class="hf-scoreboard hf-mb-3" data-testid="scoreboard-{{ $match->id }}">
    <div class="hf-scoreboard__team">
        <x-team-shield :team="$home" size="lg" />
        <strong class="hf-team-name">{{ $home?->name ?? 'TBD' }}</strong>
    </div>
    <div class="hf-scoreboard__score">
        <span class="hf-score">{{ $match->home_score ?? '–' }}</span>
        <span class="hf-scoreboard__dash">–</span>
        <span class="hf-score">{{ $match->away_score ?? '–' }}</span>
        <span class="hf-scoreboard__meta">{{ $match->roundLabel() }} · {{ $match->statusLabel() }}</span>
    </div>
    <div class="hf-scoreboard__team hf-scoreboard__team--away">
        <x-team-shield :team="$away" size="lg" />
        <strong class="hf-team-name">{{ $away?->name ?? 'TBD' }}</strong>
    </div>
</section>
```

Keep the asserted copy `Quarterfinals · Court 1 (Main Gym) · May 18, 2026 at 1:00 PM.` exactly where it currently appears. Do not replace that line with the strip description.

- [ ] **Step 2: Add the sticky action bar to game stats**

Wrap the existing correction/enter-score actions in:

```blade
<div class="hf-sticky-actions" data-testid="stats-controls">
    ... existing controls ...
</div>
```

Keep `data-testid="stats-controls"` and `data-testid="stats-correction"`.

- [ ] **Step 3: Add scorekeeper mode to game management**

In the game-manage header, add:

```blade
<button type="button" class="hf-btn" data-scorekeeper-toggle aria-pressed="false">
    <i class="bi bi-arrows-fullscreen" aria-hidden="true"></i> Scorekeeper mode
</button>
```

Add the same button to the game-stats header actions if the visitor is an organizer.

Wrap the save/publish/correct actions in `game-manage.blade.php` with:

```blade
<div class="hf-sticky-actions" data-testid="ingame-actions">
    ... existing actions ...
</div>
```

Keep `data-testid="ingame-actions"`, `ingame-save`, `ingame-publish`, `ingame-cancel`, `ingame-correct`, and the correction test IDs.

- [ ] **Step 4: Create the scorekeeper module**

`src/resources/js/ui/scorekeeper.js`:

```js
const MODE_KEY = 'hf-scorekeeper';

function setMode(enabled) {
    document.documentElement.dataset.scorekeeper = enabled ? 'on' : 'off';
    sessionStorage.setItem(MODE_KEY, enabled ? 'on' : 'off');
    document.querySelectorAll('[data-scorekeeper-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(enabled));
    });
}

export function initScorekeeper() {
    const enabled = sessionStorage.getItem(MODE_KEY) === 'on';
    if (document.querySelector('[data-scorekeeper-toggle]')) setMode(enabled);

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-scorekeeper-toggle]');
        if (!toggle) return;
        setMode(toggle.getAttribute('aria-pressed') !== 'true');
    });

    const form = document.querySelector('[data-testid="ingame-actions"]');
    if (!form) return;

    let dirty = false;
    const markDirty = () => { dirty = true; };

    document.querySelectorAll('[data-testid^="ingame-q-"], [data-testid^="ingame-final-"], [data-testid^="ingame-stat-"]').forEach((input) => {
        input.addEventListener('input', markDirty);
    });

    document.querySelectorAll('[data-testid="ingame-save"], [data-testid="ingame-publish"], [data-testid="ingame-save-correction"]').forEach((button) => {
        button.addEventListener('click', () => { dirty = false; });
    });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
}
```

- [ ] **Step 5: Add scoreboard, sticky bar, and scorekeeper styles**

Append to `_data.scss`:

```scss
.hf-scoreboard {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: center;
  gap: 18px;
  padding: 22px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-radius-xl);
  background: var(--hf-surface);
  box-shadow: var(--hf-shadow-rest);
}

.hf-scoreboard__team {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hf-scoreboard__team--away {
  justify-content: flex-end;
  text-align: right;
}

.hf-scoreboard__score {
  display: grid;
  justify-items: center;
  gap: 2px;
}

.hf-scoreboard__score .hf-score {
  font-size: clamp(42px, 7vw, 82px);
}

.hf-scoreboard__dash {
  display: none;
}

.hf-scoreboard__meta {
  color: var(--hf-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

@media (max-width: 767.98px) {
  .hf-scoreboard {
    grid-template-columns: 1fr;
    justify-items: center;
    text-align: center;
  }

  .hf-scoreboard__team,
  .hf-scoreboard__team--away {
    justify-content: center;
    text-align: center;
  }
}
```

Append to `_components.scss`:

```scss
.hf-sticky-actions {
  position: sticky;
  bottom: 12px;
  z-index: 20;
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
  padding: 10px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-radius-lg);
  background: color-mix(in srgb, var(--hf-surface-2) 92%, transparent);
  box-shadow: var(--hf-shadow-overlay);
  backdrop-filter: blur(8px);
}

[data-scorekeeper='on'] .hf-rail,
[data-scorekeeper='on'] .hf-topbar,
[data-scorekeeper='on'] .hf-bottom-nav {
  display: none !important;
}

[data-scorekeeper='on'] .hf-app {
  display: block;
}

[data-scorekeeper='on'] .hf-main {
  max-width: none;
  padding: 10px;
}

[data-scorekeeper='on'] .hf-input,
[data-scorekeeper='on'] .hf-btn {
  min-height: 52px;
  font-size: 16px;
}

[data-scorekeeper='on'] .hf-sticky-actions {
  bottom: 0;
  border-radius: 0;
}
```

- [ ] **Step 6: Import the module**

In `app.js`:

```js
import { initScorekeeper } from './ui/scorekeeper';
initScorekeeper();
```

- [ ] **Step 7: Verify game surfaces**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --filter="GameStatsTest|GameManageTest"`
Expected: PASS. Preserve `Select a match to manage`, `In-game score & player stats`, `Locked result`, `Game 2 of 3`, `Available after Game 2 is final`, `Player points 6 · Final 78 · 72 unassigned`, and every `ingame-*` test ID.

Manual: toggle Scorekeeper mode, confirm the rail/topbar disappear and the controls grow; edit a score, navigate away, confirm the browser asks before leaving.

- [ ] **Step 8: Commit**

```bash
git add src/resources/views/livewire/organizer/game-stats-show.blade.php src/resources/views/livewire/organizer/game-manage.blade.php src/resources/js/ui/scorekeeper.js src/resources/js/app.js src/resources/css/hf/_components.scss src/resources/css/hf/_data.scss
git commit -m "feat: add broadcast game surfaces and scorekeeper mode"
```

---

### Task 15: Standings and public pages

**Files:**
- Modify: `src/resources/views/livewire/public/standings-show.blade.php`
- Modify: `src/resources/views/pages/standings.blade.php`
- Modify: `src/resources/views/pages/tournaments/show.blade.php`
- Modify: `src/resources/views/pages/teams/show.blade.php`
- Modify: `src/resources/views/layouts/public.blade.php`
- Modify: `src/resources/views/components/standings-table.blade.php` (only if it needs the sticky column class)
- Modify: `src/resources/css/hf/_data.scss`
- Modify: `src/resources/css/hf/_surfaces.scss`
- Modify: `src/resources/css/hf/_surfaces.scss`

**Interfaces:**
- Consumes: `<x-matchday-strip>`.
- Produces: sticky standings team column, qualification line, seed badges, public matchday treatment, meta-description slot.

- [ ] **Step 1: Add the meta-description slot**

In `layouts/public.blade.php`, after `<title>`:

```blade
@hasSection('meta-description')
    <meta name="description" content="@yield('meta-description')">
@endif
```

- [ ] **Step 2: Replace the standings header**

In `livewire/public/standings-show.blade.php`, replace the header (lines 32–51) with:

```blade
<x-matchday-strip
    eyebrow="{{ $divisionName }} · {{ $tournament->name }}"
    title="Standings"
    :status="$cut ? 'Top '.$cut.' qualify' : null"
    description="Wins, losses, point differential, and the playoff cut — computed from every recorded result."
    :meta="[
        ['icon' => 'trophy', 'label' => $kpis['leader_short'].' leads'],
        ['icon' => 'bar-chart', 'label' => $kpis['results'].' results'],
        ['icon' => 'people', 'label' => $kpis['qualified'].' above the cut'],
    ]"
>
    <label class="hf-sr-only" for="standingsTournament">Tournament</label>
    <select class="hf-select" id="standingsTournament" wire:change="changeTournament($event.target.value)" data-testid="standings-tournament">
        @foreach ($this->tournaments as $tournamentOption)
            <option value="{{ $tournamentOption->id }}" @selected($tournamentOption->id === $tournament->id)>{{ $tournamentOption->name }}</option>
        @endforeach
    </select>
    <a class="hf-btn" href="{{ route('bracket', ['id' => $tournament->id]) }}" data-testid="standings-bracket"><i class="bi bi-diagram-3" aria-hidden="true"></i> Bracket</a>
    <a class="hf-btn" href="{{ route('matches', ['id' => $tournament->id]) }}" data-testid="standings-matches"><i class="bi bi-list-ul" aria-hidden="true"></i> Matchups</a>
</x-matchday-strip>
```

Keep every `standings-*` test ID and the string `Playoff cut`.

- [ ] **Step 3: Add sticky column and qualification styling**

In `_data.scss`, add:

```scss
.hf-table--standings th:nth-child(2),
.hf-table--standings td:nth-child(2) {
  position: sticky;
  left: 0;
  z-index: 1;
  background: var(--hf-surface-2);
}

.hf-table--standings .hf-qual-line td {
  height: 3px;
  padding: 0;
  background: var(--hf-gold);
}

.hf-table--standings tr.is-qualified td:first-child {
  box-shadow: inset 3px 0 0 var(--hf-gold);
}

.hf-standings-cards {
  display: none;
}

@media (max-width: 767.98px) {
  .hf-table--standings { display: none; }
  .hf-standings-cards { display: grid; gap: 8px; }
}
```

If `.hf-standings-cards` already exists, keep its current rules and only add the display switch.

- [ ] **Step 4: Add the public tournament matchday treatment**

In `pages/tournaments/show.blade.php`, replace the opening hero with:

```blade
<x-matchday-strip
    eyebrow="{{ $tournament->divisions?->first()?->name ?? 'Division' }} · {{ $tournament->statusLabel() }}"
    :title="$tournament->name"
    :description="$tournament->description"
    :meta="[
        ['icon' => 'calendar3', 'label' => $tournament->start_date.' – '.$tournament->end_date],
        ['icon' => 'cash', 'label' => number_format($tournament->divisions?->first()?->entry_fee ?? 0, 2).' entry'],
        ['icon' => 'diagram-3', 'label' => 'Single elimination'],
        ['icon' => 'people', 'label' => ($tournament->divisions?->first()?->max_teams ?? ($tournament->rules_json['slots'] ?? 0)).' slots'],
    ]"
>
    <a class="hf-btn hf-btn--primary" href="{{ route('bracket', ['id' => $tournament->id]) }}">Bracket</a>
    <a class="hf-btn" href="{{ route('schedule', ['id' => $tournament->id]) }}">Schedule</a>
    <a class="hf-btn" href="{{ route('standings', ['id' => $tournament->id]) }}">Standings</a>
    @if(auth()->user()?->hasRole('organizer'))
        <a class="hf-btn" href="{{ route('announcementCreate', ['id' => $tournament->id]) }}">Announce</a>
    @endif
</x-matchday-strip>
```

Add the meta description:

```blade
@section('meta-description', $tournament->name.' · '.$tournament->venueName().' · '.$tournament->start_date.' to '.$tournament->end_date.'. Bracket, schedule, standings, and announcements.')
```

Keep `Team list`, `Announcements`, `Open bracket`, and `Schedule preview` strings for `TournamentsTest`.

- [ ] **Step 5: Add the public team hero treatment**

In `pages/teams/show.blade.php`, add the meta description:

```blade
@section('meta-description', $team->name.' · '.$team->city.' · '.($tournament?->name ?? 'HoopFlow').' team profile, roster, results, and bracket position.')
```

Keep the existing `hf-page-head`, `team-hero`, `Public profile`, `Registration timeline`, and all `team-*` test IDs. Add the class `hf-team-hero` to the hero section and the class `hf-table--roster` to the roster table for the new sticky header.

- [ ] **Step 6: Replace the simple public standings page**

In `pages/standings.blade.php`, replace the header (lines 7–19) with:

```blade
<x-matchday-strip
    eyebrow="{{ $tournament?->divisions?->first()?->name ?? 'Division' }} · {{ $tournament?->name ?? 'Standings' }}"
    title="Standings"
    description="Wins, losses, point differential, and the playoff cut — computed from every recorded result."
>
    @if ($tournament)
        <a class="hf-btn" href="{{ route('bracket', ['id' => $tournament->id]) }}"><i class="bi bi-diagram-3" aria-hidden="true"></i> Bracket</a>
        <a class="hf-btn" href="{{ route('matches', ['id' => $tournament->id]) }}"><i class="bi bi-list-ul" aria-hidden="true"></i> Matchups</a>
    @endif
</x-matchday-strip>
```

Add the `hf-table--standings` class and `scope="col"` to every `<th>`.

- [ ] **Step 7: Add public surface styles**

Append to `_surfaces.scss`:

```scss
.hf-team-hero .hf-stat-strip {
  margin-top: 20px;
}

.hf-page > .hf-matchday:first-child {
  margin-top: 4px;
}

@media (max-width: 767.98px) {
  .hf-matchday__title {
    font-size: 2.4rem;
  }
}
```

- [ ] **Step 8: Verify public pages**

Run: `npm run build`
Expected: build succeeds.

Run: `php artisan test --filter="StandingsTest|TournamentsTest|TeamsTest|LandingTest"`
Expected: PASS. Keep `Team list`, `Schedule preview`, `Open bracket`, `Public profile`, `Team not available`, `Back to public teams`, `Paid`, and the standings `Playoff cut` copy.

Manual: open a public tournament, bracket, schedule, and standings page as a fan; confirm the matchday treatment and meta description.

- [ ] **Step 9: Commit**

```bash
git add src/resources/views/livewire/public/standings-show.blade.php src/resources/views/pages/standings.blade.php src/resources/views/pages/tournaments/show.blade.php src/resources/views/pages/teams/show.blade.php src/resources/views/layouts/public.blade.php src/resources/views/components/standings-table.blade.php src/resources/css/hf/_data.scss src/resources/css/hf/_surfaces.scss
git commit -m "feat: bring standings and public pages into the matchday broadcast"
```

---

# Phase 4 — Desk surfaces

These are the calm, dense surfaces. Keep the dark matchday strip for the tournament desk only;
directories, queues, ledgers, settings, and auth use the light paper surface, compact filters, and
clear rows. Every task in this phase is presentation-only.

### Task 16: Tournament directory, tournament desk, and create wizard

**Files:**
- Modify: `src/resources/views/livewire/organizer/tournaments-index.blade.php`
- Modify: `src/resources/views/livewire/organizer/tournament-show.blade.php`
- Modify: `src/resources/views/livewire/organizer/tournament-create.blade.php`
- Modify: `src/resources/css/hf/_components.scss`
- Modify: `src/resources/css/hf/_surfaces.scss`

**Interfaces:**
- Consumes: `<x-matchday-strip>`, `<x-breadcrumbs>`, `<x-form-errors>`, `.hf-card--link`, `.hf-callout`.
- Produces: `.hf-filter-bar`, `.hf-filter-bar__search`, `.hf-select--compact`, `.hf-results-count`,
  `.hf-wizard`, `.hf-wizard__summary`, `.hf-summary-list`, `.hf-meter`.

- [ ] **Step 1: Preserve local edits before touching the tournament views**

Run:

```powershell
git diff -- src/resources/views/livewire/organizer/tournament-show.blade.php src/resources/views/livewire/organizer/tournament-create.blade.php
```

Expected: the user's uncommitted work appears. Read it and adapt the snippets below to that structure.
Do not revert either file to `HEAD`.

- [ ] **Step 2: Replace the tournament directory header and filter card**

In `tournaments-index.blade.php`, add breadcrumbs after the `@php` block and replace the header:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Tournaments'],
    ]" />
@endsection

<header class="hf-page-head hf-mb-3">
    <div>
        <span class="hf-eyebrow">Tournament desk</span>
        <h1 class="hf-page-title">Tournaments</h1>
        <p class="hf-page-desc">Plan registration, run matchday, and archive every competition from one desk.</p>
    </div>
    <div class="hf-page-actions">
        @if ($role === 'organizer')
            <a class="hf-btn hf-btn--primary" href="{{ route('tournamentCreate') }}" data-testid="tournaments-create"><i class="bi bi-plus-lg" aria-hidden="true"></i> New tournament</a>
        @else
            <a class="hf-btn" href="{{ route('dashboard') }}">Back to dashboard</a>
        @endif
    </div>
</header>
```

Replace the filter card with:

```blade
<div class="hf-filter-bar hf-mb-3">
    <div class="hf-filter-bar__search">
        <label class="hf-sr-only" for="tournamentSearch">Search tournaments</label>
        <i class="bi bi-search" aria-hidden="true"></i>
        <input class="hf-input" id="tournamentSearch" type="search" placeholder="Name, venue, or division"
               wire:model.live.debounce.200ms="search" data-testid="tournament-search">
    </div>
    <div class="hf-field">
        <label class="hf-sr-only" for="tournamentStatus">Status</label>
        <select class="hf-select hf-select--compact" id="tournamentStatus" wire:model.live="status" data-testid="tournament-status-filter">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
            <option value="registration-open">Registration open</option>
            <option value="upcoming">Upcoming</option>
            <option value="draft">Draft</option>
        </select>
    </div>
    <div class="hf-field">
        <label class="hf-sr-only" for="tournamentDivision">Division</label>
        <select class="hf-select hf-select--compact" id="tournamentDivision" wire:model.live="division" data-testid="tournament-division-filter">
            <option value="">All divisions</option>
            @foreach ($this->divisions as $divisionName)
                <option value="{{ $divisionName }}">{{ $divisionName }}</option>
            @endforeach
        </select>
    </div>
    @if ($search !== '' || $status !== '' || $division !== '')
        <button type="button" class="hf-btn hf-btn--sm" wire:click="clearFilters" data-testid="tournament-clear-filters-top">
            <i class="bi bi-x-lg" aria-hidden="true"></i> Clear
        </button>
    @endif
</div>
```

Add a result count above `#tournamentList`:

```blade
<div class="hf-between hf-mb-2">
    <p class="hf-results-count hf-mb-0">{{ $this->tournaments->count() }} {{ \Illuminate\Support\Str::plural('tournament', $this->tournaments->count()) }}</p>
    <span class="hf-small hf-muted">Sorted by start date</span>
</div>
```

- [ ] **Step 3: Tighten the tournament card**

In the `@forelse` card, make the title a link-safe heading with an arrow and replace the inline
`font-size: 32px` value with a class:

```blade
<div class="hf-between">
    <div class="hf-min-w-0">
        <span class="hf-flex hf-wrap hf-gap-1">
            <x-status-chip :status="$tournament->status" />
            <span class="hf-chip hf-chip--slate">{{ $division?->name ?? 'Division' }}</span>
            @if ($seedLocked)
                <span class="hf-chip hf-chip--gold">Seeds locked</span>
            @endif
        </span>
        <h2 class="hf-section-title hf-mt-1 hf-mb-0">{{ $tournament->name }}</h2>
        <p class="hf-small hf-muted hf-mb-0">{{ $tournament->venueName() }} · {{ $tournament->start_date?->format('M d, Y') ?? 'TBD' }} to {{ $tournament->end_date?->format('M d, Y') ?? 'TBD' }}</p>
    </div>
    <div class="hf-text-right">
        <span class="hf-kpi-label">Teams</span>
        <strong class="hf-kpi-value hf-kpi-value--sm">{{ $approved }}/{{ $tournament->slotCount() }}</strong>
    </div>
</div>
```

Add `wire:loading.class="is-updating"` and `wire:loading.attr="aria-busy"` to `#tournamentList`.
Add `wire:loading.class="is-loading"` to `wire:click="clearFilters"` buttons.

- [ ] **Step 4: Give the tournament desk a matchday strip**

In `tournament-show.blade.php`, add breadcrumbs after the `@php` block and replace the
`<section class="hf-hero">` block with:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Tournaments', 'href' => route('tournaments')],
        ['label' => $tournament->name],
    ]" />
@endsection

<x-matchday-strip
    eyebrow="{{ $division?->name ?? 'Division' }} · {{ $tournament->statusLabel() }}"
    :title="$tournament->name"
    :description="$tournament->description ?? 'Youth basketball tournament managed with HoopFlow.'"
    :status="$tournament->statusLabel()"
    :meta="[
        ['icon' => 'calendar3', 'label' => $tournament->start_date?->format('M d, Y').' – '.$tournament->end_date?->format('M d, Y')],
        ['icon' => 'geo-alt', 'label' => $tournament->venueName()],
        ['icon' => 'people', 'label' => $approved.' approved · '.$pending.' pending'],
        ['icon' => 'cash', 'label' => $money($tournament->entryFee()).' entry'],
    ]"
    data-testid="tournament-hero-{{ $tournament->id }}"
>
    <a class="hf-btn hf-btn--primary" href="{{ route('bracket', ['id' => $tournament->id]) }}" data-testid="show-bracket">Bracket</a>
    <a class="hf-btn" href="{{ route('schedule', ['id' => $tournament->id]) }}" data-testid="show-schedule">Schedule</a>
    <a class="hf-btn" href="{{ route('standings', ['id' => $tournament->id]) }}" data-testid="show-standings">Standings</a>
    @if ($role === 'coach')
        @if ($coachTeam)
            <a class="hf-btn" href="{{ route('myTeam', ['team' => $coachTeam->id, 'tournament' => $tournament->id]) }}" data-testid="show-my-team">My team status</a>
        @elseif ($tournament->status === 'registration-open')
            <a class="hf-btn hf-btn--primary" href="{{ route('teamRegister', ['tournament' => $tournament->id]) }}" data-testid="show-register">Register team</a>
        @endif
    @endif
    @if ($role === 'organizer')
        <a class="hf-btn" href="{{ route('announcementCreate', ['tournament' => $tournament->id]) }}" data-testid="show-create-announcement">Announce</a>
    @endif
</x-matchday-strip>
```

Keep the KPI grid below the strip unchanged except for removing inline `font-size` styles elsewhere.

- [ ] **Step 5: Restyle the team list, announcements, and desk sidebar**

In the team list:

- Add `hf-card--link` to each `a.hf-card`.
- Add `<span class="hf-card__arrow" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>` inside the top row.
- Replace the inline `style="display:block"` coach line with `<span class="hf-small hf-muted hf-block">`.
- Keep `data-testid="tournament-team-{{ ... }}"`.

For the announcements column, keep the current markup and test IDs, but add `hf-card--flat` and the
empty-state class `hf-empty--compact`. Do not change the `Announcements` heading.

In the sidebar, keep the exact string `Open bracket` on the bracket action. Add
`wire:loading.class="is-loading"` to `requestGenerateBracket`, `requestGenerateSchedule`, and their
confirm buttons. Add `wire:loading.attr="aria-busy"` to the same buttons.

- [ ] **Step 6: Rebuild the create wizard shell**

In `tournament-create.blade.php`, add breadcrumbs after `@section('title', 'Create tournament')`,
keep the existing header copy, and add a native progress element below the header:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Tournaments', 'href' => route('tournaments')],
        ['label' => 'Create tournament'],
    ]" />
@endsection

<progress class="hf-meter hf-mb-2" value="{{ $step + 1 }}" max="3">{{ $step + 1 }} of 3</progress>
```

Wrap the form and a new summary aside in `.hf-wizard`:

```blade
<div class="hf-wizard">
    <form wire:submit="save" class="hf-card hf-wizard__form" novalidate data-testid="create-form">
        <x-form-errors />
        {{-- existing step sections stay here, unchanged --}}

        <div class="hf-sticky-actions hf-mt-3">
            <button type="button" class="hf-btn" wire:click="previousStep" data-testid="create-back" @if ($step === 0) hidden @endif wire:loading.attr="disabled">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
            </button>
            <button type="button" class="hf-btn hf-btn--primary" wire:click="nextStep" data-testid="create-next" @if ($step === 2) hidden @endif wire:loading.attr="disabled" wire:loading.class="is-loading">
                Continue <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
            <button type="submit" class="hf-btn hf-btn--primary" data-testid="create-publish" @if ($step !== 2) hidden @endif wire:loading.attr="disabled" wire:loading.class="is-loading">
                <span wire:loading.remove wire:target="save">Publish tournament</span>
                <span wire:loading wire:target="save">Publishing…</span>
            </button>
        </div>
    </form>

    <aside class="hf-wizard__summary" aria-label="Tournament summary">
        <span class="hf-eyebrow">Preview</span>
        <h2 class="hf-section-title hf-mt-1">{{ $name !== '' ? $name : 'Untitled tournament' }}</h2>
        <dl class="hf-summary-list">
            <div><dt>Division</dt><dd>{{ $division_name }}</dd></div>
            <div><dt>Venue</dt><dd>{{ $venue_name !== '' ? $venue_name : 'Venue TBD' }}</dd></div>
            <div><dt>Dates</dt><dd>{{ $start_date }} – {{ $end_date }}</dd></div>
            <div><dt>Format</dt><dd>{{ match ($format) { 'double-elimination' => 'Double elimination', 'round-robin' => 'Round robin', default => 'Single elimination' } }}</dd></div>
            <div><dt>Slots</dt><dd>{{ $bracketSlots }} teams</dd></div>
            <div><dt>Entry</dt><dd>₱{{ number_format((float) $fee) }}</dd></div>
            <div><dt>Courts</dt><dd>{{ count($courts) }}</dd></div>
        </dl>
        <p class="hf-small hf-muted hf-mt-2 hf-mb-0">The summary updates as you move between steps.</p>
    </aside>
</div>
```

Keep every existing `create-*` test ID and `data-step-panel` value. Add `wire:loading.attr="disabled"`
to `create-back` and `create-next`. Preserve the existing format helper copy and the
`create-best-of-final-reset-note` strings.

- [ ] **Step 7: Add the desk styles**

Append to `_components.scss`:

```scss
.hf-filter-bar {
  display: grid;
  grid-template-columns: minmax(220px, 1fr) repeat(2, minmax(160px, 220px)) auto;
  gap: 10px;
  align-items: end;
  padding: 12px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-radius-lg);
  background: var(--hf-surface);
}

.hf-filter-bar__search {
  position: relative;
}

.hf-filter-bar__search > .bi {
  position: absolute;
  top: 50%;
  left: 12px;
  color: var(--hf-muted);
  pointer-events: none;
  transform: translateY(-50%);
}

.hf-filter-bar__search .hf-input {
  padding-left: 34px;
}

.hf-select--compact {
  min-height: 40px;
  padding-block: 8px;
  font-size: 13px;
}

.hf-results-count {
  color: var(--hf-muted);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.hf-kpi-value--sm {
  font-size: 30px;
}

.hf-meter {
  display: block;
  width: 100%;
  height: 6px;
  accent-color: var(--hf-primary);
}

.hf-wizard {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 18px;
  align-items: start;
}

.hf-wizard__summary {
  position: sticky;
  top: 84px;
  padding: 18px;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-radius-lg);
  background: var(--hf-surface);
}

.hf-summary-list {
  display: grid;
  gap: 8px;
  margin: 14px 0 0;
}

.hf-summary-list div {
  display: grid;
  grid-template-columns: 84px minmax(0, 1fr);
  gap: 8px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--hf-border);
}

.hf-summary-list dt {
  color: var(--hf-muted);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.hf-summary-list dd {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
}

@media (max-width: 991.98px) {
  .hf-filter-bar {
    grid-template-columns: 1fr 1fr;
  }

  .hf-filter-bar__search {
    grid-column: 1 / -1;
  }

  .hf-wizard {
    grid-template-columns: 1fr;
  }

  .hf-wizard__summary {
    position: static;
    order: -1;
  }
}

@media (max-width: 575.98px) {
  .hf-filter-bar {
    grid-template-columns: 1fr;
  }
}
```

Append to `_surfaces.scss`:

```scss
.hf-wizard__form {
  min-width: 0;
}

.hf-wizard__form [data-step-panel] {
  min-width: 0;
}

.hf-wizard__form .hf-card {
  box-shadow: none;
}
```

- [ ] **Step 8: Verify the tournament surfaces**

Run:

```powershell
npm run build
php artisan test --filter=TournamentsTest
```

Expected: build succeeds; `TournamentsTest` passes, including `Team list`, `Open bracket`,
`show-create-announcement`, `show-my-team`, `show-register`, the create defaults, and the
double-elimination copy.

Manual at 390px: open the directory, open a tournament desk, and walk all three create steps. Confirm
the filter bar stacks, the wizard summary moves above the form, and the sticky action bar stays
reachable.

- [ ] **Step 9: Commit**

```bash
git add src/resources/views/livewire/organizer/tournaments-index.blade.php src/resources/views/livewire/organizer/tournament-show.blade.php src/resources/views/livewire/organizer/tournament-create.blade.php src/resources/css/hf/_components.scss src/resources/css/hf/_surfaces.scss
git commit -m "feat: redesign tournament desk and setup wizard"
```

---

### Task 17: Team directory, team profile, coach roster, and registration

**Files:**
- Modify: `src/resources/views/livewire/organizer/teams-index.blade.php`
- Modify: `src/resources/views/pages/teams/show.blade.php`
- Modify: `src/resources/views/livewire/coach/my-team.blade.php`
- Modify: `src/resources/views/livewire/coach/team-register.blade.php`
- Modify: `src/resources/css/hf/_components.scss`
- Modify: `src/resources/css/hf/_surfaces.scss`
- Modify: `src/resources/css/hf/_data.scss`

**Interfaces:**
- Consumes: `.hf-filter-bar`, `.hf-card--link`, `<x-callout>`, `<x-form-errors>`, `.hf-wizard` from Task 16.
- Produces: `.hf-team-card__meta`, `.hf-team-hero`, `.hf-next-step`, `.hf-payment-summary`,
  `.hf-roster-table`, `.hf-register-summary`.

- [ ] **Step 1: Replace the team directory header and filters**

In `teams-index.blade.php`, add breadcrumbs for both branches:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="array_values(array_filter([
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Teams', 'href' => route('teams')],
        $selectedTournament ? ['label' => $selectedTournament->name] : null,
    ]))" />
@endsection
```

Keep the `Teams` and `Select a tournament` H1s. Replace the filter card with the same
`.hf-filter-bar` structure used in Task 16, preserving `team-search`, `team-status-filter`, and
`team-division-filter`. Add a result count and a clear button when any filter is active.

Add `wire:loading.class="is-updating"` and `wire:loading.attr="aria-busy"` to `#teamList`. Add
`wire:loading.class="is-loading"` to filter actions.

- [ ] **Step 2: Make the team cards scannable**

In each `article.hf-card`, keep `data-testid="team-card-{{ $team->id }}"`, add `hf-card--link`, and
replace the inline `style="display:block"` spans with `.hf-block`. Add an arrow:

```blade
<span class="hf-card__arrow" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>
```

Group the four facts (tournament, division, record, roster) inside:

```blade
<dl class="hf-team-card__meta">
    <div><dt>Tournament</dt><dd>{{ $registration?->tournament?->name ?? '—' }}</dd></div>
    <div><dt>Division</dt><dd>{{ $divisionName }}</dd></div>
    <div><dt>Record</dt><dd>{{ $record['wins'] }}-{{ $record['losses'] }}</dd></div>
    <div><dt>Roster</dt><dd>{{ $team->players->count() }}</dd></div>
</dl>
```

Keep `team-open-*` and `team-manage-*` test IDs and the `Manage roster` label.

- [ ] **Step 3: Rebuild the public team profile hero**

In `pages/teams/show.blade.php`, add breadcrumbs after the `@section('title', ...)`:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="array_values(array_filter([
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Teams', 'href' => route('teams', $tournament ? ['tournament' => $tournament->id] : [])],
        ['label' => $team->name],
    ]))" />
@endsection
```

Add `hf-team-hero` to the hero section and keep `data-testid="team-hero-{{ $team->id }}"`. Replace the
hero's `<h1>` with `<h2 class="hf-hero__name">{{ $team->name }}</h2>`; the page head already owns the
single `<h1>`. Keep the stat strip labels `Record`, `Points for`, `Point diff`, and `Roster`.

In the roster table:

- Add `hf-table--roster` and a `<caption class="hf-sr-only">Team roster</caption>`.
- Keep every `<th scope="col">`.
- Add `data-label` to each `<td>` for the mobile stacked table:

```blade
<td data-label="#">{{ $player->jersey_number }}</td>
<td data-label="Player"><strong>{{ $player->first_name }} {{ $player->last_name }}</strong></td>
```

Keep the exact registration heading line
`<h3 class="hf-section-title hf-mt-1">Pending</h3>` — `TeamsTest` asserts it.

- [ ] **Step 4: Add a next-step callout to My Team**

In `my-team.blade.php`, after the page header and before the KPI grid, add:

```blade
@php
    $nextStep = match (true) {
        $playerCount < 8 => [
            'Add '.max(0, 8 - $playerCount).' more '.($playerCount === 7 ? 'player' : 'players').' to reach the eight-player minimum.',
            'Add player',
            'openPlayerDialog',
        ],
        ! $payment => ['Submit your entry fee reference so the organizer can verify it.', 'Add payment reference', 'openPaymentDialog'],
        $status === 'rejected' => ['Update the roster using the organizer feedback below, then resubmit.', null, null],
        $status === 'submitted' => ['Your registration is in the organizer queue. No action needed right now.', null, null],
        $status === 'approved' => ['Your team is approved. Watch the bracket for the first game.', null, null],
        default => ['Check the tournament timeline for the next registration step.', null, null],
    };
@endphp

<x-callout
    :tone="$status === 'rejected' ? 'danger' : ($status === 'approved' ? 'success' : 'info')"
    :title="$nextStep[0]"
    class="hf-next-step"
>
    @if ($nextStep[1])
        <button type="button" class="hf-btn hf-btn--sm" wire:click="{{ $nextStep[2] }}">{{ $nextStep[1] }}</button>
    @endif
</x-callout>
```

Keep `data-testid="my-team-empty"`, `my-team-public`, `my-team-register-another`,
`my-team-select`, `my-team-roster`, `my-team-timeline`, `my-team-payment`, and every player test ID.

- [ ] **Step 5: Upgrade the My Team roster, payment, and dialogs**

In the roster table:

- Add `hf-table--roster` and `<caption class="hf-sr-only">Team roster</caption>`.
- Add `data-label` to every `<td>`.
- Keep the exact heading `Roster ({{ $playerCount }})`.
- Add `wire:loading.class="is-loading"` to `my-team-add-player`.

In the payment card:

- Keep the exact `Send to:` text.
- Add `class="hf-payment-summary"` to the card.
- Group the method, reference, amount, and status into a `<dl>`.

In every dialog form, add `<x-form-errors />` immediately inside the form and make the footer sticky:

```blade
<div class="hf-dialog__foot hf-dialog__foot--sticky">
    ...
</div>
```

Keep `my-player-save`, `my-payment-save`, `confirm-ok`, and `dialog-close` test IDs. Add
`wire:loading.class="is-loading"` and `wire:loading.attr="aria-busy"` to both save buttons.

- [ ] **Step 6: Upgrade the team registration wizard**

In `team-register.blade.php`, keep the header and stepper, but add a step counter and wrap the form:

```blade
<p class="hf-small hf-muted hf-mb-2">Step {{ $step + 1 }} of 4 · {{ ['Team', 'Roster', 'Eligibility & payment', 'Review'][$step] }}</p>

<div class="hf-wizard">
    <form wire:submit="save" class="hf-card hf-wizard__form" novalidate data-testid="register-form">
        <x-form-errors />
        {{-- existing step panels stay here --}}
        <div class="hf-sticky-actions hf-mt-3">
            {{-- existing back / next / submit buttons, with wire:loading states --}}
        </div>
    </form>

    <aside class="hf-wizard__summary" aria-label="Registration summary">
        <span class="hf-eyebrow">Registration</span>
        <h2 class="hf-section-title hf-mt-1">{{ $name !== '' ? $name : 'Your team' }}</h2>
        <dl class="hf-summary-list">
            <div><dt>Tournament</dt><dd>{{ $tournament->name }}</dd></div>
            <div><dt>Division</dt><dd>{{ $division?->name ?? 'Division' }}</dd></div>
            <div><dt>Venue</dt><dd>{{ $tournament->venueName() }}</dd></div>
            <div><dt>Entry</dt><dd>{{ $money($tournament->entryFee()) }}</dd></div>
            <div><dt>Roster</dt><dd>{{ count($players) }} players</dd></div>
        </dl>
        <p class="hf-small hf-muted hf-mt-2 hf-mb-0">You can edit the roster until the organizer verifies it.</p>
    </aside>
</div>
```

Keep every `register-*` test ID and the `Fill demo data` button. Add
`wire:loading.attr="disabled"` and `wire:loading.class="is-loading"` to the save button.

- [ ] **Step 7: Add team surfaces styles**

Append to `_components.scss`:

```scss
.hf-block {
  display: block;
}

.hf-min-w-0 {
  min-width: 0;
}

.hf-team-card__meta {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin: 14px 0 0;
}

.hf-team-card__meta dt {
  color: var(--hf-muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.hf-team-card__meta dd {
  margin: 2px 0 0;
  font-size: 13px;
  font-weight: 700;
}

@media (max-width: 575.98px) {
  .hf-team-card__meta {
    grid-template-columns: 1fr 1fr;
  }
}
```

Append to `_surfaces.scss`:

```scss
.hf-team-hero {
  border-top: 3px solid var(--hf-primary);
}

.hf-next-step {
  margin-bottom: 16px;
}

.hf-payment-summary dl {
  display: grid;
  gap: 6px;
  margin: 10px 0 0;
}

.hf-payment-summary dl div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
}

.hf-payment-summary dt {
  color: var(--hf-muted);
}

.hf-payment-summary dd {
  margin: 0;
  font-weight: 700;
  text-align: right;
}
```

Append to `_data.scss`:

```scss
.hf-table--roster th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--hf-surface-2);
}

.hf-table--roster tbody tr:nth-child(even) {
  background: color-mix(in srgb, var(--hf-ink) 2%, transparent);
}

.hf-dialog__foot--sticky {
  position: sticky;
  bottom: 0;
  background: var(--hf-surface-2);
  border-top: 1px solid var(--hf-border);
}
```

- [ ] **Step 8: Verify the team surfaces**

Run:

```powershell
npm run build
php artisan test --filter="TeamsTest|DashboardTest"
```

Expected: build succeeds; tests pass, including `Public profile`, `Team not available`,
`Back to public teams`, `Roster (n)`, `Registration timeline`, `Send to:`, the exact
`<h3 class="hf-section-title hf-mt-1">Pending</h3>` markup, and the coach register/my-team links.

Manual at 390px: open the team directory, a team profile, My Team, and the registration wizard.
Confirm the roster tables stack with labels and the summary moves above the form.

- [ ] **Step 9: Commit**

```bash
git add src/resources/views/livewire/organizer/teams-index.blade.php src/resources/views/pages/teams/show.blade.php src/resources/views/livewire/coach/my-team.blade.php src/resources/views/livewire/coach/team-register.blade.php src/resources/css/hf/_components.scss src/resources/css/hf/_surfaces.scss src/resources/css/hf/_data.scss
git commit -m "feat: redesign team directory, profiles, and coach flows"
```

---

### Task 18: Submissions queue and payments ledger

**Files:**
- Modify: `src/resources/views/livewire/organizer/submissions-index.blade.php`
- Modify: `src/resources/views/livewire/organizer/payments-index.blade.php`
- Modify: `src/resources/css/hf/_components.scss`
- Modify: `src/resources/css/hf/_data.scss`

**Interfaces:**
- Consumes: `.hf-filter-bar`, `<x-callout>`, `<x-form-errors>`, `.hf-sticky-actions`.
- Produces: `.hf-queue-row`, `.hf-queue-row__identity`, `.hf-queue-row__evidence`,
  `.hf-queue-row__actions`, `.hf-payment-row`, `.hf-money`, `.hf-dialog__body--split`.

- [ ] **Step 1: Rebuild the submissions header, KPIs, and filters**

Keep the exact strings `Submissions`, `Pending review`, `Across all tournaments`, `Approved`,
`Rejected`, and `Open registration`. Add breadcrumbs:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Submissions'],
    ]" />
@endsection
```

Replace the filter card with `.hf-filter-bar`, preserving `submission-search`,
`submission-status-filter`, `submission-tournament-filter`, and `submissions-open-payments`.
Add a result count:

```blade
<p class="hf-results-count hf-mb-2">{{ $this->registrations->count() }} {{ \Illuminate\Support\Str::plural('team', $this->registrations->count()) }} in this view</p>
```

Add `wire:loading.class="is-updating"` and `wire:loading.attr="aria-busy"` to `#submissionList`.

- [ ] **Step 2: Turn each submission into a queue row**

Replace the body of `article.hf-card` with:

```blade
<div class="hf-queue-row">
    <div class="hf-queue-row__identity">
        <x-team-shield :team="$team" />
        <div>
            <strong>{{ $team?->name ?? 'Team' }}</strong>
            <span class="hf-small hf-muted hf-block">{{ $team?->coach_name ?? 'Coach TBD' }} · {{ $team?->city ?? 'City TBD' }}</span>
            <span class="hf-small hf-muted hf-block">{{ $registration->tournament?->name ?? 'Tournament' }} · {{ $registration->division?->name ?? 'Division' }}</span>
        </div>
    </div>
    <div class="hf-queue-row__evidence">
        <x-status-chip :status="$registration->status" />
        @if ($registration->payment)
            <x-payment-chip :payment="$registration->payment" />
        @endif
        <span class="hf-small hf-muted">{{ $team?->players->count() ?? 0 }} players</span>
    </div>
    <div class="hf-queue-row__actions">
        <button type="button" class="hf-btn hf-btn--sm" wire:click="openReview({{ $registration->id }})" data-testid="submission-open-{{ $registration->id }}">Review detail</button>
        @if ($registration->status === 'submitted' || $registration->status === 'pending')
            <button type="button" class="hf-btn hf-btn--sm hf-btn--primary" wire:click="approve({{ $registration->id }})" data-testid="submission-approve-{{ $registration->id }}" wire:loading.class="is-loading" wire:loading.attr="aria-busy">Approve</button>
            <button type="button" class="hf-btn hf-btn--sm hf-btn--danger" wire:click="reject({{ $registration->id }})" data-testid="submission-reject-{{ $registration->id }}" wire:loading.class="is-loading" wire:loading.attr="aria-busy">Reject</button>
        @endif
    </div>
</div>
```

Keep every existing `submission-*` test ID. Do not remove the `submission-seeds-locked-*` chip or the
`Open registration` KPI.

- [ ] **Step 3: Restructure the review, approve, and reject dialogs**

For each dialog, add `hf-dialog__body--split` to the body and a sticky footer. In the review dialog:

- Keep `submission-review-dialog`, `submission-consent-*`, `submission-document-*`, and
  `aria-haspopup="listbox"` intact.
- Keep the exact strings `Guardian consent recorded` and `Birth certificate approved`.
- Add `data-testid="submission-review-summary"` to the summary card.

In the approve dialog, keep the age-blocker callout and `submission-approve-anyway`,
`submission-approve-cancel`, and `submission-approve-confirm`. In the reject dialog, keep
`submission-reject-reason`, `submission-reject-cancel`, and `submission-reject-confirm`.

Add `<x-form-errors />` inside the reject dialog form and inside the review dialog if it contains a
form. Add `wire:loading.attr="disabled"` to the confirm buttons.

- [ ] **Step 4: Rebuild the payments header, KPIs, and destination**

Keep the exact KPI labels `Collected`, `Outstanding`, `Expected`, and `Collection rate`. Add
breadcrumbs:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Payments'],
    ]" />
@endsection
```

Replace the destination card with:

```blade
<x-callout tone="info" title="Send payments to" class="hf-mb-3" data-testid="payment-destination">
    @if ($this->destinationTournament)
        <strong>{{ $this->destinationTournament->payment_method ?? 'GCash' }} · {{ $this->destinationTournament->payment_account ?? 'TBD' }}</strong>
        <p class="hf-small hf-muted hf-mb-0">{{ $this->destinationTournament->name }}</p>
    @else
        <p class="hf-small hf-muted hf-mb-0">Select a tournament to see the payment destination.</p>
    @endif
</x-callout>
```

Replace the filter card with `.hf-filter-bar`, preserving `payment-search`,
`payment-status-filter`, `payment-tournament-filter`, and `payments-open-submissions`. Add
`wire:loading.class="is-updating"` and `wire:loading.attr="aria-busy"` to `#paymentList`.

- [ ] **Step 5: Turn each payment into a ledger row**

Replace the payment card body with:

```blade
<div class="hf-payment-row">
    <div class="hf-payment-row__identity">
        <x-team-shield :team="$team" />
        <div>
            <strong>{{ $team?->name ?? 'Team' }}</strong>
            <span class="hf-small hf-muted hf-block">{{ $registration->tournament?->name ?? 'Tournament' }}</span>
        </div>
    </div>
    <dl class="hf-payment-row__facts">
        <div><dt>Reference</dt><dd class="hf-mono">{{ $payment->reference_no ?: '—' }}</dd></div>
        <div><dt>Method</dt><dd>{{ $payment->method ?: '—' }}</dd></div>
        <div><dt>Amount</dt><dd class="hf-money">{{ $money($payment->amount ?? 0) }}</dd></div>
    </dl>
    <div class="hf-payment-row__status">
        <x-payment-chip :payment="$payment" />
        <span class="hf-small hf-muted">{{ $payment->verified_at?->format('M d, Y') ?? 'Not verified' }}</span>
    </div>
    <div class="hf-payment-row__actions">
        <button type="button" class="hf-btn hf-btn--sm {{ $pstatus !== 'verified' ? 'hf-btn--primary' : '' }}" wire:click="openVerify({{ $payment->id }})" data-testid="payment-verify-{{ $payment->id }}" wire:loading.class="is-loading" wire:loading.attr="aria-busy">Verify payment</button>
        <a class="hf-btn hf-btn--sm" href="{{ route('teamShow', ['team' => $team->id]) }}" data-testid="payment-team-{{ $payment->id }}">Team detail</a>
    </div>
</div>
```

Keep `payment-row-*`, `payment-verify-*`, `payment-team-*`, and every dialog test ID. Keep the exact
`?1,500` amount rendering produced by `$money()`.

- [ ] **Step 6: Add the queue and ledger styles**

Append to `_components.scss`:

```scss
.hf-queue-row {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(180px, 0.8fr) auto;
  gap: 14px;
  align-items: center;
}

.hf-queue-row__identity {
  display: flex;
  gap: 10px;
  align-items: center;
  min-width: 0;
}

.hf-queue-row__evidence,
.hf-queue-row__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.hf-queue-row__actions {
  justify-content: flex-end;
}

.hf-payment-row {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(240px, 1.2fr) minmax(120px, 0.6fr) auto;
  gap: 14px;
  align-items: center;
}

.hf-payment-row__identity {
  display: flex;
  gap: 10px;
  align-items: center;
  min-width: 0;
}

.hf-payment-row__facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}

.hf-payment-row__facts dt {
  color: var(--hf-muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.hf-payment-row__facts dd {
  margin: 2px 0 0;
  font-size: 13px;
  font-weight: 600;
}

.hf-payment-row__status,
.hf-payment-row__actions {
  display: grid;
  gap: 4px;
}

.hf-payment-row__actions {
  justify-items: end;
}

.hf-money {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.hf-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12px;
}

@media (max-width: 991.98px) {
  .hf-queue-row,
  .hf-payment-row {
    grid-template-columns: 1fr;
  }

  .hf-queue-row__actions,
  .hf-payment-row__actions {
    justify-content: flex-start;
    justify-items: start;
  }
}
```

Append to `_data.scss`:

```scss
.hf-dialog__body--split {
  display: grid;
  grid-template-columns: minmax(0, 1.3fr) minmax(220px, 0.7fr);
  gap: 16px;
  align-items: start;
}

@media (max-width: 767.98px) {
  .hf-dialog__body--split {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 7: Verify queues and ledgers**

Run:

```powershell
npm run build
php artisan test --filter=SubmissionsPaymentsTest
```

Expected: build succeeds; tests pass, including `Pending review`, `Across all tournaments`,
`Open registration`, `Collected`, `Outstanding`, `Expected`, `Collection rate`, `?1,500`,
`Guardian consent recorded`, `Birth certificate approved`, and all filter/action test IDs.

Manual: approve one submission and verify one payment. Confirm one toast, the progress bar, and the
row updating without a full reload.

- [ ] **Step 8: Commit**

```bash
git add src/resources/views/livewire/organizer/submissions-index.blade.php src/resources/views/livewire/organizer/payments-index.blade.php src/resources/css/hf/_components.scss src/resources/css/hf/_data.scss
git commit -m "feat: redesign submissions queue and payments ledger"
```

---

### Task 19: Announcements, audit, profile, and auth

**Files:**
- Modify: `src/resources/views/livewire/organizer/announcements-index.blade.php`
- Modify: `src/resources/views/livewire/organizer/announcement-create.blade.php`
- Modify: `src/resources/views/livewire/organizer/audit-log-index.blade.php`
- Modify: `src/resources/views/pages/profile.blade.php`
- Modify: `src/resources/views/auth/login.blade.php`
- Modify: `src/resources/views/auth/register.blade.php`
- Modify: `src/resources/css/hf/_components.scss`
- Modify: `src/resources/css/hf/_surfaces.scss`

**Interfaces:**
- Consumes: `<x-breadcrumbs>`, `<x-callout>`, `<x-form-errors>`, `.hf-filter-bar`, `.hf-wizard`.
- Produces: `.hf-announcement-feed`, `.hf-composer-preview`, `.hf-audit-day`, `.hf-auth-shell`,
  `.hf-auth-aside`, `.hf-auth-points`, `.hf-profile-grid`.

- [ ] **Step 1: Rebuild the announcements index**

Add breadcrumbs after `@section('title', 'Announcements')`:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Announcements'],
    ]" />
@endsection
```

Keep the `Announcements` H1 and `announcement-new` CTA. Replace the filter card with
`.hf-filter-bar`, preserving `announcement-search`, `announcement-tournament-filter`, and the
category buttons.

Wrap the feed by adding `hf-announcement-feed` to the existing `#announcementFeed` wrapper (do not add a
second wrapper, and keep its `data-testid="announcement-feed"`):

```blade
<div id="announcementFeed" class="hf-announcement-feed" data-testid="announcement-feed">
```

Keep `announcement-feed`, `announcement-empty`, and `announcement-{{ $announcement->id }}` test IDs.
Add `wire:loading.class="is-updating"` and `wire:loading.attr="aria-busy"` to the feed.

In the announcement dialog, keep `announcement-dialog`, `announcement-delete`, `announcement-pin`,
and `announcement-close`. Add `hf-dialog__body--split` to the body and a sticky footer.

- [ ] **Step 2: Restyle the announcement composer and its existing preview**

In `announcement-create.blade.php`, add breadcrumbs and wrap the form and preview:

```blade
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Announcements', 'href' => route('announcements')],
        ['label' => 'New announcement'],
    ]" />
@endsection

<div class="hf-split">
    <form wire:submit="save" id="announcementForm" data-testid="announcement-create-form">
        <x-form-errors />
        {{-- existing cards and fields --}}
        <div class="hf-sticky-actions">
            <a class="hf-btn" href="{{ route('announcements') }}" data-testid="announcement-cancel">Cancel</a>
            <button type="submit" class="hf-btn hf-btn--primary" wire:loading.attr="disabled" wire:loading.class="is-loading" data-testid="announcement-publish">
                <span wire:loading.remove wire:target="save">Publish announcement</span>
                <span wire:loading wire:target="save">Publishing…</span>
            </button>
        </div>
    </form>

    <aside class="hf-composer-preview" aria-label="Announcement preview">
        <span class="hf-eyebrow">Live preview</span>
        {{-- Move the existing preview card here, keeping its markup and test ID --}}
        <article class="hf-card hf-announcement-preview {{ $pinned ? 'hf-announcement--pinned' : '' }}" data-preview data-testid="announcement-preview">
            <div class="hf-between">
                <span class="hf-chip hf-chip--slate">{{ \App\Models\Announcement::categoryLabel($category) }}</span>
                @if ($pinned)
                    <span class="hf-chip hf-chip--gold"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i>Pinned</span>
                @endif
            </div>
            <h3 class="hf-section-title hf-mt-1">{{ trim($title) !== '' ? $title : 'Your headline' }}</h3>
            <p class="hf-small hf-muted hf-mb-0">{{ trim($body) !== '' ? $body : 'The message coaches and fans will see.' }}</p>
        </article>
        <p class="hf-small hf-muted">Published announcements appear in the feed and on the tournament desk.</p>
    </aside>
</div>
```

The composer already renders `announcement-preview`, `announcement-cancel`, and `announcement-publish`,
and the header already owns `announcement-composer-back`. Reuse those test IDs; do not add a second
back link or a second preview card.

- [ ] **Step 3: Rebuild the audit log**

Add breadcrumbs after the header's opening tag area, then replace the filter card with
`.hf-filter-bar`, preserving `audit-entity-filter`, `audit-actor-filter`, and `audit-search`.

Keep the exact count markup:

```blade
<span class="hf-small hf-muted" data-testid="audit-count">{{ $entries->count() }} of {{ $allEntries->count() }} entries</span>
```

Group the timeline by day:

```blade
@php
    $groupedEntries = $entries->groupBy(fn ($log) => $log->created_at?->format('Y-m-d') ?? 'unknown');
@endphp

@foreach ($groupedEntries as $day => $logs)
    <section class="hf-audit-day" aria-label="{{ $day === 'unknown' ? 'Undated entries' : \Carbon\Carbon::parse($day)->format('l, F j, Y') }}">
        <h2 class="hf-audit-day__title">{{ $day === 'unknown' ? 'Undated' : \Carbon\Carbon::parse($day)->format('M j, Y') }}</h2>
        <div class="hf-timeline">
            @foreach ($logs as $log)
                {{-- existing audit entry markup --}}
            @endforeach
        </div>
    </section>
@endforeach
```

Keep every `audit-*` test ID, the exact strings `Score corrected`,
`Verified payment for San Antonio Vipers`, `Status: paid`, `Box score corrected`, `Corrected the`,
`box score`, `No audit entries match`, and `audit-clear-inline`. Add
`wire:loading.class="is-updating"` to `#auditList`.

- [ ] **Step 4: Rebuild the profile page**

Keep `Profile`, `profile-identity`, `profile-role-card`, `profile-appearance`, and the role links
from Task 5. Replace the layout with:

```blade
<div class="hf-profile-grid">
    <section class="hf-card hf-profile-head" data-testid="profile-identity">
        <span class="hf-avatar hf-avatar--xl">{{ $initials }}</span>
        <div class="hf-profile-meta">
            <span class="hf-chip hf-chip--primary">{{ ucfirst($role) }}</span>
            <h2>{{ $user->name }}</h2>
            <p class="hf-mb-0">{{ $user->email }}</p>
        </div>
    </section>

    <section id="appearance" class="hf-card" data-testid="profile-appearance">
        {{-- Task 5 appearance controls --}}
    </section>

    <section class="hf-card" data-testid="profile-role-card">
        {{-- role context and role links --}}
    </section>

    <section id="shortcuts" class="hf-card">
        {{-- Task 5 shortcut reference --}}
    </section>
</div>
```

Remove the inline `style="text-transform: capitalize"` from the role chip and heading; use
`ucfirst($role)` in PHP. Keep the exact role-link test IDs `profile-link-{{ $key }}`.

- [ ] **Step 5: Rebuild the sign-in page**

In `auth/login.blade.php`, keep `data-testid="login-card"`, `login-password-toggle`,
`login-submit`, all three `quick-login-*` test IDs, and the exact text `Quick-fill accounts`.
Replace the card wrapper with:

```blade
<div class="hf-auth-shell">
    <aside class="hf-auth-aside" aria-hidden="true">
        <x-brand size="lg" on-dark tagline="Tournament ops" />
        <p class="hf-eyebrow hf-eyebrow--on-dark">Courtside, organized</p>
        <h2 class="hf-auth-aside__title">Every roster, bracket, and final score in one place.</h2>
        <ul class="hf-auth-points">
            <li><i class="bi bi-clipboard-check" aria-hidden="true"></i> Review eligibility before seeding</li>
            <li><i class="bi bi-diagram-3" aria-hidden="true"></i> Run the bracket from the first tip-off</li>
            <li><i class="bi bi-megaphone" aria-hidden="true"></i> Keep coaches and fans on the same page</li>
        </ul>
    </aside>

    <div class="hf-auth-card" data-testid="login-card" x-data="{ showPassword: false, email: @js(old('email', '')), password: '' }">
        {{-- existing heading, form, errors, and quick-fill accounts --}}
    </div>
</div>
```

Add the `x-form-errors` summary from Task 10 immediately after `@csrf`. Add
`wire:loading.attr="disabled"` is not available on a plain form; use
`x-on:submit="$el.querySelector('[type=submit]').disabled = true"` only if it does not break the
existing form. Keep the markup simple and do not add a dead “Forgot password?” link.

- [ ] **Step 6: Rebuild the registration page**

In `auth/register.blade.php`, keep `data-testid="register-card"`, every `register-*` test ID, the
exact `Create account` heading, and the role/organization fields. Use the same
`hf-auth-shell` / `hf-auth-aside` wrapper as sign-in. Keep the form in `.hf-auth-card` and add the
`x-form-errors` summary from Task 10 after `@csrf`.

Add a password helper below the password field only if the existing validation rules still match:

```blade
<span class="hf-help">Use at least 8 characters, including a letter and a number.</span>
```

- [ ] **Step 7: Add the remaining desk and auth styles**

Append to `_components.scss`:

```scss
.hf-announcement-feed {
  display: grid;
  gap: 10px;
}

.hf-composer-preview {
  position: sticky;
  top: 84px;
}

.hf-announcement-preview h2 {
  font-size: 26px;
}

.hf-audit-day {
  margin-bottom: 18px;
}

.hf-audit-day__title {
  margin: 0 0 8px;
  color: var(--hf-muted);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

@media (max-width: 991.98px) {
  .hf-composer-preview {
    position: static;
  }
}
```

Append to `_surfaces.scss`:

```scss
.hf-profile-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.hf-profile-grid > [data-testid='profile-identity'] {
  grid-column: 1 / -1;
}

.hf-auth-shell {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
  gap: 28px;
  width: min(1040px, 100%);
  margin: 0 auto;
  align-items: center;
}

.hf-auth-aside {
  position: relative;
  overflow: hidden;
  padding: 32px;
  border-radius: var(--hf-radius-xl);
  background:
    radial-gradient(circle at 82% 18%, color-mix(in srgb, var(--hf-primary) 30%, transparent), transparent 42%),
    var(--hf-rail);
  color: var(--hf-rail-ink);
  box-shadow: var(--hf-shadow-rest);
}

.hf-auth-aside::after {
  position: absolute;
  right: -90px;
  bottom: -120px;
  width: 300px;
  height: 300px;
  border: 2px solid rgba(255, 255, 255, 0.10);
  border-radius: 50%;
  content: '';
}

.hf-auth-aside__title {
  margin: 10px 0 0;
  font-family: var(--hf-font-display);
  font-size: clamp(2rem, 3.6vw, 3.2rem);
  font-weight: 800;
  line-height: 0.95;
}

.hf-auth-points {
  display: grid;
  gap: 10px;
  margin: 22px 0 0;
  padding: 0;
  list-style: none;
  color: var(--hf-rail-muted);
  font-size: 13px;
}

.hf-auth-points li {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.hf-auth-points .bi {
  margin-top: 2px;
  color: var(--hf-gold-ink);
}

.hf-auth-shell .hf-auth-card {
  width: 100%;
  margin: 0;
}

@media (max-width: 900px) {
  .hf-auth-shell {
    grid-template-columns: 1fr;
  }

  .hf-auth-aside {
    display: none;
  }

  .hf-profile-grid {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 8: Verify announcements, audit, profile, and auth**

Run:

```powershell
npm run build
php artisan test --filter="AnnouncementsTest|ProfileAuditTest|LoginTest|RegistrationTest"
```

Expected: build succeeds; tests pass, including `Quick-fill accounts`, `Create account`, every
`register-*` test ID, `8 of 8 entries`, `3 of 8 entries`, `1 of 8 entries`,
`Verified payment for San Antonio Vipers`, `Status: paid`, and the audit entity links.

Manual: sign in as organizer, coach, and fan; confirm each auth card is readable and the quick-fill
buttons still populate the form. Open `/announcements`, `/audit`, and `/profile` at 390px.

- [ ] **Step 9: Commit**

```bash
git add src/resources/views/livewire/organizer/announcements-index.blade.php src/resources/views/livewire/organizer/announcement-create.blade.php src/resources/views/livewire/organizer/audit-log-index.blade.php src/resources/views/pages/profile.blade.php src/resources/views/auth/login.blade.php src/resources/views/auth/register.blade.php src/resources/css/hf/_components.scss src/resources/css/hf/_surfaces.scss
git commit -m "feat: redesign announcements, audit, profile, and auth"
```

---

# Phase 5 — Motion, copy, and release gate

### Task 20: Motion and micro-interaction pass

**Files:**
- Modify: `src/resources/css/hf/_motion.scss`
- Modify: `src/resources/css/hf/_components.scss`
- Create: `src/resources/js/ui/motion.js`
- Modify: `src/resources/js/app.js`
- Modify: `src/resources/views/pages/dashboard.blade.php`
- Modify: `src/resources/views/livewire/organizer/tournaments-index.blade.php`
- Modify: `src/resources/views/livewire/organizer/teams-index.blade.php`
- Modify: `src/resources/views/livewire/organizer/announcements-index.blade.php`

**Interfaces:**
- Consumes: `--hf-dur-*`, `--hf-ease-out`, `data-motion`, `prefers-reduced-motion`.
- Produces: `initMotion()`; `.hf-reveal`; one-shot `hf-page-in`, `hf-reveal-in`, `hf-dialog-in`,
  `hf-backdrop-in`, `hf-toast-in`; `.hf-theme-transition`.

- [ ] **Step 1: Create the motion module**

`src/resources/js/ui/motion.js`:

```js
export function initMotion() {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (reduce.matches) return;

    document.querySelectorAll('[data-reveal]').forEach((element, index) => {
        element.classList.add('hf-reveal');
        element.style.setProperty('--hf-reveal-delay', `${Math.min(index * 40, 200)}ms`);
    });

    document.addEventListener('hf:theme-changed', () => {
        document.documentElement.classList.add('hf-theme-transition');
        window.setTimeout(() => {
            document.documentElement.classList.remove('hf-theme-transition');
        }, 220);
    });
}
```

Import and call it in `app.js`:

```js
import { initMotion } from './ui/motion';
initMotion();
```

- [ ] **Step 2: Move the toast entrance into the motion layer**

In `_components.scss`, remove the `animation: hf-toast-in ...` declaration from `.hf-toast` and delete
the old `@keyframes hf-toast-in` block that Task 8 left below the timer keyframes. Keep every other
toast rule. `_motion.scss` now owns the entrance, so the name must exist in exactly one partial.

- [ ] **Step 3: Add the motion rules**

Append to `_motion.scss`:

```scss
@media (prefers-reduced-motion: no-preference) {
  .hf-main {
    animation: hf-page-in var(--hf-dur-expressive) var(--hf-ease-out) both;
  }

  .hf-reveal {
    opacity: 0;
    transform: translateY(8px);
    animation: hf-reveal-in var(--hf-dur-expressive) var(--hf-ease-out) both;
    animation-delay: var(--hf-reveal-delay, 0ms);
  }

  .hf-dialog[open] {
    animation: hf-dialog-in var(--hf-dur-standard) var(--hf-ease-out) both;
  }

  .hf-dialog[open]::backdrop {
    animation: hf-backdrop-in var(--hf-dur-standard) linear both;
  }

  .hf-toast {
    animation: hf-toast-in var(--hf-dur-standard) var(--hf-ease-out) both;
  }

  .hf-btn:active {
    transform: translateY(0) scale(0.98);
  }

  .hf-rail-link .bi {
    transition: transform var(--hf-dur-micro) var(--hf-ease-out);
  }

  .hf-rail-link:hover .bi {
    transform: translateX(2px);
  }

  .hf-card--link .hf-card__arrow {
    transition: transform var(--hf-dur-standard) var(--hf-ease-out);
  }
}

@keyframes hf-page-in {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: none; }
}

@keyframes hf-reveal-in {
  to { opacity: 1; transform: none; }
}

@keyframes hf-dialog-in {
  from { opacity: 0; transform: translateY(10px) scale(0.985); }
  to { opacity: 1; transform: none; }
}

@keyframes hf-backdrop-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes hf-toast-in {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: none; }
}

.hf-theme-transition,
.hf-theme-transition *,
.hf-theme-transition *::before,
.hf-theme-transition *::after {
  transition-property: background-color, border-color, color, fill, stroke;
  transition-duration: var(--hf-dur-standard);
  transition-timing-function: linear;
}

@media (prefers-reduced-motion: reduce) {
  .hf-main,
  .hf-reveal,
  .hf-dialog[open],
  .hf-dialog[open]::backdrop,
  .hf-toast {
    animation: none !important;
  }

  .hf-reveal {
    opacity: 1;
    transform: none;
  }
}

[data-motion='reduced'] .hf-main,
[data-motion='reduced'] .hf-reveal,
[data-motion='reduced'] .hf-dialog[open],
[data-motion='reduced'] .hf-dialog[open]::backdrop,
[data-motion='reduced'] .hf-toast {
  animation: none !important;
}

[data-motion='reduced'] .hf-reveal {
  opacity: 1;
  transform: none;
}
```

- [ ] **Step 4: Mark the reveal points**

Add `data-reveal` to these existing containers. Do not add it to every card; keep the stagger short.

- `pages/dashboard.blade.php`: the KPI grid, the action-card grid, and the matchday strip.
- `livewire/organizer/tournaments-index.blade.php`: the `#tournamentList` wrapper.
- `livewire/organizer/teams-index.blade.php`: the `#teamList` wrapper.
- `livewire/organizer/announcements-index.blade.php`: the `#announcementFeed` wrapper.

Example:

```blade
<div class="hf-grid hf-grid--4 hf-mb-3" data-reveal>
```

When the element already has a test ID, keep the test ID and append the attribute:

```blade
<div id="teamList" data-testid="team-list" data-reveal>
```

- [ ] **Step 5: Verify motion and reduced motion**

Run:

```powershell
npm run build
php artisan test --filter="DashboardTest|AnnouncementsTest|TournamentsTest|TeamsTest"
```

Expected: build succeeds; tests pass.

Manual:

1. Reload the dashboard. The page fades in once; KPI tiles stagger once; nothing loops.
2. Open and close a dialog. The panel and backdrop animate; focus still returns to the trigger.
3. Switch theme. Colors cross-fade for about 180ms; layout does not jump.
4. Emulate `prefers-reduced-motion: reduce` in DevTools and reload. No fade, slide, count-up, or
   shimmer. Content is fully visible.
5. Set the Profile motion preference to Reduced and reload. Same result.

- [ ] **Step 6: Commit**

```bash
git add src/resources/css/hf/_motion.scss src/resources/css/hf/_components.scss src/resources/js/ui/motion.js src/resources/js/app.js src/resources/views/pages/dashboard.blade.php src/resources/views/livewire/organizer/tournaments-index.blade.php src/resources/views/livewire/organizer/teams-index.blade.php src/resources/views/livewire/organizer/announcements-index.blade.php
git commit -m "feat: add one-shot broadcast motion and reduced-motion safeguards"
```

---

### Task 21: Copy, heading, table, and accessibility sweep

**Files:**
- Modify: `src/resources/views/**/*.blade.php`
- Modify: `src/resources/css/hf/_base.scss`
- Modify: `src/resources/css/hf/_shell.scss`
- Modify: `src/resources/css/hf/_surfaces.scss`
- Modify: `src/resources/css/hf/_components.scss`

**Interfaces:**
- Consumes: every surface from Phases 3–4.
- Produces: product-ready copy, sentence-case entity names, one `<h1>` per page, labelled tables,
  labelled dialogs, and a short inline-style list.

- [ ] **Step 1: Find and remove demo narration**

Run:

```powershell
Get-ChildItem src/resources/views -Recurse -Filter *.blade.php | Select-String -Pattern "demo|seeded|prototype|during the demo|demo default" | Select-Object Path,LineNumber,Line
```

Replace these safe strings:

| File | Old | New |
|---|---|---|
| `pages/dashboard.blade.php` | `seeded venues` hint | `in the workspace` |
| `livewire/organizer/tournaments-index.blade.php` | “Three seeded competitions…” description | “Plan registration, run matchday, and archive every competition from one desk.” |
| `livewire/organizer/teams-index.blade.php` | `Seeded venues` | `In the workspace` |
| `pages/profile.blade.php` | demo-default theme sentence | the Task 5 appearance panel |
| `landing/hero.blade.php` and `landing/closing.blade.php` | `Explore the demo` | `See how it runs` |
| `livewire/coach/team-register.blade.php` | `Fill demo data` | `Fill sample data` |

Do **not** change:

- `Quick-fill accounts` (asserted by `LoginTest`).
- `The matchup link is invalid or the demo has been reset.` (asserted by `MatchesTest`).
- Any string listed in the Global Constraints copy contract.
- The parallel not-found strings in schedule, standings, and tournament views; keep them consistent
  with the asserted matchups string.

Search the tests before changing any other visible string:

```powershell
Get-ChildItem src/tests/Feature -Recurse -Filter *.php | Select-String -Pattern "assertSee|assertDontSee" | Select-Object Path,LineNumber,Line
```

If a copy change would require a test edit, revert the copy change and keep the test contract.

- [ ] **Step 2: Keep entity names in sentence case**

Entity names are already sentence case: `.hf-matchday__title` and `.hf-hero__name` were written
without `text-transform` in Task 4. Do not add uppercase there.

Keep uppercase only on labels: `.hf-eyebrow`, `.hf-label`, `.hf-kpi-label`, `.hf-chip`,
`.hf-summary-list dt`, `.hf-page-title`, and table headers. `.hf-page-title` names a page
("Tournaments", "Payments"), not an entity, so it keeps the broadcast treatment.

Verify with:

```powershell
Get-ChildItem src/resources/css/hf -Recurse -Filter *.scss | Select-String -Pattern "text-transform: uppercase" | Select-Object Path,LineNumber,Line
```

Expected: every match sits on a label, chip, eyebrow, page title, or table header; none sits on
`.hf-matchday__title`, `.hf-hero__name`, or `.hf-team-name`.

- [ ] **Step 3: Audit headings**

Run:

```powershell
Get-ChildItem src/resources/views -Recurse -Filter *.blade.php | Select-String -Pattern "<h1" | Select-Object Path,LineNumber,Line
```

Expected:

- No `<h1>` in `layouts/public.blade.php`.
- One `<h1>` per page. Dashboard role branches each have exactly one.
- Coach/fan hero names, tournament hero names, team hero names, and champion names are `<h2>`.
- No heading level jumps inside a section (`h2` → `h3`, never `h2` → `h4`).

Fix any page with two `<h1>`s by demoting the visual hero name, not by hiding the heading.

- [ ] **Step 4: Label tables and dialogs**

For every `<table class="hf-table">`:

- Add a `<caption class="hf-sr-only">…</caption>` or an `aria-label` on the table.
- Confirm every `<th>` has `scope="col"` or `scope="row"`.
- Add `data-label` to each `<td>` in tables that stack on mobile (roster, standings, payments).

For every `<dialog class="hf-dialog">`:

- Add `aria-labelledby="<id>"` to the dialog.
- Give the dialog title `<h2 class="hf-dialog__title" id="<id>">` the matching ID.
- Keep the existing close button `aria-label="Close dialog"`.
- Confirm `x-on:cancel.prevent` and backdrop-click close behavior still call the Livewire close
  method, not a bare `dialog.close()`.

Run:

```powershell
Get-ChildItem src/resources/views,src/resources/js -Recurse -Include *.blade.php,*.js | Select-String -Pattern "\.close\(\)" | Select-Object Path,LineNumber,Line
```

Expected: no `dialog.close()` call. The `keepModalsOpen()` hook in `app.js` re-opens any
`dialog.hf-dialog` present after a morph, so a local close that bypasses the server would be undone;
every close path must set the Livewire flag. If a bare `.close()` exists, replace it with the
component's close method before finishing this step.

- [ ] **Step 5: Label icon buttons and form controls**

Run:

```powershell
Get-ChildItem src/resources/views -Recurse -Filter *.blade.php | Select-String -Pattern "<button[^>]*>" | Where-Object { $_.Line -notmatch "aria-label|>[^<]" } | Select-Object Path,LineNumber,Line
```

For every icon-only button, add an `aria-label` that names the action and the entity, for example:

```blade
<button type="button" class="hf-icon-btn" wire:click="openPlayerDialog({{ $player->id }})" aria-label="Edit {{ $playerName }}" data-testid="my-team-edit-{{ $player->id }}">
```

For every input, select, and textarea:

- Confirm a `<label for="…">` exists, or add `aria-label`.
- Add `aria-describedby` when a help text or error message is present.
- Keep `aria-invalid="true"` on fields with errors.

- [ ] **Step 6: Add public meta descriptions**

Add a `meta-description` section to:

- `pages/tournaments/show.blade.php`
- `pages/teams/show.blade.php`
- `pages/standings.blade.php`
- `livewire/public/standings-show.blade.php` (use the tournament name)
- `livewire/organizer/matches-index.blade.php`
- `livewire/organizer/schedule-show.blade.php`

Example:

```blade
@section('meta-description', $tournament->name.' · '.$tournament->venueName().' · '.$tournament->start_date.' to '.$tournament->end_date.'. Bracket, schedule, standings, and announcements.')
```

The layout slot was added in Task 15.

- [ ] **Step 7: Reduce inline styles**

Run:

```powershell
Get-ChildItem src/resources/views -Recurse -Filter *.blade.php | Select-String -Pattern 'style="' | Select-Object Path,LineNumber,Line
```

Replace static spacing, colors, and font sizes with existing utilities or new classes. Keep only
dynamic state values, and prefer a CSS custom property over a full declaration:

```blade
<div class="hf-progress-track" style="--hf-progress: {{ $progress }}%">
```

Add the matching rule once:

```scss
.hf-progress-track > span {
  width: var(--hf-progress, 0%);
}
```

Target: fewer than 15 inline styles remain, and every remaining one carries dynamic state.

- [ ] **Step 8: Verify the sweep**

Run:

```powershell
npm run build
php artisan test --filter="DashboardTest|TournamentsTest|TeamsTest|SubmissionsPaymentsTest|AnnouncementsTest|ProfileAuditTest|LoginTest|RegistrationTest|BracketTest|ScheduleTest|MatchesTest"
```

Expected: build succeeds; every selected test passes. If a test fails because of copy, restore the
asserted string.

Manual:

- Tab through the shell, a filter bar, and a dialog. Every focus stop is visible.
- Open a dialog with a keyboard. Focus lands inside; Escape closes; focus returns.
- Zoom to 200%. No horizontal scrolling except inside the bracket canvas.
- Check light and dark themes for contrast on buttons, chips, muted text, and table headers.

- [ ] **Step 9: Commit**

Stage the four CSS partials, then stage every view file you changed in Steps 1-8 by full path:

```bash
git add src/resources/css/hf/_base.scss src/resources/css/hf/_shell.scss src/resources/css/hf/_surfaces.scss src/resources/css/hf/_components.scss
git status --short src/resources/views
```

For each view path that `git status` lists and that you changed in this task, run `git add <path>`. Do
not stage `src/resources/views` as a directory.

Then commit:

```bash
git commit -m "chore: polish copy, headings, labels, and inline styles"
```

---

### Task 22: Final verification and release gate

**Files:**
- No planned file changes. Fix only regressions found in this task.

**Interfaces:**
- Consumes: all previous tasks.
- Produces: a verified, buildable, test-passing UI with no test edits.

- [ ] **Step 1: Check the working tree before running the gate**

Run:

```powershell
git status --short
```

Expected: only UI files from this plan are modified. The user's pre-existing changes
(`.obsidian/graph.json`, `src/app/Models/Tournament.php`, `src/resources/views/livewire/organizer/bracket-show.blade.php`,
`src/resources/views/livewire/organizer/tournament-create.blade.php`,
`src/resources/views/livewire/organizer/tournament-show.blade.php`,
`src/tests/Feature/Pages/BracketTest.php`, `src/tests/Feature/Pages/TournamentsTest.php`, and
`.kilo/`) may still appear. Do not stage or overwrite them.

Run:

```powershell
git diff --check
```

Expected: no whitespace errors.

- [ ] **Step 2: Build the frontend**

Run:

```powershell
npm run build
```

Expected: Vite build succeeds with no Sass errors and no missing imports. Record the output.

- [ ] **Step 3: Run the quality gate**

Run from `src`:

```powershell
composer quality
```

Expected: Pint passes, PHPStan passes, and the full Pest suite passes. This is the release gate; do
not claim completion without the actual output.

If Pint reformats a presentation-only Livewire hook, include the formatting change in the same UI
commit. If PHPStan reports a new issue, fix the PHP or revert the presentation hook; do not suppress
it.

- [ ] **Step 4: Run the full suite explicitly**

If `composer quality` stops before the tests, run:

```powershell
php artisan test
```

Expected: all tests pass. Pay special attention to:

- `BracketTest` and `GameManageTest` — `Finalize score?`, `Record forfeit?`, `Apply correction?`,
  `Correct score`, `Open scoring`, `ingame-*` test IDs.
- `GameStatsTest` — `Select a match to manage`, `Locked result`, `Game 2 of 3`,
  `Available after Game 2 is final`, `Player points 6 · Final 78 · 72 unassigned`.
- `ScheduleTest` — `Court schedule`, `Court filters`, `Reschedule match`, `Playoffs reserved`,
  `Settings changed - rebuild the preview before publishing.`, `Rebuild schedule`.
- `MatchesTest` — `Every bracket game grouped by matchday, with court, status, and a link to the official box score.`
  and the asserted demo-reset empty state.
- `TournamentsTest` — `Team list`, `Schedule preview`, `Open bracket`, the create defaults, and the
  double-elimination copy.
- `SubmissionsPaymentsTest` — `Pending review`, `Across all tournaments`, `Open registration`,
  `Collected`, `Outstanding`, `Expected`, `Collection rate`, `?1,500`.
- `DashboardTest` — `6 teams unpaid`, `?9,000`, and the coach register/my-team links.
- `ProfileAuditTest` — `8 of 8 entries`, `3 of 8 entries`, `1 of 8 entries`, `Score corrected`,
  `Verified payment for San Antonio Vipers`, `Status: paid`.
- `LoginTest` and `RegistrationTest` — `Quick-fill accounts`, `Create account`, and the auth test IDs.

- [ ] **Step 5: Verify no test or backend files changed**

Run:

```powershell
git status --short src/tests src/app src/routes src/database src/config
```

Expected: no test file changed by this plan; `src/app` shows only presentation hooks under
`src/app/Livewire/**` plus the user's pre-existing `src/app/Models/Tournament.php` edit; nothing under
`src/routes`, `src/database`, or `src/config`. If this plan changed a test file, stop and restore it.
The plan is not allowed to edit tests, routes, or domain code.

- [ ] **Step 6: Run the manual smoke matrix**

Sign in as organizer, coach, and fan. Check each surface at 1920px, 1366px, 1024px, 768px, and 390px.

| Surface | Light | Dark | Keyboard | Reduced motion |
|---|---|---|---|---|
| Dashboard, organizer | ☐ | ☐ | ☐ | ☐ |
| Dashboard, coach | ☐ | ☐ | ☐ | ☐ |
| Dashboard, fan | ☐ | ☐ | ☐ | ☐ |
| Tournaments directory | ☐ | ☐ | ☐ | ☐ |
| Tournament desk | ☐ | ☐ | ☐ | ☐ |
| Create tournament wizard | ☐ | ☐ | ☐ | ☐ |
| Teams directory | ☐ | ☐ | ☐ | ☐ |
| Team profile | ☐ | ☐ | ☐ | ☐ |
| My Team | ☐ | ☐ | ☐ | ☐ |
| Team registration | ☐ | ☐ | ☐ | ☐ |
| Submissions + review dialog | ☐ | ☐ | ☐ | ☐ |
| Payments + verify dialog | ☐ | ☐ | ☐ | ☐ |
| Announcements + composer | ☐ | ☐ | ☐ | ☐ |
| Audit log | ☐ | ☐ | ☐ | ☐ |
| Profile | ☐ | ☐ | ☐ | ☐ |
| Sign in + register | ☐ | ☐ | ☐ | ☐ |
| Bracket + score console | ☐ | ☐ | ☐ | ☐ |
| Schedule + matchups | ☐ | ☐ | ☐ | ☐ |
| Game stats + game scoring | ☐ | ☐ | ☐ | ☐ |
| Standings + public pages | ☐ | ☐ | ☐ | ☐ |

Also verify:

- At 1024px the compact 76px rail is visible with hover labels; at 390px the bottom nav and More
  sheet expose every workspace tool, Profile, My Team, and Log out.
- The More sheet closes on Escape, on backdrop click, and on link click; focus returns to the More
  tab.
- The toast host shows one toast per action, pauses on hover/focus, and dismisses correctly.
- The offline banner appears when the network is disabled and the “Back online” toast appears when
  it returns.
- `/not-a-real-page` shows the branded 404. A coach visiting `/audit` sees the branded 403.
- Print preview of the bracket, schedule, and matchups hides the shell and keeps the content.
- Every `data-testid` in the Global Constraints is still present in the rendered HTML.
- In DevTools, run
  `[...document.querySelectorAll('[data-testid]')].map((el) => el.dataset.testid).filter((id, i, all) => all.indexOf(id) !== i)`
  and confirm the result is empty (no duplicate test IDs on a page).
- The browser console is clean: no Livewire, Alpine, or JavaScript errors, and no 404s for built
  assets.
- Lighthouse in Chrome DevTools on `/dashboard`, `/bracket`, `/schedule`, and `/login`, light and
  dark: accessibility 100, no contrast failures. Record the four scores in the final report. If
  Lighthouse is unavailable, say so explicitly rather than claiming a pass.

- [ ] **Step 7: Commit only the fixes from this task**

If this task required changes, stage each file you changed by full path, then commit. Do not use
`git add .` or stage a directory.

```bash
git status --short
git add path/to/each/changed/file
git commit -m "fix: resolve final UI verification findings"
```

Do not include the user's pre-existing changes in this commit.

- [ ] **Step 8: Report the result**

In the final response, include:

- The exact `npm run build` result.
- The exact `composer quality` result, including the Pest summary.
- The manual smoke matrix result.
- Any item intentionally left out, with the reason.
- Confirmation that no test files were edited and no backend/domain files were touched.
