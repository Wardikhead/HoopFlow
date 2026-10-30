export const SEED_VERSION = '2026-10-30';

const ACTIVE = 'tournament-1';
const OPEN = 'tournament-2';
const CDO = 'tournament-3';

const POSITIONS = [
  'Point Guard (PG)',
  'Shooting Guard (SG)',
  'Small Forward (SF)',
  'Power Forward (PF)',
  'Center (C)',
];

const FIRST_NAMES = [
  'Jayson', 'Kobe', 'Carl', 'Kai', 'June Mar', 'Gabe', 'CJ', 'Dwight',
  'Calvin', 'RR', 'Marc', 'Prince', 'Andre', 'Jolo', 'Troy', 'Luis',
  'Paolo', 'Nico', 'Miguel', 'Rafael',
];
const LAST_NAMES = [
  'Castro', 'Ramos', 'Cruz', 'Gomez', 'Fajardo', 'Diaz', 'Santos', 'Reyes',
  'Bautista', 'Villanueva', 'Macaraeg', 'Torres', 'Aquino', 'Navarro',
  'Mendoza', 'Del Rosario', 'Garcia', 'Lim', 'Tan', 'Pascual',
];

function makePlayers(teamId, teamIndex, divisionMax, count, pending = false) {
  return Array.from({ length: count }, (_, index) => ({
    id: `${teamId}-player-${index + 1}`,
    name: `${FIRST_NAMES[(teamIndex * 7 + index) % FIRST_NAMES.length]} ${LAST_NAMES[(teamIndex * 3 + index) % LAST_NAMES.length]}`,
    number: index + 1,
    position: POSITIONS[index % POSITIONS.length],
    age: divisionMax - (index % 3),
    guardianConsent: pending ? index !== count - 1 : true,
    documentStatus: pending && index === count - 1 ? 'missing' : pending ? 'pending' : 'verified',
  }));
}

function makeTeam({
  id, tournamentId, name, shortName, coach, city, color, seed,
  status = 'approved', paymentStatus = 'paid', paymentRef = '',
  divisionMax, pending = false, submittedAt, rejectionReason = '',
}) {
  return {
    id,
    tournamentId,
    name,
    shortName,
    coach,
    city,
    avatarColor: color,
    seed,
    status,
    rejectionReason,
    submittedAt,
    payment: {
      status: paymentStatus,
      method: paymentStatus === 'paid' ? 'GCash' : '',
      ref: paymentRef,
      amount: tournamentId === CDO ? 1000 : 1500,
      verifiedAt: paymentStatus === 'paid' ? submittedAt : null,
    },
    eligibility: {
      documentsComplete: !pending,
      consentComplete: !pending,
      verifiedAt: status === 'approved' ? submittedAt : null,
    },
    players: makePlayers(id, seed, divisionMax, 10, pending),
  };
}

const activeTeams = [
  ['San Antonio Vipers', 'VPR', 'Marcus Rivera', 'Pasig City', '#ea580c', 'paid', 'GCASH-9823412'],
  ['Kapitolyo Knights', 'KNT', 'Danilo Santos', 'Pasig City', '#2563eb', 'paid', 'MAYA-4412093'],
  ['Mandaluyong Tigers', 'TGR', 'Ramon Bautista', 'Mandaluyong City', '#d97706', 'paid', 'CASH-REC-001'],
  ['Ortigas Raptors', 'RPT', 'Kenneth Cruz', 'Pasig City', '#7c3aed', 'paid', 'BANK-BDO-771'],
  ['Ugong Blue Hawks', 'HWK', 'Allan Gomez', 'Pasig City', '#0284c7', 'paid', 'GCASH-1123498'],
  ['Maybunga Titans', 'TTN', 'Eric Villanueva', 'Pasig City', '#059669', 'pending', ''],
  ['Rosario Strikers', 'STR', 'Joel Macaraeg', 'Pasig City', '#dc2626', 'paid', 'GCASH-5512034'],
  ['Pinagbuhatan Warriors', 'WRR', 'Ferdinand Reyes', 'Pasig City', '#475569', 'pending', ''],
  ['Bagong Ilog Falcons', 'BIF', 'Noel Aquino', 'Pasig City', '#0891b2', 'paid', 'MAYA-7731120'],
  ['Kalawaan Comets', 'KLC', 'Edwin Navarro', 'Pasig City', '#9333ea', 'paid', 'GCASH-4409821'],
  ['Manggahan Jets', 'MGJ', 'Rodel Mendoza', 'Pasig City', '#ca8a04', 'paid', 'CASH-REC-002'],
  ['Santolan Saints', 'STL', 'Arnel Garcia', 'Pasig City', '#16a34a', 'paid', 'GCASH-3321908'],
  ['Dela Paz Dragons', 'DPZ', 'Marlon Lim', 'Pasig City', '#e11d48', 'paid', 'BANK-BPI-204'],
  ['Caniogan Cardinals', 'CNG', 'Boyet Tan', 'Pasig City', '#be123c', 'paid', 'GCASH-9988123'],
  ['Sumilang Spartans', 'SMS', 'Dennis Pascual', 'Pasig City', '#334155', 'paid', 'MAYA-1200345'],
];

