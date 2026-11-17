# SPA Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every link in the authenticated HoopFlow shell navigate through Livewire's `wire:navigate` transport so the browser never reloads the shell again.

**Architecture:** Explicit `wire:navigate` on the rail, mobile tabs, More sheet, brand, bell, and account links; one delegated interceptor for all other internal content links; page-scoped JavaScript re-armed on `livewire:navigated` and torn down on `livewire:navigate`; the existing `.hf-progress` bar reports navigation; Livewire's redirects use `navigate: true`.

**Tech Stack:** Laravel 13, Livewire 4.4.8, Alpine 3, Vite 8, plain ES modules, Node's built-in test runner.

**Spec:** `docs/superpowers/specs/2026-11-17-spa-navigation-design.md`

## Global Constraints

- No new npm or Composer dependency. No Tailwind, Vue, React, Turbo, HTMX, or Bootstrap JavaScript.
- No changes to routes, controllers, policies, services, models, migrations, or domain behavior.
- No change to the landing page, login, register, or guest header behavior.
- Preserve every `data-testid`, route name, form contract, and test-asserted string.
- `.obsidian/` and `.kilo/` are never staged.
- Run commands from `src/` unless a step says otherwise.
- The full Pest suite takes ~9 minutes and `composer test` may exceed Composer's 300s process timeout. Use `php artisan test` directly with a long timeout.
- `composer analyse` must stay at Larastan level 6 with no errors.
- Commit only the files listed in each task. The repository has unrelated uncommitted work.

---

### Task 1: Link eligibility predicate and interceptor

**Files:**
- Create: `src/resources/js/ui/spa-nav.js`
- Create: `src/tests/js/spa-nav.test.js`

**Interfaces:**
- Consumes: nothing.
- Produces: `shouldInterceptLink(link: LinkDescriptor, currentUrl: string): boolean` and `initSpaNavigation(): void`.

`LinkDescriptor` shape:

```js
{
  href: string,            // raw href attribute
  target: string,          // '' when absent
  download: boolean,       // download attribute present
  role: string,            // '' when absent
  hasWireNavigate: boolean,
  hasClickHandler: boolean,
  hasDynamicHref: boolean,
  isOptedOut: boolean,
  shell: boolean,          // this link is inside .hf-app
}
```

- [ ] **Step 1: Write the failing tests**

Create `src/tests/js/spa-nav.test.js`:

```js
import assert from 'node:assert/strict';
import test from 'node:test';
import { shouldInterceptLink } from '../../resources/js/ui/spa-nav.js';

const current = 'https://hoopflow.test/dashboard';

const base = {
    href: '/tournaments',
    target: '',
    download: false,
    role: '',
    hasWireNavigate: false,
    hasClickHandler: false,
    hasDynamicHref: false,
    isOptedOut: false,
    shell: true,
};

test('accepts a plain internal link inside the shell', () => {
    assert.equal(shouldInterceptLink(base, current), true);
});

test('accepts a relative link with a query string', () => {
    assert.equal(shouldInterceptLink({ ...base, href: '/standings?id=3' }, current), true);
});

test('rejects an external origin', () => {
    assert.equal(shouldInterceptLink({ ...base, href: 'https://example.com/tournaments' }, current), false);
});

test('rejects non-http protocols', () => {
    assert.equal(shouldInterceptLink({ ...base, href: 'mailto:ops@hoopflow.test' }, current), false);
    assert.equal(shouldInterceptLink({ ...base, href: 'javascript:void(0)' }, current), false);
});

test('rejects empty and hash-only hrefs', () => {
    assert.equal(shouldInterceptLink({ ...base, href: '' }, current), false);
    assert.equal(shouldInterceptLink({ ...base, href: '#' }, current), false);
});

test('rejects a same-page hash link', () => {
    assert.equal(shouldInterceptLink({ ...base, href: '/dashboard#top' }, current), false);
});

test('rejects the current URL', () => {
    assert.equal(shouldInterceptLink({ ...base, href: current }, current), false);
});

test('rejects links Livewire already owns', () => {
    assert.equal(shouldInterceptLink({ ...base, hasWireNavigate: true }, current), false);
});

test('rejects links owned by a click handler', () => {
    assert.equal(shouldInterceptLink({ ...base, hasClickHandler: true }, current), false);
});

test('rejects links with a dynamically bound href', () => {
    assert.equal(shouldInterceptLink({ ...base, hasDynamicHref: true }, current), false);
});

test('rejects opted-out links', () => {
    assert.equal(shouldInterceptLink({ ...base, isOptedOut: true }, current), false);
});

test('rejects links outside the authenticated shell', () => {
    assert.equal(shouldInterceptLink({ ...base, shell: false }, current), false);
});

test('rejects downloads, new tabs, and button roles', () => {
    assert.equal(shouldInterceptLink({ ...base, download: true }, current), false);
    assert.equal(shouldInterceptLink({ ...base, target: '_blank' }, current), false);
    assert.equal(shouldInterceptLink({ ...base, role: 'button' }, current), false);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `node --test tests/js/spa-nav.test.js`

Expected: FAIL with `Cannot find module .../spa-nav.js` or `shouldInterceptLink is not a function`.

- [ ] **Step 3: Write the implementation**

Create `src/resources/js/ui/spa-nav.js`:

```js
function hasClickHandler(link) {
    return [...link.attributes].some((attribute) => {
        const name = attribute.name;

        return name === 'wire:click'
            || name.startsWith('wire:click.')
            || name === '@click'
            || name.startsWith('@click.')
            || name === 'x-on:click'
            || name.startsWith('x-on:click.');
    });
}

function hasDynamicHref(link) {
    return [...link.attributes].some((attribute) => {
        const name = attribute.name;

        return name === ':href' || name === 'x-bind:href';
    });
}

function hasWireNavigate(link) {
    return [...link.attributes].some((attribute) => attribute.name.startsWith('wire:navigate'));
}

