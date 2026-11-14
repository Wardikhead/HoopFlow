// Client-side entry point.
//
// Alpine 3 ships with Livewire, so no separate Alpine import is needed.
// Bootstrap's JavaScript is intentionally not imported (ADR-0001).
import 'bootstrap-icons/font/bootstrap-icons.css';
import { initBracketZoom } from './ui/bracket';
import { initCalendar } from './ui/calendar';
import { initCountUp } from './ui/countup';
import { initFeedback } from './ui/feedback';
import { initMotion } from './ui/motion';
import { initPreferences, syncPreferenceButtons } from './ui/preferences';
import { initScoreConsole } from './ui/score-console';
import { initScorekeeper } from './ui/scorekeeper';
import { initMoreSheet, initNavRail, initShell } from './ui/shell';
import { registerToasts } from './ui/toasts';
import { initValidation } from './ui/validation';

initBracketZoom();
initCalendar();
initCountUp();
initFeedback();
initMotion();
initPreferences();
document.addEventListener('hf:theme-changed', syncPreferenceButtons);
initScoreConsole();
initScorekeeper();
initNavRail();
initShell();
initMoreSheet();
initValidation();
registerToasts();

// Native <dialog> elements keep their open state outside the DOM attributes that
// Livewire morphs. Re-open any server-rendered modal after a morph so score
// buttons, forfeit changes, and other roundtrips do not close the dialog.
function keepModalsOpen() {
    Livewire.hook('morphed', ({ el }) => {
        el.querySelectorAll?.('dialog.hf-dialog').forEach((dialog) => {
            if (!dialog.open) {
                dialog.showModal();
            }
        });
    });
}

// Livewire removes a closing dialog instead of calling close(), so the browser
// cannot restore focus to the element that opened it. Record the control that
// opened each dialog, then restore it on removal. When that control disappears
// with a replaced dialog, fall back to the last still-connected trigger so a
// chooser that supersedes a detail dialog returns to the original card.
function restoreDialogFocus() {
    const triggers = new WeakMap();
    const activations = [];

    const remember = (element) => {
        if (!(element instanceof HTMLElement)) return;
        if (activations[activations.length - 1] === element) return;

        activations.push(element);

        if (activations.length > 12) {
            activations.shift();
        }
    };

    const latestConnected = () => {
        for (let index = activations.length - 1; index >= 0; index--) {
            if (activations[index].isConnected) return activations[index];
        }

        return null;
    };

    document.addEventListener(
        'click',
        (event) => {
            remember(
                event.target instanceof Element
                    ? event.target.closest('button, a, [role="button"], [tabindex], input, select, textarea')
                    : null,
            );
        },
        true,
    );

    document.addEventListener(
        'keydown',
        (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;

            remember(
                event.target instanceof Element
                    ? event.target.closest('button, a, [role="button"], [tabindex]')
                    : null,
            );
        },
        true,
    );

    document.addEventListener(
        'toggle',
        (event) => {
            const dialog = event.target;

            if (!(dialog instanceof HTMLDialogElement) || !dialog.classList.contains('hf-dialog') || !dialog.open) return;
            if (triggers.has(dialog)) return;

            triggers.set(dialog, latestConnected());
        },
        true,
    );

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.removedNodes) {
                if (!(node instanceof Element)) continue;

                const dialogs = node.matches('dialog.hf-dialog')
                    ? [node]
                    : [...node.querySelectorAll('dialog.hf-dialog')];

                for (const dialog of dialogs) {
                    const trigger = triggers.get(dialog);

                    if (trigger instanceof HTMLElement && trigger.isConnected) {
                        trigger.focus({ preventScroll: true });

                        return;
                    }
                }
            }
        }
    }).observe(document.body, { childList: true, subtree: true });
}

if (window.Livewire) {
    keepModalsOpen();
} else {
    document.addEventListener('livewire:init', keepModalsOpen);
}

restoreDialogFocus();
