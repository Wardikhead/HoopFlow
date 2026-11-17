import assert from 'node:assert/strict';
import test from 'node:test';
import { resolveNavState } from '../../resources/js/ui/shell.js';

test('a saved expanded preference survives a page swap', () => {
    assert.equal(resolveNavState({ stored: 'expanded', current: 'collapsed', mediaMatches: false }), 'expanded');
});

test('a saved collapsed preference survives a page swap', () => {
    assert.equal(resolveNavState({ stored: 'collapsed', current: 'expanded', mediaMatches: true }), 'collapsed');
});

test('the current document state is used when nothing is saved', () => {
    assert.equal(resolveNavState({ stored: null, current: 'expanded', mediaMatches: false }), 'expanded');
    assert.equal(resolveNavState({ stored: null, current: 'collapsed', mediaMatches: true }), 'collapsed');
});

test('the viewport default is used when the attribute was wiped', () => {
    assert.equal(resolveNavState({ stored: null, current: null, mediaMatches: true }), 'expanded');
    assert.equal(resolveNavState({ stored: null, current: null, mediaMatches: false }), 'collapsed');
});

test('invalid values fall through to the next source', () => {
    assert.equal(resolveNavState({ stored: 'nope', current: 'expanded', mediaMatches: false }), 'expanded');
    assert.equal(resolveNavState({ stored: 'nope', current: 'nope', mediaMatches: true }), 'expanded');
    assert.equal(resolveNavState({ stored: 'nope', current: 'nope', mediaMatches: false }), 'collapsed');
});