const teams = [];
activeTeams.forEach((row, index) => {
  const [name, shortName, coach, city, color, paymentStatus, paymentRef] = row;
  teams.push(makeTeam({
    id: `team-${index + 1}`,
    tournamentId: ACTIVE,
    name,
    shortName,
    coach,
    city,
    color,
    seed: index + 1,
    paymentStatus,
    paymentRef,
    divisionMax: 15,
    submittedAt: '2026-05-02T09:00:00+08:00',
  }));
});
teams.push(makeTeam({
  id: 'team-16',
  tournamentId: ACTIVE,
  name: 'Napico Nuggets',
  shortName: 'NPC',
  coach: 'Vic Sotto',
  city: 'Pasig City',
  color: '#64748b',
  seed: 16,
  status: 'rejected',
  rejectionReason: 'Withdrew before seeding.',
  paymentStatus: 'pending',
  divisionMax: 15,
  submittedAt: '2026-05-02T09:00:00+08:00',
}));

const openTeams = [
  ['Pasig Pirates', 'PSG', 'Alvin Cruz', 'Pasig City', '#0e7490', 'approved', 'paid', 'GCASH-1010101'],
  ['Kapitolyo Kings', 'KNG', 'Bong Reyes', 'Pasig City', '#1d4ed8', 'approved', 'paid', 'MAYA-2020202'],
  ['Ugong United', 'UGU', 'Carlo Santos', 'Pasig City', '#047857', 'approved', 'pending', ''],
  ['Rosario Rockets', 'RCK', 'Dante Lim', 'Pasig City', '#b91c1c', 'approved', 'paid', 'CASH-REC-003'],
  ['Maybunga Mavericks', 'MVR', 'Elmer Tan', 'Pasig City', '#7e22ce', 'approved', 'pending', ''],
  ['Pinagbuhatan Pilots', 'PLT', 'Francisco Diaz', 'Pasig City', '#475569', 'approved', 'paid', 'GCASH-3030303'],
  ['Bagong Ilog Bears', 'BRS', 'Glen Aquino', 'Pasig City', '#a16207', 'pending', 'pending', ''],
  ['Kalawaan Kestrels', 'KST', 'Hector Navarro', 'Pasig City', '#0f766e', 'pending', 'pending', ''],
];
openTeams.forEach((row, index) => {
  const [name, shortName, coach, city, color, status, paymentStatus, paymentRef] = row;
  teams.push(makeTeam({
    id: `team-open-${index + 1}`,
    tournamentId: OPEN,
    name,
    shortName,
    coach,
    city,
    color,
    seed: index + 1,
    status,
    paymentStatus,
    paymentRef,
    divisionMax: 17,
    pending: status === 'pending',
    submittedAt: '2026-05-20T09:00:00+08:00',
  }));
});

