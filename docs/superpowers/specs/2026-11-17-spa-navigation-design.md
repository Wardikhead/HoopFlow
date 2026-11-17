# Single-Page Navigation (AJAX Page Transitions) — Design

- **Date:** 2026-11-17
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Scope:** `src/resources/js`, `src/resources/views`, `src/app/Livewire`, `src/config`, `src/tests`. No route, controller, domain, migration, or model changes.
- **Related:** [[2026-11-14-nav-toggle-design]], [[2026-11-07-phase-1-shell-theme-design]], [[2026-11-15-confirmations-undo-password-strength-design]]

## Problem

Every link in the authenticated shell is a full browser navigation. The document,
CSS bundle, and JavaScript bundle are re-downloaded and re-parsed; the rail,
topbar, More sheet, and logout dialog are rebuilt byte-for-byte on every click.
The shell markup is identical on every authenticated destination, so this work is
pure waste. On a slow connection the whole screen is replaced by the skeleton
loader even though only the `<main>` content changed.

Livewire 4 already ships the transport: `wire:navigate` fetches the next page in
the background, swaps the `<body>`, updates the URL and `<title>`, honors
back/forward, and restores scroll. There is no page cache in 4.4.8, so every
visit still reaches the server and gets fresh data.

The real gap is the client bootstrap. `resources/js/app.js` runs every initializer
once when the bundle loads. After a body swap that would leave page-scoped modules
dead: bracket zoom, calendar arrow keys, the live-score poller, dashboard
count-up tiles, scorekeeper dirty tracking, and the password meter. Shell
listeners attached to elements would also die if a morph ever replaces them.

## Goals

1. Rail, mobile tabs, More sheet, brand, topbar bell, and account links never
   reload the browser.
2. In-page internal links (cards, table rows, "View", pagination, back links)
   navigate the same way, without editing ~170 anchors by hand.
3. Redirects after Livewire mutations (create tournament, save/publish game,
   switch standings filter) also swap content instead of reloading.
4. Page-scoped JavaScript re-arms after every swap and tears down cleanly
   before the next one.
5. One progress indicator, focus moved to the new content, and a visible failure
   path when a navigation request fails.
6. Preserve every route, policy, `data-testid`, form contract, and
   test-asserted string.

## Non-goals

- No SPA framework, no Vue/React/Turbo/HTMX, no new npm or Composer package.
- No change to the landing page, login, register, or the guest header. The
  feature applies only where the authenticated `.hf-app` shell is rendered.
- No conversion of plain forms (logout, profile update, login, register) to
  AJAX. Logout must destroy the session with a real submit.
- No page caching, hover prefetch, view transitions, or `@persist` shell.
- No new routes, controllers, services, or domain behavior.
- No navigation animation beyond the existing progress bar and reveal motion.

## Decisions

| Decision | Choice |
|---|---|
| Transport | Livewire 4 `wire:navigate` / `Livewire.navigate()` |
| Shell links | Explicit `wire:navigate` on rail, mobile tabs, sheet, brand, bell, account links |
| Content links | One delegated interceptor in `spa-nav.js` as a safety net |
| Guest pages | Untouched; interceptor requires `.hf-app` |
| Redirects | `$this->redirect(..., navigate: true)` on the 13 Livewire redirects |
| Progress | Existing `.hf-progress` bar; disable Livewire's navigate bar |
| Full-page skeleton | Kept for genuine full loads (logout, form POST, login/register) |
| JS lifecycle | Globals once; page init on `livewire:navigated`; teardown on `livewire:navigate` |
| Active nav | Server-rendered `is-active` / `aria-current`; morph updates them |
| Focus | `<main id="main">` after each swap plus a polite page announcement |
| Flash toast | Payload rendered in a `data-` attribute and consumed once per page |
| Prefetch | Livewire's default mousedown strategy only; no `.hover` |

## Architecture

### 1. Link handling

#### Shell links (explicit)

Add `wire:navigate` to:

| Location | File |
|---|---|
| Rail brand | `resources/views/layouts/public.blade.php` |
| Rail links | `resources/views/components/app-nav.blade.php` (`rail` variant) |
| More sheet links | `resources/views/components/app-nav.blade.php` (`sheet` variant) |
| Mobile bottom tabs | `resources/views/layouts/public.blade.php` |
| Topbar announcements bell | `resources/views/layouts/public.blade.php` |
| User menu Profile / Appearance / Shortcuts | `resources/views/components/user-menu.blade.php` |
| More sheet account links | `resources/views/layouts/public.blade.php` |

Cross-page hash links such as `/profile#appearance` are allowed. Same-page hash
links are ignored by Livewire, so the browser handles the scroll.

#### Content links (interceptor)

New module `resources/js/ui/spa-nav.js` exports:

- `shouldInterceptLink(descriptor, location)` — pure, testable predicate.
- `initSpaNavigation()` — one `document` click listener in the bubble phase.

