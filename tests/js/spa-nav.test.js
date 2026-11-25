// Front-end tests for Spa nav.test.

import assert from 'node:assert/strict';
import test from 'node:test';
import { shouldInterceptLink } from '../../resources/js/ui/spa-nav.js';

const current = 'https://hoopflow.test/dashboard';

const base = {
    href: '/tournaments',
    target: '',
    download: false,
    role: '',
    hasWireNavigate: false,
    hasClickHandler: false,
    hasDynamicHref: false,
    isOptedOut: false,
    shell: true,
};

test('accepts a plain internal link inside the shell', () => {
    assert.equal(shouldInterceptLink(base, current), true);
});

test('accepts a relative link with a query string', () => {
    assert.equal(shouldInterceptLink({ ...base, href: '/standings?id=3' }, current), true);
});

test('rejects an external origin', () => {
    assert.equal(shouldInterceptLink({ ...base, href: 'https://example.com/tournaments' }, current), false);
});

test('rejects non-http protocols', () => {
    assert.equal(shouldInterceptLink({ ...base, href: 'mailto:ops@hoopflow.test' }, current), false);
    assert.equal(shouldInterceptLink({ ...base, href: 'javascript:void(0)' }, current), false);
});

test('rejects empty and hash-only hrefs', () => {
    assert.equal(shouldInterceptLink({ ...base, href: '' }, current), false);
    assert.equal(shouldInterceptLink({ ...base, href: '#' }, current), false);
});

test('rejects a same-page hash link', () => {
    assert.equal(shouldInterceptLink({ ...base, href: '/dashboard#top' }, current), false);
});

test('rejects the current URL', () => {
    assert.equal(shouldInterceptLink({ ...base, href: current }, current), false);
});

test('rejects links Livewire already owns', () => {
    assert.equal(shouldInterceptLink({ ...base, hasWireNavigate: true }, current), false);
});

test('rejects links owned by a click handler', () => {
    assert.equal(shouldInterceptLink({ ...base, hasClickHandler: true }, current), false);
});

test('rejects links with a dynamically bound href', () => {
    assert.equal(shouldInterceptLink({ ...base, hasDynamicHref: true }, current), false);
});

test('rejects opted-out links', () => {
    assert.equal(shouldInterceptLink({ ...base, isOptedOut: true }, current), false);
});

test('rejects links outside the authenticated shell', () => {
    assert.equal(shouldInterceptLink({ ...base, shell: false }, current), false);
});

test('rejects downloads, new tabs, and button roles', () => {
    assert.equal(shouldInterceptLink({ ...base, download: true }, current), false);
    assert.equal(shouldInterceptLink({ ...base, target: '_blank' }, current), false);
    assert.equal(shouldInterceptLink({ ...base, role: 'button' }, current), false);
});
