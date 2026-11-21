export default function initSplash() {
    const splash = document.querySelector('[data-lp-splash]');

    if (!splash) {
        return;
    }

    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    if (sessionStorage.getItem('hf-lp-splash') === '1') {
        splash.remove();
        return;
    }

    sessionStorage.setItem('hf-lp-splash', '1');

    const hide = () => {
        splash.classList.add('is-hiding');
        window.setTimeout(() => splash.remove(), reduceMotion ? 0 : 420);
    };

    if (reduceMotion) {
        // Skip the animation entirely.
        splash.remove();
        return;
    }

    window.setTimeout(hide, 1100);
}