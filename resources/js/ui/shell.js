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
const TOOLTIP_GAP = 10;
const VIEWPORT_GAP = 8;

export function initNavRail() {
    const root = document.documentElement;
    const toggle = document.querySelector('[data-nav-toggle]');
    const rail = document.getElementById('hf-rail');

    if (!toggle || !rail) return;

    const desktop = window.matchMedia('(min-width: 1200px)');
    const tooltip = document.createElement('div');
    tooltip.className = 'hf-rail-tooltip';
    tooltip.setAttribute('aria-hidden', 'true');
    document.body.appendChild(tooltip);

    let tooltipTarget = null;

    const hideTooltip = () => {
        tooltipTarget = null;
        tooltip.classList.remove('is-visible');
    };

    const positionTooltip = (link) => {
        const linkRect = link.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const left = Math.min(
            linkRect.right + TOOLTIP_GAP,
            window.innerWidth - tooltipRect.width - VIEWPORT_GAP,
        );
        const top = Math.min(
            window.innerHeight - tooltipRect.height - VIEWPORT_GAP,
            Math.max(VIEWPORT_GAP, linkRect.top + linkRect.height / 2 - tooltipRect.height / 2),
        );

        tooltip.style.left = `${Math.max(VIEWPORT_GAP, left)}px`;
        tooltip.style.top = `${top}px`;
    };

    const showTooltip = (link) => {
        if (root.dataset.nav !== 'collapsed' || window.innerWidth < 768) return;

        const label = link.dataset.label;
        if (!label) return;

        tooltipTarget = link;
        tooltip.textContent = label;
        tooltip.classList.add('is-visible');
        positionTooltip(link);
    };

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
        hideTooltip();
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

    rail.addEventListener('pointerover', (event) => {
        const link = event.target instanceof Element ? event.target.closest('.hf-rail-link') : null;
        if (link && link !== tooltipTarget) showTooltip(link);
    });

    rail.addEventListener('pointerout', (event) => {
        const link = event.target instanceof Element ? event.target.closest('.hf-rail-link') : null;
        if (!link) return;
        if (!link.contains(event.relatedTarget)) hideTooltip();
    });

    rail.addEventListener('focusin', (event) => {
        const link = event.target instanceof Element ? event.target.closest('.hf-rail-link') : null;
        if (link) showTooltip(link);
    });

    rail.addEventListener('focusout', (event) => {
        if (event.target instanceof Element && event.target.closest('.hf-rail-link')) hideTooltip();
    });

    window.addEventListener('scroll', hideTooltip, true);
    window.addEventListener('resize', hideTooltip);
}
