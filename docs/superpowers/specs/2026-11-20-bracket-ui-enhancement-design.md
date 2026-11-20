# Bracket UI Enhancement — Arena Tree — Design

- **Date:** 2026-11-20
- **Owner:** Jeward T. Mahinay
- **Status:** Approved
- **Scope:** Frontend only — `src/resources/views`, `src/resources/css`, `src/resources/js`
- **Related:** [[2026-11-13-hoopflow-ui-ux-enhancement-design]], [[2026-11-09-hoopflow-subproject2b-bracket-schedule-ui-design]], [[2026-11-12-bracket-series-schedule-ux-design]]
- **Approved direction:** A · Arena Tree (from `.superpowers/brainstorm/bracket-ux/content/bracket-directions.html`)

## Problem

The bracket page works, but it reads as a stack of cards rather than a tournament board:

- Rounds are separate columns with no visual connection, so the progression from the Round of 16 to the Championship Final is not legible at a glance.
- Cards are top-aligned inside each round, so a quarterfinal does not sit between the two round-of-16 games that feed it.
- The final is visually identical to a first-round game. Nothing signals that it decides the title.
- The zoom control scales the whole canvas with `transform`, which does not change the scroll area. At zoom levels below 1 the page keeps the original height and at levels above 1 the board clips.
- The mobile round pager works but has no tab semantics, no swipe, and no section context.
- Round-robin tournaments are forced into a tree layout even though their rounds are matchdays, not playoff rounds.
- Light theme renders the bracket as ordinary white cards; it misses the courtside-broadcast identity already used by the matchday header and landing page.

## Goals

1. Make the playoff bracket read as a true NBA-style tree: aligned rounds, connector lines, a featured final, and a visible road to the title.
2. Preserve the HoopFlow identity: navy arena surface, orange signal color, gold championship accent, Barlow Condensed labels, and team shields.
3. Add one memorable interaction: hovering or focusing a matchup traces its path through the bracket.
4. Make the board usable at every width: fit/zoom/jump controls on desktop, an upgraded round stepper with swipe on mobile.
5. Keep round-robin as a matchday grid using the same card language instead of forcing a tree.
6. Preserve every existing `data-testid`, `wire:click`, keyboard handler, dialog flow, and test-asserted string.
7. Respect reduced motion, forced colors, keyboard focus, and print.

## Non-goals

- No backend changes: no models, migrations, seeders, services, routes, controllers, middleware, validation, or Livewire component logic.
- No change to the bracket data contract or the order of sections and rounds supplied by `BracketShow`.
- No new npm dependency, CSS framework, font, or icon set.
- No true feeder metadata. The data model does not store parent match ids, so connectors represent adjacent-round progression, not a guaranteed bracket lineage. Single elimination and the winners bracket map cleanly; losers-bracket lines are visual progression. See “Connector graph” below.
- No animation loop. Live games use the existing status chip, not a pulsing beacon.
- No redesign of the champion hero, KPI tiles, dialogs, or the match detail content.
- No test edits.

## Decisions

### 1. One arena stage, sections inside it

All playoff brackets render inside a single dark `.hf-arena` panel. The panel uses `--hf-rail` as its base so it stays navy in both themes, matching the matchday strip and landing-page bracket. The arena contains:

1. A compact top row: eyebrow, progress summary, zoom controls, and “Jump to final”.
2. A progress bar for completed games.
3. One `.hf-bracket-section` per data section (`Bracket`, `Winners bracket`, `Losers bracket`, `Grand final`, `Playoffs`, `Group stage`).
4. A small footer line explaining the hover interaction.

Section titles remain `<h2>` elements and keep their exact server-supplied text. The section header also shows round count and game count. In double elimination the three sections stack, each with its own tree; connectors never cross sections.

Round-robin group sections do not render a tree. Any section with key `group` renders a matchday grid. A round-robin tournament that has generated playoffs renders the group as matchdays and the `playoffs` section as a tree in the same arena.

### 2. Tree layout and alignment

The tree is a horizontal flex row of rounds:

- `.hf-bracket` is the horizontal scroller and carries the existing `data-testid="bracket-tree"` and `data-bracket-canvas`.
- `.hf-bracket-tree` is the inner positioning context (`.position: relative`), holds the SVG connector layer and the round columns, and is the element that receives CSS `zoom`.
- Each round is a column with a fixed-basis width (`~232px`) that can grow on wide screens, and a body that distributes its cards with `justify-content: space-around`.
- Cards use a consistent minimum height and single-line rows. With equal card heights and equal gap, `space-around` places each next-round card exactly at the midpoint of its two feeders. This is the same geometry used by the approved mockup.
- A section with one round (Grand final) centers its single card in a taller stage so the final feels like a trophy presentation, not an afterthought.

