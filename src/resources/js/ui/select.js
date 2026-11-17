import { motionEnabled } from './motion.js';

// The native <select> stays in the DOM as the source of truth: it keeps
// wire:model, form posts, and constraint validation working. This module only
// paints a designed trigger and listbox over it.
export const MENU_CLOSE_MS = 100;

// Filter bars opt in by structure; everything else opts in with the attribute.
const SELECTOR = 'select[data-hf-select], .hf-filter-bar select.hf-select';

export function readRows(select) {
    return Array.from(select.options).map((option, index) => ({
        index,
        value: option.value,
        label: option.textContent.trim(),
        disabled: option.disabled,
    }));
}

export function nextEnabledIndex(rows, current, delta) {
    if (!rows.length) return -1;

    let index = current;

    for (let step = 0; step < rows.length; step += 1) {
        const next = index === -1
            ? (delta > 0 ? 0 : rows.length - 1)
            : (index + delta + rows.length) % rows.length;

        if (!rows[next].disabled) return next;

        index = next;
    }

    return current;
}

export function typeaheadIndex(rows, query, current) {
    if (!query) return -1;

    const needle = query.toLowerCase();

    for (let offset = 1; offset <= rows.length; offset += 1) {
        const index = (Math.max(current, 0) + offset) % rows.length;
        const row = rows[index];

        if (!row.disabled && row.label.toLowerCase().startsWith(needle)) return index;
    }

    return -1;
}

// Fixed positioning keeps the panel out of scroll containers and dialog clip
// paths; flip above the trigger when the viewport runs out below.
export function menuPlacement(rect, menuHeight, viewportHeight, margin = 8) {
    const below = viewportHeight - rect.bottom - margin;
    const above = rect.top - margin;
    const openUp = menuHeight > below && above > below;

    return {
        openUp,
        maxHeight: Math.max(160, Math.min(menuHeight, openUp ? above : below)),
    };
}

// A fixed panel is positioned against its containing block, which is an
// animated dialog (transform) or the viewport. Resolve that box so the panel
// uses the dialog's own coordinates instead of clipping at its edge.
function containingBox(element) {
    const parent = element.offsetParent;

    if (!(parent instanceof Element) || parent === document.body || parent === document.documentElement) {
        return { left: 0, top: 0, width: window.innerWidth, height: window.innerHeight };
    }

    const rect = parent.getBoundingClientRect();

    return {
        left: rect.left + parent.clientLeft,
        top: rect.top + parent.clientTop,
        width: parent.clientWidth,
        height: parent.clientHeight,
    };
}

// The mobile tab bar floats over the page; cap the panel at its top edge in
// the same coordinates the menu is positioned in.
function usableHeight(box) {
    const nav = document.querySelector('.hf-bottom-nav');

    if (!nav) return box.height;

    const navRect = nav.getBoundingClientRect();

    if (navRect.height <= 0) return box.height;

    const localNavTop = navRect.top - box.top;

    return localNavTop > 0 && localNavTop < box.height ? localNavTop : box.height;
}

function labelledBy(select) {
    if (!select.id) return null;

    const label = document.querySelector(`label[for="${CSS.escape(select.id)}"]`);

    if (!label) return null;

    if (!label.id) label.id = `${select.id}-label`;

    return label.id;
}

function buildMenu(menu, select, choose) {
    menu.replaceChildren();

    readRows(select).forEach((row) => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'hf-combo__option';
        option.setAttribute('role', 'option');
        option.dataset.value = row.value;
        option.disabled = row.disabled;
        option.setAttribute('aria-selected', row.value === select.value ? 'true' : 'false');
        if (row.value === select.value) option.classList.add('is-selected');

        const label = document.createElement('span');
        label.textContent = row.label;
        option.appendChild(label);

        const check = document.createElement('i');
        check.className = 'bi bi-check2 hf-combo__check';
        check.setAttribute('aria-hidden', 'true');
        option.appendChild(check);

        option.addEventListener('click', () => choose(row.value));
        menu.appendChild(option);
    });
}

