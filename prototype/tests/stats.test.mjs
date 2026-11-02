import test from 'node:test';
import assert from 'node:assert/strict';
import { SEED } from '../assets/js/seed.js';
import { boxScoreRows, boxScoreTotals, sortBoxScore } from '../assets/js/stats.js';

const match = SEED.matches.find((item) => item.id === 'qf-1');
const team = SEED.teams.find((item) => item.id === match.team1Id);

test('boxScoreRows joins box score entries to the roster', () => {
  const rows = boxScoreRows(match, team);
  assert.equal(rows.length, team.players.length);
  assert.equal(rows[0].playerId, team.players[0].id);
  assert.equal(rows[0].name, team.players[0].name);
  assert.equal(rows[0].number, team.players[0].number);
  assert.equal(rows.reduce((sum, row) => sum + row.points, 0), 78);
});

test('boxScoreRows falls back when a player left the roster', () => {
  const stale = {
    ...match,
    boxScore: {
      team1: [{ playerId: 'player-missing', points: 4, assists: 1, rebounds: 2 }],
      team2: [],
    },
  };
  const rows = boxScoreRows(stale, team);
  assert.equal(rows.length, 1);
  assert.equal(rows[0].name, 'Unknown player');
  assert.equal(rows[0].number, null);
});

test('sortBoxScore sorts by points by default and supports ascending', () => {
  const rows = [
    { name: 'Bautista', number: 12, points: 4, assists: 2, rebounds: 1 },
    { name: 'Aquino', number: 7, points: 9, assists: 1, rebounds: 5 },
    { name: 'Cruz', number: 3, points: 4, assists: 6, rebounds: 4 },
  ];
  assert.deepEqual(sortBoxScore(rows).map((row) => row.name), ['Aquino', 'Bautista', 'Cruz']);
  assert.deepEqual(sortBoxScore(rows, 'number', 'asc').map((row) => row.name), ['Cruz', 'Aquino', 'Bautista']);
  assert.deepEqual(sortBoxScore(rows, 'rebounds', 'desc').map((row) => row.name), ['Aquino', 'Cruz', 'Bautista']);
});

test('boxScoreTotals sums the visible rows', () => {
  const rows = boxScoreRows(match, team);
  const totals = boxScoreTotals(rows);
  assert.equal(totals.points, 78);
  assert.equal(totals.assists, rows.reduce((sum, row) => sum + row.assists, 0));
  assert.equal(totals.rebounds, rows.reduce((sum, row) => sum + row.rebounds, 0));
});