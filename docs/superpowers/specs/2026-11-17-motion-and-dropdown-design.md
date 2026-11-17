# Page, Dialog, and Dropdown Motion - Design

- **Date:** 2026-11-17
- **Owner:** Jeward T. Mahinay
- **Status:** Approved
- **Scope:** `src/resources/views`, `src/resources/css/hf`, `src/resources/js/ui`, `src/tests/js`, `src/tests/Feature`. No route, controller, policy, service, model, migration, or domain changes.
- **Related:** [[2026-11-17-spa-navigation-design]], [[2026-11-15-confirmations-undo-password-strength-design]], [[2026-11-14-nav-toggle-design]]

## Problem

The authenticated shell now survives `wire:navigate` cleanly: the rail no longer
expands and collapses on every visit, display preferences survive the swap, and
page entry motion no longer replays. That fixed the flash, but it also left SPA
navigation visually abrupt: the new page simply appears.

Dialogs are also one-sided. Entry is animated, but closing a dialog is instant.
Only the logout dialog has an exit (`is-closing`), and it uses a private
implementation. Confirmation prompts such as Delete and Approve vanish the
moment Livewire morphs them away.

The account dropdown has no motion at all. It pops open and closed, its items
have no hover treatment beyond a flat background, and its panel is visually
indistinguishable from a plain list.

## Goals

1. Animate the outgoing and incoming page content during SPA navigation while
   the rail, topbar, and progress bar stay completely still.
2. Animate every dialog dismissal, including close buttons, Cancel, backdrop
   clicks, Escape, and destructive confirmation actions, without delaying the
   server request or showing an empty modal backdrop.
3. Give the account dropdown an entrance, an exit, an account header, and
   readable item-level interaction states.
4. Respect both `prefers-reduced-motion: reduce` and the in-app
   `data-motion="reduced"` preference.
5. Preserve every route, component behavior, `data-testid`, form contract, and
   test-asserted string.

## Non-goals

- No cross-document transition for full page loads (login, register, logout,
  direct visits). Full loads keep their current entry motion.
- No change to the guest layout, which must continue to render zero
  `wire:navigate` or `wire:transition.navigate` attributes.
- No styling of native `<select>` popups. Those menus are OS-rendered.
- No replacement of `<details>` with a custom listbox. Native disclosure
  semantics and keyboard behavior stay.
- No new dependencies, no Vue/React/Tailwind/Bootstrap JS, no page cache, no
  prefetch changes.

## Decisions

| Decision | Choice |
|---|---|
| Page transport | Livewire 4 `wire:transition.navigate="hf-page"` on `<main>` |
| Page animation | Old snapshot fades and lifts 4px in 140ms; new snapshot fades and rises 8px in 240ms |
| Shell during navigation | Fixed; no transition name on rail, topbar, or progress |
| Page entry on full load | Unchanged `hf-page-in` and `hf-reveal` |
| SPA entry replay | Still suppressed by `hf-spa-nav` |
| Dialog exit trigger | Livewire `morph.removing` hook, before the node leaves the DOM |
| Dialog exit timing | 160ms, after the server responds |
| Optimistic dialog fade | Rejected: an empty backdrop would show during slow requests |
| Dialog entry | Existing fade/rise/scale, aligned to the new duration tokens |
| Client dialogs | Logout and More sheet share the same `is-closing` style |
| Dropdown motion | 180ms open, 120ms close, 24ms item stagger |
| Dropdown header | Avatar, "Signed in as", name, email; rail menu only |
| Item effect | Soft icon tiles, brand tint on hover/focus, danger Log out |
| Reduced motion | No animation and no timers |

## Architecture

### 1. Page transitions

`<main id="main" class="hf-main" tabindex="-1">` gains
`wire:transition.navigate="hf-page"`. Livewire names the element `hf-page` on
both sides of the swap and calls `document.startViewTransition()` around the
body update. Livewire already disables the root transition when a named element
is present, so the shell swaps instantly while only the page content animates.

CSS, added to `_motion.scss`:

```css
::view-transition-old(hf-page) {
  animation: hf-page-vt-out var(--hf-dur-exit) ease-in both;
}

::view-transition-new(hf-page) {
  animation: hf-page-vt-in var(--hf-dur-page) var(--hf-ease-out) both;
}
```

The keyframes move transform and opacity only. There is no scale, so pages with
different heights do not squash or stretch.

The existing `hf-spa-nav` class continues to suppress `hf-page-in` and
`hf-reveal`, so the view transition is the only motion on an SPA visit. Full
page loads do not run a view transition and keep `hf-page-in` plus
`hf-reveal`.