function moveFocus(menu, delta) {
    const options = Array.from(menu.querySelectorAll('[role="option"]:not(:disabled)'));
    if (!options.length) return;

    const active = options.indexOf(document.activeElement);
    const next = active === -1
        ? (delta > 0 ? 0 : options.length - 1)
        : (active + delta + options.length) % options.length;

    options[next].focus();
}

function upgrade(select) {
    if (select.closest('.hf-combo')) return;

    const compact = select.classList.contains('hf-select--compact');
    const wrapper = document.createElement('div');
    wrapper.className = compact ? 'hf-combo hf-combo--compact' : 'hf-combo';

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.classList.add('hf-combo__native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'hf-combo__trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const labelId = labelledBy(select);
    const accessibleName = select.getAttribute('aria-label') || select.name || 'Choose an option';

    if (labelId) trigger.setAttribute('aria-labelledby', labelId);
    else trigger.setAttribute('aria-label', accessibleName);

    const value = document.createElement('span');
    value.className = 'hf-combo__value';

    const caret = document.createElement('i');
    caret.className = 'bi bi-chevron-down hf-combo__caret';
    caret.setAttribute('aria-hidden', 'true');

    trigger.append(value, caret);

    const menu = document.createElement('div');
    menu.className = 'hf-combo__menu';
    menu.id = `hf-combo-${select.id || Math.random().toString(36).slice(2)}`;
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;
    if (labelId) menu.setAttribute('aria-labelledby', labelId);
    else menu.setAttribute('aria-label', accessibleName);
    trigger.setAttribute('aria-controls', menu.id);

    wrapper.append(trigger, menu);

    let closeTimer = 0;

    const sync = () => {
        const selected = select.options[select.selectedIndex];
        value.textContent = selected ? selected.textContent.trim() : '';
        trigger.disabled = select.disabled;

        menu.querySelectorAll('[role="option"]').forEach((option) => {
            const active = option.dataset.value === select.value;
            option.setAttribute('aria-selected', active ? 'true' : 'false');
            option.classList.toggle('is-selected', active);
        });
    };

    const position = () => {
        const box = containingBox(menu);
        const rect = trigger.getBoundingClientRect();
        const local = {
            top: rect.top - box.top,
            bottom: rect.bottom - box.top,
        };
        const placement = menuPlacement(local, menu.scrollHeight || 320, usableHeight(box));
        const width = Math.min(rect.width, Math.max(160, box.width - 16));
        const maxLeft = Math.max(8, box.width - width - 8);

        menu.style.left = `${Math.min(Math.max(8, rect.left - box.left), maxLeft)}px`;
        menu.style.width = `${width}px`;
        menu.style.maxHeight = `${placement.maxHeight}px`;
        menu.style.top = placement.openUp
            ? `${Math.max(8, local.top - placement.maxHeight - 6)}px`
            : `${local.bottom + 6}px`;
    };

    // Dialogs live in the top layer and clip their children, so the panel
    // must sit beside the select inside that layer while it is open.
    const mount = () => {
        const host = select.closest('dialog[open]');

        if (host) host.appendChild(menu);
        else if (menu.parentNode !== wrapper) wrapper.appendChild(menu);
    };

    const focusTrigger = (focus = true) => {
        if (focus) trigger.focus();
    };

    const close = (focus = true) => {
        if (menu.hidden) return;

        menu.classList.remove('is-open');
        window.clearTimeout(closeTimer);

        const finish = () => {
            menu.hidden = true;
            menu.classList.remove('is-closing');
            trigger.setAttribute('aria-expanded', 'false');
            if (menu.parentNode !== wrapper) wrapper.appendChild(menu);
            focusTrigger(focus);
        };

        if (!motionEnabled()) {
            finish();

            return;
        }

        menu.classList.add('is-closing');
        closeTimer = window.setTimeout(finish, MENU_CLOSE_MS);
    };

    const choose = (next) => {
        if (select.value !== next) {
            select.value = next;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        sync();
        close();
    };

    const open = () => {
        if (select.disabled) return;

        window.clearTimeout(closeTimer);
        mount();
        buildMenu(menu, select, choose);
        menu.hidden = false;
        menu.classList.remove('is-closing');
        position();
        menu.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');

        const target = menu.querySelector('.is-selected:not(:disabled)')
            ?? menu.querySelector('[role="option"]:not(:disabled)');
        target?.focus();
    };

    trigger.addEventListener('click', () => {
        if (menu.hidden) open();
        else close();
    });

    trigger.addEventListener('keydown', (event) => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            open();
        }
    });

    menu.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') { event.preventDefault(); moveFocus(menu, 1); }
        if (event.key === 'ArrowUp') { event.preventDefault(); moveFocus(menu, -1); }
        if (event.key === 'Home') {
            event.preventDefault();
            menu.querySelector('[role="option"]:not(:disabled)')?.focus();
        }
        if (event.key === 'End') {
            event.preventDefault();
            Array.from(menu.querySelectorAll('[role="option"]:not(:disabled)')).pop()?.focus();
        }
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'Tab') close(false);
    });

    menu.addEventListener('keydown', (event) => {
        if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) return;

        const query = (menu.dataset.typeahead ?? '') + event.key;
        menu.dataset.typeahead = query;
        window.clearTimeout(Number(menu.dataset.typeaheadTimer));
        menu.dataset.typeaheadTimer = String(window.setTimeout(() => {
            menu.dataset.typeahead = '';
        }, 500));

        const rows = readRows(select);
        const current = rows.findIndex((row) => row.label === document.activeElement?.textContent?.trim());
        const index = typeaheadIndex(rows, query, current);

        if (index === -1) return;

        event.preventDefault();
        menu.querySelectorAll('[role="option"]')[index]?.focus();
    });

    document.addEventListener('pointerdown', (event) => {
        if (!wrapper.contains(event.target) && !menu.contains(event.target)) close(false);
    });

    // A fixed panel must follow its trigger; dismiss only once the trigger
    // has actually left the viewport or the dialog that clips the panel.
    window.addEventListener('scroll', () => {
        if (menu.hidden) return;

        const rect = trigger.getBoundingClientRect();
        const box = containingBox(menu);

        if (
            rect.bottom < 0
            || rect.top > window.innerHeight
            || rect.bottom < box.top
            || rect.top > box.top + box.height
        ) {
            close(false);

            return;
        }

        position();
    }, true);
    window.addEventListener('resize', () => {
        if (!menu.hidden) position();
    });

    // A dialog can close with the panel open (Save, Cancel, or Escape).
    select.closest('dialog')?.addEventListener('close', () => close(false));

    // Livewire re-renders the select after every filter change; keep the
    // painted value and rows in step with whatever lands in the DOM.
    new MutationObserver(sync).observe(select, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['disabled'],
    });
    select.addEventListener('change', sync);
    select.addEventListener('input', sync);

    // Livewire sets the select's value property without mutating attributes,
    // so a morph hook is the only reliable signal.
    wrapper.addEventListener('hf:select:sync', () => {
        sync();
        if (!menu.hidden) position();
    });

    buildMenu(menu, select, choose);
    sync();
}

