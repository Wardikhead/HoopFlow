# Collapsible Responsive Nav Rail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a persisted, responsive collapse/expand toggle to the HoopFlow rail and render the rail and More sheet navigation from one reusable Blade component, without changing routes, test contracts, or the mobile bottom bar.

**Architecture:** Extract the duplicated rail and sheet link loops into `x-app-nav`, driven by one `$navGroups` / `$items` / `$activeKeys` payload. Put the expanded/collapsed state on `<html data-nav>`, set it before paint from `localStorage.hf-nav`, and control it from a topbar button handled by `initNavRail()` in `shell.js`. CSS owns the 248px/76px layout, label fades, active bar, and reduced-motion behavior; JS owns persistence and one fixed-position tooltip that escapes the rail's scroll container.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, Livewire 4, Alpine 3, Bootstrap 5.3 SCSS, Vite 8, Pest 4, vanilla ES modules.

**Spec:** `docs/superpowers/specs/2026-11-14-nav-toggle-design.md`

## Global Constraints

- **UI-only:** allowed paths are `src/resources/views/**`, `src/resources/css/**`, and `src/resources/js/**`. Do not touch routes, controllers, middleware, services, models, migrations, seeders, config, `.env`, or dependencies.
- **No test edits.** Preserve every `data-testid`, especially `data-testid="nav-{key}"` on rail links. `GameManageTest` asserts `nav-gameManage`; it must keep passing.
- **No new dependencies.** No Tailwind, Bootstrap JavaScript, SPA, npm UI library, confetti, sound, or looping animation.
- **Navigation contract:** rail links keep `data-testid="nav-{key}"`; sheet links keep `data-more-link="{key}"`; mobile tabs keep `mobile-nav-{key}` and `mobile-nav-more`.
- **Copy contract:** do not change navigation labels, group names, routes, or icons.
- **Staging:** never `git add` a directory. Stage the exact files named in each task. Never stage `.obsidian/` or `.kilo/`; both contain user work.
- **Motion:** 240ms rail width, 200ms label/group fades, 160ms hover, 120ms tooltip fade. No loops or bounce. `[data-motion='reduced']` and `prefers-reduced-motion: reduce` must win.
- **Responsive contract:** below 768px the rail and toggle are hidden and the bottom bar is unchanged; 768–1199px defaults collapsed; ≥1200px defaults expanded; a stored choice wins at every width ≥768px.
- **Verification:** `npm run build` and `composer quality` must pass. Browser checks are the UI regression gate because test files may not change.

## File Structure

**Create**
- `src/resources/views/components/app-nav.blade.php` — shared rail/sheet link renderer.

**Modify**
- `src/resources/views/layouts/public.blade.php` — nav groups/active keys, component calls, pre-paint `data-nav`, topbar toggle, rail id.
- `src/resources/css/hf/_shell.scss` — grid columns, rail states, toggle, label/chrome transitions, active bar, tooltip.
- `src/resources/js/ui/shell.js` — `initNavRail()` toggle, persistence, resize fallback, tooltip.
- `src/resources/js/app.js` — call `initNavRail()`.
- `docs/superpowers/specs/2026-11-14-nav-toggle-design.md` — already committed; no edit needed.
- `docs/superpowers/plans/2026-11-14-nav-toggle.md` — this plan.

---

### Task 1: Extract the shared `x-app-nav` component

**Files:**
- Create: `src/resources/views/components/app-nav.blade.php`
- Modify: `src/resources/views/layouts/public.blade.php` (nav data near the `$isActive` closure; rail block; More sheet blocks)

**Interfaces:**
- Consumes: `$navItems` keyed by destination, `$matchdayKeys`, `$roleTools`, `$isActive`.
- Produces: `<x-app-nav variant="rail|sheet" :groups="$navGroups" :items="$navItems" :active-keys="$activeKeys" />`. Rail links expose `data-testid="nav-{key}"`, `data-label`, `data-nav-link`; sheet links expose `data-more-link="{key}"`.

- [ ] **Step 1: Create the shared component**

Create `src/resources/views/components/app-nav.blade.php` with this exact content:

