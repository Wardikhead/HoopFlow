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

    if (reduceMotion) {
        // Skip the animation entirely.
        splash.remove();
        return;
    }

    let hiding = false;

    const hide = () => {
        if (hiding) {
            return;
        }

        hiding = true;
        splash.classList.add('is-hiding');
        window.setTimeout(() => splash.remove(), 560);
    };

    ['pointerdown', 'keydown', 'wheel', 'touchmove'].forEach((event) => {
        window.addEventListener(event, hide, { once: true, passive: true });
    });

    window.setTimeout(hide, 2300);
}