let hooked = false;

function hookLivewire() {
    if (hooked) return;

    if (!window.Livewire?.hook) {
        document.addEventListener('livewire:init', hookLivewire, { once: true });

        return;
    }

    hooked = true;

    window.Livewire.hook('morph.updated', ({ el }) => {
        if (!(el instanceof Element)) return;

        el.querySelectorAll(SELECTOR).forEach(upgrade);
        if (el.matches(SELECTOR)) upgrade(el);

        // The hook fires while the subtree is still settling; repaint after
        // the browser has applied the morph.
        requestAnimationFrame(() => {
            const wrappers = new Set(el.querySelectorAll('.hf-combo'));
            const own = el.closest?.('.hf-combo');

            if (own) wrappers.add(own);

            wrappers.forEach((wrapper) => {
                wrapper.dispatchEvent(new Event('hf:select:sync'));
            });
        });
    });
}

let watching = false;

export function initRichSelects(root = document) {
    root.querySelectorAll(SELECTOR).forEach(upgrade);
    hookLivewire();

    if (watching || root !== document) return;
    watching = true;

    // Morphs replace the wrapper with the server's plain select, so watch for
    // insertions instead of relying on a navigation hook.
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;

                if (node.matches(SELECTOR)) upgrade(node);
                else node.querySelectorAll(SELECTOR).forEach(upgrade);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
}
