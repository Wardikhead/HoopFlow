export default function initHeader() {
    const header = document.querySelector('[data-landing-header]');

    if (header) {
        const update = () => header.classList.toggle('is-scrolled', window.scrollY > 24);
        update();
        window.addEventListener('scroll', update, { passive: true });
    }

    const button = document.querySelector('[data-landing-menu]');
    const menu = document.getElementById('landing-menu');

    if (!button || !menu) {
        return;
    }

    const close = () => {
        menu.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', 'Open menu');
    };

    button.addEventListener('click', () => {
        const open = menu.hidden;
        menu.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });

    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });

    document.addEventListener('click', (event) => {
        if (!menu.hidden && !menu.contains(event.target) && !button.contains(event.target)) {
            close();
        }
    });
}