```blade
@props([
    'groups' => [],
    'items' => [],
    'activeKeys' => [],
    'variant' => 'rail',
])

@php
    $isSheet = $variant === 'sheet';
@endphp

@foreach ($groups as $group)
    @php
        $keys = array_values(array_filter($group['keys'] ?? [], fn ($key) => isset($items[$key])));
    @endphp

    @if (count($keys))
        @if ($isSheet)
            <div class="hf-sheet__group">
                <span class="hf-rail-label">{{ $group['label'] }}</span>
        @else
            <span class="hf-rail-label">{{ $group['label'] }}</span>
        @endif

        @foreach ($keys as $key)
            @php
                $item = $items[$key];
                $active = in_array($key, $activeKeys, true);
            @endphp

            @if ($isSheet)
                <a href="{{ route($item['route']) }}"
                   data-more-link="{{ $key }}"
                   @if ($active) aria-current="page" @endif>
                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>{{ $item['label'] }}
                </a>
            @else
                <a class="hf-rail-link{{ $active ? ' is-active' : '' }}"
                   href="{{ route($item['route']) }}"
                   data-testid="nav-{{ $key }}"
                   data-label="{{ $item['label'] }}"
                   data-nav-link
                   @if ($active) aria-current="page" @endif>
                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                    <span class="hf-nav-link__label">{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach

        @if ($isSheet)
            </div>
        @endif
    @endif
@endforeach
```

- [ ] **Step 2: Build the group and active-key payload once**

In `src/resources/views/layouts/public.blade.php`, after the `$isActive` closure and before `$initials`, add:

```php
$navGroups = [
    ['label' => 'Matchday', 'keys' => $matchdayKeys],
    ['label' => 'Workspace', 'keys' => $roleTools],
];

$activeKeys = collect(array_merge($matchdayKeys, $roleTools))
    ->filter(fn (string $key): bool => $isActive($key))
    ->values()
    ->all();
```

- [ ] **Step 3: Replace the rail's two link loops**

In the `<aside class="hf-rail">` block, remove the `<span class="hf-rail-label">Matchday</span>` loop, the workspace label/loop, and both `@foreach` blocks. Put this single call in their place, between the brand anchor and `.hf-rail-footer`:

```blade
<x-app-nav variant="rail" :groups="$navGroups" :items="$navItems" :active-keys="$activeKeys" />
```

The rail must now read, in order: brand anchor, `<x-app-nav variant="rail" ... />`, `.hf-rail-footer`. Do not change the footer or brand.

- [ ] **Step 4: Replace the More sheet's two link loops**

Inside `.hf-sheet__body`, remove the two `.hf-sheet__group` blocks that loop `$matchdayKeys` and `$roleTools`. Put this single call after the `.hf-sheet__account` details block and before the Account group:

```blade
<x-app-nav variant="sheet" :groups="$navGroups" :items="$navItems" :active-keys="$activeKeys" />
```

Keep the `.hf-sheet__account` details block and the Account group containing the Profile link exactly as they are.

- [ ] **Step 5: Build and run the nav regression**

Run from `src`:

```powershell
npm run build
php artisan test --filter=GameManageTest
```

Expected: build succeeds; `GameManageTest` passes and still sees `data-testid="nav-gameManage"`.

- [ ] **Step 6: Smoke-check the two render targets**

Open `/dashboard` at 1440px:
- Rail still shows Matchday and Workspace labels in the same order.
- The active link still has `is-active` and `aria-current="page"`.
- The rail footer user menu still opens.

At 390px, open the More sheet:
- Matchday and Workspace groups are present with the same links.
- Each sheet link still has `data-more-link`.
- The account block and Profile link are still present.

- [ ] **Step 7: Commit**

```powershell
git add src/resources/views/components/app-nav.blade.php src/resources/views/layouts/public.blade.php
git commit -m "refactor: extract shared app navigation component"
```

---

### Task 2: Add persisted collapse state and the topbar toggle

**Files:**
- Modify: `src/resources/views/layouts/public.blade.php` (pre-paint script, rail id, topbar toggle)
- Modify: `src/resources/css/hf/_shell.scss` (`.hf-app`, `.hf-rail`, toggle, collapsed state block)
- Modify: `src/resources/js/ui/shell.js` (add `initNavRail()`)
- Modify: `src/resources/js/app.js` (import and call `initNavRail()`)