function descriptorFor(link) {
    return {
        href: link.getAttribute('href') ?? '',
        target: link.getAttribute('target') ?? '',
        download: link.hasAttribute('download'),
        role: link.getAttribute('role') ?? '',
        hasWireNavigate: hasWireNavigate(link),
        hasClickHandler: hasClickHandler(link),
        hasDynamicHref: hasDynamicHref(link),
        isOptedOut: link.closest('[data-no-navigate]') !== null,
        shell: link.closest('.hf-app') !== null,
    };
}

export function shouldInterceptLink(link, currentUrl) {
    if (!link || !link.shell) return false;
    if (link.hasWireNavigate) return false;
    if (link.hasClickHandler) return false;
    if (link.hasDynamicHref) return false;
    if (link.isOptedOut) return false;
    if (link.download) return false;
    if (link.role === 'button') return false;
    if (link.target && link.target !== '_self') return false;

    const raw = String(link.href ?? '').trim();
    if (raw === '' || raw.startsWith('#')) return false;

    let url;
    let current;

    try {
        url = new URL(raw, currentUrl);
        current = new URL(currentUrl);
    } catch {
        return false;
    }

    if (url.protocol !== 'http:' && url.protocol !== 'https:') return false;
    if (url.origin !== current.origin) return false;
    if (url.href === current.href) return false;
    if (url.pathname === current.pathname && url.search === current.search && url.hash) return false;

    return true;
}

export function initSpaNavigation() {
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented) return;
        if (event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link) return;
        if (!shouldInterceptLink(descriptorFor(link), window.location.href)) return;
        if (!window.Livewire?.navigate) return;

        const url = new URL(link.href, window.location.href);

        event.preventDefault();
        window.Livewire.navigate(url.href);
    });
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `node --test tests/js/spa-nav.test.js`

Expected: 13 tests, 0 failures.

- [ ] **Step 5: Commit**

```bash
git add "src/resources/js/ui/spa-nav.js" "src/tests/js/spa-nav.test.js"
git commit -m "feat: add SPA navigation link interceptor"
```

---

### Task 2: Split the JavaScript bootstrap into global and page lifecycles

**Files:**
- Create: `src/resources/js/ui/livewire-bridge.js`
- Create: `src/resources/js/ui/page-lifecycle.js`
- Modify: `src/resources/js/app.js` (replace the whole file)
- Modify: `src/resources/js/ui/motion.js` (split `initMotion` and `applyReveal`)
- Modify: `src/resources/js/ui/scorekeeper.js` (split globals and page binding)
- Modify: `src/resources/js/ui/validation.js` (export `refreshValidation`)
- Modify: `src/resources/js/ui/live-scores.js` (restartable poller)
- Test: `src/tests/js/live-scores.test.js` (existing tests must still pass)

**Interfaces:**
- Consumes: `initSpaNavigation()` from Task 1.
- Produces:
  - `initPageLifecycle(): void`
  - `initLivewireBridge(): void`
  - `applyReveal(): void`, `initMotion(): void`
  - `bindScorekeeperPage(): void`, `initScorekeeper(): void`
  - `refreshValidation(): void`, `initValidation(): void`
  - `initLiveScores(): { stop(): void } | null`

- [ ] **Step 1: Create the Livewire modal bridge**

Create `src/resources/js/ui/livewire-bridge.js` with the two functions currently defined at the bottom of `app.js`, wrapped in one initializer:

```js
export function initLivewireBridge() {
    keepModalsOpen();
    restoreDialogFocus();
}

// Native <dialog> elements keep their open state outside the DOM attributes that
// Livewire morphs. Re-open any server-rendered modal after a morph so score
// buttons, forfeit changes, and other roundtrips do not close the dialog.
function keepModalsOpen() {
    function register() {
        Livewire.hook('morphed', ({ el }) => {
            el.querySelectorAll?.('dialog.hf-dialog').forEach((dialog) => {
                if (!dialog.open) {
                    dialog.showModal();
                }
            });
        });
    }

    if (window.Livewire) {
        register();
    } else {
        document.addEventListener('livewire:init', register);
    }
}

// Livewire removes a closing dialog instead of calling close(), so the browser
// cannot restore focus to the element that opened it. Record the control that
// opened each dialog, then restore it on removal. When that control disappears
// with a replaced dialog, fall back to the last still-connected trigger so a
// chooser that supersedes a detail dialog returns to the original card.
function restoreDialogFocus() {
    const triggers = new WeakMap();
    const activations = [];

    const remember = (element) => {
        if (!(element instanceof HTMLElement)) return;
        if (activations[activations.length - 1] === element) return;

        activations.push(element);

        if (activations.length > 12) {
            activations.shift();
        }
    };

    const latestConnected = () => {
        for (let index = activations.length - 1; index >= 0; index--) {
            if (activations[index].isConnected) return activations[index];
        }

        return null;
    };

    document.addEventListener(
        'click',
        (event) => {
            remember(
                event.target instanceof Element
                    ? event.target.closest('button, a, [role="button"], [tabindex], input, select, textarea')
                    : null,
            );
        },
        true,
    );

    document.addEventListener(
        'keydown',
        (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;

            remember(
                event.target instanceof Element
                    ? event.target.closest('button, a, [role="button"], [tabindex]')
                    : null,
            );
        },
        true,
    );

    document.addEventListener(
        'toggle',
        (event) => {
            const dialog = event.target;

            if (!(dialog instanceof HTMLDialogElement) || !dialog.classList.contains('hf-dialog') || !dialog.open) return;
            if (triggers.has(dialog)) return;

            triggers.set(dialog, latestConnected());
        },
        true,
    );

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.removedNodes) {
                if (!(node instanceof Element)) continue;

                const dialogs = node.matches('dialog.hf-dialog')
                    ? [node]
                    : [...node.querySelectorAll('dialog.hf-dialog')];

                for (const dialog of dialogs) {
                    const trigger = triggers.get(dialog);

                    if (trigger instanceof HTMLElement && trigger.isConnected) {
                        trigger.focus({ preventScroll: true });

                        return;
                    }
                }
            }
        }
    }).observe(document.body, { childList: true, subtree: true });
}
```

