// Client-side entry point.
//
// Alpine 3 ships with Livewire, so no separate Alpine import is needed.
// Bootstrap's JavaScript is intentionally not imported (ADR-0001).
import 'bootstrap-icons/font/bootstrap-icons.css';

const THEME_KEY = 'hoopflow_theme';

function applyTheme(value) {
    const next = value === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
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

function syncThemeIcons() {
    document.querySelectorAll('[data-theme-toggle] i').forEach((icon) => {
        icon.className = `bi bi-${theme.get() === 'dark' ? 'sun' : 'moon-stars'}`;
    });
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-theme-toggle]');
    if (!button) {
        return;
    }
    theme.set(theme.get() === 'dark' ? 'light' : 'dark');
    syncThemeIcons();
});

syncThemeIcons();
document.addEventListener('hf:theme-changed', syncThemeIcons);
