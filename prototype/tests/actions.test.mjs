import test from 'node:test';
import assert from 'node:assert/strict';
import { createStore } from '../assets/js/store.js';
import { memoryStorage } from './helpers.mjs';

function makeStore() {
  return createStore({ storage: memoryStorage() });
}

function roster(count = 8) {
  return Array.from({ length: count }, (_, index) => ({
    name: `Player ${index + 1}`,
    number: index + 1,
    position: 'Point Guard (PG)',
    age: 16,
    guardianConsent: true,
    documentStatus: 'verified',
  }));
}

function registration(overrides = {}) {
  return {
    name: 'Pasig Panthers',
    shortName: 'PNT',
    city: 'Pasig City',
    coach: 'Marcus Rivera',
    players: roster(),
    payment: { method: 'GCash', ref: 'GCASH-7778888' },
    ...overrides,
  };
}

function bareSeed() {
  return {
    seedVersion: 'task-4-custom-seed',
    role: 'organizer',
    currentUserId: 'custom-organizer',
    users: [
      {
        id: 'custom-organizer',
        name: 'Custom Organizer',
        email: 'organizer@example.test',
        role: 'organizer',
      },
    ],
    tournaments: [
      {
        id: 'custom-tournament',
        name: 'Custom Cup',
        division: 'Under 15 (U15)',
        status: 'registration-open',
        registrationOpen: true,
        seedLocked: false,
        fee: 1000,
      },
    ],
    teams: [],
    matches: [],
    announcements: [],
    audit: [],
  };
}

test('organizer creates a tournament and writes audit', () => {
  const store = makeStore();
  const id = store.actions.createTournament({
    name: 'Navotas Night League',
    division: 'Under 16 (U16)',
    startDate: '2026-08-01',
    endDate: '2026-08-09',
    venue: 'Navotas Sports Complex',
    slots: 8,
    format: 'single-elimination',
    courts: ['Court A', 'Court B'],
    slotMinutes: 25,
    restMinutes: 10,
    fee: 1200,
    description: 'Night league for barangay teams.',
  });
  assert.equal(id, 'tournament-4');
  const created = store.select.tournament(id);
  assert.equal(created.status, 'registration-open');
  assert.equal(created.registrationOpen, true);
  assert.equal(created.courts.length, 2);
  assert.equal(store.select.tournaments().length, 4);
  assert.equal(store.select.audit({ entityType: 'tournament' })[0].action, 'tournament.create');
});

test('createTournament rejects invalid dates and slots', () => {
  const store = makeStore();
  assert.throws(
    () => store.actions.createTournament({
      name: 'Bad Dates', division: 'U15', startDate: '2026-08-09', endDate: '2026-08-01',
      venue: 'Gym', slots: 8, courts: ['Court A'],
    }),
    (error) => error.code === 'VALIDATION' && error.field === 'endDate'
  );
  assert.throws(
    () => store.actions.createTournament({
      name: 'Bad Slots', division: 'U15', startDate: '2026-08-01', endDate: '2026-08-09',
      venue: 'Gym', slots: 12, courts: ['Court A'],
    }),
    (error) => error.code === 'VALIDATION' && error.field === 'slots'
  );
});

test('coach registers a team as pending with a pending payment', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  const id = store.actions.registerTeam('tournament-2', registration());
  const team = store.select.team(id);
  assert.equal(team.status, 'pending');
  assert.equal(team.tournamentId, 'tournament-2');
  assert.equal(team.players.length, 8);
  assert.equal(team.payment.status, 'pending');
  assert.equal(team.payment.ref, 'GCASH-7778888');
  assert.equal(store.select.teams('tournament-2').length, 9);
  assert.equal(store.select.audit({ entityType: 'team' })[0].action, 'team.register');
});

test('registerTeam rejects duplicates, closed registration, bad ages, and duplicate jerseys', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  store.actions.registerTeam('tournament-2', registration());
  assert.throws(
    () => store.actions.registerTeam('tournament-2', registration()),
    (error) => error.code === 'DUPLICATE_TEAM' && error.field === 'name'
  );
  assert.throws(
    () => store.actions.registerTeam('tournament-1', registration({ name: 'New Team' })),
    (error) => error.code === 'REGISTRATION_CLOSED'
  );
  assert.throws(
    () => store.actions.registerTeam('tournament-2', registration({
      name: 'Too Old', players: roster().map((player, index) => index === 0 ? { ...player, age: 18 } : player),
    })),
    (error) => error.code === 'VALIDATION' && error.field === 'age'
  );
  assert.throws(
    () => store.actions.registerTeam('tournament-2', registration({
      name: 'Bad Numbers', players: roster().map((player, index) => index === 1 ? { ...player, number: 1 } : player),
    })),
    (error) => error.code === 'DUPLICATE_NUMBER' && error.field === 'number'
  );
});

test('organizer approves and rejects teams; coach cannot review', () => {
  const store = makeStore();
  const approved = store.actions.reviewTeam('team-open-7', 'approve');
  assert.equal(approved.status, 'approved');
  assert.ok(approved.eligibility.verifiedAt);
  assert.equal(store.select.audit({ entityType: 'team' })[0].action, 'team.approve');
  assert.throws(
    () => store.actions.reviewTeam('team-open-8', 'reject', ''),
    (error) => error.code === 'VALIDATION' && error.field === 'reason'
  );
  const rejected = store.actions.reviewTeam('team-open-8', 'reject', 'Incomplete documents.');
  assert.equal(rejected.status, 'rejected');
  assert.equal(rejected.rejectionReason, 'Incomplete documents.');
  store.actions.setRole('coach');
  assert.throws(
    () => store.actions.reviewTeam('team-open-7', 'approve'),
    (error) => error.code === 'FORBIDDEN'
  );
});

