// Front-end script for Shell.

import { closeDialogWithMotion } from './dialog-motion.js';
import { closeUserMenu, initUserMenus } from './menus.js';

/**
 * Init shell.
 * @returns {*} The result.
 */
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

let moreSheetBound = false;
let sheetTrigger = null;

/**
 * Init more sheet.
 * @returns {*} The result.
 */
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
            const sheet = document.querySelector('dialog[data-more-sheet][open]');
            if (sheet instanceof HTMLDialogElement) closeDialogWithMotion(sheet);

            return;
        }

        if (target.closest('dialog[data-more-sheet] a')) {
            document.querySelector('dialog[data-more-sheet][open]')?.close();

            return;
        }

        const sheet = target.closest('dialog[data-more-sheet]');
        if (sheet && event.target === sheet) closeDialogWithMotion(sheet);
    });

    document.addEventListener('cancel', (event) => {
        if (!(event.target instanceof HTMLDialogElement) || !event.target.matches('dialog[data-more-sheet]')) return;

        event.preventDefault();
        closeDialogWithMotion(event.target);
    }, true);

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

let bottomNavBound = false;

let navBound = false;
let tooltip = null;
let tooltipTarget = null;

/**
 * Ensure tooltip.
 * @returns {*} The result.
 */
function ensureTooltip() {
    if (tooltip?.isConnected) return tooltip;

    tooltip = document.createElement('div');
    tooltip.className = 'hf-rail-tooltip';
    tooltip.setAttribute('aria-hidden', 'true');
    document.body.appendChild(tooltip);

    return tooltip;
}

/**
 * Hide tooltip.
 * @returns {*} The result.
 */
function hideTooltip() {
    tooltipTarget = null;
    tooltip?.classList.remove('is-visible');
}

/**
 * Position tooltip.
 *
 * @param {*} link The link.
 * @returns {*} The result.
 */
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

/**
 * Show tooltip.
 *
 * @param {*} link The link.
 * @returns {*} The result.
 */
function showTooltip(link) {
    if (document.documentElement.dataset.nav !== 'collapsed' || window.innerWidth < 768) return;

    const label = link.dataset.label;
    if (!label) return;

    tooltipTarget = link;
    ensureTooltip().textContent = label;
    tooltip.classList.add('is-visible');
    positionTooltip(link);
}

/**
 * Stored nav.
 * @returns {*} The result.
 */
function storedNav() {
    try {
        const value = localStorage.getItem(NAV_KEY);

        return value === 'expanded' || value === 'collapsed' ? value : null;
    } catch {
        return null;
    }
}

/**
 * Render nav.
 *
 * @param {*} state The state.
 * @returns {*} The result.
 */
function renderNav(state) {
    document.documentElement.dataset.nav = state;

    const toggle = document.querySelector('[data-nav-toggle]');
    if (toggle) {
        toggle.setAttribute('aria-expanded', state === 'expanded' ? 'true' : 'false');
        toggle.setAttribute('aria-label', state === 'expanded' ? 'Collapse navigation' : 'Expand navigation');
    }

    hideTooltip();
}

let railGlowTarget = null;

/**
 * Ensure rail glow.
 *
 * @param {*} rail The rail.
 * @returns {*} The glow element.
 */
function ensureRailGlow(rail) {
    if (!rail) return null;

    let glow = rail.querySelector('.hf-rail-glow');

    if (!glow) {
        glow = document.createElement('span');
        glow.className = 'hf-rail-glow';
        glow.setAttribute('aria-hidden', 'true');
        rail.prepend(glow);
    }

    return glow;
}

/**
 * Move rail glow.
 *
 * @param {*} link The link.
 * @returns {*} The result.
 */
function moveRailGlow(link) {
    const rail = link?.closest('.hf-rail');
    if (!rail) return;

    const created = !rail.querySelector('.hf-rail-glow');
    const glow = ensureRailGlow(rail);
    if (!glow) return;

    const railRect = rail.getBoundingClientRect();
    const linkRect = link.getBoundingClientRect();

    if (!linkRect.width) {
        glow.classList.remove('is-visible');

        return;
    }

    // The first placement lands in one paint; only later moves glide.
    if (created) glow.classList.add('is-instant');

    glow.style.setProperty('--hf-glow-x', `${linkRect.left - railRect.left - rail.clientLeft}px`);
    glow.style.setProperty('--hf-glow-y', `${linkRect.top - railRect.top - rail.clientTop + rail.scrollTop}px`);
    glow.style.setProperty('--hf-glow-w', `${linkRect.width}px`);
    glow.style.setProperty('--hf-glow-h', `${linkRect.height}px`);

    rail.classList.add('has-glow');
    glow.classList.add('is-visible');

    if (created) {
        window.requestAnimationFrame(() => glow.classList.remove('is-instant'));
    }
}

