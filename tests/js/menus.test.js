// Front-end tests for Menus.test.

import assert from 'node:assert/strict';
import test from 'node:test';
import { menuCloseDelay } from '../../resources/js/ui/menus.js';

test('the menu exit is skipped when motion is reduced', () => {
    assert.equal(menuCloseDelay({ reduce: true, preference: 'system' }), 0);
    assert.equal(menuCloseDelay({ reduce: false, preference: 'reduced' }), 0);
});

test('the menu uses the micro exit duration otherwise', () => {
    assert.equal(menuCloseDelay({ reduce: false, preference: 'system' }), 120);
});