**Interfaces:**
- Consumes: `#hf-rail` and `[data-nav-toggle]` from the layout.
- Produces: `<html data-nav="expanded|collapsed">`; `localStorage.hf-nav`; exported `initNavRail()`; collapsed CSS at every width ≥768px.

- [ ] **Step 1: Apply the nav state before paint**

In `src/resources/views/layouts/public.blade.php`, inside the existing head IIFE, add a **separate** try/catch after the theme try/catch and before the IIFE closes:

```js
        try {
            var nav = localStorage.getItem('hf-nav');
            if (nav !== 'expanded' && nav !== 'collapsed') {
                nav = window.matchMedia('(min-width: 1200px)').matches ? 'expanded' : 'collapsed';
            }
            document.documentElement.dataset.nav = nav;
        } catch (e) {
            document.documentElement.dataset.nav = window.matchMedia('(min-width: 1200px)').matches ? 'expanded' : 'collapsed';
        }
```

Do not put this inside the theme try/catch: a storage failure must not reset the resolved theme to light.

- [ ] **Step 2: Add the rail id and topbar toggle**

Change the rail opening tag to:

```blade
<aside class="hf-rail" id="hf-rail" aria-label="Primary navigation">
```

Add this button as the first child of `<header class="hf-topbar">`, before `.hf-topbar-context`:

```blade
<button type="button"
        class="hf-icon-btn hf-nav-toggle"
        data-nav-toggle
        aria-controls="hf-rail"
        aria-expanded="true"
        aria-label="Collapse navigation">
    <i class="bi bi-chevron-double-left" aria-hidden="true"></i>
</button>
```

- [ ] **Step 3: Add `initNavRail()` to the shell module**

Append this to `src/resources/js/ui/shell.js`; keep the existing `initShell()` and `initMoreSheet()` unchanged:

```js
const NAV_KEY = 'hf-nav';

export function initNavRail() {
    const root = document.documentElement;
    const toggle = document.querySelector('[data-nav-toggle]');
    const rail = document.getElementById('hf-rail');

    if (!toggle || !rail) return;

    const desktop = window.matchMedia('(min-width: 1200px)');

    const stored = () => {
        try {
            const value = localStorage.getItem(NAV_KEY);
            return value === 'expanded' || value === 'collapsed' ? value : null;
        } catch {
            return null;
        }
    };

    const render = (state) => {
        root.dataset.nav = state;
        toggle.setAttribute('aria-expanded', state === 'expanded' ? 'true' : 'false');
        toggle.setAttribute('aria-label', state === 'expanded' ? 'Collapse navigation' : 'Expand navigation');
    };

    render(root.dataset.nav === 'expanded' ? 'expanded' : 'collapsed');

    toggle.addEventListener('click', () => {
        const next = root.dataset.nav === 'expanded' ? 'collapsed' : 'expanded';
        render(next);

        try {
            localStorage.setItem(NAV_KEY, next);
        } catch {
            // Storage can be unavailable; the current session still works.
        }
    });

    desktop.addEventListener('change', (event) => {
        if (stored()) return;
        render(event.matches ? 'expanded' : 'collapsed');
    });
}
```

- [ ] **Step 4: Initialize the module**

In `src/resources/js/app.js`, change the shell import to:

```js
import { initMoreSheet, initNavRail, initShell } from './ui/shell';
```

Add `initNavRail();` immediately before `initShell();`:

```js
initNavRail();
initShell();
initMoreSheet();
```

- [ ] **Step 5: Make the app grid track follow the rail width**

In `src/resources/css/hf/_shell.scss`, replace the `.hf-app` block with:

```scss
.hf-app {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  grid-template-rows: auto 1fr;
  min-height: 100vh;
}
```

Add an explicit width to `.hf-rail`:

```scss
  width: 248px;
```

Place it after `gap: 6px;` and before `height: 100vh;`.

- [ ] **Step 6: Replace the old compact-rail media block**

Delete the entire block beginning `@media (min-width: 768px) and (max-width: 1199.98px) {` and ending with its matching `}` (the block that currently sets the 76px grid, hides labels, and draws `::after` tooltips). Replace it with:

