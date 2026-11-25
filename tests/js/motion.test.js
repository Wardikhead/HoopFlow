// Front-end tests for Motion.test.

import assert from 'node:assert/strict';
import test from 'node:test';
import { motionEnabled } from '../../resources/js/ui/motion.js';

test('motion is enabled when the OS setting is normal', () => {
    assert.equal(motionEnabled({ reduce: false, preference: 'system' }), true);
});

test('OS reduced motion disables animation', () => {
    assert.equal(motionEnabled({ reduce: true, preference: 'system' }), false);
});

test('the in-app reduced preference disables animation', () => {
    assert.equal(motionEnabled({ reduce: false, preference: 'reduced' }), false);
});
