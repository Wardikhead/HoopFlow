import test from 'node:test';
import assert from 'node:assert/strict';
import { createStore } from '../assets/js/store.js';
import { SEED } from '../assets/js/seed.js';
import { memoryStorage } from './helpers.mjs';

function makeStore() {
  return createStore({ storage: memoryStorage() });
}

test('generateBracket builds the CDO bracket and locks seeds', () => {
  const store = makeStore();
  const result = store.actions.generateBracket('tournament-3');
  assert.equal(result.matches, 7);
  assert.equal(result.byes, 0);
  assert.equal(store.select.matches('tournament-3').length, 7);
  assert.equal(store.select.tournament('tournament-3').seedLocked, true);
  assert.equal(store.select.tournament('tournament-3').status, 'upcoming');
  const bracket = store.select.bracket('tournament-3');
  assert.equal(bracket.rounds.length, 3);
  assert.equal(bracket.rounds[0].label, 'Quarterfinals');
  assert.equal(bracket.champion, null);
  assert.equal(store.select.audit({ entityType: 'tournament' })[0].action, 'bracket.generate');
});

test('generateBracket refuses to disturb a bracket with results', () => {
  const store = makeStore();
  assert.throws(
    () => store.actions.generateBracket('tournament-1'),
    (error) => error.code === 'BRACKET_LOCKED'
  );
});

test('generateBracket allows a 16-slot bracket with only byes to be scheduled and regenerated', () => {
  const seed = structuredClone(SEED);
  seed.teams = seed.teams.map((team) =>
    team.tournamentId === 'tournament-2' ? { ...team, status: 'approved' } : team
  );
  const store = createStore({ storage: memoryStorage(), seed });
  const first = store.actions.generateBracket('tournament-2');
  assert.equal(first.matches, 15);
  assert.equal(first.byes, 8);
  const plan = store.planSchedule('tournament-2');
  assert.equal(plan.assignments.length, 7);
  const regenerated = store.actions.generateBracket('tournament-2');
  assert.equal(regenerated.matches, 15);
  assert.equal(regenerated.byes, 8);
});

test('the 15-team active cup bracket has one bye and can be scheduled', () => {
  const seed = structuredClone(SEED);
  seed.matches = [];
  seed.teams = seed.teams.filter(
    (team) => team.tournamentId !== 'tournament-1' || team.status === 'approved'
  );
  seed.tournaments.find((tournament) => tournament.id === 'tournament-1').seedLocked = false;
  const store = createStore({ storage: memoryStorage(), seed });
  const result = store.actions.generateBracket('tournament-1');
  assert.equal(result.byes, 1);
  const plan = store.planSchedule('tournament-1');
  assert.equal(plan.assignments.length, 14);
});

test('generateBracket rejects tournaments below half the bracket slots', () => {
  const store = makeStore();
  assert.throws(
    () => store.actions.generateBracket('tournament-2'),
    (error) => error.code === 'NOT_ENOUGH_TEAMS' && error.field === 'teams'
  );
});

test('planSchedule and generateSchedule assign courts and times', () => {
  const store = makeStore();
  store.actions.generateBracket('tournament-3');
  const plan = store.planSchedule('tournament-3');
  assert.equal(plan.assignments.length, 7);
  assert.equal(new Set(plan.assignments.map((item) => `${item.courtId}|${item.scheduledAt}`)).size, 7);
  assert.ok(plan.assignments.every((item) => item.scheduledAt.endsWith('+08:00')));
  const count = store.actions.generateSchedule('tournament-3');
  assert.equal(count, 7);
  const scheduled = store.select.matches('tournament-3');
  assert.ok(scheduled.every((match) => match.courtId && match.scheduledAt));
  assert.equal(store.select.audit({ entityType: 'tournament' })[0].action, 'schedule.generate');
});

test('generateSchedule requires a bracket', () => {
  const store = makeStore();
  assert.throws(
    () => store.actions.generateSchedule('tournament-2'),
    (error) => error.code === 'NO_BRACKET'
  );
});

test('saveScore advances the winner and updates standings', () => {
  const store = makeStore();
  store.actions.saveScore('qf-3', 70, 66);
  const match = store.select.match('qf-3');
  assert.equal(match.status, 'final');
  assert.equal(match.score1, 70);
  assert.equal(match.score2, 66);
  assert.equal(match.winnerId, 'team-2');
  assert.equal(store.select.match('sf-2').team1Id, 'team-2');
  const knights = store.select.standings('tournament-1').find((row) => row.teamId === 'team-2');
  assert.equal(knights.wins, 2);
  assert.equal(knights.losses, 0);
  assert.equal(store.select.audit({ entityType: 'match' })[0].action, 'score.save');
});