```scss
.hf-nav-toggle {
  display: none;
}

@media (min-width: 768px) {
  .hf-nav-toggle {
    display: inline-flex;
    flex: none;
  }

  .hf-rail .hf-user-meta {
    display: block;
    max-width: 140px;
    overflow: hidden;
  }

  [data-nav='collapsed'] .hf-rail {
    align-items: center;
    width: 76px;
    padding-inline: 8px;
  }

  [data-nav='collapsed'] .hf-rail-brand {
    justify-content: center;
    padding-inline: 0;
  }

  [data-nav='collapsed'] .hf-rail-brand .hf-brand {
    gap: 0;
  }

  [data-nav='collapsed'] .hf-rail-brand .hf-brand__text,
  [data-nav='collapsed'] .hf-rail .hf-nav-link__label,
  [data-nav='collapsed'] .hf-rail-footer .hf-user-meta,
  [data-nav='collapsed'] .hf-rail-footer .hf-user-chip > .bi {
    max-width: 0;
    opacity: 0;
    overflow: hidden;
    pointer-events: none;
  }

  [data-nav='collapsed'] .hf-rail .hf-rail-label {
    max-height: 8px;
    margin: 2px 0 0;
    overflow: hidden;
    opacity: 0;
  }

  [data-nav='collapsed'] .hf-rail-link {
    justify-content: center;
    gap: 0;
    width: 52px;
    padding: 10px 0;
  }

  [data-nav='collapsed'] .hf-rail-footer {
    width: 100%;
  }

  [data-nav='collapsed'] .hf-rail-footer .hf-user-chip {
    justify-content: center;
    gap: 0;
    width: 52px;
    padding: 4px;
  }
}
```

Leave the existing `@media (min-width: 1200px) { .hf-user-meta { display: block; } }` rule in place for the More sheet account block. Leave the existing `@media (max-width: 767.98px)` block unchanged; it hides the rail and shows the bottom nav.

- [ ] **Step 7: Build**

Run from `src`:

```powershell
npm run build
```

Expected: exit 0; the compiled CSS contains `[data-nav=collapsed]` and `.hf-nav-toggle`.

- [ ] **Step 8: Verify the functional state model**

At 1440px with `localStorage.removeItem('hf-nav')` and a reload:
- `document.documentElement.dataset.nav === 'expanded'`.
- `#hf-rail` is 248px wide; labels are visible.
- The toggle is visible before the breadcrumbs.

Click the toggle:
- `data-nav` becomes `collapsed`; rail becomes 76px; labels disappear; the main content shifts left by 172px.
- `aria-expanded` is `false`; `aria-label` is `Expand navigation`.
- Reload: the rail stays collapsed because `hf-nav=collapsed`.

Clear `hf-nav`, set the viewport to 1024px, and reload:
- `data-nav === 'collapsed'`; rail 76px.
- Click the toggle: rail becomes 248px and the rail's account name/role become visible.

Set the viewport to 390px:
- Toggle is `display: none`; rail is hidden; the bottom nav is visible.

- [ ] **Step 9: Run the nav regression again**

Run from `src`:

```powershell
php artisan test --filter=GameManageTest
```

Expected: PASS.

- [ ] **Step 10: Commit**

```powershell
git add src/resources/views/layouts/public.blade.php src/resources/css/hf/_shell.scss src/resources/js/ui/shell.js src/resources/js/app.js
git commit -m "feat: add collapsible rail with persisted nav state"
```

---

### Task 3: Add motion polish, the active bar, and unclipped tooltips

**Files:**
- Modify: `src/resources/css/hf/_shell.scss`
- Modify: `src/resources/js/ui/shell.js`

**Interfaces:**
- Consumes: `data-nav`, `[data-nav-link]`, `data-label`, `.hf-rail-link`, `#hf-rail`.
- Produces: `div.hf-rail-tooltip` appended to `document.body`; 240ms rail/label transitions; `::before` active bar; 1px icon hover lift; tooltip visible only while collapsed at ≥768px.

- [ ] **Step 1: Add the base transitions and collapsible max-widths**

In `_shell.scss`, update the `.hf-rail` rule to transition width and padding:

