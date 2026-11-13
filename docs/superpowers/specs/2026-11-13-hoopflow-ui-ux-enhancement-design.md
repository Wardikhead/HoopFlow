# HoopFlow UI/UX Enhancement — Design

- **Date:** 2026-11-13
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Scope:** UI layer only — `src/resources/views`, `src/resources/css`, `src/resources/js`, `src/public`, plus small Livewire view-state hooks only where presentation state cannot be expressed in Blade.
- **Related:** [[2026-10-30-prototype-ui-interactivity-design]], [[2026-11-07-phase-1-shell-theme-design]], [[2026-11-09-hoopflow-subproject2b-bracket-schedule-ui-design]]

## Problem

The product works, but the interface has drifted from the courtside-broadcast identity defined for the prototype. A live audit of the Laravel app found:

- Navigation is incomplete below 992px. The rail disappears and the five-tab bottom bar exposes only Home, Bracket, Schedule, Standings, and Profile. Organizer workspace tools — tournaments, teams, announcements, submissions, payments, audit, create tournament, game stats, and game scoring — become unreachable. Coach Profile is also lost because My Team replaces the fifth tab.
- Feedback is fragmented. Toast markup and `pushToast` logic are duplicated in nine views. Pages without a Livewire component cannot show a toast. Buttons disable during requests but there is no global progress indicator, no list-level updating state, and no consistent skeleton or empty state.
- Accessibility fails in several places. Lighthouse reports 3.55:1 for white on `#ea580c`, 3.55:1 for active bottom-nav labels, and 2.99–4.14:1 for status chips. Every page renders two `<h1>` elements — the topbar title and the page title.
- Layout breaks in the tablet range. At roughly 900px the page header keeps its side-by-side layout, squeezing long tournament names into four or five lines beside five action buttons. The bracket and schedule pages show this most clearly.
- The visual language is applied too uniformly. Long entity names are set in all-caps condensed display type, secondary buttons carry heavy offset shadows, and 67 inline styles override spacing, color, and font size.
- Error and loading surfaces are unbranded. There are no custom 403, 404, 419, 429, 500, or 503 pages, no offline state, no request-failure feedback, and no global form-error summary.
- Copy is written for the rehearsal, not the product: “Three seeded competitions cover registration…”, “4 seeded venues”. The user asked for product-ready copy.

## Goals

1. Deliver a **bold courtside-broadcast redesign** without losing the operational clarity of the current product.
2. Make navigation complete at every width, including all role-specific workspace tools.
3. Create one feedback system: progress, toasts, loading states, validation, empty states, and failures.
4. Fix the contrast, heading, focus, keyboard, and touch-target failures found in the audit.
5. Establish a reusable visual and motion system so future screens do not invent new spacing, colors, or states.
6. Replace demo narration with product-ready copy while keeping the demo accounts available.
7. Keep the change strictly UI-only and preserve the existing Pest suite, `data-testid` attributes, routes, and form contracts.

## Non-goals

- No new domain features, routes, controllers, services, models, migrations, validation rules, or persistence.
- No real-time updates, notification backend, unread counts, search backend, profile editing, or file-upload changes.
- No new client framework, Tailwind, Bootstrap JavaScript, SPA, or new npm UI dependency.
- No PWA/offline caching. The offline banner is informational only.
- No confetti, sound, or looping decorative animation.
- No test edits. Test-asserted copy is preserved; if a copy change would require a test change, work stops for approval.
- No command palette, per-table density toggle, or other power-user feature in this pass. They can follow later.

## Decisions

### 1. Two-speed broadcast

The product has two visual speeds that share one shell and one token set.

| Speed | Surfaces | Character |
|---|---|---|
| **Matchday** | Dashboard, bracket, schedule, game stats, game management, standings, public tournament pages | Ink-dark matchday panels, court-line geometry, oversized numerals, orange signal color, next-up line, expressive motion |
| **Desk** | Tournaments, teams, submissions, payments, announcements, audit, profile, auth | Warm paper surfaces, compact headers, dense tables, quiet cards, restrained motion |

The matchday treatment creates energy where the tournament is happening. Desk surfaces stay calm where organizers compare, verify, and edit. Management screens must not become harder to scan for the sake of the theme.

### 2. Product-ready copy