- [ ] **Step 2: Make the page modules restartable**

Replace `src/resources/js/ui/motion.js`:

```js
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

let bound = false;

export function applyReveal() {
    if (reduce.matches) return;

    document.querySelectorAll('[data-reveal]').forEach((element, index) => {
        element.classList.add('hf-reveal');
        element.style.setProperty('--hf-reveal-delay', `${Math.min(index * 40, 200)}ms`);
    });
}

export function initMotion() {
    if (bound) return;
    bound = true;

    document.addEventListener('hf:theme-changed', () => {
        document.documentElement.classList.add('hf-theme-transition');
        window.setTimeout(() => {
            document.documentElement.classList.remove('hf-theme-transition');
        }, 220);
    });
}
```

Replace `src/resources/js/ui/scorekeeper.js`:

```js
const MODE_KEY = 'hf-scorekeeper';

let bound = false;
let dirty = false;

function setMode(enabled) {
    document.documentElement.dataset.scorekeeper = enabled ? 'on' : 'off';
    sessionStorage.setItem(MODE_KEY, enabled ? 'on' : 'off');
    document.querySelectorAll('[data-scorekeeper-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(enabled));
    });
}

export function initScorekeeper() {
    if (bound) return;
    bound = true;

    document.addEventListener('click', (event) => {
        const toggle = event.target instanceof Element ? event.target.closest('[data-scorekeeper-toggle]') : null;
        if (!toggle) return;

        setMode(toggle.getAttribute('aria-pressed') !== 'true');
    });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;

        event.preventDefault();
        event.returnValue = '';
    });
}

export function bindScorekeeperPage() {
    dirty = false;

    const enabled = sessionStorage.getItem(MODE_KEY) === 'on';
    if (document.querySelector('[data-scorekeeper-toggle]')) setMode(enabled);

    const form = document.querySelector('[data-testid="ingame-actions"]');
    if (!form) return;

    const markDirty = () => { dirty = true; };
    const markClean = () => { dirty = false; };

    document.querySelectorAll('[data-testid^="ingame-q-"], [data-testid^="ingame-final-"], [data-testid^="ingame-stat-"]').forEach((input) => {
        if (input.dataset.hfDirtyBound === '1') return;

        input.dataset.hfDirtyBound = '1';
        input.addEventListener('input', markDirty);
    });

    document.querySelectorAll('[data-testid="ingame-save"], [data-testid="ingame-publish"], [data-testid="ingame-save-correction"]').forEach((button) => {
        if (button.dataset.hfDirtyBound === '1') return;

        button.dataset.hfDirtyBound = '1';
        button.addEventListener('click', markClean);
    });
}
```

In `src/resources/js/ui/validation.js`, rename the private `sync` function to an exported `refreshValidation`, and guard the global listeners:

```js
let bound = false;

export function refreshValidation() {
    ensureErrorIds();
    buildSummary();
}

export function initValidation() {
    refreshValidation();

    if (document.querySelector('[data-hf-error-summary]')) {
        window.requestAnimationFrame(focusFirstInvalid);
    }

    if (bound) return;
    bound = true;

    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morphed', () => {
            refreshValidation();
            focusFirstInvalid();
        });
    });

    document.addEventListener('submit', () => {
        window.setTimeout(refreshValidation, 0);
    });
}
```

Delete the old `function sync()` definition at the bottom of the file.

In `src/resources/js/ui/live-scores.js`, add a module-level active instance and stop it before starting a new one. Move the `visibilitychange` handler into a named function so `stop()` can remove it:

```js
let active = null;

export function initLiveScores() {
    active?.stop();

    const root = document.querySelector('[data-live-scores]');

    if (!root) return null;

    // ... existing body unchanged ...

    const onVisibilityChange = () => {
        if (document.hidden) {
            clearTimeout(timer);

            return;
        }

        clearTimeout(timer);
        poll();
    };

    document.addEventListener('visibilitychange', onVisibilityChange);

    const instance = {
        stop() {
            generation++;
            clearTimeout(timer);
            controller?.abort();
            document.removeEventListener('visibilitychange', onVisibilityChange);

            if (active === instance) active = null;
        },
    };

    active = instance;

    return instance;
}
```

Replace only the old final `return { stop() { ... } };` block with the `instance` construction shown above. The existing anonymous `document.addEventListener('visibilitychange', ...)` call must be replaced by the named handler.

- [ ] **Step 3: Create the lifecycle orchestrator**

Create `src/resources/js/ui/page-lifecycle.js`:

```js
import { initBracketZoom } from './bracket';
import { initCalendar } from './calendar';
import { initCountUp } from './countup';
import { initFeedback } from './feedback';
import { initLiveScores } from './live-scores';
import { initLivewireBridge } from './livewire-bridge';
import { initPageLoader } from './loading';
import { initLogoutDialog } from './logout';
import { applyReveal, initMotion } from './motion';
import { registerPasswordMeter } from './password-strength';
import { initPreferences, syncPreferenceButtons } from './preferences';
import { initScoreConsole } from './score-console';
import { bindScorekeeperPage, initScorekeeper } from './scorekeeper';
import { initSpaNavigation } from './spa-nav';
import { initMoreSheet, initNavRail, initShell, syncNavRail } from './shell';
import { registerToasts } from './toasts';
import { initValidation, refreshValidation } from './validation';

let liveScores = null;
let lastUrl = null;
let globalsReady = false;

function initGlobals() {
    if (globalsReady) return;
    globalsReady = true;

    initBracketZoom();
    initCalendar();
    initFeedback();
    initLivewireBridge();
    initPageLoader();
    initLogoutDialog();
    initMotion();
    initMoreSheet();
    initNavRail();
    initPreferences();
    initScoreConsole();
    initScorekeeper();
    initShell();
    initSpaNavigation();
    initValidation();
    registerPasswordMeter();
    registerToasts();
}

function initPage({ focus = true } = {}) {
    applyReveal();
    initCountUp();

    liveScores?.stop();
    liveScores = initLiveScores();

    bindScorekeeperPage();
    syncNavRail();
    syncPreferenceButtons();
    refreshValidation();

    if (focus) {
        focusMain();
        announcePage();
    }

    consumeFlashToast();
}

function teardownPage() {
    liveScores?.stop();
    liveScores = null;

    document.querySelector('dialog[data-more-sheet][open]')?.close();
    document.querySelector('dialog[data-hf-logout-dialog][open]')?.close();
    document.querySelector('.hf-rail-tooltip')?.remove();
}

function focusMain() {
    document.getElementById('main')?.focus({ preventScroll: true });
}

function announcePage() {
    const region = document.querySelector('[data-hf-page-announcer]');

    if (region) region.textContent = document.title;
}

function consumeFlashToast() {
    const dispatch = () => {
        window.setTimeout(() => {
            const region = document.querySelector('[data-flash-toast]');
            if (!region) return;

            const payload = region.getAttribute('data-flash-toast');
            region.removeAttribute('data-flash-toast');
            if (!payload) return;

            try {
                window.dispatchEvent(new CustomEvent('hf-toast', { detail: JSON.parse(payload) }));
            } catch {
                // Ignore malformed flash payloads.
            }
        }, 0);
    };

    // On the initial load the entry module can run before Livewire starts
    // Alpine, so the toast region has no window listener yet.
    if (document.readyState === 'complete') {
        dispatch();

        return;
    }

    document.addEventListener('DOMContentLoaded', dispatch, { once: true });
}

export function initPageLifecycle() {
    initGlobals();

    document.addEventListener('livewire:navigate', teardownPage);
    document.addEventListener('livewire:navigated', () => {
        if (lastUrl === window.location.href) return;

        const initial = lastUrl === null;

        lastUrl = window.location.href;
        initPage({ focus: !initial });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (lastUrl !== null) return;

            lastUrl = window.location.href;
            initPage({ focus: false });
        });
    } else if (lastUrl === null) {
        lastUrl = window.location.href;
        initPage({ focus: false });
    }
}
```

- [ ] **Step 4: Replace the entry point**

Replace `src/resources/js/app.js` with:

```js
// Client-side entry point.
//
// Alpine 3 ships with Livewire, so no separate Alpine import is needed.
// Bootstrap's JavaScript is intentionally not imported (ADR-0001).
import 'bootstrap-icons/font/bootstrap-icons.css';
import { initPageLifecycle } from './ui/page-lifecycle';

initPageLifecycle();
```

- [ ] **Step 5: Verify the existing JS tests and the bundle**

Run: `node --test "tests/js/*.test.js"`

Expected: all existing tests pass, including `live-scores.test.js`.

Run: `npm run build`

Expected: exit 0. Sass deprecation warnings from Bootstrap are pre-existing and acceptable.

- [ ] **Step 6: Commit**

```bash
git add "src/resources/js/app.js" "src/resources/js/ui/livewire-bridge.js" "src/resources/js/ui/page-lifecycle.js" "src/resources/js/ui/motion.js" "src/resources/js/ui/scorekeeper.js" "src/resources/js/ui/validation.js" "src/resources/js/ui/live-scores.js"
git commit -m "refactor: split JS bootstrap into global and page lifecycles"
```

---

### Task 3: Make the shell dialogs and rail survive a body swap

**Files:**
- Modify: `src/resources/js/ui/shell.js` (replace `initMoreSheet` and `initNavRail`, add `syncNavRail`)
- Modify: `src/resources/js/ui/logout.js` (replace the whole file with delegated handling)

**Interfaces:**
- Consumes: `showPageLoader()` from `loading.js`.
- Produces: `initMoreSheet()`, `initNavRail()`, `syncNavRail()`, `initLogoutDialog()` — all idempotent.

- [ ] **Step 1: Replace `initMoreSheet` and `initNavRail` in `src/resources/js/ui/shell.js`**

Keep `initShell()` unchanged. Replace everything from `export function initMoreSheet()` to the end of the file with:

```js
let moreSheetBound = false;
let sheetTrigger = null;

export function initMoreSheet() {
    if (moreSheetBound) return;
    moreSheetBound = true;

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        const opener = target.closest('[data-more-sheet-open]');
        if (opener) {
            const sheet = document.querySelector('[data-more-sheet]');
            if (!(sheet instanceof HTMLDialogElement)) return;

            sheetTrigger = opener;
            sheet.showModal();

            return;
        }

        if (target.closest('[data-more-sheet-close]')) {
            document.querySelector('dialog[data-more-sheet][open]')?.close();

            return;
        }

        if (target.closest('dialog[data-more-sheet] a')) {
            document.querySelector('dialog[data-more-sheet][open]')?.close();

            return;
        }

        const sheet = target.closest('dialog[data-more-sheet]');
        if (sheet && event.target === sheet) sheet.close();
    });

    document.addEventListener('close', (event) => {
        if (!(event.target instanceof HTMLDialogElement)) return;
        if (!event.target.matches('dialog[data-more-sheet]')) return;

        sheetTrigger?.focus({ preventScroll: true });
        sheetTrigger = null;
    }, true);
}

const NAV_KEY = 'hf-nav';
const TOOLTIP_GAP = 10;
const VIEWPORT_GAP = 8;

let navBound = false;
let tooltip = null;
let tooltipTarget = null;

function ensureTooltip() {
    if (tooltip?.isConnected) return tooltip;

    tooltip = document.createElement('div');
    tooltip.className = 'hf-rail-tooltip';
    tooltip.setAttribute('aria-hidden', 'true');
    document.body.appendChild(tooltip);

    return tooltip;
}

function hideTooltip() {
    tooltipTarget = null;
    tooltip?.classList.remove('is-visible');
}

function positionTooltip(link) {
    const node = ensureTooltip();
    const linkRect = link.getBoundingClientRect();
    const tooltipRect = node.getBoundingClientRect();
    const left = Math.min(
        linkRect.right + TOOLTIP_GAP,
        window.innerWidth - tooltipRect.width - VIEWPORT_GAP,
    );
    const top = Math.min(
        window.innerHeight - tooltipRect.height - VIEWPORT_GAP,
        Math.max(VIEWPORT_GAP, linkRect.top + linkRect.height / 2 - tooltipRect.height / 2),
    );

    node.style.left = `${Math.max(VIEWPORT_GAP, left)}px`;
    node.style.top = `${top}px`;
}

function showTooltip(link) {
    if (document.documentElement.dataset.nav !== 'collapsed' || window.innerWidth < 768) return;

    const label = link.dataset.label;
    if (!label) return;

    tooltipTarget = link;
    ensureTooltip().textContent = label;
    tooltip.classList.add('is-visible');
    positionTooltip(link);
}

function storedNav() {
    try {
        const value = localStorage.getItem(NAV_KEY);

        return value === 'expanded' || value === 'collapsed' ? value : null;
    } catch {
        return null;
    }
}

function renderNav(state) {
    document.documentElement.dataset.nav = state;

    const toggle = document.querySelector('[data-nav-toggle]');
    if (toggle) {
        toggle.setAttribute('aria-expanded', state === 'expanded' ? 'true' : 'false');
        toggle.setAttribute('aria-label', state === 'expanded' ? 'Collapse navigation' : 'Expand navigation');
    }

    hideTooltip();
}

export function syncNavRail() {
    renderNav(document.documentElement.dataset.nav === 'expanded' ? 'expanded' : 'collapsed');
}

export function initNavRail() {
    if (navBound) return;
    navBound = true;

    const desktop = window.matchMedia('(min-width: 1200px)');

    syncNavRail();

    document.addEventListener('click', (event) => {
        const toggle = event.target instanceof Element ? event.target.closest('[data-nav-toggle]') : null;
        if (!toggle) return;

        const next = document.documentElement.dataset.nav === 'expanded' ? 'collapsed' : 'expanded';
        renderNav(next);

        try {
            localStorage.setItem(NAV_KEY, next);
        } catch {
            // Storage can be unavailable; the current session still works.
        }
    });

    desktop.addEventListener('change', (event) => {
        if (storedNav()) return;

        renderNav(event.matches ? 'expanded' : 'collapsed');
    });

    document.addEventListener('pointerover', (event) => {
        const link = event.target instanceof Element ? event.target.closest('.hf-rail-link') : null;
        if (link && link !== tooltipTarget) showTooltip(link);
    });

    document.addEventListener('pointerout', (event) => {
        const link = event.target instanceof Element ? event.target.closest('.hf-rail-link') : null;
        if (!link) return;
        if (!link.contains(event.relatedTarget)) hideTooltip();
    });

    document.addEventListener('focusin', (event) => {
        const link = event.target instanceof Element ? event.target.closest('.hf-rail-link') : null;
        if (link) showTooltip(link);
    });

    document.addEventListener('focusout', (event) => {
        if (event.target instanceof Element && event.target.closest('.hf-rail-link')) hideTooltip();
    });

    window.addEventListener('scroll', hideTooltip, true);
    window.addEventListener('resize', hideTooltip);
}
```

- [ ] **Step 2: Replace `src/resources/js/ui/logout.js`**

```js
import { showPageLoader } from './loading';

const EXIT_MS = 180;
const SUBMIT_MS = 560;

let bound = false;
let activeForm = null;
let closing = false;
let busy = false;

function dialog() {
    return document.querySelector('[data-hf-logout-dialog]');
}

function reset() {
    closing = false;
    busy = false;

    const node = dialog();
    if (!(node instanceof HTMLDialogElement)) return;

    const confirmButton = node.querySelector('[data-hf-logout-confirm]');
    const cancelButton = node.querySelector('[data-hf-logout-cancel]');
    const copy = node.querySelector('[data-hf-logout-copy]');
    const label = node.querySelector('[data-hf-logout-label]');

    node.classList.remove('is-closing', 'is-confirming');
    confirmButton?.classList.remove('is-loading');
    if (confirmButton) confirmButton.disabled = false;
    if (cancelButton) cancelButton.disabled = false;
    if (label) label.textContent = 'Log out';
    if (copy) copy.textContent = copy.dataset.defaultCopy ?? '';
}

function open(form) {
    const node = dialog();
    if (!(node instanceof HTMLDialogElement)) return;

    activeForm = form;

    const copy = node.querySelector('[data-hf-logout-copy]');
    if (copy && !copy.dataset.defaultCopy) {
        copy.dataset.defaultCopy = copy.textContent.trim();
    }

    reset();

    const sheet = document.querySelector('dialog[data-more-sheet][open]');
    if (sheet instanceof HTMLDialogElement) sheet.close();

    node.showModal();
}

function close() {
    const node = dialog();
    if (!(node instanceof HTMLDialogElement) || closing || busy) return;

    closing = true;
    node.classList.add('is-closing');

    const finish = () => {
        node.close();
        node.classList.remove('is-closing');
        closing = false;
    };

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (reduceMotion.matches || document.documentElement.dataset.motion === 'reduced') {
        finish();

        return;
    }

    window.setTimeout(finish, EXIT_MS);
}

function submit() {
    const node = dialog();
    if (!activeForm || busy || !(node instanceof HTMLDialogElement)) return;

    busy = true;
    node.classList.add('is-confirming');

    const confirmButton = node.querySelector('[data-hf-logout-confirm]');
    const cancelButton = node.querySelector('[data-hf-logout-cancel]');
    const copy = node.querySelector('[data-hf-logout-copy]');
    const label = node.querySelector('[data-hf-logout-label]');

    confirmButton?.classList.add('is-loading');
    if (confirmButton) confirmButton.disabled = true;
    if (cancelButton) cancelButton.disabled = true;
    if (label) label.textContent = 'Signing out';
    if (copy) copy.textContent = 'Closing your session. See you on the court.';

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const delay = reduceMotion.matches || document.documentElement.dataset.motion === 'reduced' ? 0 : SUBMIT_MS;

    window.setTimeout(() => {
        showPageLoader();
        HTMLFormElement.prototype.submit.call(activeForm);
    }, delay);
}

export function initLogoutDialog() {
    if (bound) return;
    bound = true;

    document.addEventListener('submit', (event) => {
        const form = event.target instanceof Element ? event.target.closest('form[data-hf-logout-form]') : null;
        if (!form) return;

        event.preventDefault();
        open(form);
    });

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        if (target.closest('[data-hf-logout-cancel]')) {
            close();

            return;
        }

        if (target.closest('[data-hf-logout-confirm]')) {
            submit();

            return;
        }

        const node = dialog();
        if (node && event.target === node) close();
    });

    document.addEventListener('cancel', (event) => {
        if (!(event.target instanceof HTMLDialogElement) || !event.target.matches('[data-hf-logout-dialog]')) return;

        event.preventDefault();
        close();
    }, true);

    document.addEventListener('close', (event) => {
        if (event.target instanceof HTMLDialogElement && event.target.matches('[data-hf-logout-dialog]')) reset();
    }, true);
}
```