```scss
.hf-rail {
  grid-row: 1 / span 2;
  position: sticky;
  top: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 248px;
  height: 100vh;
  overflow-y: auto;
  padding: 18px 14px;
  background: var(--hf-rail);
  color: var(--hf-rail-ink);
  transition: width 240ms cubic-bezier(0.2, 0.7, 0.2, 1),
    padding 240ms cubic-bezier(0.2, 0.7, 0.2, 1);
}
```

Add this declaration inside the existing `.hf-rail-brand` rule:

```scss
  transition: padding 240ms cubic-bezier(0.2, 0.7, 0.2, 1);
```

Add `position: relative;` inside the existing `.hf-rail-link` rule and replace its transition line with:

```scss
  transition: background 160ms ease, color 160ms ease, gap 240ms ease,
    padding 240ms cubic-bezier(0.2, 0.7, 0.2, 1);
```

Add the icon hover behavior to the existing `.hf-rail-link .bi` rule:

```scss
  transition: transform 160ms ease;
```

Then add this rule after `.hf-rail-link .bi`:

```scss
.hf-rail-link:hover .bi {
  transform: translateY(-1px);
}
```

Add these base rules after the existing `.hf-rail-link .bi` block and before `.hf-rail-badge`:

```scss
.hf-rail-brand .hf-brand {
  transition: gap 240ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

.hf-rail-brand .hf-brand__text {
  max-width: 160px;
  overflow: hidden;
  transition: max-width 240ms cubic-bezier(0.2, 0.7, 0.2, 1),
    opacity 160ms ease,
    transform 240ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

.hf-rail-label {
  max-height: 20px;
  overflow: hidden;
  transition: max-height 200ms ease, margin 200ms ease, opacity 160ms ease;
}

.hf-rail-link .hf-nav-link__label {
  max-width: 180px;
  overflow: hidden;
  white-space: nowrap;
  transition: max-width 240ms cubic-bezier(0.2, 0.7, 0.2, 1),
    opacity 160ms ease,
    transform 240ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

.hf-rail-footer .hf-user-meta {
  max-width: 140px;
  overflow: hidden;
  transition: max-width 240ms cubic-bezier(0.2, 0.7, 0.2, 1),
    opacity 160ms ease,
    transform 240ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

.hf-rail-footer .hf-user-chip > .bi {
  max-width: 16px;
  overflow: hidden;
  transition: max-width 240ms cubic-bezier(0.2, 0.7, 0.2, 1),
    opacity 160ms ease;
}
```

Add the toggle chevron transition next to `.hf-nav-toggle`:

```scss
.hf-nav-toggle .bi {
  transition: transform 240ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

[data-nav='collapsed'] .hf-nav-toggle .bi {
  transform: rotate(180deg);
}
```

- [ ] **Step 2: Replace the collapsed overrides with animated versions**

Inside the `@media (min-width: 768px)` block from Task 2, replace the collapsed block with:

```scss
  [data-nav='collapsed'] .hf-rail {
    align-items: center;
    width: 76px;
    padding-inline: 8px;
  }

  [data-nav='collapsed'] .hf-rail-brand {
    justify-content: center;
    padding-inline: 0;
  }

  [data-nav='collapsed'] .hf-rail-brand .hf-brand {
    gap: 0;
  }

  [data-nav='collapsed'] .hf-rail-brand .hf-brand__text,
  [data-nav='collapsed'] .hf-rail .hf-nav-link__label,
  [data-nav='collapsed'] .hf-rail-footer .hf-user-meta {
    max-width: 0;
    opacity: 0;
    transform: translateX(-8px);
    pointer-events: none;
  }

  [data-nav='collapsed'] .hf-rail-footer .hf-user-chip > .bi {
    max-width: 0;
    opacity: 0;
    pointer-events: none;
  }

  [data-nav='collapsed'] .hf-rail .hf-rail-label {
    max-height: 8px;
    margin: 2px 0 0;
    opacity: 0;
  }

  [data-nav='collapsed'] .hf-rail-link {
    justify-content: center;
    gap: 0;
    width: 52px;
    padding: 10px 0;
  }

  [data-nav='collapsed'] .hf-rail-footer {
    width: 100%;
  }

  [data-nav='collapsed'] .hf-rail-footer .hf-user-chip {
    justify-content: center;
    gap: 0;
    width: 52px;
    padding: 4px;
  }
```

