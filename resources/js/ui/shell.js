export function initShell() {
    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.hf-user-menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('details.hf-user-menu[open]').forEach((menu) => {
            menu.removeAttribute('open');
            menu.querySelector('summary')?.focus();
        });
    });
}

export function initMoreSheet() {
    const sheet = document.querySelector('[data-more-sheet]');
    if (!sheet) return;

    let trigger = null;

    document.querySelectorAll('[data-more-sheet-open]').forEach((button) => {
        button.addEventListener('click', () => {
            trigger = button;
            sheet.showModal();
        });
    });

    document.querySelectorAll('[data-more-sheet-close]').forEach((button) => {
        button.addEventListener('click', () => sheet.close());
    });

    sheet.addEventListener('click', (event) => {
        if (event.target === sheet) sheet.close();
    });

    sheet.addEventListener('close', () => {
        trigger?.focus({ preventScroll: true });
    });

    sheet.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => sheet.close());
    });
}

const NAV_KEY = 'hf-nav';

export function initNavRail() {
    const root = document.documentElement;
    const toggle = document.querySelector('[data-nav-toggle]');
    const rail = document.getElementById('hf-rail');

    if (!toggle || !rail) return;

    const desktop = window.matchMedia('(min-width: 1200px)');

    const stored = () => {
        try {
            const value = localStorage.getItem(NAV_KEY);
            return value === 'expanded' || value === 'collapsed' ? value : null;
        } catch {
            return null;
        }
    };

    const render = (state) => {
        root.dataset.nav = state;
        toggle.setAttribute('aria-expanded', state === 'expanded' ? 'true' : 'false');
        toggle.setAttribute('aria-label', state === 'expanded' ? 'Collapse navigation' : 'Expand navigation');
    };

    render(root.dataset.nav === 'expanded' ? 'expanded' : 'collapsed');

    toggle.addEventListener('click', () => {
        const next = root.dataset.nav === 'expanded' ? 'collapsed' : 'expanded';
        render(next);

        try {
            localStorage.setItem(NAV_KEY, next);
        } catch {
            // Storage can be unavailable; the current session still works.
        }
    });

    desktop.addEventListener('change', (event) => {
        if (stored()) return;
        render(event.matches ? 'expanded' : 'collapsed');
    });
}