Each round header shows the round label, game count, and a thin completion meter. Headers align at the top of the board.

### 3. Match card

The card keeps its root contract exactly:

- `class="hf-bracket-match hf-bracket-match--{status}"`
- `data-match-id`, `data-testid="bracket-match-{id}"` and the `-mobile` suffix
- `role="button"`, `tabindex="0"`
- `wire:key`, `wire:click="openMatchDetail"`, and Enter/Space handlers

New internal structure:

| Row | Content |
|---|---|
| Top | Round/slot label, rescheduled chip when present, status chip |
| Teams | Two rows with team shield, name, and large Barlow score. Winner is bright with an orange score; loser is dimmed after a decided game. |
| Footer | Court and tip-off when scheduled; “Schedule TBD” otherwise. Series cards also show `Best of X · Game Y`, the series score, and win pips. |

The final card gets `is-featured`: a gold border, a faint trophy watermark, and gold score/pip accents. It applies to the last section’s last round when the section is `grand_final`, `main`, or `playoffs`. Winners Final and Losers Final stay ordinary until the Grand Final.

The root gets a descriptive `aria-label` (round, slot, teams, scores, status) and a focus-visible ring. The score values, “Best of X”, and “Game Y” text stay verbatim.

### 4. Connector layer

An SVG (`data-bracket-lines`, `aria-hidden="true"`, `wire:ignore`) is absolutely positioned behind the cards inside `.hf-bracket-tree`.

For each adjacent round pair:

- Connect the right edge of every feeder to the left edge of the next card.
- Draw an elbow path: `M x1 y1 H midX V y2 H x2`, with round line joins and caps.
- Map feeders with a count ratio. Eight to four connects cards 1–2 to the first quarterfinal, 3–4 to the second, and so on. Four to four connects one-to-one; four to two connects pairs; two to one connects both; one-to-one is a straight line.
- Each path carries `data-from` and `data-to` match ids and a base class.

Connector appearance:

- Base: `rgba(243, 245, 249, 0.20)`, 1.5px.
- Hot: `--hf-primary`, 2px, soft drop shadow.
- The SVG is sized from the inner tree’s unscaled `offsetWidth`/`offsetHeight`, so CSS zoom scales lines and cards together.

Redraw after first paint, after `document.fonts.ready`, on a debounced `ResizeObserver` of the tree, on window resize, after Livewire morphs, and after SPA navigation. Recompute rather than cache geometry.

### 5. Path tracing

Hovering a matchup with a mouse, or focusing it with the keyboard, adds a tracing state to that board:

- The hovered card, every ancestor reachable through drawn connectors, and every descendant becomes `.is-path`.
- Connectors touching those cards become hot.
- Cards outside the path dim to roughly 55% opacity.
- Leaving the card or moving focus away clears the state.

The graph is built from the rendered connector pairs, so the highlight always matches the lines the user sees. Only `pointerType === 'mouse'` starts a trace; touch taps open the match detail directly and never leave a stuck highlight.

### 6. Zoom, fit, and jump

- Zoom is CSS `zoom` on `.hf-bracket-tree`, not `transform`, so the scroller gets correct width and height at every scale.
- Range: 0.55–1.50, step 0.10. The toolbar exposes zoom out, Fit, and zoom in.
- Fit measures each tree’s unscaled width and picks the smallest scale that fits every board, clamped to the range. It runs on demand; initial render stays at 1.0 unless the board would overflow, in which case the first fit is applied after fonts load.
- “Jump to final” scrolls the last tree horizontally to its final round, focuses the featured final card, and respects reduced-motion.
- Controls are hidden below the desktop breakpoint where the tree is replaced by the mobile stepper.
- Buttons keep the existing `data-bracket-zoom` values (`in`, `out`, `reset`) so any existing hook keeps working; “Fit” uses `reset` and recalculates.
- Add `data-bracket-jump="final"` for the jump control.

### 7. Mobile round stepper

The existing `data-testid="bracket-mobile"`, `bracket-round-tab-{n}`, `bracket-prev-round`, and `bracket-next-round` remain. The pager is upgraded in place:

- Tablist semantics: `role="tablist"` with an accessible label; each button is `role="tab"` with `aria-selected`, `aria-controls`, and roving `tabindex`.
- Each round panel is `role="tabpanel"` with an `id` and `aria-labelledby`.
- Left/Right arrow keys move between tabs and focus the new tab; Home/End jump to the first/last round.
- Swipe left/right on the panel area advances one round when the horizontal delta exceeds 50px and dominates the vertical delta. Vertical scrolling is never blocked (`touch-action: pan-y`).
- Progress bar stays and gains `role="progressbar"` with min/max/now and a “Round X of Y” label.
- Back/Next buttons stay and keep their test ids.
- Mobile cards use the same component with `mobile=true`, auto height, and the same status, reschedule, and series test ids.