All screens are written as a real tournament-operations product. Demo/seed narration is removed from page copy. The quick-fill accounts on login stay, framed as “Demo access” under “Quick-fill accounts”, because the walkthrough still needs them.

Copy rules:

- Say what the screen is for, not how the demo is seeded.
- Prefer concrete nouns: “teams”, “courts”, “payments”, “next tip-off”.
- Errors state what happened, why, and what to do next. No blame, no raw exception text.
- Empty states explain the next useful action.

### 3. UI-only boundary

Allowed:

- `src/resources/views/**` — Blade layouts, components, pages, Livewire views, new error views.
- `src/resources/css/**` — tokens, component styles, surfaces, motion, print.
- `src/resources/js/**` — theme/density/motion preferences, global feedback, progress, dialogs, small progressive enhancements.
- `src/public/**` — new static SVG/CSS assets only when a CSS-drawn motif is not sufficient.
- Small Livewire view-state hooks in `src/app/Livewire/**` only when a presentation state cannot be expressed in Blade. Examples: a transient “updating” flag or a view-only flash payload. Presentation only; no validation, persistence, or domain decisions.

Not allowed:

- Routes, controllers, middleware, services, models, migrations, seeders, config, or `.env`.
- Changes to form field names, route names, query parameters, or `data-testid` values.
- Test edits.

### 4. Theme, density, and motion preferences

- Light remains the default; dark remains the “floodlit navy” theme; **System** is added.
- **Comfortable / Compact** density is stored in `localStorage` and applied through `data-density` on `<html>`. Compact reduces card padding, table row height, and section gaps by roughly 20%; it never hides content.
- **System / Reduced** motion is stored in `localStorage` and applied through `data-motion`. The operating-system `prefers-reduced-motion` setting always wins.
- Theme changes crossfade for 180ms. First paint never animates.
- Preferences are managed from Profile, not from a hidden demo menu.

## Visual system

### Color

Keep the existing brand palette, but correct usage and contrast.

| Role | Light | Dark | Notes |
|---|---|---|---|
| Primary action background | `#c2410c` | `#fb7a1e` | White text in light, near-black ink text in dark |
| Primary hover | `#9a3412` | `#ff8f3f` | |
| Brand accent | `#ea580c` | `#fb7a1e` | Decorative marks, indicators, large display type; not small text on light |
| Primary text on light | `#b93d0a` | n/a | Links, active labels, chip text |
| Gold text on light | `#7a5200` | `#f5b301` | Champions and qualification only |
| Success text | `#0b5f49` | `#34d399` | |
| Warning text | `#8a3d07` | `#fbbf24` | |
| Danger text | `#b31632` | `#ff5c7a` | |
| Info text | `#1d4f91` | `#7dd3fc` | New semantic token |

Rules:

- Primary and danger buttons must meet 4.5:1 with their text in both themes.
- Rail active state is a tinted orange surface plus an orange bar and white text, not a solid orange fill.
- Bottom-nav active labels use the darkened primary text in light mode and the bright primary in dark mode.
- Status chips pair an icon with the label and use the darker text tokens above.
- Gold is never used for generic emphasis. It means champion, trophy, or qualification.
- Links stay ink-colored with an orange underline on hover, as decided in the prototype spec.

### Typography

Keep **Barlow Condensed** for display and **Inter** for body.

- Uppercase condensed type is limited to page titles, short section labels, chips, buttons, and numerals.
- Tournament, team, player, venue, and announcement names use **Inter 700 sentence case**.
- The topbar context is no longer a heading. The page owns the only `<h1>`.

Scale:

| Token | Size | Use |
|---|---|---|
| Display XL | `clamp(2.5rem, 4vw, 4rem)` | Matchday page title |
| Display L | `clamp(2rem, 3vw, 3rem)` | Desk page title |
| Display M | `1.5rem` | Section and card titles |
| Title | `1.25rem` | Entity names in cards |
| Body | `0.9375rem` | Default |
| Small | `0.8125rem` | Metadata, table cells |
| Micro | `0.6875rem` | Labels, chips, eyebrows |

Numerals use tabular figures. Body line height stays 1.5; display line height 0.95–1.

### Shape, spacing, and depth

