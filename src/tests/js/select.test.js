// Front-end tests for Select.test.

import assert from 'node:assert/strict';
import test from 'node:test';
import { menuPlacement, nextEnabledIndex, readRows, typeaheadIndex } from '../../resources/js/ui/select.js';

const rows = [
    { index: 0, value: '', label: 'All statuses', disabled: false },
    { index: 1, value: 'active', label: 'Active', disabled: false },
    { index: 2, value: 'archived', label: 'Archived', disabled: true },
    { index: 3, value: 'draft', label: 'Draft', disabled: false },
];

test('arrow movement wraps and skips disabled rows', () => {
    assert.equal(nextEnabledIndex(rows, 1, 1), 3);
    assert.equal(nextEnabledIndex(rows, 3, 1), 0);
    assert.equal(nextEnabledIndex(rows, 0, -1), 3);
    assert.equal(nextEnabledIndex(rows, -1, 1), 0);
    assert.equal(nextEnabledIndex([], -1, 1), -1);
});

test('type-ahead finds the next row by prefix and loops', () => {
    assert.equal(typeaheadIndex(rows, 'd', 0), 3);
    assert.equal(typeaheadIndex(rows, 'a', 1), 0);
    assert.equal(typeaheadIndex(rows, 'ar', 0), -1);
    assert.equal(typeaheadIndex(rows, '', 0), -1);
});

test('readRows flattens options into label and value', () => {
    const select = {
        options: [
            { value: '', textContent: ' All statuses ', disabled: false },
            { value: 'active', textContent: 'Active', disabled: true },
        ],
    };

    assert.deepEqual(readRows(select), [
        { index: 0, value: '', label: 'All statuses', disabled: false },
        { index: 1, value: 'active', label: 'Active', disabled: true },
    ]);
});

test('the panel opens upward only when below cannot hold it', () => {
    const nearBottom = menuPlacement({ top: 600, bottom: 640 }, 260, 700);

    assert.equal(nearBottom.openUp, true);
    assert.equal(nearBottom.maxHeight, 260);

    const roomy = menuPlacement({ top: 80, bottom: 120 }, 200, 700);

    assert.equal(roomy.openUp, false);
    assert.equal(roomy.maxHeight, 200);

    const shortBelow = menuPlacement({ top: 100, bottom: 540 }, 400, 700);

    assert.equal(shortBelow.openUp, false);
    assert.equal(shortBelow.maxHeight, 160);
});
