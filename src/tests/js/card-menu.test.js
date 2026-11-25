// Front-end tests for Card menu.test.

import assert from 'node:assert/strict';
import test from 'node:test';
import { cardMenuLayout } from '../../resources/js/ui/card-menu.js';

const base = {
    open: true,
    button: { right: 400, bottom: 300 },
    panelWidth: 200,
    panelHeight: 88,
    viewportWidth: 700,
    viewportHeight: 800,
};

test('a menu that fits stays anchored to the button', () => {
    assert.deepEqual(cardMenuLayout(base), { up: false, shift: 0 });
});

test('a wrapped menu at the left edge shifts inside the viewport', () => {
    const placement = cardMenuLayout({
        ...base,
        button: { right: 131, bottom: 383 },
        viewportWidth: 390,
    });

    assert.equal(placement.shift, 77);
});

test('a menu at the right edge keeps its gutter', () => {
    const placement = cardMenuLayout({
        ...base,
        button: { right: 390, bottom: 383 },
        viewportWidth: 390,
    });

    // Panel right would sit on the viewport edge; move it in by the gutter.
    assert.equal(placement.shift, -8);
});

test('a menu near the bottom opens upward above the nav reserve', () => {
    const placement = cardMenuLayout({
        ...base,
        button: { right: 400, bottom: 700 },
        viewportHeight: 800,
        reserve: 59,
    });

    assert.equal(placement.up, true);
});

test('a menu clears the bottom nav when there is room below', () => {
    const placement = cardMenuLayout({
        ...base,
        button: { right: 400, bottom: 600 },
        viewportHeight: 800,
        reserve: 59,
    });

    assert.equal(placement.up, false);
});

test('closing resets the placement', () => {
    assert.deepEqual(cardMenuLayout({ ...base, open: false }), { up: false, shift: 0 });
});
