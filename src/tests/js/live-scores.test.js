import assert from 'node:assert/strict';
import test from 'node:test';
import { normalizeSnapshot, pollInterval } from '../../resources/js/ui/live-scores.js';

test('normalizeSnapshot unwraps the data envelope', () => {
    const snapshot = normalizeSnapshot({
        data: {
            server_time: '2026-11-17T10:00:00+08:00',
            matches: [{ id: 1, home_score: 21 }],
        },
    });

    assert.equal(snapshot.serverTime, '2026-11-17T10:00:00+08:00');
    assert.equal(snapshot.matches.length, 1);
    assert.equal(snapshot.matches[0].home_score, 21);
});

test('normalizeSnapshot falls back to an empty list for invalid payloads', () => {
    assert.deepEqual(normalizeSnapshot(null), { matches: [], serverTime: null });
    assert.deepEqual(normalizeSnapshot({ data: { matches: 'nope' } }), { matches: [], serverTime: null });
});

test('pollInterval keeps a valid interval and clamps short ones', () => {
    assert.equal(pollInterval('30000'), 30000);
    assert.equal(pollInterval(1000), 20000);
    assert.equal(pollInterval('not-a-number'), 20000);
    assert.equal(pollInterval(undefined), 20000);
});
