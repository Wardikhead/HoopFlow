// Front-end script for Header.

export default function initHeader() {
    const header = document.querySelector('[data-landing-header]');
    const progress = document.querySelector('[data-landing-progress]');

    if (header) {
        const update = () => {
            header.classList.toggle('is-scrolled', window.scrollY > 24);

            if (progress) {
                const max = document.documentElement.scrollHeight - window.innerHeight;
                const ratio = max > 0 ? Math.min(1, Math.max(0, window.scrollY / max)) : 0;
                progress.style.transform = `scaleX(${ratio.toFixed(4)})`;
            }
        };

        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update, { passive: true });
    }

    const button = document.querySelector('[data-landing-menu]');
    const menu = document.getElementById('landing-menu');

    if (!button || !menu) {
        return;
    }

    const icon = button.querySelector('i');
    const MENU_MS = 420;
    let closeTimer = null;

    const motionAllowed = () => !window.matchMedia('(prefers-reduced-motion: reduce)').matches
        && document.documentElement.dataset.motion !== 'reduced';

    const setIcon = (open) => {
        if (!icon) return;

        icon.classList.toggle('bi-list', !open);
        icon.classList.toggle('bi-x-lg', open);
    };

    const open = () => {
        window.clearTimeout(closeTimer);
        menu.hidden = false;

        // Let the closed state paint before opening so the height transition runs.
        window.requestAnimationFrame(() => menu.classList.add('is-open'));

        button.setAttribute('aria-expanded', 'true');
        button.setAttribute('aria-label', 'Close menu');
        setIcon(true);
    };

    const settle = () => {
        menu.hidden = true;
        menu.classList.remove('is-open');
    };

    const close = ({ instant = false } = {}) => {
        window.clearTimeout(closeTimer);
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', 'Open menu');
        setIcon(false);

        if (menu.hidden) return;

        menu.classList.remove('is-open');

        if (instant || !motionAllowed()) {
            settle();

            return;
        }

        closeTimer = window.setTimeout(settle, MENU_MS);
    };

    button.addEventListener('click', () => {
        if (button.getAttribute('aria-expanded') === 'true') {
            close();
        } else {
            open();
        }
    });

    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => close()));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !menu.hidden) {
            close();
        }
    });

    document.addEventListener('click', (event) => {
        if (!menu.hidden && !menu.contains(event.target) && !button.contains(event.target)) {
            close();
        }
    });

    window.matchMedia('(min-width: 992px)').addEventListener('change', (event) => {
        if (event.matches) close({ instant: true });
    });
}
