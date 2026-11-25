// Front-end script for Page lifecycle.

import { initBracketZoom } from './bracket';
import { initCalendar } from './calendar';
import { initCardMenus } from './card-menu';
import { initCountUp } from './countup';
import { initFeedback } from './feedback';
import { initDialogMotion } from './dialog-motion.js';
import { initLiveScores } from './live-scores';
import { initLivewireBridge } from './livewire-bridge';
import { initPageLoader } from './loading';
import { initLogoutDialog } from './logout';
import { applyReveal, initMotion } from './motion';
import { registerPasswordMeter } from './password-strength';
import { applyPreferences, initPreferences, syncPreferenceButtons } from './preferences';
import { initScoreConsole } from './score-console';
import { bindScorekeeperPage, initScorekeeper } from './scorekeeper';
import { initRichSelects } from './select.js';
import { initSpaNavigation } from './spa-nav';
import { initMoreSheet, initNavRail, initShell, syncNavRail } from './shell';
import { registerToasts } from './toasts';
import { initValidation, refreshValidation } from './validation';

let liveScores = null;
let lastUrl = null;
let globalsReady = false;

/**
 * Sync document state.
 * @returns {*} The result.
 */
function syncDocumentState() {
    applyPreferences();
    syncNavRail();
}

/**
 * Init globals.
 * @returns {*} The result.
 */
function initGlobals() {
    if (globalsReady) return;
    globalsReady = true;

    initBracketZoom();
    initCalendar();
    initCardMenus();
    initFeedback();
    initLivewireBridge();
    initDialogMotion();
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

    // Livewire's SPA swap deletes <html> attributes that the server doesn't
    // render. Restore them inside onSwap so the new page never paints with
    // the wrong theme or an expanded rail.
    document.addEventListener('livewire:navigating', (event) => {
        if (typeof event.detail?.onSwap !== 'function') return;

        event.detail.onSwap(() => {
            document.documentElement.classList.add('hf-spa-nav');
            syncDocumentState();
            syncPreferenceButtons();
        });
    });
}

/**
 * Init page.
 *
 * @param {*} { focus  The { focus.
 * @param {*} animate  The animate.
 * @returns {*} The result.
 */
function initPage({ focus = true, animate = true } = {}) {
    if (animate) {
        applyReveal();
        initCountUp();
    }

    liveScores?.stop();
    liveScores = initLiveScores();

    bindScorekeeperPage();
    syncDocumentState();
    syncPreferenceButtons();
    refreshValidation();
    initRichSelects();

    if (focus) {
        focusMain();
        announcePage();
    }

    consumeFlashToast();
}

/**
 * Teardown page.
 * @returns {*} The result.
 */
function teardownPage() {
    liveScores?.stop();
    liveScores = null;

    document.querySelector('dialog[data-more-sheet][open]')?.close();
    document.querySelector('dialog[data-hf-logout-dialog][open]')?.close();
    document.querySelector('.hf-rail-tooltip')?.remove();
}

/**
 * Focus main.
 * @returns {*} The result.
 */
function focusMain() {
    document.getElementById('main')?.focus({ preventScroll: true });
}

/**
 * Announce page.
 * @returns {*} The result.
 */
function announcePage() {
    const region = document.querySelector('[data-hf-page-announcer]');

    if (region) region.textContent = document.title;
}

/**
 * Consume flash toast.
 * @returns {*} The result.
 */
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

    if (document.readyState === 'complete') {
        dispatch();

        return;
    }

    document.addEventListener('DOMContentLoaded', dispatch, { once: true });
}

/**
 * Init page lifecycle.
 * @returns {*} The result.
 */
export function initPageLifecycle() {
    initGlobals();

    document.addEventListener('livewire:navigate', teardownPage);
    document.addEventListener('livewire:navigated', () => {
        if (lastUrl === window.location.href) return;

        const initial = lastUrl === null;

        lastUrl = window.location.href;
        initPage({ focus: !initial, animate: initial });
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