Livewire skips the page transition when a modal dialog or popover is open, when
the browser lacks `startViewTransition`, or when the user prefers reduced
motion. Those cases fall back to today's instant swap.

### 2. Dialog exit

A new module, `resources/js/ui/dialog-motion.js`, registers a Livewire hook:

```js
Livewire.hook('morph.removing', ({ el, skip }) => {
  if (!shouldHoldDialog(el)) return;

  skip();
  holdAndRemove(el);
});
```

`shouldHoldDialog(el)` is a pure, exported predicate:

- true when `el` is an `HTMLDialogElement`
- true when it has the `hf-dialog` class
- true when `el.open` is true
- false when motion is disabled
- false when the node is already `is-closing`

`holdAndRemove(dialog)` adds `is-closing`, waits 160ms (matching the exit
keyframes), calls `dialog.close()` if still open, and finally
`dialog.remove()`. If the node was already detached, it exits quietly.

Because the hook runs on the morph that follows the server response, the
animation always plays **after** the action succeeds. If validation fails and
the server keeps the dialog open, no removal occurs and no animation plays. The
modal stays visible and interactive for the whole request, so there is no empty
backdrop and no delayed request.

The hook filters on the dialog itself. Parent Livewire components are never
skipped. Multiple dialogs closing in one response each get their own hold.

CSS:

```css
.hf-dialog.is-closing {
  animation: hf-dialog-out 160ms ease-in both;
}

.hf-dialog.is-closing::backdrop {
  animation: hf-backdrop-out 160ms linear both;
}
```

The existing `.hf-logout.is-closing` rules stay and remain more specific, so
the logout choreography is unchanged.

Client-closed dialogs do not pass through a morph:

- `logout.js` already adds `is-closing` and waits 180ms before `close()`. Keep
  it; align its `EXIT_MS` with the shared constant if practical.
- `shell.js` closes the More sheet from the close button, backdrop, Escape,
  and navigation links. The close button, backdrop, and Escape move behind a
  `closeDialogWithMotion(sheet)` helper exported by `dialog-motion.js`; the
  sheet uses the same 160ms hold with a sheet-specific slide-down keyframe.
  Clicking a link inside the sheet still closes it immediately so navigation
  is not delayed.
- The More sheet also gains an entrance: slide-up and fade over
  `--hf-dur-standard`, with the backdrop fading in. Reduced motion skips both.
- `page-lifecycle.js` closes the logout dialog and More sheet during SPA
  teardown. Those closes are cleanup, not user dismissals, and stay instant.

Focus restoration remains in `livewire-bridge.js`: it observes dialog removal
and focuses the recorded trigger. The hold only delays removal by 160ms.

### 3. Account dropdown

#### Header

`resources/views/components/user-menu.blade.php` gains a header inside the
panel:

```blade
<div class="hf-user-menu__head">
    <span class="hf-avatar hf-avatar--lg">{{ $initials }}</span>
    <span class="hf-user-menu__identity">
        <span class="hf-user-menu__eyebrow">Signed in as</span>
        <strong>{{ $user?->name }}</strong>
        <span class="hf-user-menu__email">{{ $user?->email }}</span>
    </span>
</div>
```

The name and email truncate with ellipsis. `_shell.scss` gains an
`.hf-avatar--lg` modifier (40px). The panel keeps its 220px minimum width so it
still fits inside the expanded rail.

The More sheet has its own duplicated account block whose summary already shows
the avatar, name, and role. It does not get a second header; it inherits the
shared panel, item, and divider styles. No markup refactor is required there.

#### Open and close

- Open: `.hf-user-menu[open] .hf-user-menu__panel` runs
  `hf-menu-in` (fade, 6px rise, 0.98 to 1 scale, 180ms). In the rail footer the
  panel opens upward, so its transform origin is `bottom right`. The More sheet
  override sets `animation: none` because the sheet supplies the entrance and
  its panel is already open inside it.
- Close: `menus.js` intercepts a summary click while open, adds `is-closing`,
  waits 120ms, then removes `open`. Outside-click and Escape handlers in
  `shell.js` call the same `closeUserMenu(menu)` helper and return focus to the
  summary on Escape.
- Clicking a panel link or the logout button removes `open` immediately so
  navigation and logout are never delayed.
- Items stagger 24ms via `:nth-child` delays. The More sheet override disables
  the stagger because its panel is already open inside the sheet.

