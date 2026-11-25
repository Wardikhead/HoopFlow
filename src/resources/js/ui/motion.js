// Front-end script for Motion.

const reduce = typeof window !== 'undefined'
    ? window.matchMedia('(prefers-reduced-motion: reduce)')
    : { matches: false };

let bound = false;

/**
 * Motion enabled.
 *
 * @param {*} { reduce: reduced  The { reduce: reduced.
 * @param {*} preference  The preference.
 * @returns {*} The result.
 */
export function motionEnabled({ reduce: reduced = null, preference = null } = {}) {
    const osReduced = reduced ?? reduce.matches;
    const preferenceValue = preference
        ?? (typeof document !== 'undefined' ? document.documentElement.dataset.motion : 'system');

    return !osReduced && preferenceValue !== 'reduced';
}

/**
 * Apply reveal.
 * @returns {*} The result.
 */
export function applyReveal() {
    if (reduce.matches) return;

    document.querySelectorAll('[data-reveal]').forEach((element, index) => {
        element.classList.add('hf-reveal');
        element.style.setProperty('--hf-reveal-delay', `${Math.min(index * 40, 200)}ms`);
    });
}

/**
 * Init motion.
 * @returns {*} The result.
 */
export function initMotion() {
    if (bound) return;
    bound = true;

    document.addEventListener('hf:theme-changed', () => {
        document.documentElement.classList.add('hf-theme-transition');
        window.setTimeout(() => {
            document.documentElement.classList.remove('hf-theme-transition');
        }, 220);
    });
}