- Radius tokens: 4px, 8px, 12px, 16px, pill.
- Spacing scale: 4, 8, 12, 16, 24, 32, 48, 64.
- Card radius 14px remains; controls use 10px; chips use pill.
- Shadows: `--hf-shadow-rest`, `--hf-shadow-hover`, `--hf-shadow-overlay`.
- Primary and danger buttons keep the 2px darker bottom edge and 1px hover lift. Secondary and ghost buttons drop the heavy offset shadow.
- Interactive cards lift 2px, strengthen the shadow, and slide a 2px arrow. Non-interactive cards do not move.
- Court-line motif: a half-court arc, free-throw key, and baseline drawn in CSS or inline SVG. Used at low opacity in the matchday strip, auth panel, empty states, and error pages. Never behind body text.

### Icons and imagery

- Bootstrap Icons only. 18–20px in controls, 24px in empty states.
- Icon-only controls always have `aria-label` and a tooltip.
- Team shields keep the monogram and team color, with a subtle inner ring for definition.
- No new raster imagery. Decorative art is CSS/SVG.

## Shell and navigation

### Breakpoints

| Width | Navigation |
|---|---|
| ≥1200px | Full 248px press-box rail |
| 768–1199px | 76px compact icon rail with hover/focus tooltips |
| <768px | Five-tab bottom bar plus More sheet |

The 768–1199px compact rail replaces the current dead zone. It keeps every destination reachable on small laptops and tablets. Below 768px, the bottom bar is **Home, Bracket, Schedule, Standings, More**. Tournaments, Teams, Announcements, the role’s workspace tools, Profile or My Team, theme, density, and logout live in the More sheet.

### Desktop rail

- Brand lockup and active-tournament context chip.
- “Matchday” group: Dashboard, Tournaments, Teams, Bracket, Schedule, Standings, Announcements.
- “Workspace” group by role: organizer gets Submissions, Payments, Audit, Create tournament, Game stats, Game scoring; coach gets My Team, Register team; fan gets no workspace group.
- Active item: 3px orange left bar, tinted orange surface, white label, orange icon.
- Hover: surface tint and a 1px icon slide.
- Footer: user card with name and role. Opens a menu with Profile, Appearance, Keyboard shortcuts, and Logout. No full-width red logout button.

### Compact rail

- Brand icon only.
- Same destinations, grouped by separators.
- Icon-only links with tooltips that show on hover and keyboard focus.
- User avatar at the bottom opens the same user menu.
- Badge counts, if present, become small dots.

### Mobile bottom bar and More sheet

- Five equal tabs; active tab has a 3px top indicator and a darkened label.
- Safe-area padding at the bottom; content padding accounts for the bar.
- More opens a native `<dialog>` bottom sheet with a drag handle, grouped links, theme control, and logout.
- Sheet closes on backdrop click, Escape, and route navigation; focus returns to the More tab.
- The sheet is scrollable when the viewport is short.

### Topbar

- Desktop: breadcrumb/context on the left; active tournament and role context; actions on the right — theme, announcements, user menu.
- Mobile: back button on detail pages, current page label, overflow menu. Tournament context hides below 480px.
- No second `<h1>`. The topbar title becomes a `<p>` with a small uppercase label style.
- The notification dot is removed until unread data exists. Do not fake an unread count.

### Breadcrumbs and page header

- Detail pages: Home / Tournaments / [Name] / Bracket. Mobile uses a back button.
- **Matchday strip:** dark ink panel, court-line motif, status chip, page title, date/venue/courts, primary action, and a “Next up” line. Used on dashboard, tournament show, bracket, schedule, game stats, standings, and public pages. A desk surface may use the strip for tournament identity while keeping the rest of the page calm.
- **Desk header:** surface panel with a 4px orange left rule, page title, one-line description, metadata, and actions. Stacks below 1200px so the title never competes with five buttons.
- On mobile, secondary page actions collapse into an overflow menu; the primary action stays visible.

### Content width

- Add `--hf-content-max: 1520px` and center `.hf-main` content.
- Matchday strips and full-width tables may span the content width, not the viewport.
- Prose remains capped at 68ch.

## Feedback and error handling

### Global progress

- A 2px orange bar fixed to the top of the viewport.
- Driven by Livewire 4 `Livewire.interceptRequest` with `onSend` and `onFinish`.
- Appears after 150ms to avoid flicker; stays for at least 300ms.
- Indeterminate animation; `aria-hidden="true"`.
- Disabled under reduced motion.

### Global toast host

