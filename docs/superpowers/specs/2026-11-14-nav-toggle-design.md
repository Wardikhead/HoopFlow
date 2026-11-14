# Collapsible Responsive Navigation Rail — Design

- **Date:** 2026-11-14
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Scope:** UI layer only — `src/resources/views`, `src/resources/css`, `src/resources/js`. No domain, route, controller, middleware, model, migration, config, or test changes.
- **Related:** [[2026-11-13-hoopflow-ui-ux-enhancement-design]], [[2026-11-07-phase-1-shell-theme-design]]

## Problem

The press-box rail is fixed by breakpoint:

- 248px with labels at ≥1200px.
- A hard-coded 76px icon rail between 768px and 1199px.
- Hidden below 768px, where the bottom tab bar and More sheet take over.

This leaves no way to reclaim horizontal space on a desktop, and no way to read labels on a tablet without resizing the window. The compact rail's tooltips are also broken: `.hf-rail` uses `overflow-y: auto`, which forces `overflow-x` to clip, so the absolutely positioned `::after` tooltips are cut off. At 1024px the rail measures **61px client width against 187px scroll width**.

The navigation markup is duplicated. The rail loops over `$matchdayKeys` and `$roleTools` in one place, and the More sheet repeats the same two loops in another. Adding, moving, or renaming a destination requires two edits that can drift.

`.hf-user-meta` is hidden by default and only shown at ≥1200px, so an expanded rail at tablet width could not show the account name even if labels were visible.

## Goals

1. Let the user toggle the rail between expanded labels and a collapsed icon rail at 768px and up.
2. Render the rail and More sheet navigation from one reusable Blade component.
3. Add subtle, product-consistent motion without loops or bounce.
4. Remember the user's expanded/collapsed choice across visits.
5. Replace the clipped CSS tooltips with tooltips that render outside the rail's scroll container.
6. Preserve every `data-testid`, route, form contract, and test-asserted string.

## Non-goals

- No change to the mobile bottom navigation or More sheet layout and behavior. Only their link markup moves into the shared component.
- No phone drawer, hamburger, or replacement of the bottom bar.
- No separate remembered state per breakpoint.
- No new navigation destinations, badges, search, or command palette.
- No new npm or Composer dependency, no Tailwind, no Bootstrap JavaScript, no SPA.
- No test edits.

## Decisions

| Decision | Choice |
|---|---|
| Toggle target | Desktop/tablet rail at ≥768px. Mobile stays on the bottom bar plus More sheet. |
| States | 248px expanded ↔ 76px collapsed. Pushes content; never overlays. |
| Defaults | Expanded at ≥1200px; collapsed at 768–1199px. |
| Persistence | `localStorage` key `hf-nav`, values `expanded` or `collapsed`. |
| Toggle placement | Topbar left, before the breadcrumb/context block. |
| Effects | Broadcast-subtle: 240ms width ease, labels fade/slide 8px, toggle icon rotates, 1px icon hover lift, active bar grows. No loops or bounce. |
| Reduced motion | Existing global `[data-motion='reduced']` and `prefers-reduced-motion` rules win. |
| Mobile | Unchanged below 768px. |

## Architecture

### Shared nav component

New anonymous Blade component: `src/resources/views/components/app-nav.blade.php`.

Props:

| Prop | Shape | Purpose |
|---|---|---|
| `groups` | `[['label' => string, 'keys' => string[]], ...]` | Ordered groups such as Matchday and Workspace. Empty groups are skipped. |
| `items` | `[key => ['label' => string, 'icon' => string, 'route' => string], ...]` | Destination definitions. |
| `activeKeys` | `string[]` | Keys that should render as the current page. |
| `variant` | `rail` or `sheet` | Selects wrapper markup and test attributes. |

Rendering rules:

- `rail` variant emits the group label plus links with:
  - `class="hf-rail-link"` and `is-active` when the key is active.
  - `data-testid="nav-{key}"`, preserving the existing contract used by `GameManageTest`.
  - `data-label="{label}"`, used by the tooltip.
  - `data-nav-link` for the shell module.
  - `aria-current="page"` on the active link.
  - `<i class="bi bi-{icon}" aria-hidden="true">` and `<span class="hf-nav-link__label">{label}</span>`.
- `sheet` variant emits `.hf-sheet__group` wrappers, `.hf-rail-label` group labels, and links with `data-more-link="{key}"`. Active links also receive `aria-current="page"`; no new sheet styling is introduced.
- Icons, labels, routes, order, and role filtering are unchanged.

Layout flow:

1. `public.blade.php` keeps `$navItems`, `$matchdayKeys`, `$roleTools`, and `$isActive`.
2. It builds `$navGroups` once:
   - Matchday → `$matchdayKeys`
   - Workspace → `$roleTools` (omitted when empty)
3. It builds `$activeKeys` by filtering the combined keys through `$isActive`.
4. The rail calls `<x-app-nav variant="rail" ... />` between the brand and the footer.
5. The More sheet calls `<x-app-nav variant="sheet" ... />` in place of the two duplicated loops. The sheet account block stays where it is.

