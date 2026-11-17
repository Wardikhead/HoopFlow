# Page, Dialog, and Dropdown Motion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add natural in/out motion to SPA page changes, dialog dismissals, and the account dropdown, plus an account header and item-level dropdown treatment, without moving the shell or touching domain behavior.

**Architecture:** Livewire 4's `wire:transition.navigate` names `<main>` for a native view transition, so only page content animates. A `morph.removing` hook holds server-rendered dialogs for a 160ms exit after the response succeeds. A small `<details>` controller animates the account menu open and closed, with CSS for the header, icon tiles, and stagger.

**Tech Stack:** Laravel 13, Livewire 4, Blade, SCSS (Bootstrap 5.3 compiled by Vite), vanilla ES modules, Node test runner, Pest.

## Global Constraints

- No new dependencies; no Tailwind, Vue, React, or Bootstrap JavaScript.
- Preserve every route, controller, policy, service, model, migration, `data-testid`, form contract, and test-asserted string.
- Full page loads keep the existing `hf-page-in` and `hf-reveal` behavior; do not add cross-document transitions.
- SPA navigation must not move or animate the rail, topbar, or progress bar.
- Respect both `prefers-reduced-motion: reduce` and `document.documentElement.dataset.motion === 'reduced'`.
- Never stage `.obsidian/` or `.kilo/`.
- Run `composer quality` before claiming completion. If Composer's 300s cap interrupts the test phase, run `php artisan test` directly and report both results.
- The authenticated shell is the only scope. Guest pages must render zero `wire:navigate` and zero `wire:transition.navigate` attributes.
- Commands are run from `src/` for PHP, Node, and npm work.

---

### Task 1: Shared motion gate

**Files:**
- Modify: `src/resources/js/ui/motion.js`
- Test: `src/tests/js/motion.test.js`

**Interfaces:**
- Produces: `motionEnabled({ reduce, preference } = {}) => boolean` from `motion.js`.
- Later tasks import `motionEnabled` into `dialog-motion.js` and `menus.js`.

- [ ] **Step 1: Write the failing test**

Create `src/tests/js/motion.test.js`:

```js
import assert from 'node:assert/strict';
import test from 'node:test';
import { motionEnabled } from '../../resources/js/ui/motion.js';

test('motion is enabled when the OS setting is normal', () => {
    assert.equal(motionEnabled({ reduce: false, preference: 'system' }), true);
});

test('OS reduced motion disables animation', () => {
    assert.equal(motionEnabled({ reduce: true, preference: 'system' }), false);
});

test('the in-app reduced preference disables animation', () => {
    assert.equal(motionEnabled({ reduce: false, preference: 'reduced' }), false);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `node --test "tests/js/motion.test.js"`

Expected: FAIL because `motion.js` does not export `motionEnabled`.

- [ ] **Step 3: Implement the gate**

In `src/resources/js/ui/motion.js`, replace the opening line:

```js
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
```

with:

```js
const reduce = typeof window !== 'undefined'
    ? window.matchMedia('(prefers-reduced-motion: reduce)')
    : { matches: false };
```

Then add this export above `applyReveal`:

```js
export function motionEnabled({ reduce: reduced = null, preference = null } = {}) {
    const osReduced = reduced ?? reduce.matches;
    const preferenceValue = preference
        ?? (typeof document !== 'undefined' ? document.documentElement.dataset.motion : 'system');

    return !osReduced && preferenceValue !== 'reduced';
}
```

Leave `applyReveal` and `initMotion` unchanged.

- [ ] **Step 4: Run the test to verify it passes**

Run: `node --test "tests/js/motion.test.js"`

Expected: 3 tests pass, 0 fail.

- [ ] **Step 5: Commit**

```bash
git add src/resources/js/ui/motion.js src/tests/js/motion.test.js
git commit -m "feat: add shared motion preference gate"
```

---

### Task 2: Name the authenticated page for view transitions

**Files:**
- Modify: `src/resources/views/layouts/public.blade.php` (authenticated `<main>`)
- Test: `src/tests/Feature/Accessibility/SpaNavigationTest.php`

**Interfaces:**
- Produces: `<main id="main" class="hf-main" ... wire:transition.navigate="hf-page">`.
- Task 3 targets the transition name `hf-page`.

- [ ] **Step 1: Write the failing Pest tests**

Append to `src/tests/Feature/Accessibility/SpaNavigationTest.php`:

```php
it('names the authenticated main element for view transitions', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $response = $this->actingAs($organizer)->get(route('dashboard'))->assertOk();
    $xpath = spaXPath((string) $response->getContent());
    $main = $xpath->query("//main[@id='main']")->item(0);

    expect($main)->not->toBeNull();
    expect($main->getAttribute('wire:transition.navigate'))->toBe('hf-page');
});

