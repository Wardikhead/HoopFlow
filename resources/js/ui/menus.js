import { motionEnabled } from './motion.js';

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