- [ ] **Step 3: Verify the bundle and tests**

Run: `node --test "tests/js/*.test.js"`

Expected: all tests pass.

Run: `npm run build`

Expected: exit 0.

- [ ] **Step 4: Commit**

```bash
git add "src/resources/js/ui/shell.js" "src/resources/js/ui/logout.js"
git commit -m "refactor: make shell dialogs survive page swaps"
```

---

### Task 4: Wire navigation progress, skeleton gating, page announcement, and flash toasts

**Files:**
- Create: `src/config/livewire.php`
- Modify: `src/resources/js/ui/feedback.js` (export navigation helpers)
- Modify: `src/resources/js/ui/loading.js` (navigation hooks and watchdog)
- Modify: `src/resources/views/layouts/public.blade.php` (announcer, flash attribute)
- Test: `src/tests/Feature/Pages/FlashToastTest.php` (new)

**Interfaces:**
- Consumes: `beginNavigation()` and `finishNavigation()` from `feedback.js`.
- Produces: `beginNavigation(): void`, `finishNavigation(): void`; a `[data-hf-page-announcer]` live region; a `[data-flash-toast]` payload attribute.

- [ ] **Step 1: Write the failing feature test**

Create `src/tests/Feature/Pages/FlashToastTest.php`:

```php
<?php

use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('renders the flash toast payload for the client to consume', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->withSession(['announcement_toast' => [
            'title' => 'Announcement published',
            'message' => 'The bulletin is live for its audience.',
            'tone' => 'success',
        ]])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-flash-toast', false)
        ->assertSee('Announcement published', false);
});

it('renders the page announcer region in the authenticated shell', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-hf-page-announcer', false);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Pages/FlashToastTest.php`

Expected: FAIL because `data-hf-page-announcer` and `data-flash-toast` are not rendered.

- [ ] **Step 3: Add the announcer and flash attribute to the layout**

In `src/resources/views/layouts/public.blade.php`, immediately after the skip link:

```blade
<a class="visually-hidden-focusable" href="#main">Skip to content</a>
<p class="hf-sr-only" role="status" aria-live="polite" aria-atomic="true" data-hf-page-announcer></p>
```

Replace the toast region opening tag with the flash payload. The existing `x-init` is removed so the lifecycle handler is the only consumer:

```blade
<div class="hf-toast-region"
     aria-live="polite"
     aria-atomic="false"
     x-data="hfToasts"
     @hf-toast.window="push($event.detail)"
     @if ($flashToast)
         data-flash-toast="{{ json_encode($flashToast) }}"
     @endif>
```

The lifecycle handler reads the attribute on the next task, removes it, and
dispatches the toast exactly once. The attribute is re-rendered by the server on
the next request, so no JavaScript path can show it twice.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Pages/FlashToastTest.php`

Expected: 2 tests pass.

- [ ] **Step 5: Export navigation progress helpers**

In `src/resources/js/ui/feedback.js`, append:

```js
export function beginNavigation() {
    startProgress();
}

export function finishNavigation() {
    finishProgress();
}
```

- [ ] **Step 6: Add navigation hooks to the page loader**

In `src/resources/js/ui/loading.js`, add the import at the top:

```js
import { beginNavigation, finishNavigation } from './feedback';
```

Add a watchdog variable near `safetyTimer`:

```js
let navigationWatchdog = null;
```

At the very start of `initPageLoader()`, before the `if (!loaderElement()) return;`
guard, add:

```js
    document.addEventListener('livewire:navigate', () => {
        beginNavigation();

        window.clearTimeout(navigationWatchdog);
        navigationWatchdog = window.setTimeout(() => {
            finishNavigation();

            window.dispatchEvent(new CustomEvent('hf-toast', {
                detail: {
                    tone: 'danger',
                    title: 'The page didn’t load',
                    message: 'Check your connection and try again.',
                    actionLabel: 'Retry',
                    actionHref: window.location.href,
                },
            }));
        }, 15000);
    });

    document.addEventListener('livewire:navigated', () => {
        window.clearTimeout(navigationWatchdog);
        finishNavigation();
        hidePageLoader();
    });
