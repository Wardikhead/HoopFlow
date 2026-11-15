import assert from 'node:assert/strict';
import test from 'node:test';
import { initCalendar } from '../../resources/js/ui/calendar.js';

const listeners = {};

globalThis.document = {
    addEventListener(type, handler) {
        listeners[type] = handler;
    },
};

initCalendar();

const cells = [
    { focused: false, focus() { this.focused = true; } },
    { focused: false, focus() { this.focused = true; } },
];

const calendar = { querySelectorAll: () => cells };

for (const cell of cells) {
    cell.closest = (selector) => (selector === '[data-calendar-day]' ? cell : calendar);
}

function press(key, index = 0) {
    let prevented = false;

    listeners.keydown({
        key,
        target: cells[index],
        preventDefault() { prevented = true; },
    });

    return prevented;
}

test('Enter activates the focused day instead of being swallowed', () => {
    assert.equal(press('Enter'), false);
    assert.equal(cells[0].focused, false);
});

test('Space activates the focused day instead of being swallowed', () => {
    assert.equal(press(' '), false);
    assert.equal(cells[0].focused, false);
});

test('arrow keys move focus and prevent scrolling', () => {
    assert.equal(press('ArrowRight'), true);
    assert.equal(cells[1].focused, true);
    assert.equal(press('ArrowRight', 1), false);
});
