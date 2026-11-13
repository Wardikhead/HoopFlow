const THEME_KEY = 'hf-theme';
const DENSITY_KEY = 'hf-density';
const MOTION_KEY = 'hf-motion';
const media = window.matchMedia('(prefers-color-scheme: dark)');

export function themePreference() {
    return localStorage.getItem(THEME_KEY) || 'system';
}

export function resolvedTheme() {
    const pref = themePreference();
    if (pref === 'system') return media.matches ? 'dark' : 'light';
    return pref === 'dark' ? 'dark' : 'light';
}

export function applyPreferences() {
    const theme = resolvedTheme();
    document.documentElement.dataset.theme = theme;
    document.documentElement.dataset.themePreference = themePreference();
    document.documentElement.dataset.density = localStorage.getItem(DENSITY_KEY) || 'comfortable';
    document.documentElement.dataset.motion = localStorage.getItem(MOTION_KEY) || 'system';
    document.documentElement.style.colorScheme = theme;
}

export function setPreference(name, value) {
    if (name === 'theme') localStorage.setItem(THEME_KEY, value);
    if (name === 'density') localStorage.setItem(DENSITY_KEY, value);
    if (name === 'motion') localStorage.setItem(MOTION_KEY, value);
    applyPreferences();
    syncPreferenceButtons();
    document.dispatchEvent(new CustomEvent('hf:theme-changed', { detail: { theme: resolvedTheme() } }));
}

export function toggleTheme() {
    setPreference('theme', resolvedTheme() === 'dark' ? 'light' : 'dark');
}

export function syncPreferenceButtons() {
    document.querySelectorAll('[data-hf-pref]').forEach((button) => {
        const name = button.dataset.hfPref;
        const current = name === 'theme'
            ? themePreference()
            : (name === 'density' ? (localStorage.getItem(DENSITY_KEY) || 'comfortable') : (localStorage.getItem(MOTION_KEY) || 'system'));
        button.setAttribute('aria-pressed', String(button.dataset.hfValue === current));
    });
    document.querySelectorAll('[data-theme-toggle] i').forEach((icon) => {
        icon.className = `bi bi-${resolvedTheme() === 'dark' ? 'sun' : 'moon-stars'}`;
    });
}

export function initPreferences() {
    applyPreferences();

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-theme-toggle]');
        if (toggle) {
            toggleTheme();
            return;
        }

        const pref = event.target.closest('[data-hf-pref]');
        if (pref) {
            setPreference(pref.dataset.hfPref, pref.dataset.hfValue);
        }
    });

    media.addEventListener('change', () => {
        if (themePreference() === 'system') {
            applyPreferences();
            syncPreferenceButtons();
        }
    });

    syncPreferenceButtons();
    document.addEventListener('hf:theme-changed', syncPreferenceButtons);
}