```

Keep the existing `if (!loaderElement()) return;` after those two listeners.

- [ ] **Step 7: Disable Livewire's duplicate navigate progress bar**

Create `src/config/livewire.php`:

```php
<?php

return [
    'navigate' => [
        'show_progress_bar' => false,
        'progress_bar_color' => '#2299dd',
    ],
];
```

Laravel's `mergeConfigFrom` is shallow, so both keys are repeated intentionally.

- [ ] **Step 8: Verify the build**

Run: `npm run build`

Expected: exit 0.

- [ ] **Step 9: Commit**

```bash
git add "src/config/livewire.php" "src/resources/js/ui/feedback.js" "src/resources/js/ui/loading.js" "src/resources/views/layouts/public.blade.php" "src/tests/Feature/Pages/FlashToastTest.php"
git commit -m "feat: wire navigation progress and flash toasts"
```

---

### Task 5: Enable `wire:navigate` on the shell and Livewire redirects

**Files:**
- Modify: `src/resources/views/components/app-nav.blade.php`
- Modify: `src/resources/views/components/user-menu.blade.php`
- Modify: `src/resources/views/layouts/public.blade.php`
- Modify: `src/app/Livewire/Organizer/AnnouncementCreate.php:133`
- Modify: `src/app/Livewire/Public/StandingsShow.php:90`
- Modify: `src/app/Livewire/Organizer/GameManage.php:828,839,919`
- Modify: `src/app/Livewire/Organizer/BracketShow.php:827`
- Modify: `src/app/Livewire/Organizer/GameStatsShow.php:687,783,833,871,944,999`
- Test: `src/tests/Feature/Accessibility/SpaNavigationTest.php` (new)

**Interfaces:**
- Consumes: `initSpaNavigation()` (Task 1), lifecycle (Task 2), feedback (Task 4).
- Produces: `wire:navigate` on shell anchors and `navigate: true` on Livewire redirects.

- [ ] **Step 1: Write the failing feature test**

Create `src/tests/Feature/Accessibility/SpaNavigationTest.php`:

```php
<?php

use App\Livewire\Public\StandingsShow;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function spaXPath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($dom);
}

it('marks rail and mobile navigation links for AJAX navigation', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $response = $this->actingAs($organizer)->get(route('dashboard'))->assertOk();
    $xpath = spaXPath((string) $response->getContent());

    foreach (['nav-dashboard', 'nav-tournaments', 'nav-bracket'] as $testId) {
        $link = $xpath->query("//a[@data-testid='{$testId}']")->item(0);

        expect($link)->not->toBeNull("Missing {$testId}");
        expect($link->hasAttribute('wire:navigate'))->toBeTrue("{$testId} lacks wire:navigate");
    }

    $tabs = $xpath->query("//a[starts-with(@data-testid, 'mobile-nav-') and @data-testid != 'mobile-nav-more']");
    expect($tabs->length)->toBeGreaterThan(0);

    foreach ($tabs as $tab) {
        expect($tab->hasAttribute('wire:navigate'))->toBeTrue($tab->getAttribute('data-testid').' lacks wire:navigate');
    }
});

it('leaves guest pages as normal browser navigation', function () {
    $response = $this->get(route('login'))->assertOk();

    expect(substr_count((string) $response->getContent(), 'wire:navigate'))->toBe(0);
});