### 8. Round-robin matchdays

Sections with key `group` render:

```
.hf-bracket--matchdays
  .hf-bracket-matchdays
    .hf-matchday-block (one per round)
      header: round label, game count, final count
      .hf-matchday-block__grid
        match cards
```

The grid is `repeat(auto-fill, minmax(260px, 1fr))` with the same cards, spacing, and hierarchy as the tree. No connectors, no fixed heights, no horizontal scroll. This is the desktop layout; the mobile stepper still renders the same rounds one at a time.

## Visual specification

All values come from existing tokens. No new palette.

| Element | Treatment |
|---|---|
| Arena | `--hf-rail` base, orange radial glow top-right, navy radial glow bottom-left, faint 80px court grid masked to the center, 1px `rgba(255,255,255,.12)` border, `--hf-radius-xl` |
| Arena kicker | Orange, 10px, 800 weight, 0.16em tracking, uppercase, short orange rule before the text |
| Arena summary | Rail ink for numbers, rail muted for the label, 13px |
| Section title | Barlow Condensed, 18–20px, 800, uppercase, rail ink; meta in rail muted |
| Round label | Barlow Condensed, 14px, 800, uppercase, 0.08em tracking; count and meter beneath/beside |
| Card | `rgba(255,255,255,.055)` to `rgba(255,255,255,.015)` gradient over `--hf-rail`, 1px `rgba(255,255,255,.12)` border, 10px radius, 3px left status bar, soft shadow |
| Card hover/focus | Orange border, subtle lift, orange outer ring |
| Winner | Rail ink, 800 name, orange Barlow score |
| Loser | 60% opacity, muted score |
| Featured final | Gold 1px border, 4px gold left bar, gold-tinted gradient, trophy watermark at 8% opacity, gold score and pips |
| Connectors | Rail ink at 20%, orange when hot |
| Progress bar | 4px rail, orange fill, smooth width transition |
| Chips inside arena | Translucent dark surface, white-ish text, tone-colored icon/text; never light-theme chip fills on the navy board |
| Focus ring | 2px orange or gold outline with 2px offset, visible on the dark board |

Typography stays Barlow Condensed for display and numerals, Inter for body. Team names are single-line with ellipsis.

## Component and file changes

| File | Change |
|---|---|
| `src/resources/views/livewire/organizer/bracket-show.blade.php` | Replace the standalone toolbar and plain section loop with the arena, section headers, tree/matchday branches, progress summary, and upgraded mobile tablist. Keep all dialogs and KPI/hero markup untouched. |
| `src/resources/views/components/bracket-match.blade.php` | Add `featured` prop, new internal rows, schedule meta, series pips, featured state, and ARIA label. Keep every existing attribute and test id. |
| `src/resources/js/ui/bracket.js` | Replace the transform zoom with CSS zoom, fit, jump, connector drawing, path tracing, observers, Livewire redraw, and mobile swipe/keyboard support. |
| `src/resources/js/ui/page-lifecycle.js` | Keep `initBracketZoom()` in globals; ensure redraw runs after SPA navigation (the module can listen for `livewire:navigated` itself). |
| `src/resources/css/hf/_bracket.scss` | New partial with every bracket style: arena, tree, cards, connectors, final, matchdays, mobile stepper, responsive, forced colors. |
| `src/resources/css/hf/_hoopflow.scss` | Import `hf/bracket` after `hf/data` so bracket styles sit with the other data surfaces. |
| `src/resources/css/hf/_data.scss` | Remove the old bracket blocks (`.hf-bracket*`, `.hf-round-pager`, `.hf-bracket-champion`, old toolbar/connector rules). Leave schedule and standings untouched. |
| `src/resources/css/hf/_print.scss` | Force the arena and cards to a light print palette, hide controls, show connectors in gray, and keep the board unclipped. |

No Blade partial split is required, but the card component remains the single source of truth for both desktop and mobile cards.

## Data flow

1. `BracketShow` supplies `sections`, `kpis`, `rescheduledMatchIds`, and the champion exactly as today.
2. Blade computes only presentation values: total games, completed games, per-round completion, last-round flags, slot meta, and series pips.
3. JavaScript reads the rendered DOM only. It never fetches, mutates Livewire state, or infers data from the server.
4. Livewire updates replace cards; the `morphed` hook redraws connectors and clears any stale trace.
5. SPA navigation re-runs the initializer for the new page.

