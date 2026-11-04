import test from 'node:test';
import assert from 'node:assert/strict';
import { createStore, SCHEMA_VERSION, STORAGE_KEY, StoreError } from '../assets/js/store.js';
import { SEED } from '../assets/js/seed.js';
import { memoryStorage } from './helpers.mjs';

function makeStore(initial) {
  return createStore({ storage: memoryStorage(initial) });
}

test('loads the seeded store and exposes the organizer', () => {
  const store = makeStore();
  const state = store.getState();
  assert.equal(state.schemaVersion, SCHEMA_VERSION);
  assert.equal(store.getRole(), 'organizer');
  assert.equal(store.getCurrentUser().name, 'Jeward Mahinay');
  assert.equal(store.select.tournaments().length, 3);
});

test('persists the store under hoopflow_db', () => {
  const storage = memoryStorage();
  createStore({ storage });
  const raw = storage.getItem(STORAGE_KEY);
  assert.ok(raw);
  assert.equal(JSON.parse(raw).schemaVersion, SCHEMA_VERSION);
});

test('reseeds when the schema version is stale', () => {
  const storage = memoryStorage({
    [STORAGE_KEY]: JSON.stringify({ schemaVersion: 1, seedVersion: 'old', tournaments: [] }),
  });
  const store = createStore({ storage });
  assert.equal(store.getState().schemaVersion, SCHEMA_VERSION);
  assert.equal(store.getState().tournaments.length, 3);
});

test('reset restores the exact seed', () => {
  const store = makeStore();
  const state = store.getState();
  state.tournaments[0].name = 'Mutated';
  store.actions.reset();
  assert.equal(store.getState().tournaments[0].name, SEED.tournaments[0].name);
});

test('setRole switches the current user and persists', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  assert.equal(store.getRole(), 'coach');
  assert.equal(store.getCurrentUser().email, 'coach.marcus@hoopflow.ph');
  assert.throws(() => store.actions.setRole('referee'), (error) => error instanceof StoreError && error.code === 'INVALID_ROLE');
});

test('permissions are role-based', () => {
  const store = makeStore();
  assert.equal(store.can('team.approve'), true);
  assert.equal(store.canRole(['organizer', 'coach']), true);
  assert.equal(store.canRole(['coach']), false);
  store.actions.setRole('coach');
  assert.equal(store.can('score.save'), false);
  assert.equal(store.can('team.approve'), false);
  store.actions.setRole('fan');
  assert.equal(store.can('score.save'), false);
  assert.equal(store.can('public.view'), true);
});

test('custom seed versions survive reloads and reset', () => {
  const storage = memoryStorage();
  const customSeed = {
    seedVersion: 'custom-2026-10-30',
    role: 'organizer',
    currentUserId: 'custom-organizer',
    users: [
      { id: 'custom-organizer', name: 'Custom Organizer', email: 'organizer@example.test', role: 'organizer' },
      { id: 'custom-coach', name: 'Custom Coach', email: 'coach@example.test', role: 'coach' },
    ],
    tournaments: [{ id: 'custom-tournament', name: 'Custom Cup' }],
    teams: [],
    matches: [],
    announcements: [],
    audit: [],
  };

  const first = createStore({ storage, seed: customSeed });
  first.actions.setRole('coach');

  const second = createStore({ storage, seed: customSeed });
  assert.equal(second.getRole(), 'coach');
  assert.equal(second.getState().schemaVersion, SCHEMA_VERSION);
  assert.equal(second.getState().seedVersion, 'custom-2026-10-30');

  second.actions.reset();
  const third = createStore({ storage, seed: customSeed });
  assert.equal(third.getRole(), 'organizer');
  assert.equal(third.getState().schemaVersion, SCHEMA_VERSION);
  assert.equal(third.getState().seedVersion, 'custom-2026-10-30');
});

test('selectors are tournament-scoped', () => {
  const store = makeStore();
  const activeTeams = store.select.teams('tournament-1');
  const openTeams = store.select.teams('tournament-2');
  assert.equal(activeTeams.length, 16);
  assert.equal(openTeams.length, 8);
  assert.equal(store.select.teams('tournament-2', { status: 'pending' }).length, 2);
  assert.equal(store.select.matches('tournament-1').length, 15);
  assert.equal(store.select.matches('tournament-2').length, 0);
  assert.equal(store.select.match('qf-1').roundLabel, 'Quarterfinals');
  assert.equal(store.select.team('team-1').name, 'San Antonio Vipers');
});