- One Alpine component in the app shell listens for `hf-toast` window events.
- Removes the nine duplicated toast blocks and their inline `pushToast` implementations.
- Tone: success, warning, danger, info. Each toast has an icon, title, message, close button, and a thin timer bar.
- Maximum three visible; older toasts collapse.
- Auto-dismiss after 4.2s. Pauses on hover and focus.
- `aria-live="polite"` for normal tones; `role="alert"` for danger.
- The existing announcement-published flash dispatches into the same host.
- No toast steals focus.

### Loading states

- Buttons: `wire:loading.class="is-loading"` and `aria-busy="true"`. A small spinner replaces the leading icon; the label remains unless the operation is long-running.
- Result regions: `wire:loading.class="is-updating"`, 60% opacity, an “Updating…” pill, and `aria-busy="true"`.
- Slow lists: skeleton rows shaped like real cards or table rows.
- Filters: debounced input, pending state, and an `aria-live` result count.
- Page navigation: top progress bar plus a short content fade. No full-page spinner.

### Validation

- Every field has `aria-invalid`, `aria-describedby`, and an inline error with `role="alert"`.
- Forms get an error summary above the fields: “Please fix 3 fields”, with anchor links to each error.
- After a failed submit, focus moves to the first invalid field.
- Disabled actions explain why in helper text or a tooltip.
- Destructive actions always use the shared dialog, never `confirm()`.

### Request failures and offline

- Network failure: toast “Connection lost. Your change may not have saved.” with a Retry action.
- Server error: toast “That action didn’t go through. Try again.” and preserve form state.
- Offline: persistent banner using `online`/`offline` events — “You’re offline. Changes won’t save until you reconnect.” A brief “Back online” confirmation follows reconnection.
- No raw exception messages reach the user.

### Branded error pages

Add `403`, `404`, `419`, `429`, `500`, and `503` views with the brand, court-line motif, plain-language explanation, and two actions. 419 explains session expiry; 429 explains rate limiting; 500/503 avoid technical detail.

### Empty states

One shared shape: court-line or icon mark, title, one-line explanation, primary action, optional secondary link. Every list gets one, including dashboard announcements. Filter empty states name the active filters and offer “Clear filters”.

## Components and interaction states

Every interactive component supports **rest, hover, focus-visible, active, selected, disabled, loading, error, and success**.

- **Buttons:** primary, secondary, ghost, danger, icon; small, medium, large. Primary keeps the press edge. Secondary is a quiet bordered surface.
- **Cards:** base, interactive, stat, match, tournament, announcement, team. Interactive cards get hover lift, shadow, and arrow.
- **Tables:** sticky header, row hover, selected row with orange inset bar, right-aligned tabular numerals, captions, `scope`, and a mobile stack variant on every table.
- **Forms:** label, optional marker, 44px control, focus ring, help text, error text, success state. Selects and date inputs match text inputs.
- **Dialogs:** consistent header/body/footer, sizes, `aria-labelledby`, native focus trap, Escape, focus restore. Short content becomes a bottom sheet below 576px.
- **Tabs, segmented controls, round pager:** one active-indicator pattern, arrow-key navigation, `aria-selected`.
- **Chips:** icon + label, semantic tone, accessible contrast. No color-only meaning.
- **Tooltips:** icon buttons, truncated names, locked actions, schedule cells.
- **Skeletons:** text, card, table-row, bracket-match.
- **Callouts:** info, warning, danger, success.
- **Breadcrumbs, user menu, More sheet, matchday strip:** shared components with the same focus and hover rules.

## Motion and delight

Motion tokens: 120ms micro, 180ms standard, 280ms expressive. Ease-out with a slight overshoot only for score pops. Transform and opacity only.

- Page content enters with a 6px fade-up, staggered 40ms per section, only on full page load.
- Nav active indicator grows in; rail icons slide 1px on hover.
- Buttons press down; cards lift.
- Dialogs scale from 0.98 with a backdrop fade; mobile sheets slide up.
- Toasts slide in; timer bar drains; pause on hover.
- Score changes pop `1 → 1.12 → 1` with a brief orange flash on the changed side.
- Bracket winner paths draw on advance; champion card gets one restrained gold glow.
- KPI numbers count up once on first load, never on every Livewire update.
- Theme changes crossfade for 180ms; first paint never animates.
- Skeletons shimmer once softly; reduced motion makes them static.

Delight details:

- “Next up” line in the matchday strip with next tip-off, court, and matchup.
- Score console `+1 / +2 / +3` press states and keyboard shortcuts: `1`, `2`, `3`, `U` for undo, `Enter` to finalize.
- Bracket champion card includes a “road to the title” path.
- Court-line empty states.
- Print-ready bracket and schedule with a Print action.
- **Scorekeeper mode:** a toggle on game management hides the rail/topbar and enlarges scoring controls for a courtside tablet. Persisted for the session.

Reduced motion disables transforms, count-ups, path drawing, shimmer, and looping transitions. Opacity changes remain.

## Page treatment

### Auth

Desktop split canvas: brand and court-line panel on the left, form on the right. Mobile collapses to one card. Product-ready headline and helper copy. Quick-fill accounts remain under “Demo access”. Add inline validation, caps-lock hint, loading submit button, and error summary.

### Dashboard

- Organizer: matchday strip; large next-action card; KPI row with one-time count-up; active tournament with milestone progress (Round of 16 → Quarterfinals → Semifinals → Final); recent results; standings snapshot; announcements with a compact empty state.
- Coach: team hero; readiness checklist with progress ring; next game; open tournaments.
- Fan: broadcast-style public hero; up-next games; recent results; standings; announcements.
- Remove “seeded” copy.

### Tournaments

- Index: labeled filters, active filter chips, result count, Reset, and cards with status, division, sentence-case name, venue/date, team progress, fee, and quick actions.
- Show: matchday strip, readiness checklist (teams / bracket / schedule / payments), page tabs, grouped organizer controls.
- Create: three-step wizard, sticky footer, progress rail, review summary, error summary. No new actions.

### Teams

- Index: selectable tournament cards with check state and hover; search; team cards with shield, coach, city, record, roster, status.
- Show: shield hero, coach/city/record/roster, eligibility, results, bracket position.
- Coach My Team / Register: readiness panel, roster cards, add/edit player dialogs, payment reference, clear locked-state explanations.

### Submissions

Desktop queue + detail split view; mobile full-height detail sheet. Eligibility checklist with pass/fail/needs-review icons, document status, and payment reference. Approve/reject dialog with consequence and rejection reason. Empty state when the queue is clear.

### Payments

KPI row with collected, outstanding, expected, collection rate, and a progress bar. Visible filter labels. The dead “Select a tournament…” banner becomes a contextual hint only. Rows show method, reference, amount, status, and Verify. Verification dialog shows a receipt preview when a file exists, with a missing-file fallback.

### Bracket

Desktop tree with ink connectors, sticky round headers, active-path highlight, and fit/zoom. Mobile round pager with progress, match cards, and a sticky round label. Match dialog, score console, forfeit, and correction flows keep their current labels. Champion card gets the gold glow and road-to-title path. Score console adds keyboard shortcuts and a clearer finalize step.

### Schedule and matches

Matchday strip with dates, courts, slot length, and primary generate/publish action. Month calendar with today marker, event counts, reserved playoff days, legend, hover preview, and arrow-key navigation. Mobile week strip plus selected-day summary. Court filter chips and grouped match list. Generation preview uses warning callouts for conflicts and rest violations. Print styling for the match list.

### Game stats and game management

Broadcast scoreboard header with team panels, large score, period navigation, and status. Player stats table with sticky header and totals. Sticky action bar for Save, Publish, and Correct. Scorekeeper mode. Unsaved-change warning when leaving a live scoring screen.

### Standings

Sticky team column, qualification line, seed badges, tabular numerals. Row highlight when standings update. Mobile card list. Public standings use the matchday treatment.

### Announcements

Feed with pinned notices, category and audience chips, author/time, and detail dialog. Filter bar with active chips and result count. Delete confirmation and empty state. Product-ready copy.

### Audit

Timeline/table toggle, filters by actor/action/date, readable diffs, and an empty state. Clear “who changed what, when” hierarchy.

### Profile

Identity card, role scope, appearance panel (Light / System / Dark, Comfortable / Compact, System / Reduced), session card with logout, and a keyboard-shortcut reference. Profile editing remains out of scope.

### Public and landing

Align the landing page typography, orange, and motion tokens with the app shell. Public tournament, bracket, schedule, and standings pages use the matchday treatment. Add a meta-description slot for public pages.

### Error and print surfaces