The listener runs after element-level handlers. It calls `preventDefault()` and
`window.Livewire.navigate(path)` only when all of these hold:

- Left button, no `metaKey` / `ctrlKey` / `shiftKey` / `altKey`.
- `event.defaultPrevented` is still false.
- The shell is present: `document.querySelector('.hf-app')` is not null.
- The link is same-origin `http:` or `https:`.
- No `target` (or `target="_self"`), no `download`, no `data-no-navigate`.
- Not `href=""`, `#...`, `javascript:`, `mailto:`, `tel:`.
- Not the current URL, and not a same-page hash.
- No `wire:navigate` (Livewire already owns it).
- No `wire:click`, `@click`, `x-on:click`, or `x-bind:href` (someone else owns it).
- `window.Livewire` exists; otherwise leave the click alone and let the browser
  perform a normal navigation.

Eligible links inside a Livewire component work because the interceptor is
delegated on `document`, not bound to specific nodes.

### 2. JavaScript lifecycle

`resources/js/app.js` becomes a thin entry point. New
`resources/js/ui/page-lifecycle.js` owns three phases.

**`initGlobals()` — once per browser load**

- `initPageLoader()` (navigation-aware, below)
- `initFeedback()` (progress bar, offline banner, request errors)
- `initShell()`, `initNavRail()`, `initMoreSheet()`, `initLogoutDialog()`
- `initPreferences()`, `registerToasts()`, `initValidation()`
- `initBracketZoom()`, `initCalendar()`, `initScoreConsole()`
- `keepModalsOpen()`, `restoreDialogFocus()`

**`initPage()` — initial load and every `livewire:navigated`**

- `initMotion()` (reveal classes)
- `initCountUp()`
- `initLiveScores()` (start a fresh poller)
- `initScorekeeper()` (per-form dirty tracking)
- `registerPasswordMeter()`
- `syncPreferenceButtons()`
- `refreshValidation()` (re-run the error summary sync)
- Focus `<main id="main">` and update the page announcer
- Consume a pending flash toast

**`teardownPage()` — every `livewire:navigate`**

- Stop the live-score poller.
- Close the More sheet and the logout dialog if either is open.
- Remove a stale rail tooltip.

`initPage()` is guarded by the current URL so the initial `livewire:navigated`
does not double-run it.

#### Module refactors

| Module | Change |
|---|---|
| `live-scores.js` | `initLiveScores()` returns `{ stop }`; calling it again stops the previous instance first. |
| `scorekeeper.js` | Split the document listener (globals, behind a flag) from the per-form dirty listeners (page). |
| `shell.js` | `initNavRail` re-attaches after a swap and re-creates the tooltip if the body morph removed it. More-sheet open/close/backdrop and `close` use delegated listeners so they survive replacement. |
| `logout.js` | Logout form submit, cancel, confirm, backdrop, and `cancel` use delegated/capture listeners; state lives in module scope, not in a closure over one dialog node. |
| `feedback.js` | Export `beginNavigation()` / `finishNavigation()` for the navigate hooks. |
| `validation.js` | Export `refreshValidation()`; keep the global `Livewire.hook('morphed')` registration in `initValidation()`. |

### 3. Loading and failure feedback

- Publish a minimal `src/config/livewire.php` containing:
  ```php
  'navigate' => [
      'show_progress_bar' => false,
      'progress_bar_color' => '#2299dd',
  ],
  ```
  Laravel merges this with the package defaults.
- `livewire:navigate` → `beginNavigation()` (the existing `.hf-progress` bar).
- `livewire:navigated` → `finishNavigation()`.
- A 15-second watchdog finishes the bar and dispatches a danger toast if no
  `livewire:navigated` arrives.
- The full-screen skeleton keeps its current click/submit logic. Because both
  Livewire and the interceptor call `preventDefault()`, a navigate click never
  schedules it. Real full loads (logout, login, register, profile POST) still
  show it.
- Flash toast: render `data-flash-toast="@js($flashToast)"` on the toast region.
  On each page init, if the attribute exists, dispatch `hf-toast` once and remove
  the attribute. The existing `x-init` is removed so there is exactly one path.

### 4. Redirects

Add `navigate: true` to these Livewire calls:

| File | Lines |
|---|---|
| `app/Livewire/Organizer/AnnouncementCreate.php` | 133 |
| `app/Livewire/Public/StandingsShow.php` | 90 |
| `app/Livewire/Organizer/GameManage.php` | 828, 839, 919 |
| `app/Livewire/Organizer/BracketShow.php` | 827 |
| `app/Livewire/Organizer/GameStatsShow.php` | 687, 783, 833, 871, 944, 999 |

Controller redirects in `AuthController` and `ProfileController` stay as real
redirects.

### 5. Accessibility and edge cases

- After each swap, focus `<main id="main" tabindex="-1">` and set a visually
  hidden polite live region to the new page title. The region reuses the existing
  `.hf-sr-only` utility in `_base.scss`, so no CSS change is needed.
