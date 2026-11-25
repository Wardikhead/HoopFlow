// Front-end script for Tabs.

export default function initTabs() {
    const tabs = [...document.querySelectorAll('[data-demo-tab]')];
    const panels = [...document.querySelectorAll('[data-demo-panel]')];

    if (!tabs.length || !panels.length) {
        return;
    }

    const activate = (name, focus = false) => {
        tabs.forEach((tab) => {
            const selected = tab.dataset.demoTab === name;
            tab.classList.toggle('is-active', selected);
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;

            if (selected && focus) {
                tab.focus();
            }
        });

        panels.forEach((panel) => {
            panel.classList.toggle('is-active', panel.dataset.demoPanel === name);
        });
    };

    const supportsTransition =
        typeof document.startViewTransition === 'function' &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const swap = (name, focus = false) => {
        const current = panels.find((panel) => panel.classList.contains('is-active'));
        const next = panels.find((panel) => panel.dataset.demoPanel === name);

        if (!supportsTransition || !current || !next || current === next) {
            activate(name, focus);
            return;
        }

        current.style.viewTransitionName = 'hf-demo-panel';

        const transition = document.startViewTransition(() => {
            current.style.viewTransitionName = '';
            activate(name, focus);
            next.style.viewTransitionName = 'hf-demo-panel';
        });

        transition.finished.finally(() => {
            next.style.viewTransitionName = '';
        });
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => swap(tab.dataset.demoTab));

        tab.addEventListener('keydown', (event) => {
            let next = null;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                next = (index + 1) % tabs.length;
            }
            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                next = (index - 1 + tabs.length) % tabs.length;
            }
            if (event.key === 'Home') {
                next = 0;
            }
            if (event.key === 'End') {
                next = tabs.length - 1;
            }

            if (next !== null) {
                event.preventDefault();
                swap(tabs[next].dataset.demoTab, true);
            }
        });
    });

    document.querySelectorAll('[data-demo-target]').forEach((link) => {
        link.addEventListener('click', () => swap(link.dataset.demoTarget));
    });

    const fromHash = window.location.hash.replace('#', '');

    if (['bracket', 'calendar', 'standings'].includes(fromHash)) {
        activate(fromHash);
    } else {
        activate('bracket');
    }
}