/**
 * Sync rail glow.
 * @returns {*} The result.
 */
export function syncRailGlow() {
    const rail = document.querySelector('.hf-rail');
    if (!rail) return;

    const target = railGlowTarget?.isConnected
        ? railGlowTarget
        : rail.querySelector('.hf-rail-link.is-active');

    if (!target) {
        rail.querySelector('.hf-rail-glow')?.classList.remove('is-visible');

        return;
    }

    moveRailGlow(target);
}

/**
 * Resolve nav state.
 *
 * @param {*} { stored  The { stored.
 * @param {*} current  The current.
 * @param {*} mediaMatches  The media matches.
 * @returns {*} The result.
 */
export function resolveNavState({ stored = null, current = null, mediaMatches = false } = {}) {
    if (stored === 'expanded' || stored === 'collapsed') return stored;
    if (current === 'expanded' || current === 'collapsed') return current;

    return mediaMatches ? 'expanded' : 'collapsed';
}

/**
 * Sync nav rail.
 * @returns {*} The result.
 */
export function syncNavRail() {
    renderNav(resolveNavState({
        stored: storedNav(),
        current: document.documentElement.dataset.nav ?? null,
        mediaMatches: window.matchMedia('(min-width: 1200px)').matches,
    }));

    syncRailGlow();
}

/**
 * Init nav rail.
 * @returns {*} The result.
 */
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
        if (!link) return;
        if (link !== tooltipTarget) showTooltip(link);

        if (link === railGlowTarget) return;

        railGlowTarget = link;
        moveRailGlow(link);
    });

    document.addEventListener('pointerout', (event) => {
        const link = event.target instanceof Element ? event.target.closest('.hf-rail-link') : null;
        if (!link) return;
        if (!link.contains(event.relatedTarget)) hideTooltip();

        // Moving between rows should glide straight across, never via the active row.
        const next = event.relatedTarget instanceof Element
            ? event.relatedTarget.closest('.hf-rail-link')
            : null;
        if (next) return;

        railGlowTarget = null;
        syncRailGlow();
    });

    document.addEventListener('focusin', (event) => {
        const link = event.target instanceof Element ? event.target.closest('.hf-rail-link') : null;
        if (!link) return;

        showTooltip(link);
        railGlowTarget = link;
        moveRailGlow(link);
    });

    document.addEventListener('focusout', (event) => {
        if (!(event.target instanceof Element) || !event.target.closest('.hf-rail-link')) return;

        hideTooltip();

        const next = event.relatedTarget instanceof Element
            ? event.relatedTarget.closest('.hf-rail-link')
            : null;
        if (next) return;

        railGlowTarget = null;
        syncRailGlow();
    });

    window.addEventListener('scroll', hideTooltip, true);
    window.addEventListener('resize', hideTooltip);
    window.addEventListener('resize', syncRailGlow);

    // The rail scrolls on its own when the viewport is short.
    document.addEventListener('scroll', (event) => {
        if (event.target instanceof Element && event.target.classList.contains('hf-rail')) {
            syncRailGlow();
        }
    }, true);

    // Collapsing and expanding animates the width, so re-measure at the end.
    document.addEventListener('transitionend', (event) => {
        if (event.target instanceof Element
            && event.target.classList.contains('hf-rail')
            && event.propertyName === 'width') {
            syncRailGlow();
        }
    });
}

// The mobile dock paints a single pill behind the active tab. Measuring the tab
// instead of hard-coding a fraction of the bar keeps the pill aligned while the
// safe-area inset, font scale, or viewport width changes.
/**
 * Sync bottom nav indicator.
 * @returns {*} The result.
 */
export function syncBottomNavIndicator() {
    const nav = document.querySelector('[data-bottom-nav]');
    const indicator = nav?.querySelector('.hf-bottom-nav__indicator');
    const active = nav?.querySelector('.hf-nav-tab.is-active');

    if (!nav || !indicator || !active) {
        indicator?.classList.remove('is-visible');

        return;
    }

    const navRect = nav.getBoundingClientRect();
    const tabRect = active.getBoundingClientRect();

    if (!tabRect.width) {
        indicator.classList.remove('is-visible');

        return;
    }

    indicator.style.setProperty('--hf-nav-x', `${tabRect.left - navRect.left - nav.clientLeft}px`);
    indicator.style.setProperty('--hf-nav-w', `${tabRect.width}px`);
    indicator.classList.add('is-visible');
}

/**
 * Init bottom nav.
 * @returns {*} The result.
 */
export function initBottomNav() {
    if (bottomNavBound) return;
    bottomNavBound = true;

    const sync = () => window.requestAnimationFrame(syncBottomNavIndicator);

    sync();
    window.addEventListener('resize', sync, { passive: true });
    window.addEventListener('orientationchange', sync);

    // Widths settle once the display font is ready.
    document.fonts?.ready.then(sync).catch(() => {});
}