This removes the duplicated Matchday and Workspace loops from the layout without changing destination behavior.

### State model

`<html>` carries `data-nav="expanded|collapsed"`.

The existing pre-paint `<head>` script gains this logic, next to theme, density, and motion:

1. Read `localStorage.hf-nav`.
2. Accept only `expanded` or `collapsed`.
3. If absent or invalid, use `matchMedia('(min-width: 1200px)')`: expanded when true, collapsed when false.
4. Set `document.documentElement.dataset.nav`.
5. Wrap the nav read in its own `try/catch` inside the existing block, so a storage failure cannot override the already-resolved theme. On failure, use the width default.

Because the attribute is set before the stylesheet paints, there is no open/closed flash on reload.

Below 768px the attribute may still be `collapsed`, but the rail and toggle are hidden, so it has no visual effect.

### Toggle

The topbar gets a 44px icon button before `.hf-topbar-context`:

```html
<button type="button"
        class="hf-icon-btn hf-nav-toggle"
        data-nav-toggle
        aria-controls="hf-rail"
        aria-expanded="true"
        aria-label="Collapse navigation">
    <i class="bi bi-chevron-double-left" aria-hidden="true"></i>
</button>
```

- Hidden below 768px.
- The server renders `aria-expanded="true"`; `initNavRail()` synchronizes it from `data-nav` on load, before the user interacts.
- `aria-expanded` reflects the current state after every toggle.
- The label flips between “Collapse navigation” and “Expand navigation”.
- The icon rotates 180° in the collapsed state.
- The rail gets `id="hf-rail"` to match `aria-controls`.

### Persistence and resize behavior

In `initNavRail()` (`shell.js`):

- Clicking the toggle flips `data-nav`, updates ARIA, and writes `hf-nav` inside `try/catch`.
- If no choice is stored, a `matchMedia('(min-width: 1200px)')` change listener follows the width default when the viewport crosses 1200px.
- Once the user has chosen, the stored value wins at every width ≥768px and resizing does not override it.
- There is no SPA navigation, so initializing once per page load is sufficient.

## CSS and Motion

### Layout

- `.hf-app` changes to `grid-template-columns: auto minmax(0, 1fr)`.
- `.hf-rail` gets an explicit `width: 248px` and a width transition. Because the grid track is `auto`, it follows the rail's animated width and pushes the topbar and main content.
- `[data-nav='collapsed'] .hf-rail` sets `width: 76px` and the tighter inline padding.
- The old `@media (min-width: 768px) and (max-width: 1199.98px)` compact-rail block is removed. Collapsed styling is now state-driven at every width ≥768px.
- The tablet rail can now be expanded to 248px, including labels and account meta.
- Add `.hf-rail .hf-user-meta { display: block; max-width: 140px; }` inside the ≥768px rules so an expanded tablet rail shows the account name. Keep the existing ≥1200px rule for the sheet's account block; the rail's collapsed state overrides with max-width/opacity. The sheet's account block otherwise keeps its current behavior.

### Label and chrome transitions

All transitions are one-shot state changes; nothing loops.

- `.hf-rail .hf-nav-link__label`: expanded `max-width: 180px`, opacity 1, no offset. Collapsed `max-width: 0`, opacity 0, translateX(-8px), `overflow: hidden`, `white-space: nowrap`. Sheet labels are not affected by these rules.
- Rail group labels: fade out and collapse to an 8px spacer so Matchday and Workspace remain separated.
- `.hf-rail-brand .hf-brand__text`: fade and collapse its max-width; the icon mark stays centered.
- Account meta and chevron: fade and collapse; the footer chip becomes a centered avatar. Set the collapsed chip to `gap: 0`, `width: 52px`, and centered content so the avatar does not shift off-center.
- Collapsed links and the brand lockup also set `gap: 0`; a zero-width label must not leave a 10px flex gap that offsets the icon.
- Durations: 240ms width with a soft deceleration curve; 200ms for label/group fades; 160ms for hover states.
- No `display: none` on text that needs to animate. `opacity` plus `max-width` keeps the text in the accessibility tree.

### Toggle, active bar, and hover

- `.hf-nav-toggle`: hidden by default, `display: inline-flex` at ≥768px.
- Chevron rotates 180° over 240ms when `[data-nav='collapsed']`.
- `.hf-rail-link` becomes `position: relative`.
- The active link's inset box-shadow is replaced by a `::before` 3px bar, 22px tall, vertically centered. It starts at `scaleY(0.6)` and grows to full height on hover/active. The existing tinted background and white text stay.
- `.hf-rail-link:hover .bi` translates up 1px. No movement on the label.
- Focus-visible uses the existing global outline and the forced-colors block still outlines the active link.

### Tooltip replacement

The current `::after` tooltips cannot escape the rail's scroll container. The replacement is one fixed-position element owned by `initNavRail()`:

- A single `div.hf-rail-tooltip` is appended to `document.body`.
- Shown on `pointerenter` and `focusin` of `.hf-rail-link` only when the rail is collapsed and the viewport is ≥768px.
- Text comes from the link's `data-label`.
- Positioned to the right of the link with `getBoundingClientRect()`, clamped to the viewport vertically.
- `position: fixed`, `pointer-events: none`, high z-index, fade-in 120ms.
- Hidden on pointer leave, focus out, scroll (capture), resize, and toggle.
- `aria-hidden="true"` because the link's clipped label already provides the accessible name; the tooltip is visual only.
- If JavaScript fails, links remain navigable and labeled for assistive technology; only the visual tooltip is absent.

### Reduced motion

No new motion file is required. The existing global rules in `_tokens.scss` and `_motion.scss` collapse all transition durations for `[data-motion='reduced']` and `prefers-reduced-motion: reduce`. The rail snaps between widths, the chevron jumps to its final rotation, and the tooltip appears without a fade.

## Responsive Rules

| Width | Rail | Toggle | Bottom nav / More |
|---|---|---|---|
| <768px | Hidden | Hidden | Shown, unchanged |
| 768–1199px | Collapsed by default; can expand to 248px | Shown | Hidden |
| ≥1200px | Expanded by default; can collapse to 76px | Shown | Hidden |

A stored choice applies at every width ≥768px. Resizing across 1200px only changes the state when the user has never toggled.

## Accessibility

- Toggle is a normal tab stop with a 44px target, `aria-controls`, `aria-expanded`, and a state-specific `aria-label`.
- No focus trap; the rail is not a modal.
- Link labels stay in the accessibility tree when collapsed because they are clipped with `opacity` and `max-width`, not removed from layout.
- Tooltips are `aria-hidden` to avoid double announcements.
- Active link keeps `aria-current="page"`.
- Existing focus-visible styling and forced-colors active-link outline are preserved.
- Tooltip does not steal focus and does not intercept pointer events.

## Files

| File | Change |
|---|---|
| `src/resources/views/components/app-nav.blade.php` | New shared rail/sheet nav renderer. |
| `src/resources/views/layouts/public.blade.php` | Build groups/active keys, call the component twice, add the topbar toggle, extend the pre-paint script with `data-nav`. |
| `src/resources/css/hf/_shell.scss` | Rail width states, transitions, toggle, label/chrome collapse, active bar, fixed tooltip styles; remove the old compact-rail media block. |
| `src/resources/js/ui/shell.js` | `initNavRail()`: toggle, persistence, resize fallback, tooltip lifecycle. |
| `src/resources/js/app.js` | Call `initNavRail()` during shell initialization. |
| `docs/superpowers/specs/2026-11-14-nav-toggle-design.md` | This spec. |

No changes to `src/resources/views/components/user-menu.blade.php`, routes, Livewire components, tests, or dependencies.

## Contracts Preserved

- Rail links keep `data-testid="nav-{key}"`; `GameManageTest` continues to assert `nav-gameManage`.
- More sheet links keep `data-more-link="{key}"`.
- Mobile tab IDs `mobile-nav-{key}` and `mobile-nav-more` are untouched.
- Routes, icons, labels, group order, and role filtering are unchanged.
- No test files are edited.
- `.obsidian/` and `.kilo/` are never staged.

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Animating the rail through the grid's `auto` track may jank on low-end machines. | Verify in the browser at 768/1200/1440. If needed, transition `grid-template-columns` directly instead; the visual result is the same. |
| `localStorage` is blocked or throws. | Existing `try/catch` pattern; fall back to the width default and keep the toggle functional for the session. |
| Labels could overflow during the width animation. | `overflow: hidden`, `white-space: nowrap`, and max-width collapse on the label span. |
| Short viewports scroll the rail. | Keep `overflow-y: auto`; the tooltip is fixed and independent of that scroll container. |
| More sheet markup moves into a shared component. | Preserve `data-more-link`, group order, account block, and run the submissions/payments feature tests in `composer quality`. |
| Expanded tablet rail is new and untested visually. | Include 768px and 1024px expanded states in the browser pass, including account meta and brand lockup. |

## Verification

1. `npm run build` passes; confirm the new rules appear in the compiled CSS.
2. Browser pass at 390, 768, 1024, 1199, 1200, and 1440:
   - Toggle hidden below 768.
   - Correct default state per width.
   - Expand and collapse push the content; no overlay, no horizontal page overflow.
   - Tooltip is readable and not clipped when collapsed.
   - Labels, group separation, brand, and account meta render correctly when expanded at 768–1199.
   - More sheet links still navigate.
   - Keyboard focus reaches the toggle and rail links; `aria-expanded` flips.
   - Reload preserves the chosen state; clearing `hf-nav` restores the width default.
   - Reduced motion (`data-motion="reduced"` and OS preference) removes the animation.
   - Dark mode remains legible.
3. `composer quality` passes with the existing 511 tests and 2254 assertions; report the actual output.
4. Commit only the files listed above.
