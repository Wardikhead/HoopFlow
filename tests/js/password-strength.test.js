import assert from 'node:assert/strict';
import test from 'node:test';
import { evaluatePassword } from '../../resources/js/ui/password-strength.js';

test('an empty password has no label and the empty hint', () => {
    const result = evaluatePassword('');

    assert.equal(result.score, 0);
    assert.equal(result.label, '');
    assert.equal(result.met, 0);
    assert.equal(result.missing.length, 5);
    assert.match(result.hint, /at least 8 characters/i);
});

test('a lowercase-only password scores very weak', () => {
    const result = evaluatePassword('abcdefg');

    assert.equal(result.checks.length, false);
    assert.equal(result.checks.lowercase, true);
    assert.equal(result.checks.uppercase, false);
    assert.equal(result.score, 1);
    assert.equal(result.label, 'Very weak');
});

test('three classes score fair', () => {
    const result = evaluatePassword('Abcdefgh');

    assert.equal(result.met, 3);
    assert.equal(result.score, 3);
    assert.equal(result.label, 'Fair');
});

test('four classes score strong', () => {
    const result = evaluatePassword('Abcdefg1');

    assert.equal(result.met, 4);
    assert.equal(result.score, 4);
    assert.equal(result.label, 'Strong');
});

test('all five classes label the password very strong', () => {
    const result = evaluatePassword('Abcdefg1!');

    assert.equal(result.met, 5);
    assert.equal(result.score, 4);
    assert.equal(result.label, 'Very strong');
});

test('twelve characters add one score point without claiming all classes', () => {
    const result = evaluatePassword('Abcdefghijkl');

    assert.equal(result.met, 3);
    assert.equal(result.score, 4);
    assert.equal(result.label, 'Strong');
});

test('missing checks stay in display order', () => {
    const result = evaluatePassword('abc');

    assert.deepEqual(result.missing, [
        'At least 8 characters',
        'An uppercase letter',
        'A number',
        'A symbol',
    ]);
});

test('the hint lists the missing checks by name', () => {
    const result = evaluatePassword('abcdefgh');

    assert.match(result.hint, /uppercase letter/i);
    assert.match(result.hint, /number/i);
    assert.match(result.hint, /symbol/i);
});
