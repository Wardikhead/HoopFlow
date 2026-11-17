import { beginNavigation, finishNavigation } from './feedback';

const SHOW_DELAY = 160;
const SAFETY_TIMEOUT = 12000;

let safetyTimer = null;
let navigationWatchdog = null;

function loaderElement() {
    return document.querySelector('[data-hf-page-loader]');
}

export function showPageLoader() {
    const loader = loaderElement();

    document.documentElement.classList.add('hf-is-loading');

    if (!loader) return;

    loader.classList.add('is-visible');
    loader.setAttribute('aria-hidden', 'false');

    const status = loader.querySelector('[data-hf-loader-status]');
    if (status) status.textContent = 'Loading page content';

    window.clearTimeout(safetyTimer);
    safetyTimer = window.setTimeout(hidePageLoader, SAFETY_TIMEOUT);
}

export function hidePageLoader() {
    const loader = loaderElement();

    window.clearTimeout(safetyTimer);
    document.documentElement.classList.remove('hf-is-loading');

    if (!loader) return;

    loader.classList.remove('is-visible');
    loader.setAttribute('aria-hidden', 'true');

    const status = loader.querySelector('[data-hf-loader-status]');
    if (status) status.textContent = '';
}

export function initPageLoader() {
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

    if (!loaderElement()) return;

    let timer = null;

    const schedule = () => {
        if (document.documentElement.classList.contains('hf-is-loading')) return;

        window.clearTimeout(timer);
        timer = window.setTimeout(showPageLoader, SHOW_DELAY);
    };

    const eligibleLink = (link) => {
        if (!(link instanceof HTMLAnchorElement)) return false;
        if (link.target && link.target !== '_self') return false;
        if (link.hasAttribute('download') || link.dataset.noLoader !== undefined) return false;
        if (link.closest('[data-no-loader]')) return false;
        if (!link.getAttribute('href') || link.getAttribute('href').startsWith('#')) return false;

        let url;

        try {
            url = new URL(link.href, window.location.href);
        } catch {
            return false;
        }

        if (url.protocol !== 'http:' && url.protocol !== 'https:') return false;
        if (url.origin !== window.location.origin) return false;

        // A same-page hash link only scrolls, so the skeleton would never clear.
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
            return false;
        }

        return url.href !== window.location.href;
    };

    document.addEventListener('click', (event) => {
        if (event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || !eligibleLink(link)) return;

        // Handlers further down the tree may cancel the navigation (or open a
        // dialog); checking on the next task avoids a skeleton that never clears.
        window.setTimeout(() => {
            if (!event.defaultPrevented) schedule();
        }, 0);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.matches('[data-hf-logout-form], [data-no-loader]')) return;
        if (form.target === '_blank') return;
        if (form.method.toLowerCase() === 'dialog') return;

        window.setTimeout(() => {
            if (!event.defaultPrevented) schedule();
        }, 0);
    });

    window.addEventListener('pageshow', () => {
        window.clearTimeout(timer);
        hidePageLoader();
    });

    window.addEventListener('pagehide', () => {
        window.clearTimeout(timer);
    });

    // A navigation that gets cancelled (download fallback, aborted request)
    // must not leave the user behind an opaque skeleton.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') hidePageLoader();
    });
}