- The server still renders `is-active` and `aria-current="page"` on the current
  link; the body morph updates them on every visit.
- Back/forward, scroll restoration, and title updates come from Livewire.
- A navigate request that redirects to login (expired session) swaps in the guest
  layout. The interceptor then stops applying because `.hf-app` is gone.
- External links, downloads, hash links, and links owned by `wire:click` are
  never intercepted.
- If JavaScript fails, every link still works as a normal anchor.

## Files

| File | Change |
|---|---|
| `src/resources/js/ui/spa-nav.js` | New interceptor and pure eligibility predicate. |
| `src/resources/js/ui/page-lifecycle.js` | New globals / page / teardown orchestration. |
| `src/resources/js/app.js` | Register the lifecycle, remove one-shot init calls. |
| `src/resources/js/ui/loading.js` | Navigation hooks; skeleton only for full loads. |
| `src/resources/js/ui/feedback.js` | Export navigation begin/finish helpers. |
| `src/resources/js/ui/live-scores.js` | Restartable poller with `stop()`. |
| `src/resources/js/ui/scorekeeper.js` | Globals/page split. |
| `src/resources/js/ui/shell.js` | Re-arm rail and sheet after a swap. |
| `src/resources/js/ui/logout.js` | Delegated dialog handling. |
| `src/resources/js/ui/validation.js` | Export `refreshValidation()`. |
| `src/resources/views/layouts/public.blade.php` | `wire:navigate` on shell links, announcer, flash data attribute. |
| `src/resources/views/components/app-nav.blade.php` | `wire:navigate` on rail and sheet links. |
| `src/resources/views/components/user-menu.blade.php` | `wire:navigate` on account links. |
| `src/app/Livewire/Organizer/AnnouncementCreate.php` | `navigate: true`. |
| `src/app/Livewire/Public/StandingsShow.php` | `navigate: true`. |
| `src/app/Livewire/Organizer/GameManage.php` | `navigate: true` (3). |
| `src/app/Livewire/Organizer/BracketShow.php` | `navigate: true`. |
| `src/app/Livewire/Organizer/GameStatsShow.php` | `navigate: true` (6). |
| `src/config/livewire.php` | Disable Livewire's duplicate navigate progress bar. |
| `src/tests/js/spa-nav.test.js` | Eligibility matrix. |
| `src/tests/Feature/Accessibility/SpaNavigationTest.php` | Shell renders `wire:navigate`; guest does not. |
| `docs/superpowers/specs/2026-11-17-spa-navigation-design.md` | This spec. |

## Contracts Preserved

- Every route, controller action, policy, and Livewire action keeps its behavior.
- `data-testid` values (`nav-*`, `mobile-nav-*`, `logout`, `toast-dismiss`, etc.)
  are unchanged.
- Logout remains a real POST that invalidates the session.
- Login, register, and profile remain real form submissions.
- The landing page and guest header are not modified.
- No test file is edited except the two new test files.
- `.obsidian/` and `.kilo/` are never staged.

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Shell elements are replaced by the body morph, killing listeners. | Delegate shell controls on `document`; re-create the rail tooltip on `navigated`; keep a URL guard so page init never double-runs. |
| A page-scoped initializer is missed, so a page ships with dead JS. | Walk every module in `app.js` and classify it as globals or page; browser pass over every destination. |
| Two progress bars appear. | Disable Livewire's via `config/livewire.php`; reuse `.hf-progress`. |
| A navigation request hangs or errors. | 15s watchdog finishes the bar and shows a retry toast. |
| Flash toast is shown twice or not at all. | Single `data-flash-toast` path consumed once per page init; browser test on announcement publish. |
| Interceptor hijacks a link that must do something else. | Skip `wire:click`, Alpine click handlers, `data-no-navigate`, downloads, external, hashes, and links already using `wire:navigate`. |
| Livewire is unavailable or the bundle fails. | Interceptor no-ops when `window.Livewire` is missing; links behave as anchors. |
| Back/forward shows stale content. | No page cache in 4.4.8; live-score page still polls on arrival. |

## Verification

1. `npm run build` exits 0 and the bundle contains the new modules.
2. `node --test tests/js` passes, including the new eligibility matrix.
3. `composer quality` passes (Pint, Larastan, full Pest suite).
4. Browser smoke test with the seeded demo:
   - Set `window.__spaMarker = 'alive'`, click every rail destination, return to
     Dashboard, and confirm `window.__spaMarker` is still `'alive'` (no reload).
   - Click a content link (tournament card, team row, bracket match) and confirm
     the same.
   - Confirm the progress bar appears and clears.
   - Confirm the active rail item and `aria-current` follow the page.
   - Bracket zoom buttons, schedule arrow keys, game-stats score console, and
     dashboard count-up still work after a navigate.
   - Live board polls after arriving and stops when leaving.
   - Create an announcement and confirm the success toast still appears after the
     navigate redirect.
   - Log out and confirm the full-screen loader and a real session-ending submit.
