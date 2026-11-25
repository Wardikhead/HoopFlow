// Front-end script for Scorekeeper.

const MODE_KEY = 'hf-scorekeeper';

let bound = false;
let dirty = false;

/**
 * Set mode.
 *
 * @param {*} enabled The enabled.
 * @returns {*} The result.
 */
function setMode(enabled) {
    document.documentElement.dataset.scorekeeper = enabled ? 'on' : 'off';
    sessionStorage.setItem(MODE_KEY, enabled ? 'on' : 'off');
    document.querySelectorAll('[data-scorekeeper-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(enabled));
    });
}

/**
 * Init scorekeeper.
 * @returns {*} The result.
 */
export function initScorekeeper() {
    if (bound) return;
    bound = true;

    document.addEventListener('click', (event) => {
        const toggle = event.target instanceof Element ? event.target.closest('[data-scorekeeper-toggle]') : null;
        if (!toggle) return;

        setMode(toggle.getAttribute('aria-pressed') !== 'true');
    });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;

        event.preventDefault();
        event.returnValue = '';
    });
}

/**
 * Bind scorekeeper page.
 * @returns {*} The result.
 */
export function bindScorekeeperPage() {
    dirty = false;

    const enabled = sessionStorage.getItem(MODE_KEY) === 'on';
    if (document.querySelector('[data-scorekeeper-toggle]')) setMode(enabled);

    const form = document.querySelector('[data-testid="ingame-actions"]');
    if (!form) return;

    const markDirty = () => { dirty = true; };
    const markClean = () => { dirty = false; };

    document.querySelectorAll('[data-testid^="ingame-q-"], [data-testid^="ingame-final-"], [data-testid^="ingame-stat-"]').forEach((input) => {
        if (input.dataset.hfDirtyBound === '1') return;

        input.dataset.hfDirtyBound = '1';
        input.addEventListener('input', markDirty);
    });

    document.querySelectorAll('[data-testid="ingame-save"], [data-testid="ingame-publish"], [data-testid="ingame-save-correction"]').forEach((button) => {
        if (button.dataset.hfDirtyBound === '1') return;

        button.dataset.hfDirtyBound = '1';
        button.addEventListener('click', markClean);
    });
}
