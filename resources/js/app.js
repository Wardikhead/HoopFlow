// Client-side entry point.
//
// Alpine 3 ships with Livewire, so no separate Alpine import is needed.
// Bootstrap's JavaScript is intentionally not imported (ADR-0001).
import 'bootstrap-icons/font/bootstrap-icons.css';

const THEME_KEY = 'hf-theme';

function applyTheme(value) {
    const next = value === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.theme = next;
    document.documentElement.style.colorScheme = next;
}

const theme = {
    get: () => (localStorage.getItem(THEME_KEY) === 'dark' ? 'dark' : 'light'),
    set: (value) => {
        const next = value === 'dark' ? 'dark' : 'light';
        localStorage.setItem(THEME_KEY, next);
        applyTheme(next);
        document.dispatchEvent(new CustomEvent('hf:theme-changed', { detail: { theme: next } }));
    },
};

applyTheme(theme.get());

function syncTheme() {
    document.querySelectorAll('[data-theme-toggle] i').forEach((icon) => {
        icon.className = `bi bi-${theme.get() === 'dark' ? 'sun' : 'moon-stars'}`;
    });
    document.querySelectorAll('[data-demo-theme]').forEach((button) => {
        button.setAttribute('aria-pressed', String(button.dataset.demoTheme === theme.get()));
    });
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');
    if (toggle) {
        theme.set(theme.get() === 'dark' ? 'light' : 'dark');
        syncTheme();
        return;
    }
    const demo = event.target.closest('[data-demo-theme]');
    if (demo) {
        theme.set(demo.dataset.demoTheme);
        syncTheme();
    }
});

syncTheme();
document.addEventListener('hf:theme-changed', syncTheme);
