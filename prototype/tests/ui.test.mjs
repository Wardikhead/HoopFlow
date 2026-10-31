import test from 'node:test';
import assert from 'node:assert/strict';
import {
  escapeHtml,
  initials,
  formatMoney,
  formatDate,
  formatTime,
  statusMeta,
} from '../assets/js/ui.js';

test('escapeHtml escapes markup', () => {
  assert.equal(escapeHtml('<img src=x onerror="alert(1)">'), '&lt;img src=x onerror=&quot;alert(1)&quot;&gt;');
  assert.equal(escapeHtml("O'Neil & Sons"), 'O&#039;Neil &amp; Sons');
});

test('initials takes the first two words', () => {
  assert.equal(initials('San Antonio Vipers'), 'SA');
  assert.equal(initials('Jeward'), 'J');
  assert.equal(initials(''), '?');
});

test('money and dates use Philippine formatting', () => {
  assert.equal(formatMoney(1500), '₱1,500');
  assert.equal(formatDate('2026-05-18T13:00:00+08:00'), 'May 18, 2026');
  assert.equal(formatTime('2026-05-18T13:00:00+08:00'), '1:00 PM');
});

test('statusMeta maps every store status to a label and tone', () => {
  assert.deepEqual(statusMeta('forfeit'), { tone: 'danger', label: 'Forfeit', icon: 'shield-x' });
  assert.deepEqual(statusMeta('registration-open'), {
    tone: 'success',
    label: 'Registration open',
    icon: 'unlock',
  });
  assert.deepEqual(statusMeta('unknown'), { tone: 'slate', label: 'Unknown', icon: 'circle' });
});