The base rules in Step 1 keep `overflow: hidden` on the text boxes; the collapsed block changes only max-width, opacity, transform, and pointer events.

- [ ] **Step 3: Replace the active box-shadow with a growing bar**

Find the existing `.hf-rail-link.is-active` rule and change it to:

```scss
.hf-rail-link.is-active {
  background: color-mix(in srgb, var(--hf-primary) 22%, transparent);
  color: #fff;
}

.hf-rail-link.is-active::before {
  position: absolute;
  top: 50%;
  left: 0;
  width: 3px;
  height: 22px;
  border-radius: 0 3px 3px 0;
  background: var(--hf-primary);
  content: '';
  transform: translateY(-50%) scaleY(0.6);
  transition: transform 160ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

.hf-rail-link.is-active:hover::before,
.hf-rail-link.is-active:focus-visible::before {
  transform: translateY(-50%) scaleY(1);
}
```

The old `box-shadow: inset 3px 0 0 var(--hf-primary);` line must be removed so the bar does not double up.

- [ ] **Step 4: Add the fixed tooltip styles**

Append after the rail link rules:

```scss
.hf-rail-tooltip {
  position: fixed;
  z-index: 90;
  padding: 6px 8px;
  border-radius: 6px;
  background: var(--hf-ink);
  color: var(--hf-ground);
  font-size: 11px;
  line-height: 1.2;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity 120ms linear;
}

.hf-rail-tooltip.is-visible {
  opacity: 1;
}
```

- [ ] **Step 5: Replace `initNavRail()` with the tooltip-aware version**

Replace the entire `initNavRail()` function in `src/resources/js/ui/shell.js` with:

```js
const NAV_KEY = 'hf-nav';
const TOOLTIP_GAP = 10;
const VIEWPORT_GAP = 8;

export function initNavRail() {
    const root = document.documentElement;
    const toggle = document.querySelector('[data-nav-toggle]');
    const rail = document.getElementById('hf-rail');

    if (!toggle || !rail) return;

    const desktop = window.matchMedia('(min-width: 1200px)');
    const tooltip = document.createElement('div');
    tooltip.className = 'hf-rail-tooltip';
    tooltip.setAttribute('aria-hidden', 'true');
    document.body.appendChild(tooltip);

    let tooltipTarget = null;

    const hideTooltip = () => {
        tooltipTarget = null;
        tooltip.classList.remove('is-visible');
    };

    const positionTooltip = (link) => {
        const linkRect = link.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const left = Math.min(
            linkRect.right + TOOLTIP_GAP,
            window.innerWidth - tooltipRect.width - VIEWPORT_GAP,
        );
        const top = Math.min(
            window.innerHeight - tooltipRect.height - VIEWPORT_GAP,
            Math.max(VIEWPORT_GAP, linkRect.top + linkRect.height / 2 - tooltipRect.height / 2),
        );

        tooltip.style.left = `${Math.max(VIEWPORT_GAP, left)}px`;
        tooltip.style.top = `${top}px`;
    };

    const showTooltip = (link) => {
        if (root.dataset.nav !== 'collapsed' || window.innerWidth < 768) return;

        const label = link.dataset.label;
        if (!label) return;

        tooltipTarget = link;
        tooltip.textContent = label;
        tooltip.classList.add('is-visible');
        positionTooltip(link);
    };

    const stored = () => {
        try {
            const value = localStorage.getItem(NAV_KEY);
            return value === 'expanded' || value === 'collapsed' ? value : null;
        } catch {
            return null;
        }
    };

    const render = (state) => {
        root.dataset.nav = state;
        toggle.setAttribute('aria-expanded', state === 'expanded' ? 'true' : 'false');
        toggle.setAttribute('aria-label', state === 'expanded' ? 'Collapse navigation' : 'Expand navigation');
        hideTooltip();
    };

    render(root.dataset.nav === 'expanded' ? 'expanded' : 'collapsed');

    toggle.addEventListener('click', () => {
        const next = root.dataset.nav === 'expanded' ? 'collapsed' : 'expanded';
        render(next);

        try {
            localStorage.setItem(NAV_KEY, next);
        } catch {
            // Storage can be unavailable; the current session still works.
        }
    });

    desktop.addEventListener('change', (event) => {
        if (stored()) return;
        render(event.matches ? 'expanded' : 'collapsed');
    });

    rail.addEventListener('pointerover', (event) => {
        const link = event.target instanceof Element ? event.target.closest('.hf-rail-link') : null;
        if (link && link !== tooltipTarget) showTooltip(link);
    });

    rail.addEventListener('pointerout', (event) => {
        const link = event.target instanceof Element ? event.target.closest('.hf-rail-link') : null;
        if (!link) return;
        if (!link.contains(event.relatedTarget)) hideTooltip();
    });

    rail.addEventListener('focusin', (event) => {
        const link = event.target instanceof Element ? event.target.closest('.hf-rail-link') : null;
        if (link) showTooltip(link);
    });

    rail.addEventListener('focusout', (event) => {
        if (event.target instanceof Element && event.target.closest('.hf-rail-link')) hideTooltip();
    });

    window.addEventListener('scroll', hideTooltip, true);
    window.addEventListener('resize', hideTooltip);
}
```