const cdoTeams = [
  ['CDO Stags', 'CDS', 'Rico Bautista', 'Cagayan de Oro', '#b45309', '#f59e0b'],
  ['Cagayan Cobras', 'CAG', 'Nestor Lim', 'Cagayan de Oro', '#1e3a8a', '#3b82f6'],
  ['Bukidnon Braves', 'BKB', 'Oscar Reyes', 'Malaybalay', '#166534', '#22c55e'],
  ['Misamis Mariners', 'MSM', 'Paeng Cruz', 'Oroquieta', '#0e7490', '#06b6d4'],
  ['Iligan Ironhawks', 'ILG', 'Quincy Tan', 'Iligan', '#7f1d1d', '#ef4444'],
  ['Gingoog Giants', 'GGG', 'Rene Santos', 'Gingoog', '#4c1d95', '#8b5cf6'],
  ['Valencia Vipers', 'VLV', 'Sandy Gomez', 'Valencia', '#9a3412', '#fb923c'],
  ['Malaybalay Monarchs', 'MBM', 'Tito Diaz', 'Malaybalay', '#334155', '#64748b'],
];
cdoTeams.forEach((row, index) => {
  const [name, shortName, coach, city, color] = row;
  teams.push(makeTeam({
    id: `team-cdo-${index + 1}`,
    tournamentId: CDO,
    name,
    shortName,
    coach,
    city,
    color,
    seed: index + 1,
    divisionMax: 14,
    submittedAt: '2026-06-01T09:00:00+08:00',
  }));
});

const teamId = (name) => teams.find((t) => t.name === name).id;

function makeMatch({
  id, round, roundLabel, bracketSlot, team1Id, team2Id,
  score1 = null, score2 = null, winnerId = null, status = 'scheduled',
  courtId = null, scheduledAt = null, nextMatchId = null, nextSlot = null,
  isBye = false, correction = null, boxScore = null,
}) {
  return {
    id,
    tournamentId: ACTIVE,
    round,
    roundLabel,
    bracketSlot,
    team1Id,
    team2Id,
    score1,
    score2,
    winnerId,
    status,
    courtId,
    scheduledAt,
    nextMatchId,
    nextSlot,
    isBye,
    correction,
    boxScore: boxScore || { team1: [], team2: [] },
  };
}

function splitPoints(total, count = 10) {
  const base = Math.floor(total / count);
  const remainder = total % count;
  return Array.from({ length: count }, (_, index) => base + (index < remainder ? 1 : 0));
}

function makeBoxScore(team, total) {
  const points = splitPoints(total, team.players.length);
  return team.players.map((player, index) => ({
    playerId: player.id,
    points: points[index],
    assists: (index * 3 + 1) % 7,
    rebounds: (index * 2 + 2) % 9,
  }));
}

const vipers = teams.find((t) => t.name === 'San Antonio Vipers');
const warriors = teams.find((t) => t.name === 'Pinagbuhatan Warriors');
const dragons = teams.find((t) => t.name === 'Dela Paz Dragons');
const hawks = teams.find((t) => t.name === 'Ugong Blue Hawks');
const knights = teams.find((t) => t.name === 'Kapitolyo Knights');
const comets = teams.find((t) => t.name === 'Kalawaan Comets');
const tigers = teams.find((t) => t.name === 'Mandaluyong Tigers');
const titans = teams.find((t) => t.name === 'Maybunga Titans');

