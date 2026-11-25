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