#### Item effect

- Each item's `.bi` sits in a 26px rounded tile with a soft neutral background.
- Hover and keyboard focus tint the tile with `--hf-primary` at low opacity,
  color the icon with `--hf-primary-ink`, and nudge the icon 1px to the right.
- The logout form is separated by a top border. Its tile and label use the
  danger tokens on hover and focus.
- Existing focus-visible outlines remain the accessibility source of truth; no
  outline is removed.

#### Collapsed rail

The rail clips horizontal overflow. In the 76px collapsed state the current
220px panel is anchored to the right edge of a 52px chip and can fall outside
the viewport. During implementation, verify this in the browser. If confirmed:

- when `data-nav="collapsed"`, the panel is positioned fixed beside the rail
  using the summary's bounding rect on open;
- scroll and resize close the panel;
- the same close path and motion apply.

If the panel is already usable, leave the positioning unchanged.

## Tokens

Add to `_tokens.scss`:

```css
--hf-dur-exit: 140ms;
--hf-dur-page: 240ms;
```

Dialogs reuse `--hf-dur-standard` for entry and a 160ms hold for exit. Menu
entry uses `--hf-dur-standard`; menu exit uses `--hf-dur-micro` (120ms).

## Reduced motion

- CSS keyframes are disabled by the existing global rules for
  `prefers-reduced-motion` and `[data-motion="reduced"]`.
- `dialog-motion.js` uses a shared `motionEnabled()` helper. When motion is
  disabled it does not call `skip()`, so Livewire removes the dialog
  immediately.
- `menus.js` removes `open` synchronously when motion is disabled.
- The page transition is skipped by Livewire itself under
  `prefers-reduced-motion`. The app-level `data-motion="reduced"` setting is
  also checked by a small guard: if reduced, the `<main>` attribute stays but
  the view-transition pseudo-elements are overridden to `animation: none`.

## Accessibility

- Dialogs keep their `aria-labelledby` relationships and native modal focus
  trapping. The hold does not move focus.
- Escape still cancels the dialog through the existing Alpine `cancel` handler.
- The account menu remains a native `<details>`; the summary stays the
  keyboard target, and Escape returns focus to it.
- New text is limited to the account header; no existing string changes.
- Color is never the only hover or focus signal: background, icon color, and
  movement all change together.

## Testing

### JS unit tests

- `shouldHoldDialog` returns true only for an open, animated, not-already-closing
  `.hf-dialog`.
- `motionEnabled` honors both the media query and the document attribute.
- `closeUserMenu` duration is zero when motion is disabled.

The helpers must accept injected state (reduced, class list) so tests can run in
Node without a browser.

### Pest tests

- The authenticated dashboard renders `<main>` with
  `wire:transition.navigate`.
- The login page renders neither `wire:navigate` nor
  `wire:transition.navigate`.
- The account menu still renders Profile, Appearance, Keyboard shortcuts, and
  Log out, and still exposes the existing `data-testid` values.
- The logout form still posts to the logout route.

### Browser verification

1. `/dashboard -> /teams -> /dashboard`: rail width stays `76px` or `248px`
   with no intermediate frames; no wrong-theme frames.
2. Page transition: old snapshot fades out, new snapshot fades in; rail and
   topbar do not move.
3. Dialog: open, Cancel -> ~160ms exit; open, Delete -> request completes, then
   ~160ms exit; open, force a validation error -> dialog stays open with no
   animation.
4. More sheet: open and close animate; Escape and backdrop click animate;
   focus returns to the More tab.
5. Account menu: header renders, panel animates open and closed, Escape returns
   focus, outside click closes, Log out still opens the logout dialog.
6. Collapsed rail: menu remains fully visible, and scroll/resize closes it.
7. Reduced motion: repeat 2 through 5; everything is instant.

## Risks

- **Tall page snapshots.** Naming the whole `<main>` could make view-transition
  snapshots large. Mitigation: transform/opacity only, no scale; verify paint
  and memory in the browser.
- **Morph skip behavior.** If Livewire's `skip()` proves unreliable for
  dialogs, fall back to a `MutationObserver` that re-attaches the removed
  dialog for the exit. Verify this first during implementation.
- **Nested dialog replacement.** Switching from one keyed dialog to another
  shows the old one animating out under the new one. Acceptable; verify
  stacking and focus.
- **Redirects from an open modal.** Livewire swaps the page instead of
  morphing, and it skips the page transition while a modal is open. The dialog
  disappears without an exit in that specific path. Accepted for this scope.