const matches = [
  makeMatch({ id: 'r16-1', round: '16', roundLabel: 'Round of 16', bracketSlot: 1, team1Id: vipers.id, team2Id: null, status: 'final', winnerId: vipers.id, isBye: true, nextMatchId: 'qf-1', nextSlot: 1 }),
  makeMatch({ id: 'r16-2', round: '16', roundLabel: 'Round of 16', bracketSlot: 2, team1Id: warriors.id, team2Id: teamId('Bagong Ilog Falcons'), score1: 66, score2: 61, winnerId: warriors.id, status: 'final', courtId: 'court-1', scheduledAt: '2026-05-16T09:00:00+08:00', nextMatchId: 'qf-1', nextSlot: 2 }),
  makeMatch({ id: 'r16-3', round: '16', roundLabel: 'Round of 16', bracketSlot: 3, team1Id: teamId('Ortigas Raptors'), team2Id: dragons.id, score1: 69, score2: 72, winnerId: dragons.id, status: 'final', courtId: 'court-2', scheduledAt: '2026-05-16T09:00:00+08:00', nextMatchId: 'qf-2', nextSlot: 1 }),
  makeMatch({ id: 'r16-4', round: '16', roundLabel: 'Round of 16', bracketSlot: 4, team1Id: hawks.id, team2Id: teamId('Santolan Saints'), score1: 70, score2: 64, winnerId: hawks.id, status: 'final', courtId: 'court-3', scheduledAt: '2026-05-16T09:00:00+08:00', nextMatchId: 'qf-2', nextSlot: 2 }),
  makeMatch({ id: 'r16-5', round: '16', roundLabel: 'Round of 16', bracketSlot: 5, team1Id: knights.id, team2Id: teamId('Sumilang Spartans'), score1: 77, score2: 70, winnerId: knights.id, status: 'final', courtId: 'court-4', scheduledAt: '2026-05-16T09:00:00+08:00', nextMatchId: 'qf-3', nextSlot: 1 }),
  makeMatch({ id: 'r16-6', round: '16', roundLabel: 'Round of 16', bracketSlot: 6, team1Id: teamId('Rosario Strikers'), team2Id: comets.id, score1: 66, score2: 68, winnerId: comets.id, status: 'final', courtId: 'court-1', scheduledAt: '2026-05-16T09:30:00+08:00', nextMatchId: 'qf-3', nextSlot: 2 }),
  makeMatch({ id: 'r16-7', round: '16', roundLabel: 'Round of 16', bracketSlot: 7, team1Id: tigers.id, team2Id: teamId('Caniogan Cardinals'), score1: 20, score2: 0, winnerId: tigers.id, status: 'forfeit', courtId: 'court-2', scheduledAt: '2026-05-16T09:30:00+08:00', nextMatchId: 'qf-4', nextSlot: 1 }),
  makeMatch({ id: 'r16-8', round: '16', roundLabel: 'Round of 16', bracketSlot: 8, team1Id: titans.id, team2Id: teamId('Manggahan Jets'), score1: 65, score2: 60, winnerId: titans.id, status: 'final', courtId: 'court-3', scheduledAt: '2026-05-16T09:30:00+08:00', nextMatchId: 'qf-4', nextSlot: 2 }),
  makeMatch({ id: 'qf-1', round: '8', roundLabel: 'Quarterfinals', bracketSlot: 1, team1Id: vipers.id, team2Id: warriors.id, score1: 78, score2: 70, winnerId: vipers.id, status: 'final', courtId: 'court-1', scheduledAt: '2026-05-18T13:00:00+08:00', nextMatchId: 'sf-1', nextSlot: 1, correction: { reason: 'Shot-clock correction after referee review.', previousScore1: 74, previousScore2: 70, correctedAt: '2026-05-18T16:20:00+08:00', correctedBy: 'user-organizer' }, boxScore: { team1: makeBoxScore(vipers, 78), team2: makeBoxScore(warriors, 70) } }),
  makeMatch({ id: 'qf-2', round: '8', roundLabel: 'Quarterfinals', bracketSlot: 2, team1Id: dragons.id, team2Id: hawks.id, score1: 64, score2: 69, winnerId: hawks.id, status: 'final', courtId: 'court-2', scheduledAt: '2026-05-18T13:00:00+08:00', nextMatchId: 'sf-1', nextSlot: 2, boxScore: { team1: makeBoxScore(dragons, 64), team2: makeBoxScore(hawks, 69) } }),
  makeMatch({ id: 'qf-3', round: '8', roundLabel: 'Quarterfinals', bracketSlot: 3, team1Id: knights.id, team2Id: comets.id, status: 'scheduled', courtId: 'court-1', scheduledAt: '2026-05-18T14:00:00+08:00', nextMatchId: 'sf-2', nextSlot: 1 }),
  makeMatch({ id: 'qf-4', round: '8', roundLabel: 'Quarterfinals', bracketSlot: 4, team1Id: tigers.id, team2Id: titans.id, status: 'scheduled', courtId: 'court-2', scheduledAt: '2026-05-18T14:00:00+08:00', nextMatchId: 'sf-2', nextSlot: 2 }),
  makeMatch({ id: 'sf-1', round: '4', roundLabel: 'Semifinals', bracketSlot: 1, team1Id: vipers.id, team2Id: hawks.id, status: 'scheduled', courtId: 'court-1', scheduledAt: '2026-05-20T15:00:00+08:00', nextMatchId: 'final-1', nextSlot: 1 }),
  makeMatch({ id: 'sf-2', round: '4', roundLabel: 'Semifinals', bracketSlot: 2, team1Id: null, team2Id: null, status: 'scheduled', courtId: 'court-1', scheduledAt: '2026-05-20T16:00:00+08:00', nextMatchId: 'final-1', nextSlot: 2 }),
  makeMatch({ id: 'final-1', round: '2', roundLabel: 'Championship Final', bracketSlot: 1, team1Id: null, team2Id: null, status: 'scheduled', courtId: 'court-1', scheduledAt: '2026-05-22T16:00:00+08:00' }),
];