test('verifyPayment updates the team payment and audit', () => {
  const store = makeStore();
  const payment = store.actions.verifyPayment('team-open-3', {
    method: 'GCash', ref: 'GCASH-9990001', amount: 1500,
  });
  assert.equal(payment.status, 'paid');
  assert.equal(payment.ref, 'GCASH-9990001');
  assert.equal(store.select.team('team-open-3').payment.status, 'paid');
  assert.equal(store.select.audit({ entityType: 'team' })[0].action, 'payment.verify');
  assert.throws(
    () => store.actions.verifyPayment('team-open-4', { method: 'GCash', ref: 'x' }),
    (error) => error.code === 'VALIDATION' && error.field === 'payment.ref'
  );
});

test('publishAnnouncement adds a pinned item and updates read state', () => {
  const store = makeStore();
  const id = store.actions.publishAnnouncement({
    tournamentId: 'tournament-1',
    title: 'Semifinal gate opens at 2 PM',
    body: 'Players and coaches enter through the east gate with their accreditation.',
    category: 'venue',
    audience: 'coaches',
    pinned: true,
  });
  const announcement = store.select.announcements('tournament-1').find((item) => item.id === id);
  assert.equal(announcement.pinned, true);
  assert.equal(announcement.audience, 'coaches');
  assert.equal(store.select.notifications().length, 0);
  assert.equal(store.select.audit({ entityType: 'announcement' })[0].action, 'announcement.publish');
  store.actions.setRole('coach');
  assert.throws(
    () => store.actions.publishAnnouncement({
      tournamentId: 'tournament-1', title: 'Nope', body: 'Coaches cannot publish this.',
      category: 'general', audience: 'all',
    }),
    (error) => error.code === 'FORBIDDEN'
  );
});

test('coach edits a pending roster but cannot edit a verified roster', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  const teamId = store.actions.registerTeam('tournament-2', registration());
  const player = store.actions.addPlayer(teamId, {
    name: 'Bench Guard', number: 9, position: 'Shooting Guard (SG)',
    age: 16, guardianConsent: true, documentStatus: 'pending',
  });
  assert.equal(store.select.team(teamId).players.length, 9);
  assert.equal(player.number, 9);
  assert.throws(
    () => store.actions.addPlayer(teamId, {
      name: 'Duplicate', number: 9, position: 'Center (C)',
      age: 16, guardianConsent: true, documentStatus: 'pending',
    }),
    (error) => error.code === 'DUPLICATE_NUMBER'
  );
  store.actions.updatePlayer(teamId, player.id, {
    name: 'Bench Guard', number: 10, position: 'Shooting Guard (SG)',
    age: 16, guardianConsent: true, documentStatus: 'verified',
  });
  assert.equal(store.select.team(teamId).players.find((item) => item.id === player.id).number, 10);
  store.actions.removePlayer(teamId, player.id);
  assert.equal(store.select.team(teamId).players.length, 8);
  assert.throws(
    () => store.actions.addPlayer('team-1', {
      name: 'Locked', number: 55, position: 'Center (C)',
      age: 15, guardianConsent: true, documentStatus: 'verified',
    }),
    (error) => error.code === 'ROSTER_LOCKED'
  );
});

test('coach submits a payment reference for the own team', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  const teamId = store.actions.registerTeam('tournament-2', registration());
  const payment = store.actions.submitPayment(teamId, { method: 'Maya', ref: 'MAYA-5556667' });
  assert.equal(payment.status, 'pending');
  assert.equal(payment.ref, 'MAYA-5556667');
  assert.equal(store.select.audit({ entityType: 'team' })[0].action, 'payment.submit');
});

test('registerTeam marks pending documents as incomplete eligibility', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  const pendingRoster = roster().map((player) => ({ ...player, documentStatus: 'pending' }));
  const teamId = store.actions.registerTeam('tournament-2', registration({
    name: 'Pending Docs',
    players: pendingRoster,
  }));
  const team = store.select.team(teamId);
  assert.equal(team.eligibility.documentsComplete, false);
  assert.equal(
    team.eligibility.consentComplete,
    team.players.every((player) => player.guardianConsent)
  );
});

test('registerTeam marks fully verified rosters as complete eligibility', () => {
  const store = makeStore();
  store.actions.setRole('coach');
  const teamId = store.actions.registerTeam('tournament-2', registration());
  const team = store.select.team(teamId);
  assert.equal(team.eligibility.documentsComplete, true);
  assert.equal(
    team.eligibility.consentComplete,
    team.players.every((player) => player.guardianConsent)
  );
});

test('announcement read-state actions initialize meta on custom seeds', () => {
  const seenStore = createStore({ storage: memoryStorage(), seed: bareSeed() });
  seenStore.actions.markAnnouncementsSeen();
  assert.ok(seenStore.getState().meta.lastSeenAnnouncementsAt);

  const publishStore = createStore({ storage: memoryStorage(), seed: bareSeed() });
  publishStore.actions.publishAnnouncement({
    tournamentId: 'custom-tournament',
    title: 'Custom seed notice',
    body: 'This custom seed has no meta object.',
    category: 'general',
    audience: 'all',
  });
  assert.ok(publishStore.getState().meta.lastSeenAnnouncementsAt);
});
