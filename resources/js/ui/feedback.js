// Front-end script for Feedback.

let activeRequests = 0;
let startedAt = 0;
let showTimer = null;
let hideTimer = null;

/**
 * Progress el.
 * @returns {*} The result.
 */
function progressEl() {
    return document.querySelector('[data-hf-progress]');
}

/**
 * Show progress.
 * @returns {*} The result.
 */
function showProgress() {
    const bar = progressEl();
    if (!bar) return;
    bar.classList.add('is-active');
    bar.setAttribute('aria-hidden', 'false');
}

/**
 * Hide progress.
 * @returns {*} The result.
 */
function hideProgress() {
    const bar = progressEl();
    if (!bar) return;
    bar.classList.remove('is-active');
    bar.setAttribute('aria-hidden', 'true');
}

/**
 * Start progress.
 * @returns {*} The result.
 */
function startProgress() {
    activeRequests += 1;
    if (activeRequests !== 1) return;
    startedAt = performance.now();
    window.clearTimeout(hideTimer);
    showTimer = window.setTimeout(showProgress, 150);
}

/**
 * Finish progress.
 * @returns {*} The result.
 */
function finishProgress() {
    activeRequests = Math.max(0, activeRequests - 1);
    if (activeRequests !== 0) return;
    window.clearTimeout(showTimer);
    const elapsed = performance.now() - startedAt;
    hideTimer = window.setTimeout(hideProgress, Math.max(0, 300 - elapsed));
}

/**
 * Toast.
 *
 * @param {*} detail The detail.
 * @returns {*} The result.
 */
function toast(detail) {
    window.dispatchEvent(new CustomEvent('hf-toast', { detail }));
}

/**
 * Init offline.
 * @returns {*} The result.
 */
function initOffline() {
    const banner = document.querySelector('[data-hf-offline]');
    if (!banner) return;

    let wasOffline = !navigator.onLine;

    const sync = () => {
        banner.hidden = navigator.onLine;
        if (!navigator.onLine) {
            wasOffline = true;
        } else if (wasOffline) {
            wasOffline = false;
            toast({ tone: 'success', title: 'Back online', message: 'Changes will save again.' });
        }
    };

    window.addEventListener('online', sync);
    window.addEventListener('offline', sync);
    sync();
}

/**
 * Init requests.
 * @returns {*} The result.
 */
function initRequests() {
    if (!window.Livewire) return;

    window.Livewire.interceptRequest(({ onSend, onFinish, onError, onFailure }) => {
        onSend(() => startProgress());
        onFinish(() => finishProgress());

        onError(({ response, preventDefault }) => {
            const status = response?.status ?? 0;
            if (status === 422) return;
            preventDefault?.();

            if (status === 419) {
                toast({
                    tone: 'danger',
                    title: 'Your session expired',
                    message: 'Reload the page and sign in again.',
                    actionLabel: 'Reload',
                    actionHref: window.location.href,
                });
                return;
            }

            toast({
                tone: 'danger',
                title: 'That action didn’t go through',
                message: 'Try again. Your form state is still here.',
                actionLabel: 'Retry',
                actionHref: window.location.href,
            });
        });

        onFailure(() => {
            toast({
                tone: 'danger',
                title: 'Connection lost',
                message: 'Your change may not have saved.',
                actionLabel: 'Retry',
                actionHref: window.location.href,
            });
        });
    });
}

/**
 * Init feedback.
 * @returns {*} The result.
 */
export function initFeedback() {
    initOffline();
    initRequests();
}

/**
 * Begin navigation.
 * @returns {*} The result.
 */
export function beginNavigation() {
    startProgress();
}

/**
 * Finish navigation.
 * @returns {*} The result.
 */
export function finishNavigation() {
    finishProgress();
}
