import assert from 'node:assert/strict';
import test from 'node:test';
import { shouldHoldDialog } from '../../resources/js/ui/dialog-motion.js';

function dialog({ open = true, closing = false } = {}) {
    const names = new Set(['hf-dialog']);
    if (closing) names.add('is-closing');

    return {
        tagName: 'DIALOG',
        open,
        classList: {
            contains: (name) => names.has(name),
        },
    };
}

test('holds an open animated dialog', () => {
    assert.equal(shouldHoldDialog(dialog(), { reduce: false, preference: 'system' }), true);
});

test('skips a dialog that is already closed', () => {
    assert.equal(shouldHoldDialog(dialog({ open: false }), { reduce: false, preference: 'system' }), false);
});

test('skips a dialog that is already closing', () => {
    assert.equal(shouldHoldDialog(dialog({ closing: true }), { reduce: false, preference: 'system' }), false);
});

test('skips nodes that are not dialogs', () => {
    assert.equal(
        shouldHoldDialog(
            { tagName: 'DIV', open: true, classList: { contains: () => true } },
            { reduce: false, preference: 'system' },
        ),
        false,
    );
});

test('skips reduced motion', () => {
    assert.equal(shouldHoldDialog(dialog(), { reduce: true, preference: 'system' }), false);
    assert.equal(shouldHoldDialog(dialog(), { reduce: false, preference: 'reduced' }), false);
});
