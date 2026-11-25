// Front-end script for Dialog motion.

import { motionEnabled } from './motion.js';

export const DIALOG_EXIT_MS = 160;

/**
 * Should hold dialog.
 *
 * @param {*} element The element.
 * @param {*} options  The options.
 * @returns {*} The result.
 */
export function shouldHoldDialog(element, options = {}) {
    if (!element || element.tagName !== 'DIALOG') return false;
    if (!element.classList?.contains('hf-dialog')) return false;
    if (!element.open) return false;
    if (element.classList.contains('is-closing')) return false;

    return motionEnabled(options);
}

/**
 * Hold and remove.
 *
 * @param {*} dialog The dialog.
 * @param {*} { duration  The { duration.
 * @returns {*} The result.
 */
export function holdAndRemove(dialog, { duration = DIALOG_EXIT_MS } = {}) {
    if (!dialog) return;

    dialog.classList.add('is-closing');

    window.setTimeout(() => {
        if (!dialog.isConnected) return;

        if (dialog.open) dialog.close();
        dialog.remove();
    }, duration);
}

/**
 * Close dialog with motion.
 *
 * @param {*} dialog The dialog.
 * @param {*} { duration  The { duration.
 * @returns {*} The result.
 */
export function closeDialogWithMotion(dialog, { duration = DIALOG_EXIT_MS } = {}) {
    if (!(dialog instanceof HTMLDialogElement) || !dialog.open) return;

    if (!motionEnabled()) {
        dialog.close();

        return;
    }

    if (dialog.classList.contains('is-closing')) return;

    dialog.classList.add('is-closing');

    window.setTimeout(() => {
        dialog.close();
        dialog.classList.remove('is-closing');
    }, duration);
}

let bound = false;

/**
 * Init dialog motion.
 * @returns {*} The result.
 */
export function initDialogMotion() {
    if (bound) return;
    bound = true;

    const register = () => {
        window.Livewire.hook('morph.removing', ({ el, skip }) => {
            if (!shouldHoldDialog(el)) return;
            if (typeof skip !== 'function') return;

            skip();
            holdAndRemove(el);
        });
    };

    if (window.Livewire) {
        register();
    } else {
        document.addEventListener('livewire:init', register);
    }
}