Do not leave the Task 2 version of the function in place. The `NAV_KEY` constant must appear only once in the file.

- [ ] **Step 6: Build**

Run from `src`:

```powershell
npm run build
```

Expected: exit 0; compiled CSS contains `.hf-rail-tooltip`, `.hf-nav-link__label`, and `.hf-rail-link.is-active::before`.

- [ ] **Step 7: Verify tooltip clipping and motion**

At 1024px with `hf-nav=collapsed`:
- Open DevTools or evaluate: `document.querySelector('.hf-rail').scrollWidth === document.querySelector('.hf-rail').clientWidth` (the old clipped `::after` content is gone).
- Hover Dashboard: one `.hf-rail-tooltip` appears to the right of the rail; its `getBoundingClientRect().right` is within the viewport, and `tooltip.parentElement === document.body`.
- Move the pointer away and scroll: the tooltip hides.
- Focus a rail link with Tab: the tooltip appears; tab to the next link: it moves and stays visible.

Click the toggle:
- The rail width transitions to 248px; labels fade/slide in; group labels expand; the chevron rotates; content is pushed, not overlaid.
- Click again: 76px; labels fade out; the account chip centers on the avatar.

Reduced motion:
- Set `localStorage.setItem('hf-motion', 'reduced')` and reload, or emulate `prefers-reduced-motion: reduce`.
- Toggle: the width and labels change without a visible animation; the chevron jumps; the tooltip appears without a fade.

- [ ] **Step 8: Commit**

```powershell
git add src/resources/css/hf/_shell.scss src/resources/js/ui/shell.js
git commit -m "feat: polish nav collapse motion and tooltips"
```

---

### Task 4: Cross-width verification and quality gate

**Files:**
- None expected. If a check fails, fix the relevant file from Tasks 1–3 and rerun.

**Interfaces:**
- Consumes: everything from Tasks 1–3.
- Produces: evidence that the full suite and every responsive state pass; no test edits.

- [ ] **Step 1: Confirm the working tree**

Run at the repo root:

```powershell
git status --short
```

Expected: no modified implementation files. `.obsidian/graph.json` and `.kilo/` may appear as pre-existing user work; leave them alone.

- [ ] **Step 2: Build**

Run from `src`:

```powershell
npm run build
```

Expected: exit 0. Record the output size of the main CSS/JS assets.

- [ ] **Step 3: Verify the width matrix**

For each width, clear `hf-nav` first, reload, then check the listed state. Use a same-origin iframe or resize the browser pane; media queries must use the iframe's width.

