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