test('saveScore rejects ties, unknown teams, and repeated results', () => {
  const store = makeStore();
  assert.throws(
    () => store.actions.saveScore('qf-4', 60, 60),
    (error) => error.code === 'TIE'
  );
  assert.throws(
    () => store.actions.saveScore('sf-2', 60, 50),
    (error) => error.code === 'TEAMS_PENDING'
  );
  assert.throws(
    () => store.actions.saveScore('qf-1', 80, 70),
    (error) => error.code === 'MATCH_FINAL'
  );
});

test('saveScore records a FIBA forfeit as 20-0', () => {
  const store = makeStore();
  store.actions.saveScore('qf-4', 0, 0, { forfeitTeam: 1 });
  const match = store.select.match('qf-4');
  assert.equal(match.status, 'forfeit');
  assert.equal(match.score1, 0);
  assert.equal(match.score2, 20);
  assert.equal(match.winnerId, 'team-6');
  assert.equal(store.select.match('sf-2').team2Id, 'team-6');
});

test('correctScore records a reason and re-propagates a changed winner', () => {
  const store = makeStore();
  const corrected = store.actions.correctScore('qf-2', 70, 64, 'Clock review');
  assert.equal(corrected.correction.previousScore1, 64);
  assert.equal(corrected.correction.previousScore2, 69);
  assert.equal(corrected.winnerId, 'team-13');
  assert.equal(store.select.match('sf-1').team2Id, 'team-13');
  assert.equal(store.select.match('sf-1').status, 'scheduled');
  assert.equal(store.select.audit({ entityType: 'match' })[0].action, 'score.correct');
  assert.throws(
    () => store.actions.correctScore('qf-2', 70, 64, ''),
    (error) => error.code === 'VALIDATION' && error.field === 'reason'
  );
});

test('a completed CDO bracket produces a champion', () => {
  const store = makeStore();
  store.actions.generateBracket('tournament-3');
  const byRound = (round) => store.select
    .matches('tournament-3')
    .filter((match) => match.round === round)
    .sort((a, b) => a.bracketSlot - b.bracketSlot);
  byRound('8').forEach((match, index) => {
    store.actions.saveScore(match.id, 60 + index, 50);
  });
  byRound('4').forEach((match, index) => {
    store.actions.saveScore(match.id, 55, 50 + index);
  });
  const final = byRound('2')[0];
  store.actions.saveScore(final.id, 70, 65);
  const bracket = store.select.bracket('tournament-3');
  assert.ok(bracket.champion);
  assert.equal(bracket.champion.id, store.select.match(final.id).winnerId);
});

test('kpis and payment summary are computed from teams and matches', () => {
  const store = makeStore();
  const kpis = store.select.kpis('tournament-1');
  assert.equal(kpis.registered, 16);
  assert.equal(kpis.approved, 15);
  assert.equal(kpis.pending, 0);
  assert.equal(kpis.rejected, 1);
  assert.equal(kpis.courts, 4);
  assert.equal(kpis.matchesPlayed, 10);
  assert.equal(kpis.matchesScheduled, 5);
  assert.equal(kpis.collected, 19500);
  assert.equal(kpis.expected, 22500);
  assert.equal(kpis.outstanding, 3000);
  assert.equal(kpis.collectionRate, 87);
  const summary = store.select.paymentSummary('tournament-1');
  assert.equal(summary.paidCount, 13);
  assert.equal(summary.pendingCount, 2);
});

test('standings sort by wins, differential, and seed', () => {
  const store = makeStore();
  const rows = store.select.standings('tournament-1');
  assert.equal(rows.length, 15);
  assert.equal(rows[0].teamId, 'team-5');
  assert.equal(rows[0].wins, 2);
  assert.equal(rows.filter((row) => row.qualified).length, 8);
  assert.equal(rows[1].teamId, 'team-3');
  assert.equal(rows[0].rank, 1);
});

test('notifications follow the last-seen timestamp', () => {
  const store = makeStore();
  assert.equal(store.select.notifications().length, 3);
  store.actions.markAnnouncementsSeen();
  assert.equal(store.select.notifications().length, 0);
});

test('notifications respect the announcement audience', () => {
  const seed = structuredClone(SEED);
  seed.meta.lastSeenAnnouncementsAt = '2026-05-17T09:00:00+08:00';
  const store = createStore({ storage: memoryStorage(), seed });
  assert.equal(store.select.notifications().length, 4);
  store.actions.setRole('fan');
  assert.equal(store.select.notifications().length, 3);
  assert.equal(
    store.select.notifications().every((item) => item.audience === 'all'),
    true
  );
  store.actions.setRole('coach');
  assert.equal(store.select.notifications().length, 4);
});