| Width | Expected default | Checks |
|---|---|---|
| 390px | `data-nav="collapsed"` | Toggle hidden; rail hidden; bottom nav visible; no horizontal page scroll; More sheet opens and all groups render. |
| 768px | `data-nav="collapsed"` | Toggle visible; rail 76px; expand → 248px with labels and account meta; collapse → 76px; content pushed by 172px; no horizontal page scroll. |
| 1024px | `data-nav="collapsed"` | Same as 768px; tooltip appears on hover/focus and is not clipped. |
| 1199px | `data-nav="collapsed"` | Same as 1024px; no layout jump at the boundary. |
| 1200px | `data-nav="expanded"` | Rail 248px with labels; collapse → 76px; content shifts left; no horizontal page scroll. |
| 1440px | `data-nav="expanded"` | Same as 1200px; full labels, brand lockup, and account name/role visible. |

At every width where the rail is visible, also check:
- `document.documentElement.scrollWidth === document.documentElement.clientWidth`.
- The topbar toggle does not push the breadcrumb/context into the action buttons.
- The rail can still scroll vertically on a short viewport; the tooltip remains visible outside the rail.

- [ ] **Step 4: Verify persistence, keyboard, reduced motion, and dark mode**

Persistence:
1. At 1024px, expand the rail; reload; it stays expanded.
2. Collapse it; reload; it stays collapsed.
3. Clear `hf-nav`; reload; it returns to the 1024px default (collapsed).
4. Set `hf-nav=expanded`, resize from 1024px to 1440px and back; it stays expanded (stored choice wins).

Keyboard:
- Tab reaches the toggle before the breadcrumbs; Enter and Space flip the state.
- `aria-expanded` matches the rail state after every toggle.
- Tab through rail links; focus rings are visible; the tooltip appears on focus while collapsed.
- Escape does not trap or close anything; the rail is not a modal.

Reduced motion:
- With `hf-motion=reduced` and with OS reduced motion, the rail snaps; there is no width animation, label slide, chevron rotation, or tooltip fade.

Dark mode:
- Toggle the theme at 1024px collapsed and expanded; labels, avatar, active bar, rail border, and tooltip remain legible with no contrast regression.

- [ ] **Step 5: Verify the More sheet and nav contracts**

At 390px, open More:
- Matchday and Workspace links render in the same order and navigate.
- Profile, Appearance, Keyboard shortcuts, and Logout remain in the account block.
- `data-more-link` is present on each sheet link.
- Rail links still expose `data-testid="nav-{key}"`; `nav-gameManage` is present on the game-management page.

- [ ] **Step 6: Run the full quality gate**

Run from `src`:

```powershell
composer quality
```

Expected: exit 0; Pint passes, PHPStan reports no errors, and Pest reports **511 passed (2254 assertions)**. Report the actual count and duration; if the suite count differs because the user added tests during implementation, report the actual output rather than forcing the old number.

- [ ] **Step 7: Final status**

Run:

```powershell
git status --short
```

Expected: only the known `.obsidian/graph.json` and `.kilo/` entries remain. If a check in this task failed, return to the owning task (1, 2, or 3), fix it there, commit that task's exact files, and rerun the failing check plus `composer quality`. Do not create a catch-all commit in Task 4, and never stage `.obsidian/` or `.kilo/`.

---

## Plan Self-Review

- **Spec coverage:** Task 1 covers the shared component and both render targets. Task 2 covers state, defaults, persistence, toggle placement, ARIA, and responsive widths. Task 3 covers motion, active bar, hover lift, tooltip replacement, and reduced motion. Task 4 covers every width, persistence, keyboard, dark mode, More sheet, and the full quality gate.
- **Placeholder scan:** every code step contains the exact snippet; no “implement later” or “similar to Task N”.
- **Type consistency:** `$navGroups`, `$activeKeys`, `activeKeys`, `initNavRail`, `NAV_KEY`, `hf-nav`, `data-nav-toggle`, `#hf-rail`, `.hf-nav-link__label`, and `.hf-rail-tooltip` are named consistently across tasks.
- **Test boundary:** no test file is edited; `GameManageTest` is the targeted nav regression and `composer quality` is the full gate.
- **Known risk:** if the `auto` grid-track width animation stutters, use this exact fallback in Task 3:

```scss
.hf-app {
  grid-template-columns: 248px minmax(0, 1fr);
  transition: grid-template-columns 240ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

[data-nav='collapsed'] .hf-app {
  grid-template-columns: 76px minmax(0, 1fr);
}
```

Remove the `.hf-rail` width transition when using it. The visual result is the same.