it('keeps guest pages free of navigation transition hooks', function () {
    $response = $this->get(route('login'))->assertOk();

    expect(substr_count((string) $response->getContent(), 'wire:transition.navigate'))->toBe(0);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Accessibility/SpaNavigationTest.php`

Expected: the new authenticated test fails because the attribute is missing; the guest test passes.

- [ ] **Step 3: Add the attribute**

In `src/resources/views/layouts/public.blade.php`, change the authenticated main tag from:

```blade
<main id="main" class="hf-main" tabindex="-1">
```

to:

```blade
<main id="main" class="hf-main" tabindex="-1" wire:transition.navigate="hf-page">
```

Do not change the guest `<main>` at line 99 or `layouts/landing.blade.php`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Accessibility/SpaNavigationTest.php`

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/resources/views/layouts/public.blade.php src/tests/Feature/Accessibility/SpaNavigationTest.php
git commit -m "feat: enable view transitions on authenticated pages"
```

---

### Task 3: Page transition CSS and duration tokens

**Files:**
- Modify: `src/resources/css/hf/_tokens.scss`
- Modify: `src/resources/css/hf/_motion.scss`

**Interfaces:**
- Consumes: the `hf-page` transition name from Task 2.
- Produces: `--hf-dur-exit: 140ms` and `--hf-dur-page: 240ms`.

- [ ] **Step 1: Add the duration tokens**

In `src/resources/css/hf/_tokens.scss`, after `--hf-dur-expressive: 280ms;`, add:

```css
  --hf-dur-exit: 140ms;
  --hf-dur-page: 240ms;
```

Then replace the existing `[data-motion='reduced']` block with:

```css
[data-motion='reduced'] {
  --hf-dur-exit: 0ms;
  --hf-dur-page: 0ms;
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

- [ ] **Step 2: Add the view transition styles**

At the end of `src/resources/css/hf/_motion.scss`, after the `hf-spa-nav` rules, append:

```css
/* SPA page swap: only the named <main> participates. */
::view-transition-group(hf-page) {
  animation: none;
}

::view-transition-old(hf-page) {
  animation: hf-page-vt-out var(--hf-dur-exit) ease-in both;
}

::view-transition-new(hf-page) {
  animation: hf-page-vt-in var(--hf-dur-page) var(--hf-ease-out) both;
}

@keyframes hf-page-vt-out {
  from { opacity: 1; transform: none; }
  to { opacity: 0; transform: translateY(-4px); }
}

@keyframes hf-page-vt-in {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: none; }
}
```

- [ ] **Step 3: Build and verify the compiled CSS**

Run: `npm run build`

Expected: build succeeds.

Run:

```powershell
Get-ChildItem public/build/assets/app-*.css | Select-String -Pattern 'view-transition-old\(hf-page\)' | Select-Object -First 1
```

Expected: one match showing the view-transition rule in the compiled CSS.

- [ ] **Step 4: Browser verification**

Start the dev server in the background:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Open `/dashboard`, click a rail link, and confirm while the transition runs:

- the old page content fades and lifts 4px;
- the new content fades and rises 8px;
- the rail width, topbar, and progress bar do not move;
- the rail stays at its saved width with no wrong-theme frames.

Record the rail width samples and the absence of `hf-page-in` animation events. Stop the server when done.

- [ ] **Step 5: Commit**

```bash
git add src/resources/css/hf/_tokens.scss src/resources/css/hf/_motion.scss
git commit -m "feat: animate SPA page content transitions"
```

---

### Task 4: Dialog exit predicate and hook

**Files:**
- Create: `src/resources/js/ui/dialog-motion.js`
- Test: `src/tests/js/dialog-motion.test.js`

**Interfaces:**
- Consumes: `motionEnabled` from `motion.js` (Task 1).
- Produces:
  - `DIALOG_EXIT_MS = 160`
  - `shouldHoldDialog(element, options = {}) => boolean`
  - `holdAndRemove(dialog, { duration } = {})`
  - `closeDialogWithMotion(dialog, { duration } = {})`
  - `initDialogMotion()`

- [ ] **Step 1: Write the failing tests**

Create `src/tests/js/dialog-motion.test.js`:

```js
import assert from 'node:assert/strict';
import test from 'node:test';
import { shouldHoldDialog } from '../../resources/js/ui/dialog-motion.js';

function dialog({ open = true, closing = false } = {}) {
    const names = new Set(['hf-dialog']);
    if (closing) names.add('is-closing');

    return {
        tagName: 'DIALOG',
        open,
        classList: {
            contains: (name) => names.has(name),
        },
    };
}

test('holds an open animated dialog', () => {
    assert.equal(shouldHoldDialog(dialog(), { reduce: false, preference: 'system' }), true);
});

test('skips a dialog that is already closed', () => {
    assert.equal(shouldHoldDialog(dialog({ open: false }), { reduce: false, preference: 'system' }), false);
});

test('skips a dialog that is already closing', () => {
    assert.equal(shouldHoldDialog(dialog({ closing: true }), { reduce: false, preference: 'system' }), false);
});

test('skips nodes that are not dialogs', () => {
    assert.equal(
        shouldHoldDialog(
            { tagName: 'DIV', open: true, classList: { contains: () => true } },
            { reduce: false, preference: 'system' },
        ),
        false,
    );
});

test('skips reduced motion', () => {
    assert.equal(shouldHoldDialog(dialog(), { reduce: true, preference: 'system' }), false);
    assert.equal(shouldHoldDialog(dialog(), { reduce: false, preference: 'reduced' }), false);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `node --test "tests/js/dialog-motion.test.js"`

Expected: FAIL because `dialog-motion.js` does not exist.

- [ ] **Step 3: Implement the module**

Create `src/resources/js/ui/dialog-motion.js`:

```js
import { motionEnabled } from './motion';

export const DIALOG_EXIT_MS = 160;

export function shouldHoldDialog(element, options = {}) {
    if (!element || element.tagName !== 'DIALOG') return false;
    if (!element.classList?.contains('hf-dialog')) return false;
    if (!element.open) return false;
    if (element.classList.contains('is-closing')) return false;

    return motionEnabled(options);
}

export function holdAndRemove(dialog, { duration = DIALOG_EXIT_MS } = {}) {
    if (!dialog) return;

    dialog.classList.add('is-closing');

    window.setTimeout(() => {
        if (!dialog.isConnected) return;

        if (dialog.open) dialog.close();
        dialog.remove();
    }, duration);
}

export function closeDialogWithMotion(dialog, { duration = DIALOG_EXIT_MS } = {}) {
    if (!(dialog instanceof HTMLDialogElement) || !dialog.open) return;

    if (!motionEnabled()) {
        dialog.close();

        return;
    }

    if (dialog.classList.contains('is-closing')) return;

    dialog.classList.add('is-closing');

    window.setTimeout(() => {
        dialog.close();
        dialog.classList.remove('is-closing');
    }, duration);
}

let bound = false;

export function initDialogMotion() {
    if (bound) return;
    bound = true;

    const register = () => {
        window.Livewire.hook('morph.removing', ({ el, skip }) => {
            if (!shouldHoldDialog(el)) return;
            if (typeof skip !== 'function') return;

            skip();
            holdAndRemove(el);
        });
    };

    if (window.Livewire) {
        register();
    } else {
        document.addEventListener('livewire:init', register);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `node --test "tests/js/dialog-motion.test.js"`

Expected: 5 tests pass, 0 fail.

- [ ] **Step 5: Run the full JS suite**

Run: `node --test "tests/js/*.test.js"`

Expected: all existing tests plus the new ones pass.

- [ ] **Step 6: Commit**

```bash
git add src/resources/js/ui/dialog-motion.js src/tests/js/dialog-motion.test.js
git commit -m "feat: add dialog exit motion controller"
```

---

### Task 5: Dialog and sheet exit CSS

**Files:**
- Modify: `src/resources/css/hf/_motion.scss`

**Interfaces:**
- Consumes: `.is-closing`, added by Task 4.
- Produces: entry for `.hf-sheet`, exit for `.hf-dialog` and `.hf-sheet`.

- [ ] **Step 1: Add the animations**

Inside the existing `@media (prefers-reduced-motion: no-preference)` block in `src/resources/css/hf/_motion.scss`, after the `.hf-dialog[open]::backdrop` rule, add:

```css
  .hf-sheet[open] {
    animation: hf-sheet-in var(--hf-dur-standard) var(--hf-ease-out) both;
  }

  .hf-sheet[open]::backdrop {
    animation: hf-backdrop-in var(--hf-dur-standard) linear both;
  }

  .hf-dialog.is-closing {
    animation: hf-dialog-out 160ms ease-in both;
  }

  .hf-dialog.is-closing::backdrop {
    animation: hf-backdrop-out 160ms linear both;
  }

  .hf-sheet.is-closing {
    animation: hf-sheet-out 160ms ease-in both;
  }

  .hf-sheet.is-closing::backdrop {
    animation: hf-backdrop-out 160ms linear both;
  }
```

`hf-dialog.is-closing` must appear after `.hf-dialog[open]` so it wins on equal specificity. The `.hf-dialog.hf-logout.is-closing` rules in `_overlays.scss` are more specific and keep the logout choreography.

- [ ] **Step 2: Add the keyframes**

After `@keyframes hf-toast-in`, add:

```css
@keyframes hf-sheet-in {
  from { opacity: 0; transform: translateY(24px); }
  to { opacity: 1; transform: none; }
}

@keyframes hf-sheet-out {
  from { opacity: 1; transform: none; }
  to { opacity: 0; transform: translateY(24px); }
}

@keyframes hf-dialog-out {
  from { opacity: 1; transform: none; }
  to { opacity: 0; transform: translateY(8px) scale(0.985); }
}
```

- [ ] **Step 3: Extend the reduced-motion lists**

In the existing `@media (prefers-reduced-motion: reduce)` block, add `.hf-sheet[open]` and `.hf-sheet[open]::backdrop` to the selector list:

```css
  .hf-main,
  .hf-reveal,
  .hf-dialog[open],
  .hf-dialog[open]::backdrop,
  .hf-sheet[open],
  .hf-sheet[open]::backdrop,
  .hf-toast {
    animation: none !important;
  }
```

In the `[data-motion='reduced']` selector list, add the same two selectors:

```css
[data-motion='reduced'] .hf-main,
[data-motion='reduced'] .hf-reveal,
[data-motion='reduced'] .hf-dialog[open],
[data-motion='reduced'] .hf-dialog[open]::backdrop,
[data-motion='reduced'] .hf-sheet[open],
[data-motion='reduced'] .hf-sheet[open]::backdrop,
[data-motion='reduced'] .hf-toast {
  animation: none !important;
}
```

- [ ] **Step 4: Build and verify**

Run: `npm run build`

Expected: build succeeds.

Run:

```powershell
Get-ChildItem public/build/assets/app-*.css | Select-String -Pattern 'hf-dialog-out|hf-sheet-in' | Select-Object -First 2
```

Expected: both keyframe names appear in the compiled CSS.

- [ ] **Step 5: Commit**

```bash
git add src/resources/css/hf/_motion.scss
git commit -m "feat: add dialog and sheet exit animations"
```

---

### Task 6: Wire dialog motion into Livewire and the More sheet

**Files:**
- Modify: `src/resources/js/ui/page-lifecycle.js`
- Modify: `src/resources/js/ui/shell.js`

**Interfaces:**
- Consumes: `initDialogMotion`, `closeDialogWithMotion` from `dialog-motion.js` (Task 4).
- Produces: server dialogs held for exit; More sheet close button, backdrop, and Escape animate.

- [ ] **Step 1: Register the Livewire hook**

In `src/resources/js/ui/page-lifecycle.js`, add:

```js
import { initDialogMotion } from './dialog-motion';
```

In `initGlobals()`, call it immediately after `initLivewireBridge();`:

```js
    initLivewireBridge();
    initDialogMotion();
```

- [ ] **Step 2: Animate the More sheet close paths**

In `src/resources/js/ui/shell.js`, add the import at the top:

```js
import { closeDialogWithMotion } from './dialog-motion';
```

In `initMoreSheet()`, replace the close-button block:

```js
        if (target.closest('[data-more-sheet-close]')) {
            const sheet = document.querySelector('dialog[data-more-sheet][open]');
            if (sheet instanceof HTMLDialogElement) closeDialogWithMotion(sheet);

            return;
        }
```

Replace the backdrop block:

```js
        const sheet = target.closest('dialog[data-more-sheet]');
        if (sheet && event.target === sheet) closeDialogWithMotion(sheet);
```

Leave the `target.closest('dialog[data-more-sheet] a')` block closing immediately with `sheet.close()`, so link navigation is not delayed.

Add a `cancel` listener inside `initMoreSheet()` next to the existing `close` listener:

```js
    document.addEventListener('cancel', (event) => {
        if (!(event.target instanceof HTMLDialogElement) || !event.target.matches('dialog[data-more-sheet]')) return;

        event.preventDefault();
        closeDialogWithMotion(event.target);
    }, true);
```

- [ ] **Step 3: Run the JS tests**

Run: `node --test "tests/js/*.test.js"`

Expected: all tests pass. The new module has no DOM side effects at import time.

- [ ] **Step 4: Browser verification**

Start the server with `php artisan serve --host=127.0.0.1 --port=8000`.

Verify:

1. Open a server-rendered confirmation dialog (Announcements delete or a similar page), click Cancel. The dialog fades and rises out for about 160ms, then closes.
2. Trigger a destructive confirmation (Delete) and confirm. The request completes, then the dialog fades out. No empty backdrop appears before the response.
3. Force a validation failure in a dialog (submit an invalid value). The dialog stays open and does not animate.
4. Open the mobile More sheet. The sheet slides up. Close it with the close button, backdrop, and Escape. Each animates out, and focus returns to the More tab.
5. Press Escape while a dialog is open. It animates out instead of vanishing.

Stop the server when done.

- [ ] **Step 5: Commit**

```bash
git add src/resources/js/ui/page-lifecycle.js src/resources/js/ui/shell.js
git commit -m "feat: apply dialog exit motion to livewire and the more sheet"
```

---

### Task 7: Account header and dropdown visual treatment

**Files:**
- Modify: `src/resources/views/components/user-menu.blade.php`
- Modify: `src/resources/css/hf/_shell.scss`
- Test: `src/tests/Feature/Accessibility/SpaNavigationTest.php`

**Interfaces:**
- Consumes: `$initials`, `$user`, `$role` already computed in the component.
- Produces: `.hf-user-menu__head`, `.hf-user-menu__identity`, `.hf-user-menu__eyebrow`, `.hf-user-menu__email`, `.hf-user-menu__body`, `.hf-avatar--lg`.

- [ ] **Step 1: Write the failing Pest test**

Append to `src/tests/Feature/Accessibility/SpaNavigationTest.php`:

```php
it('shows the signed-in account in the user menu header', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $response = $this->actingAs($organizer)->get(route('dashboard'))->assertOk();
    $xpath = spaXPath((string) $response->getContent());
    $head = $xpath->query("//div[contains(@class, 'hf-user-menu__head')]")->item(0);

    expect($head)->not->toBeNull();
    expect($head->textContent)->toContain('Signed in as');
    expect($head->textContent)->toContain($organizer->name);
    expect($head->textContent)->toContain($organizer->email);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter="signed-in account"`

Expected: FAIL because no header exists. The filter matches the new test description, so only that test runs.

- [ ] **Step 3: Add the header and body wrapper**

In `src/resources/views/components/user-menu.blade.php`, replace the panel contents with:

```blade
    <div class="hf-user-menu__panel">
        <div class="hf-user-menu__head">
            <span class="hf-avatar hf-avatar--lg">{{ $initials }}</span>
            <span class="hf-user-menu__identity">
                <span class="hf-user-menu__eyebrow">Signed in as</span>
                <strong>{{ $user?->name }}</strong>
                <span class="hf-user-menu__email">{{ $user?->email }}</span>
            </span>
        </div>
        <div class="hf-user-menu__body">
            <a href="{{ route('profile') }}" wire:navigate data-testid="topbar-profile"><i class="bi bi-person" aria-hidden="true"></i> Profile</a>
            <a href="{{ route('profile') }}#appearance" wire:navigate><i class="bi bi-palette" aria-hidden="true"></i> Appearance</a>
            <a href="{{ route('profile') }}#shortcuts" wire:navigate><i class="bi bi-keyboard" aria-hidden="true"></i> Keyboard shortcuts</a>
            <form method="POST" action="{{ route('logout') }}" data-hf-logout-form>
                @csrf
                <button type="submit" data-testid="logout"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log out</button>
            </form>
        </div>
    </div>
```

Do not change the More sheet block in `layouts/public.blade.php`; it keeps its existing markup and gains the shared panel styling through CSS.

- [ ] **Step 4: Add the header, body, and item styles**

In `src/resources/css/hf/_shell.scss`, after the `.hf-avatar` block, add:

```css
.hf-avatar--lg {
  flex: none;
  width: 40px;
  height: 40px;
  font-size: 15px;
}
```

After the `.hf-user-menu[open] summary` rule, add:

```css
.hf-user-menu__head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 8px 10px;
  margin-bottom: 4px;
  border-bottom: 1px solid var(--hf-border);
}

.hf-user-menu__identity {
  display: grid;
  min-width: 0;
  line-height: 1.25;
}

.hf-user-menu__eyebrow {
  color: var(--hf-muted);
  font-family: var(--hf-font-display);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.hf-user-menu__identity strong,
.hf-user-menu__email {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hf-user-menu__identity strong {
  font-size: 12px;
}

.hf-user-menu__email {
  color: var(--hf-muted);
  font-size: 10px;
}

.hf-user-menu__body {
  display: grid;
}
```

In the existing `.hf-user-menu__panel a, .hf-user-menu__panel button` rule, add:

```css
  transition: background var(--hf-dur-micro) linear, color var(--hf-dur-micro) linear;
```

After that rule, add the icon tiles and danger treatment:

```css
.hf-user-menu__panel .bi {
  display: grid;
  flex: none;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--hf-muted) 10%, transparent);
  color: var(--hf-muted);
  font-size: 13px;
  transition:
    background var(--hf-dur-micro) linear,
    color var(--hf-dur-micro) linear,
    transform var(--hf-dur-micro) var(--hf-ease-out);
}

.hf-user-menu__panel a:hover .bi,
.hf-user-menu__panel a:focus-visible .bi,
.hf-user-menu__panel button:hover .bi,
.hf-user-menu__panel button:focus-visible .bi {
  background: color-mix(in srgb, var(--hf-primary) 16%, transparent);
  color: var(--hf-primary-ink);
  transform: translateX(1px);
}

.hf-user-menu__panel form {
  margin-top: 4px;
  padding-top: 4px;
  border-top: 1px solid var(--hf-border);
}

.hf-user-menu__panel form button:hover,
.hf-user-menu__panel form button:focus-visible {
  color: var(--hf-danger-ink);
}

.hf-user-menu__panel form button:hover .bi,
.hf-user-menu__panel form button:focus-visible .bi {
  background: color-mix(in srgb, var(--hf-danger-ink) 14%, transparent);
  color: var(--hf-danger-ink);
}
```

The existing `.hf-sheet__account .hf-user-menu__panel` rule keeps the panel static inside the sheet. Task 8 adds a higher-specificity `animation: none` override in `_motion.scss` because the general dropdown rule is more specific than this rule.

- [ ] **Step 5: Run the Pest test to verify it passes**

Run: `php artisan test tests/Feature/Accessibility/SpaNavigationTest.php --filter="signed-in account"`

Expected: PASS.

- [ ] **Step 6: Build and visually verify the expanded rail menu**

Run: `npm run build`

Start the server and open `/dashboard`. Open the account menu in the rail footer. Confirm:

- the header shows the avatar, "Signed in as", name, and email;
- long names and emails truncate instead of widening the panel;
- item icons sit in rounded tiles and tint orange on hover and keyboard focus;
- Log out is separated and turns danger-colored;
- Profile, Appearance, Keyboard shortcuts, and Log out still work, and `data-testid` attributes are unchanged.

Stop the server when done.

- [ ] **Step 7: Commit**

```bash
git add src/resources/views/components/user-menu.blade.php src/resources/css/hf/_shell.scss src/tests/Feature/Accessibility/SpaNavigationTest.php
git commit -m "feat: add account header and dropdown item treatment"
```

---

### Task 8: Dropdown open/close motion and collapsed-rail flyout

**Files:**
- Create: `src/resources/js/ui/menus.js`
- Modify: `src/resources/js/ui/shell.js`
- Modify: `src/resources/css/hf/_motion.scss`
- Modify: `src/resources/css/hf/_shell.scss`
- Test: `src/tests/js/menus.test.js`

**Interfaces:**
- Consumes: `motionEnabled` from `motion.js` (Task 1).
- Produces:
  - `MENU_EXIT_MS = 120`
  - `menuCloseDelay(options = {}) => number`
  - `closeUserMenu(menu, options = {})`
  - `initUserMenus()`

- [ ] **Step 1: Write the failing tests**

Create `src/tests/js/menus.test.js`:

```js
import assert from 'node:assert/strict';
import test from 'node:test';
import { menuCloseDelay } from '../../resources/js/ui/menus.js';

test('the menu exit is skipped when motion is reduced', () => {
    assert.equal(menuCloseDelay({ reduce: true, preference: 'system' }), 0);
    assert.equal(menuCloseDelay({ reduce: false, preference: 'reduced' }), 0);
});

test('the menu uses the micro exit duration otherwise', () => {
    assert.equal(menuCloseDelay({ reduce: false, preference: 'system' }), 120);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `node --test "tests/js/menus.test.js"`

Expected: FAIL because `menus.js` does not exist.

- [ ] **Step 3: Implement the controller**

Create `src/resources/js/ui/menus.js`:

```js
import { motionEnabled } from './motion';

export const MENU_EXIT_MS = 120;

export function menuCloseDelay(options = {}) {
    return motionEnabled(options) ? MENU_EXIT_MS : 0;
}

export function closeUserMenu(menu, options = {}) {
    if (!(menu instanceof HTMLElement) || !menu.open) return;

    const delay = menuCloseDelay(options);

    if (!delay || menu.classList.contains('is-closing')) {
        menu.open = false;
        menu.classList.remove('is-closing');

        return;
    }

    menu.classList.add('is-closing');

    window.setTimeout(() => {
        menu.open = false;
        menu.classList.remove('is-closing');
    }, delay);
}

function closeOpenUserMenus() {
    document.querySelectorAll('details.hf-user-menu[open]').forEach((menu) => closeUserMenu(menu));
}

// The collapsed rail clips horizontal overflow, so the panel leaves the rail
// and anchors to the viewport beside the account chip.
function positionFloatingMenu(menu) {
    const panel = menu.querySelector('.hf-user-menu__panel');
    const summary = menu.querySelector('summary');
    const rail = menu.closest('.hf-rail');
    if (!panel || !summary || !rail) return;
    if (document.documentElement.dataset.nav !== 'collapsed' || window.innerWidth < 768) return;

    const chip = summary.getBoundingClientRect();

    panel.style.setProperty('--hf-menu-left', `${rail.getBoundingClientRect().right + 8}px`);
    panel.style.setProperty('--hf-menu-bottom', `${window.innerHeight - chip.top + 8}px`);
}

// Scroll closes only the floating collapsed-rail panel, not the always-open
// account block inside the More sheet.
function closeFloatingUserMenus() {
    if (document.documentElement.dataset.nav !== 'collapsed' || window.innerWidth < 768) return;

    closeOpenUserMenus();
}

let bound = false;

export function initUserMenus() {
    if (bound) return;
    bound = true;

    document.addEventListener('click', (event) => {
        const summary = event.target instanceof Element
            ? event.target.closest('details.hf-user-menu > summary')
            : null;
        if (!summary) return;

        const menu = summary.parentElement;
        if (!(menu instanceof HTMLDetailsElement)) return;

        if (!menu.open) {
            // Position before the browser renders the open panel so the
            // collapsed rail never paints it at the fallback coordinates.
            positionFloatingMenu(menu);

            return;
        }

        event.preventDefault();
        closeUserMenu(menu);
    }, true);

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        const action = target.closest('.hf-user-menu__panel a, .hf-user-menu__panel button');
        if (!action) return;

        action.closest('details.hf-user-menu')?.removeAttribute('open');
    });

    document.addEventListener('toggle', (event) => {
        const menu = event.target;
        if (!(menu instanceof HTMLDetailsElement) || !menu.classList.contains('hf-user-menu')) return;

        if (!menu.open) {
            menu.classList.remove('is-closing');

            return;
        }

        positionFloatingMenu(menu);
    }, true);

    window.addEventListener('resize', closeOpenUserMenus);
    window.addEventListener('scroll', closeFloatingUserMenus, true);
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `node --test "tests/js/menus.test.js"`

Expected: 2 tests pass, 0 fail.

- [ ] **Step 5: Connect the menu controller to the shell**

In `src/resources/js/ui/shell.js`, add the import:

```js
import { closeUserMenu, initUserMenus } from './menus';
```

Replace the body of `initShell()` with:

```js
export function initShell() {
    initUserMenus();

    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.hf-user-menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) closeUserMenu(menu);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('details.hf-user-menu[open]').forEach((menu) => {
            closeUserMenu(menu);
            menu.querySelector('summary')?.focus();
        });
    });
}
```

- [ ] **Step 6: Add the menu animations**

Inside the `@media (prefers-reduced-motion: no-preference)` block in `src/resources/css/hf/_motion.scss`, after the toast rule, add:

```css
  .hf-user-menu[open] .hf-user-menu__panel {
    transform-origin: bottom right;
    animation: hf-menu-in var(--hf-dur-standard) var(--hf-ease-out) both;
  }

  .hf-user-menu.is-closing .hf-user-menu__panel {
    animation: hf-menu-out var(--hf-dur-micro) ease-in both;
    pointer-events: none;
  }

  .hf-user-menu__body > * {
    animation: hf-menu-item-in var(--hf-dur-standard) var(--hf-ease-out) both;
  }

  .hf-user-menu__body > *:nth-child(2) { animation-delay: 24ms; }
  .hf-user-menu__body > *:nth-child(3) { animation-delay: 48ms; }
  .hf-user-menu__body > *:nth-child(4) { animation-delay: 72ms; }
```

After `@keyframes hf-toast-in`, add:

```css
@keyframes hf-menu-in {
  from { opacity: 0; transform: translateY(6px) scale(0.98); }
  to { opacity: 1; transform: none; }
}

@keyframes hf-menu-out {
  from { opacity: 1; transform: none; }
  to { opacity: 0; transform: translateY(4px) scale(0.99); }
}

@keyframes hf-menu-item-in {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: none; }
}
```

The More sheet's account panel is always open, so it must not replay the
dropdown entrance when the sheet is shown. The general `[open]` rule is more
specific than `.hf-sheet__account .hf-user-menu__panel` in `_shell.scss`, so add
the override immediately after the general menu rules, still inside the same
media block:

```css
  .hf-sheet__account.hf-user-menu[open] .hf-user-menu__panel {
    animation: none;
  }
```

The More sheet panel has no `.hf-user-menu__body`, so the item stagger does not
apply there.

Add these selectors to both animation-disable lists:

```css
  .hf-user-menu[open] .hf-user-menu__panel,
  .hf-user-menu.is-closing .hf-user-menu__panel,
  .hf-user-menu__body > *,
```

In the `[data-motion='reduced']` list, prefix each selector with
`[data-motion='reduced'] ` to match the existing entries.

- [ ] **Step 7: Fix the collapsed-rail flyout**

The rail clips horizontal overflow, so a 220px panel anchored to the 52px chip
in the 76px collapsed rail falls outside the viewport. The panel must escape the
rail's clip and anchor to the viewport.

`positionFloatingMenu()` in Step 3 already publishes the anchor position as
`--hf-menu-left` and `--hf-menu-bottom` using the summary's bounding rect and
the rail's right edge. Add to `src/resources/css/hf/_shell.scss`, inside the
existing `@media (min-width: 768px)` block:

```css
  [data-nav='collapsed'] .hf-rail-footer .hf-user-menu__panel {
    position: fixed;
    top: auto;
    right: auto;
    bottom: var(--hf-menu-bottom, 74px);
    left: var(--hf-menu-left, 84px);
  }
```

The fallback values cover the brief moment before the `toggle` handler runs.
Fixed positioning escapes the rail's overflow clip; the panel starts 8px to the
right of the rail and 8px above the chip. The resize listener closes the menu,
and the scroll listener closes it on any scroll while the rail is collapsed.

- [ ] **Step 8: Run the full JS suite and build**

Run: `node --test "tests/js/*.test.js"`

Expected: all tests pass.

Run: `npm run build`

Expected: build succeeds.

- [ ] **Step 9: Browser verification**

Start the server with `php artisan serve --host=127.0.0.1 --port=8000`.

Verify:

1. Expand the rail and open the account menu: it fades and rises into place; the four items stagger.
2. Close with the summary, outside click, and Escape: each closes over about 120ms. Escape returns focus to the summary.
3. Click a menu link: the menu closes immediately and navigation is not delayed.
4. Open the mobile More sheet and its account block: the panel has no entrance animation of its own; item styling still applies.
5. At a desktop width (at least 768px), collapse the rail with the nav toggle and open the account menu. The panel is fully visible beside the rail, 8px above the chip, not clipped. Scroll the page and resize the window; the menu closes.
6. Set `data-motion="reduced"`; the menu closes instantly, with no stagger or panel animation.

Stop the server when done.

- [ ] **Step 10: Commit**

```bash
git add src/resources/js/ui/menus.js src/resources/js/ui/shell.js src/tests/js/menus.test.js src/resources/css/hf/_motion.scss src/resources/css/hf/_shell.scss
git commit -m "feat: animate the account dropdown and fix its collapsed rail placement"
```

---

### Task 9: Full verification and quality gate

**Files:**
- No new files. Fix any regressions found.

- [ ] **Step 1: Run the JS suite**

Run: `node --test "tests/js/*.test.js"`

Expected: all tests pass. Record the pass/fail counts.

- [ ] **Step 2: Build the frontend**

Run: `npm run build`

Expected: build succeeds. Record the emitted `app-*.js` and `app-*.css` names.

- [ ] **Step 3: Run the PHP suite**

Run: `php artisan test`

Expected: the full suite passes. The previous baseline was 613 passed with 3,138 assertions.

- [ ] **Step 4: Run the quality gate**

Run: `composer quality`

Expected: Pint passes, PHPStan reports `[OK] No errors`, and Pest passes. If Composer's 300s cap interrupts the test phase, record that and rely on the direct `php artisan test` result from Step 3.

- [ ] **Step 5: Final browser pass**

Start the server and verify all of the following in one session:

1. `/dashboard -> /teams -> /dashboard`: page content fades/lifts out and fades/rises in; rail width has no intermediate values; no wrong-theme frames; no `hf-page-in` or `hf-reveal-in` replay.
2. A dialog Cancel and a destructive confirm both exit over about 160ms after the response; a validation failure keeps the dialog open.
3. The More sheet opens and closes with motion, and focus returns to the More tab.
4. The account menu header, hover/focus tiles, stagger, Escape focus return, and logout form all work.
5. Reduced motion turns every effect instantaneous.

Stop the server and confirm port 8000 is free.

- [ ] **Step 6: Check the working tree**

Run: `git status --short`

Expected: only `.obsidian/graph.json` and `.kilo/` are untracked or modified. Do not stage them.

- [ ] **Step 7: Commit any fixes**

If Step 5 found a regression, commit only the affected source files:

```bash
git add <changed source files>
git commit -m "fix: polish motion edge cases"
```

- [ ] **Step 8: Report**

Report the commits, JS pass/fail counts, build asset names, PHP assertion count, `composer quality` output, browser measurements (rail widths, animation events, dialog timing, menu timing), and the fact that `.obsidian/` and `.kilo/` remain unstaged.