const announcements = [
  {
    id: 'announcement-1',
    tournamentId: ACTIVE,
    title: 'Championship week moves to Court 1',
    body: 'All semifinal and final games will be played on Court 1. Gate opens 45 minutes before tip-off.',
    category: 'venue',
    audience: 'all',
    pinned: true,
    author: 'Tournament Committee',
    createdAt: '2026-05-18T18:00:00+08:00',
  },
  {
    id: 'announcement-2',
    tournamentId: ACTIVE,
    title: 'Roster verification deadline is May 19',
    body: 'Coaches must upload the final PSA birth certificate and guardian consent for every player before the semifinals.',
    category: 'eligibility',
    audience: 'coaches',
    pinned: false,
    author: 'Eligibility Committee',
    createdAt: '2026-05-17T10:00:00+08:00',
  },
  {
    id: 'announcement-3',
    tournamentId: OPEN,
    title: 'Pasig Youth Hoopfest registration closes June 1',
    body: 'Submit your team registration, twelve-player roster, and GCash reference before the deadline.',
    category: 'schedule',
    audience: 'all',
    pinned: false,
    author: 'Tournament Committee',
    createdAt: '2026-05-20T09:00:00+08:00',
  },
  {
    id: 'announcement-4',
    tournamentId: CDO,
    title: 'Court assignments posted for the CDO Classic',
    body: 'The preliminary court assignments are available on the tournament page. Brackets will be generated after the seeding meeting.',
    category: 'venue',
    audience: 'all',
    pinned: false,
    author: 'Venue Operations',
    createdAt: '2026-06-02T11:00:00+08:00',
  },
];

const users = [
  { id: 'user-organizer', name: 'Jeward Mahinay', email: 'organizer@hoopflow.ph', role: 'organizer', organization: 'HoopFlow Tournament Operations', teamId: null },
  { id: 'user-scorekeeper', name: 'Rusty Japos', email: 'scorekeeper@hoopflow.ph', role: 'scorekeeper', organization: 'HoopFlow Tournament Operations', teamId: null },
  { id: 'user-coach', name: 'Coach Marcus Rivera', email: 'coach.marcus@hoopflow.ph', role: 'coach', organization: 'Barangay San Antonio Youth Club', teamId: vipers.id },
  { id: 'user-fan', name: 'Juan Dela Cruz', email: 'fan.juan@hoopflow.ph', role: 'fan', organization: 'Barangay San Antonio', teamId: null },
];