it('navigates after switching the standings tournament', function () {
    $tournament = Tournament::query()->firstOrFail();

    $component = Livewire::test(StandingsShow::class)
        ->call('changeTournament', $tournament->id);

    $component->assertRedirect(route('standings', ['id' => $tournament->id]));
    expect($component->effects['redirectUsingNavigate'] ?? false)->toBeTrue();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Accessibility/SpaNavigationTest.php`

Expected: FAIL because the shell links lack `wire:navigate` and the standings redirect lacks `navigate: true`.

- [ ] **Step 3: Add `wire:navigate` to the shared nav component**

In `src/resources/views/components/app-nav.blade.php`, add `wire:navigate` to both anchors:

```blade
            @if ($isSheet)
                <a href="{{ route($item['route']) }}"
                   wire:navigate
                   data-more-link="{{ $key }}"
                   @if ($active) aria-current="page" @endif>
                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>{{ $item['label'] }}
                </a>
            @else
                <a class="hf-rail-link{{ $active ? ' is-active' : '' }}"
                   href="{{ route($item['route']) }}"
                   wire:navigate
                   data-testid="nav-{{ $key }}"
                   data-label="{{ $item['label'] }}"
                   data-nav-link
                   @if ($active) aria-current="page" @endif>
                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                    <span class="hf-nav-link__label">{{ $item['label'] }}</span>
                </a>
            @endif
```

- [ ] **Step 4: Add `wire:navigate` to the account menu**

In `src/resources/views/components/user-menu.blade.php`, add `wire:navigate` to all three anchors:

```blade
        <a href="{{ route('profile') }}" wire:navigate data-testid="topbar-profile"><i class="bi bi-person" aria-hidden="true"></i> Profile</a>
        <a href="{{ route('profile') }}#appearance" wire:navigate><i class="bi bi-palette" aria-hidden="true"></i> Appearance</a>
        <a href="{{ route('profile') }}#shortcuts" wire:navigate><i class="bi bi-keyboard" aria-hidden="true"></i> Keyboard shortcuts</a>
```

- [ ] **Step 5: Add `wire:navigate` to the shell links in the layout**

In `src/resources/views/layouts/public.blade.php`:

Rail brand:

```blade
                <a class="hf-rail-brand" href="{{ route('dashboard') }}" wire:navigate aria-label="HoopFlow dashboard">
```

Topbar announcements bell:

```blade
                    <a class="hf-icon-btn" href="{{ route('announcements') }}" wire:navigate aria-label="Announcements" data-testid="topbar-announcements">
```

Mobile tabs:

```blade
                    <a class="hf-nav-tab{{ $isActive($tab['key']) ? ' is-active' : '' }}"
                       href="{{ route($tab['route']) }}"
                       wire:navigate
                       data-testid="mobile-nav-{{ $tab['key'] }}"
                       @if ($isActive($tab['key'])) aria-current="page" @endif>
```

More-sheet account links:

```blade
                            <a href="{{ route('profile') }}" wire:navigate><i class="bi bi-person" aria-hidden="true"></i> Profile</a>
                            <a href="{{ route('profile') }}#appearance" wire:navigate><i class="bi bi-palette" aria-hidden="true"></i> Appearance</a>
                            <a href="{{ route('profile') }}#shortcuts" wire:navigate><i class="bi bi-keyboard" aria-hidden="true"></i> Keyboard shortcuts</a>
```

and:

```blade
                        <a href="{{ route('profile') }}" wire:navigate data-more-link="profile"><i class="bi bi-person" aria-hidden="true"></i> Profile</a>
```

- [ ] **Step 6: Add `navigate: true` to the Livewire redirects**

Make each of these calls pass `navigate: true`:

```php
// AnnouncementCreate.php:133
$this->redirect(route('announcements', ['id' => $announcement->tournament_id]), navigate: true);

// StandingsShow.php:90
$this->redirect(route('standings', ['id' => (int) $tournamentId]), navigate: true);

// GameManage.php:828, 839, 919
$this->redirect(route('gameStats', ['match' => $match->id]), navigate: true);
$this->redirect(route('gameStats', ['match' => $match->id]), navigate: true);
$this->redirect(route('gameStats', ['match' => $match->id, 'correct' => 1]), navigate: true);

// BracketShow.php:827
$this->redirect(route('gameManage', ['match' => $match->id]), navigate: true);

// GameStatsShow.php:687, 783, 833, 871, 944, 999
$this->redirect(route('gameManage', ['match' => $match->id]), navigate: true);
$this->redirect(route('gameManage', ['match' => $match->id]), navigate: true);
$this->redirect(route('gameManage', ['match' => $match->id, 'correct' => 1]), navigate: true);
$this->redirect(route('gameManage', ['match' => $match->id, 'correct' => 1]), navigate: true);
$this->redirect(route('gameManage', ['match' => $match->id]), navigate: true);
$this->redirect(route('gameManage', ['match' => $match->id, 'correct' => 1]), navigate: true);
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Accessibility/SpaNavigationTest.php`

Expected: 3 tests pass.

- [ ] **Step 8: Commit**

```bash
git add "src/resources/views/components/app-nav.blade.php" "src/resources/views/components/user-menu.blade.php" "src/resources/views/layouts/public.blade.php" "src/app/Livewire/Organizer/AnnouncementCreate.php" "src/app/Livewire/Public/StandingsShow.php" "src/app/Livewire/Organizer/GameManage.php" "src/app/Livewire/Organizer/BracketShow.php" "src/app/Livewire/Organizer/GameStatsShow.php" "src/tests/Feature/Accessibility/SpaNavigationTest.php"
git commit -m "feat: enable wire:navigate across the app shell"
```

---

### Task 6: Full quality gates and browser smoke test

**Files:**
- No planned production changes. If a gate fails, fix the failing file and amend the relevant task commit.

**Interfaces:**
- Consumes: everything from Tasks 1–5.
- Produces: a verified, shippable feature branch.

- [ ] **Step 1: Run formatting and static analysis**

Run: `vendor/bin/pint app/Livewire/Organizer/AnnouncementCreate.php app/Livewire/Public/StandingsShow.php app/Livewire/Organizer/GameManage.php app/Livewire/Organizer/BracketShow.php app/Livewire/Organizer/GameStatsShow.php config/livewire.php tests/Feature/Accessibility/SpaNavigationTest.php tests/Feature/Pages/FlashToastTest.php`

Expected: `{"tool":"pint","result":"passed"}`.

Run: `composer analyse`

Expected: `[OK] No errors`.

- [ ] **Step 2: Run the JS tests and production build**

Run: `node --test "tests/js/*.test.js"`

Expected: all tests pass, including the 12 new `spa-nav` cases and the existing `live-scores` cases.

Run: `npm run build`

Expected: exit 0.

- [ ] **Step 3: Run the full Pest suite**

Run: `php artisan test` with a timeout of at least 1,500,000 ms.

Expected: all tests pass. Record the pass count and assertion count.

- [ ] **Step 4: Browser smoke test**

Start the dev server from `src/`:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

If the local database is empty, run `php artisan migrate --seed` first and use the seeded organizer (`organizer@hoopflow.ph` / `password`).

In the browser:

1. Sign in and open the Dashboard.
2. Open DevTools and run `window.__spaMarker = 'alive'`.
3. Click every rail destination in turn and return to Dashboard.
4. Confirm `window.__spaMarker` still equals `'alive'` (the document did not reload).
5. Click a tournament card, a team row, and a bracket match; confirm each swaps without a reload and the marker survives.
6. Confirm the thin progress bar appears on a throttled connection and clears.
7. Confirm the active rail item and `aria-current="page"` follow the current page.
8. On Bracket, click a zoom button; on Schedule, use ArrowRight on a day cell; on Game Stats, open the score console; return to Dashboard and confirm count-up tiles animate.
9. Visit the live board, confirm polling updates the status line, navigate away, and confirm no further live-score requests appear in the Network panel.
10. Create an announcement, confirm the success toast appears after the redirect.
11. Log out, confirm the full-screen loader appears, and confirm the session ends with a real submit.

- [ ] **Step 5: Report**

Report:

- The actual `composer analyse` output.
- The full Pest pass and assertion counts.
- The `node --test` pass count.
- The browser observations from step 4.
- The list of commits created by Tasks 1–5.
- Any deviations from the plan.
