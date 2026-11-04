import test from 'node:test';
import assert from 'node:assert/strict';
import { createStore } from '../assets/js/store.js';
import { memoryStorage } from './helpers.mjs';

function registration() {
  return {
    name: 'San Antonio Vipers U17',
    shortName: 'V17',
    city: 'Pasig City',
    coach: 'Marcus Rivera',
    avatarColor: '#ea580c',
    players: Array.from({ length: 8 }, (_, index) => ({
      name: `Demo Player ${index + 1}`,
      number: index + 1,
      position: 'Point Guard (PG)',
      age: 15,
      guardianConsent: true,
      documentStatus: 'verified',
    })),
    payment: { method: 'GCash', ref: 'GCASH-9090909' },
  };
}

test('the ten-step demo script stays consistent end to end', () => {
  const store = createStore({ storage: memoryStorage() });

  // 1. Organizer dashboard baseline.
  const kpis = store.select.kpis('tournament-1');
  assert.equal(kpis.approved, 15);
  assert.equal(store.getState().teams.filter((team) => team.status === 'pending').length, 2);
  const beforePayments = store.select.paymentSummary('tournament-1');
  assert.equal(beforePayments.collected, 19500);
  assert.equal(beforePayments.outstanding, 3000);
  assert.equal(beforePayments.pendingCount, 2);

  // 2. Approve a pending open-cup team.
  store.actions.reviewTeam('team-open-7', 'approve');
  assert.equal(store.select.team('team-open-7').status, 'approved');
  assert.equal(store.getState().teams.filter((team) => team.status === 'pending').length, 1);

  // 3. Verify a GCash payment in the active cup.
  store.actions.verifyPayment('team-6', {
    method: 'GCash',
    ref: 'GCASH-7788991',
    amount: 1500,
  });
  const afterPayments = store.select.paymentSummary('tournament-1');
  assert.equal(afterPayments.collected, 21000);
  assert.equal(afterPayments.outstanding, 1500);
  assert.equal(afterPayments.pendingCount, 1);

  // 4. Record qf-3; the winner advances and standings update.
  const scored = store.actions.saveScore('qf-3', 71, 65);
  assert.equal(scored.status, 'final');
  assert.equal(scored.winnerId, 'team-2');
  assert.equal(store.select.match('sf-2').team1Id, 'team-2');
  const knights = store.select.standings('tournament-1').find((row) => row.teamId === 'team-2');
  assert.equal(knights.wins, 2);
  assert.equal(knights.losses, 0);

  // 6. Coach registers for the open cup.
  store.actions.setRole('coach');
  const newTeamId = store.actions.registerTeam('tournament-2', registration());
  assert.equal(store.select.team(newTeamId).status, 'pending');
  assert.equal(store.select.team(newTeamId).players.length, 8);
  assert.equal(store.select.team(newTeamId).payment.ref, 'GCASH-9090909');

  // 7. Organizer approves the new registration.
  store.actions.setRole('organizer');
  store.actions.reviewTeam(newTeamId, 'approve');
  assert.equal(store.select.team(newTeamId).status, 'approved');

  // 8. Non-organizer cannot score or approve.
  store.actions.setRole('coach');
  assert.equal(store.can('score.save'), false);
  assert.equal(store.can('team.approve'), false);
  assert.throws(
    () => store.actions.reviewTeam(newTeamId, 'approve'),
    (error) => error.code === 'FORBIDDEN'
  );
  store.actions.setRole('fan');
  assert.equal(store.can('score.save'), false);

  // 9. Fan is read-only public.
  store.actions.setRole('fan');
  assert.equal(store.can('public.view'), true);
  assert.equal(store.can('score.save'), false);
  assert.equal(store.can('team.approve'), false);

  // 10. The run writes five audit entries; reset restores the seed exactly.
  assert.equal(store.getState().audit.length, 13);
  store.actions.reset();
  assert.equal(store.getRole(), 'organizer');
  assert.equal(store.getState().audit.length, 8);
  assert.equal(store.select.team('team-open-7').status, 'pending');
  assert.equal(store.select.match('qf-3').status, 'scheduled');
  assert.equal(store.select.paymentSummary('tournament-1').collected, 19500);
  assert.equal(store.getState().teams.filter((team) => team.status === 'pending').length, 2);
});