const audit = [
  { id: 'audit-1', at: '2026-05-18T16:20:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'score.correct', entityType: 'match', entityId: 'qf-1', summary: 'Corrected QF1 from 74-70 to 78-70', changes: { previousScore1: 74, previousScore2: 70, score1: 78, score2: 70 } },
  { id: 'audit-2', at: '2026-05-18T15:40:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'payment.verify', entityType: 'team', entityId: vipers.id, summary: 'Verified GCash payment for San Antonio Vipers', changes: { status: 'paid', ref: 'GCASH-9823412' } },
  { id: 'audit-3', at: '2026-05-16T08:00:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'bracket.generate', entityType: 'tournament', entityId: ACTIVE, summary: 'Generated the 16-slot single-elimination bracket', changes: { teams: 15, byes: 1 } },
  { id: 'audit-4', at: '2026-05-15T17:00:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'schedule.generate', entityType: 'tournament', entityId: ACTIVE, summary: 'Generated the court schedule with four courts', changes: { courts: 4, slotMinutes: 30, restMinutes: 15 } },
  { id: 'audit-5', at: '2026-05-14T09:30:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'team.approve', entityType: 'team', entityId: 'team-5', summary: 'Approved Ugong Blue Hawks', changes: { status: 'approved' } },
  { id: 'audit-6', at: '2026-05-13T14:00:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'announcement.publish', entityType: 'announcement', entityId: 'announcement-1', summary: 'Published championship week venue update', changes: { pinned: true } },
  { id: 'audit-7', at: '2026-05-01T10:00:00+08:00', actorId: 'user-organizer', actorRole: 'organizer', action: 'tournament.create', entityType: 'tournament', entityId: ACTIVE, summary: 'Created HoopFlow Summer Cup', changes: { slots: 16, division: 'Under 15 (U15)' } },
  { id: 'audit-8', at: '2026-05-02T09:00:00+08:00', actorId: 'user-coach', actorRole: 'coach', action: 'team.register', entityType: 'team', entityId: vipers.id, summary: 'Registered San Antonio Vipers', changes: { players: 10 } },
];

export const SEED = {
  schemaVersion: 2,
  seedVersion: SEED_VERSION,
  currentUserId: 'user-organizer',
  role: 'organizer',
  users,
  tournaments: [
    {
      id: ACTIVE,
      name: 'HoopFlow Summer Cup',
      division: 'Under 15 (U15)',
      format: 'single-elimination',
      slots: 16,
      status: 'active',
      startDate: '2026-05-15',
      endDate: '2026-05-22',
      venue: 'San Antonio Central Sports Complex',
      courts: [
        { id: 'court-1', name: 'Court 1 (Main Gym)' },
        { id: 'court-2', name: 'Court 2 (Covered)' },
        { id: 'court-3', name: 'Court 3' },
        { id: 'court-4', name: 'Court 4' },
      ],
      slotMinutes: 30,
      restMinutes: 15,
      fee: 1500,
      description: 'Annual inter-barangay grassroots youth tournament with accredited SBP referees.',
      registrationOpen: false,
      seedLocked: true,
    },
    {
      id: OPEN,
      name: 'Pasig Youth Hoopfest',
      division: 'Under 17 (U17)',
      format: 'single-elimination',
      slots: 16,
      status: 'registration-open',
      startDate: '2026-06-01',
      endDate: '2026-06-10',
      venue: 'Ynares Sports Arena, Pasig',
      courts: [
        { id: 'court-1', name: 'Court A' },
        { id: 'court-2', name: 'Court B' },
        { id: 'court-3', name: 'Court C' },
      ],
      slotMinutes: 30,
      restMinutes: 15,
      fee: 1500,
      description: 'Pre-season invitational for competitive secondary school teams.',
      registrationOpen: true,
      seedLocked: false,
    },
    {
      id: CDO,
      name: 'CDO Grassroots Classic',
      division: 'Under 14 (U14)',
      format: 'single-elimination',
      slots: 8,
      status: 'draft',
      startDate: '2026-07-10',
      endDate: '2026-07-18',
      venue: 'CDO City Coliseum',
      courts: [
        { id: 'court-1', name: 'Main Court' },
        { id: 'court-2', name: 'Side Court' },
      ],
      slotMinutes: 30,
      restMinutes: 15,
      fee: 1000,
      description: 'Regional developmental tournament focused on fundamentals and sportsmanship.',
      registrationOpen: false,
      seedLocked: false,
    },
  ],
  teams,
  matches,
  announcements,
  audit,
  meta: {
    lastSeenAnnouncementsAt: '2026-05-17T12:00:00+08:00',
  },
};
