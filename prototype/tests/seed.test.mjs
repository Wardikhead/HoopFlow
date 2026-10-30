import test from 'node:test';
import assert from 'node:assert/strict';
import { SEED, SEED_VERSION } from '../assets/js/seed.js';

test('seed has the versioned schema and three tournaments', () => {
  assert.equal(SEED_VERSION, '2026-10-30');
  assert.equal(SEED.schemaVersion, 2);
  assert.equal(SEED.seedVersion, SEED_VERSION);
  assert.equal(SEED.tournaments.length, 3);
});

test('active cup has 16 registrations, 15 eligible, and one bye', () => {
  const active = SEED.tournaments.find((t) => t.id === 'tournament-1');
  const teams = SEED.teams.filter((t) => t.tournamentId === active.id);
  const approved = teams.filter((t) => t.status === 'approved');
  const rejected = teams.filter((t) => t.status === 'rejected');
  assert.equal(teams.length, 16);
  assert.equal(approved.length, 15);
  assert.equal(rejected.length, 1);
  assert.equal(SEED.matches.filter((m) => m.tournamentId === active.id && m.isBye).length, 1);
});

test('active cup has one forfeit and one corrected score', () => {
  const matches = SEED.matches.filter((m) => m.tournamentId === 'tournament-1');
  assert.equal(matches.filter((m) => m.status === 'forfeit').length, 1);
  assert.equal(matches.filter((m) => Boolean(m.correction)).length, 1);
  assert.equal(matches.length, 15);
});

test('open cup has a non-empty review queue and no bracket', () => {
  const teams = SEED.teams.filter((t) => t.tournamentId === 'tournament-2');
  assert.equal(teams.filter((t) => t.status === 'pending').length, 2);
  assert.equal(SEED.matches.filter((m) => m.tournamentId === 'tournament-2').length, 0);
});

test('CDO has eight approved teams and no bracket', () => {
  const teams = SEED.teams.filter((t) => t.tournamentId === 'tournament-3');
  assert.equal(teams.length, 8);
  assert.equal(teams.every((t) => t.status === 'approved'), true);
  assert.equal(SEED.matches.filter((m) => m.tournamentId === 'tournament-3').length, 0);
});

test('seed has four role users and audit history', () => {
  assert.deepEqual(
    SEED.users.map((u) => u.role).sort(),
    ['coach', 'fan', 'organizer', 'scorekeeper']
  );
  assert.ok(SEED.audit.length >= 6);
  assert.ok(SEED.announcements.length >= 4);
});
