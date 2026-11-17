import { showPageLoader } from './loading';

const EXIT_MS = 180;
const SUBMIT_MS = 560;

let bound = false;
let activeForm = null;
let closing = false;
let busy = false;

function dialog() {
    return document.querySelector('[data-hf-logout-dialog]');
}

function reset() {
    closing = false;
    busy = false;

    const node = dialog();
    if (!(node instanceof HTMLDialogElement)) return;

    const confirmButton = node.querySelector('[data-hf-logout-confirm]');
    const cancelButton = node.querySelector('[data-hf-logout-cancel]');
    const copy = node.querySelector('[data-hf-logout-copy]');
    const label = node.querySelector('[data-hf-logout-label]');

    node.classList.remove('is-closing', 'is-confirming');
    confirmButton?.classList.remove('is-loading');
    if (confirmButton) confirmButton.disabled = false;
    if (cancelButton) cancelButton.disabled = false;
    if (label) label.textContent = 'Log out';
    if (copy) copy.textContent = copy.dataset.defaultCopy ?? '';
}

function open(form) {
    const node = dialog();
    if (!(node instanceof HTMLDialogElement)) return;

    activeForm = form;

    const copy = node.querySelector('[data-hf-logout-copy]');
    if (copy && !copy.dataset.defaultCopy) {
        copy.dataset.defaultCopy = copy.textContent.trim();
    }

    reset();

    const sheet = document.querySelector('dialog[data-more-sheet][open]');
    if (sheet instanceof HTMLDialogElement) sheet.close();

    node.showModal();
}

function close() {
    const node = dialog();
    if (!(node instanceof HTMLDialogElement) || closing || busy) return;

    closing = true;
    node.classList.add('is-closing');

    const finish = () => {
        node.close();
        node.classList.remove('is-closing');
        closing = false;
    };

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (reduceMotion.matches || document.documentElement.dataset.motion === 'reduced') {
        finish();

        return;
    }

    window.setTimeout(finish, EXIT_MS);
}

function submit() {
    const node = dialog();
    if (!activeForm || busy || !(node instanceof HTMLDialogElement)) return;

    busy = true;
    node.classList.add('is-confirming');

    const confirmButton = node.querySelector('[data-hf-logout-confirm]');
    const cancelButton = node.querySelector('[data-hf-logout-cancel]');
    const copy = node.querySelector('[data-hf-logout-copy]');
    const label = node.querySelector('[data-hf-logout-label]');

    confirmButton?.classList.add('is-loading');
    if (confirmButton) confirmButton.disabled = true;
    if (cancelButton) cancelButton.disabled = true;
    if (label) label.textContent = 'Signing out';
    if (copy) copy.textContent = 'Closing your session. See you on the court.';

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const delay = reduceMotion.matches || document.documentElement.dataset.motion === 'reduced' ? 0 : SUBMIT_MS;

    window.setTimeout(() => {
        showPageLoader();
        HTMLFormElement.prototype.submit.call(activeForm);
    }, delay);
}

export function initLogoutDialog() {
    if (bound) return;
    bound = true;

    document.addEventListener('submit', (event) => {
        const form = event.target instanceof Element ? event.target.closest('form[data-hf-logout-form]') : null;
        if (!form) return;

        event.preventDefault();
        open(form);
    });

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        if (target.closest('[data-hf-logout-cancel]')) {
            close();

            return;
        }

        if (target.closest('[data-hf-logout-confirm]')) {
            submit();

            return;
        }

        const node = dialog();
        if (node && event.target === node) close();
    });

    document.addEventListener('cancel', (event) => {
        if (!(event.target instanceof HTMLDialogElement) || !event.target.matches('[data-hf-logout-dialog]')) return;

        event.preventDefault();
        close();
    }, true);

    document.addEventListener('close', (event) => {
        if (event.target instanceof HTMLDialogElement && event.target.matches('[data-hf-logout-dialog]')) reset();
    }, true);
}
