const MODE_KEY = 'hf-scorekeeper';

function setMode(enabled) {
    document.documentElement.dataset.scorekeeper = enabled ? 'on' : 'off';
    sessionStorage.setItem(MODE_KEY, enabled ? 'on' : 'off');
    document.querySelectorAll('[data-scorekeeper-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(enabled));
    });
}

export function initScorekeeper() {
    const enabled = sessionStorage.getItem(MODE_KEY) === 'on';
    if (document.querySelector('[data-scorekeeper-toggle]')) setMode(enabled);

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-scorekeeper-toggle]');
        if (!toggle) return;
        setMode(toggle.getAttribute('aria-pressed') !== 'true');
    });

    const form = document.querySelector('[data-testid="ingame-actions"]');
    if (!form) return;

    let dirty = false;
    const markDirty = () => { dirty = true; };

    document.querySelectorAll('[data-testid^="ingame-q-"], [data-testid^="ingame-final-"], [data-testid^="ingame-stat-"]').forEach((input) => {
        input.addEventListener('input', markDirty);
    });

    document.querySelectorAll('[data-testid="ingame-save"], [data-testid="ingame-publish"], [data-testid="ingame-save-correction"]').forEach((button) => {
        button.addEventListener('click', () => { dirty = false; });
    });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
}