## Accessibility

- Cards remain keyboard-operable buttons via the existing Livewire key handlers; focus-visible ring is explicit.
- The root card `aria-label` replaces the nested visual text for screen readers with one complete sentence.
- The mobile stepper follows the ARIA tabs pattern, including roving tabindex and arrow/Home/End keys.
- Connector SVG and progress decoration are hidden from assistive technology; equivalent progress is exposed through the progressbar.
- Color is never the only signal: status chips include icon and text, winners are bold as well as colored, and the final has a trophy marker.
- Motion is limited to hover/focus transitions and smooth scrolling; both collapse under `prefers-reduced-motion`.
- Forced-colors mode gets `CanvasText` borders and `Canvas` surfaces for the arena and cards, and visible connector strokes.
- Zoom controls and the jump button have text or `aria-label`; the “Fit” label stays visible.

## Print

The print stylesheet renders a legible paper bracket:

- Arena background white, text black, borders gray.
- Hide the arena toolbar, progress bar, jump/zoom controls, and mobile stepper.
- Show every section and every round; remove clipping and CSS zoom.
- Cards white with gray borders; connectors gray; the featured final keeps a thicker border.
- Avoid breaking cards across pages.

## Edge cases

- **Byes:** keep the existing “BYE” score and muted treatment. The footer reads “Bye — advances automatically” instead of court meta.
- **TBD teams:** preserve “TBD” names and muted scores; cards remain clickable for the detail dialog.
- **Long team names:** single-line ellipsis; the aria-label keeps the full name.
- **Series:** pips use `best_of`; lit pips equal `series_home_wins + series_away_wins`. Series text never disappears.
- **Rescheduled matches:** warning chip stays in the top row and keeps its test id.
- **No schedule slot:** footer reads “Schedule TBD”; no empty icon row.
- **Single-round sections:** the final is centered; no connector layer is required.
- **Double elimination:** winners, losers, and grand final are separate boards; grand final gets the gold treatment.
- **Round-robin with playoffs:** the group section stays matchdays; the playoffs section becomes a tree.
- **Reduced motion:** no trace animation, no smooth scroll.
- **Narrow desktop:** the tree scrolls horizontally inside the arena; the page itself never gains horizontal overflow.

## Acceptance criteria

1. At 1440px, the 16-team single-elimination demo shows four aligned rounds with connector lines, and each quarterfinal is vertically centered between its two feeders.
2. The Championship Final card is gold, carries a trophy marker, and is the visual endpoint of the board.
3. Hovering or keyboard-focusing any matchup highlights its cards and connectors and dims the rest; leaving clears it.
4. Zoom in/out changes both rendered size and scroll area; Fit shows the whole board; Jump to final reaches the final card.
5. At 390px, the tree is replaced by the round stepper; tabs expose ARIA state, arrows work, swipe changes rounds, and Back/Next still work.
6. Round-robin renders a matchday grid, not a tree.
7. Double elimination renders three distinct boards and the grand final gold card.
8. Light and dark themes both keep a navy arena with readable text and chips.
9. Livewire score updates redraw connectors without leaving stale lines or stuck highlights.
10. `composer quality`, `npm run build`, and the bracket feature tests pass.
11. Every existing bracket test id and asserted string is still present.
12. Print preview shows the full bracket in black on white with no controls.

## Verification plan

- `cd src && composer quality`
- `cd src && npm run build`
- `cd src && php artisan test tests/Feature/Pages/BracketTest.php`
- Browser: `/bracket?tournament=16`; `/bracket?tournament=<double-elim id>`; default round-robin; 1440×900, 1024×768, 390×844; light and dark; keyboard Tab/Enter/arrows; organizer score update; print preview; reduced-motion emulation.

## Risks

| Risk | Mitigation |
|---|---|
| CSS `zoom` measurement differences across browsers | Measure inside the unscaled tree, redraw on resize and after zoom; CSS `zoom` is supported in current Chromium, Firefox, and Safari. |
| Livewire morph removes SVG paths | `wire:ignore` on the SVG plus a redraw on Livewire’s `morphed` hook. |
| Variable card heights break exact midpoint alignment | Fixed row structure, single-line ellipsis, minimum height, and connector redraw after fonts/images settle. |
| Losers-bracket feeders are not stored | Connectors and tracing follow the rendered adjacency graph and are presented as progression, not certified lineage. |
| Too much visual density on small laptops | Fixed card basis, compact typography, internal horizontal scroll, and Fit. |
| Contrast of light-theme chips on the navy board | Arena-scoped chip overrides with dark translucent surfaces and light text. |