Branded error pages as described above. Print styles for bracket, schedule, and match list.

## Accessibility

- Exactly one `<h1>` per page and logical heading order.
- Primary/action text meets 4.5:1; large display text meets 3:1.
- `aria-current="page"`, `aria-expanded`, `aria-controls`, `aria-busy`, `aria-live`, and `aria-describedby` used consistently.
- Dialogs and More sheet are labelled, focus-trapped, Escape-closable, and restore focus.
- Keyboard support for rail, bottom bar, tabs, calendar, status select, More sheet, and score console.
- Touch targets at least 44px.
- Visible focus rings in both themes and forced-colors mode.
- Tables have captions and `scope`; icon-only controls have names.
- Reduced-motion and 200% zoom/reflow checks.

## Performance

- No new UI framework, Bootstrap JS, Tailwind, or npm UI dependency.
- Animation limited to transform and opacity.
- Skeletons reserve content dimensions to avoid layout shift.
- Decorative art is CSS/SVG, not raster.
- CSS is split into tokens, base, shell, components, surfaces, pages, motion, and print partials. `_hoopflow.scss` becomes an import wrapper.
- Lighthouse target: accessibility 100, no contrast failures, best practices ≥95 on dashboard, bracket, schedule, and login.

## Rollout

| Phase | Scope | Outcome |
|---|---|---|
| 0 | Audit, CSS split, tokens, primitives, contrast fixes | Foundation without visual breakage |
| 1 | Shell: compact rail, More sheet, topbar, breadcrumbs, single H1, user menu | Navigation complete at every width |
| 2 | Feedback: progress, toast host, loading, validation, error pages, offline banner | Every action reports what is happening |
| 3 | Matchday: dashboard, bracket, schedule, game stats, standings, public pages | Broadcast identity lands |
| 4 | Desk: tournaments, teams, submissions, payments, announcements, audit, profile, auth | Consistency and density across the product |
| 5 | Motion, delight, print, full QA | Finish pass |

Each phase is independently shippable.

## Guardrails and risks

- **Test-sensitive copy.** Tests assert strings such as “Quick-fill accounts”, “Create account”, “Finalize score?”, “Record forfeit?”, “Apply correction?”, “Correct score”, “Open scoring”, “8 of 8 entries”, “Tournament created”, and “Schedule published”. Preserve every asserted string and every `data-testid`. If a product-copy change requires a test edit, stop and ask.
- **Livewire morphs.** Native dialogs keep open state outside the DOM attributes Livewire morphs. The existing re-open hook must not resurrect a dialog the user closed. Audit it before changing dialog markup.
- **Global toast host.** Register listeners once; Livewire morphs must not duplicate them.
- **Bold versus noisy.** Matchday drama must not leak into dense desk screens.
- **Small Livewire hooks.** Only presentation state. Any hook that touches validation, persistence, or domain decisions is out of scope.
- **No route or form contract changes.** Field names, route names, query parameters, and form actions stay identical.

## Acceptance criteria

- Every destination is reachable at 390px, 768px, 1024px, and 1366px, including organizer workspace tools and coach Profile.
- Every page has exactly one `<h1>`.
- Lighthouse accessibility is 100 with no contrast failures on dashboard, bracket, schedule, and login in both themes.
- One global toast host; no duplicated toast markup remains.
- Every Livewire action shows a loading state or progress indicator.
- Branded 403/404/419/429/500/503 pages exist.
- Page headers no longer squeeze titles at 768–1199px.
- Light and dark themes are at parity.
- Existing Pest suite passes without test edits; `composer quality` and `npm run build` pass.
- All `data-testid` values remain intact.
- No backend files outside the allowed presentation hooks are modified.

## Verification

1. `composer quality` and `npm run build`.
2. Existing Pest suite, unchanged.
3. Lighthouse light and dark on dashboard, bracket, schedule, and login.
4. Screenshots at 390, 768, 1024, 1366, and 1920 widths in both themes.
5. Keyboard-only walkthrough: shell → More sheet → dialog → status select → calendar → score console.
6. Offline and failed-request checks.
7. Reduced-motion check.
8. Print preview for bracket and schedule.
9. Console clean; no duplicate `data-testid` values.

## Open questions

None. The notification dot is deferred until unread data exists; scorekeeper mode is included; print styles are included; command palette and per-table density toggle are explicitly out of scope for this pass.
