import { SEED, SEED_VERSION, POSITIONS } from './seed.js';

export const STORAGE_KEY = 'hoopflow_db';
export const SCHEMA_VERSION = 2;

export class StoreError extends Error {
  constructor(code, message, field = null) {
    super(message);
    this.name = 'StoreError';
    this.code = code;
    this.field = field;
  }
}

const ROLES = ['organizer', 'scorekeeper', 'coach', 'fan'];
const PERMISSIONS = {
  organizer: new Set(['*']),
  scorekeeper: new Set(['score.save', 'score.correct', 'stats.view']),
  coach: new Set(['team.register', 'team.edit', 'payment.submit', 'team.view.own']),
  fan: new Set(['public.view']),
};

const PAYMENT_METHODS = ['GCash', 'Maya', 'Bank transfer', 'Cash'];
const ANNOUNCEMENT_CATEGORIES = ['schedule', 'venue', 'eligibility', 'general'];
const ANNOUNCEMENT_AUDIENCES = ['all', 'coaches', 'organizers'];

export const AUDIENCE_BY_ROLE = {
  organizer: ['all', 'coaches', 'organizers'],
  scorekeeper: ['all', 'organizers'],
  coach: ['all', 'coaches'],
  fan: ['all'],
};

const ROUND_LABELS = {
  2: 'Championship Final',
  4: 'Semifinals',
  8: 'Quarterfinals',
  16: 'Round of 16',
  32: 'Round of 32',
};
const ROUND_START_HOUR = { 32: 8, 16: 9, 8: 13, 4: 15, 2: 16 };

function seedOrder(size) {
  let order = [1, 2];
  while (order.length < size) {
    const complement = order.length * 2 + 1;
    order = order.flatMap((seed) => [seed, complement - seed]);
  }
  return order;
}

function pad(value) {
  return String(value).padStart(2, '0');
}

function buildBoxScore(team, total) {
  const count = team.players.length || 1;
  const base = Math.floor(total / count);
  const remainder = total % count;
  return team.players.map((player, index) => ({
    playerId: player.id,
    points: base + (index < remainder ? 1 : 0),
    assists: (index * 3 + 1) % 7,
    rebounds: (index * 2 + 2) % 9,
  }));
}

function requireText(value, field, label, min = 1) {
  if (typeof value !== 'string' || value.trim().length < min) {
    throw new StoreError('VALIDATION', `${label} is required.`, field);
  }
  return value.trim();
}

function requireDate(value, field, label) {
  if (
    typeof value !== 'string' ||
    !/^\d{4}-\d{2}-\d{2}$/.test(value) ||
    Number.isNaN(Date.parse(`${value}T00:00:00Z`))
  ) {
    throw new StoreError('VALIDATION', `${label} must be a valid date.`, field);
  }
  return value;
}

function requireChoice(value, field, label, choices) {
  if (!choices.includes(value)) {
    throw new StoreError('VALIDATION', `${label} is invalid.`, field);
  }
  return value;
}

function requirePaymentRef(value, field = 'payment.ref') {
  if (typeof value !== 'string' || !/^[A-Za-z0-9-]{6,24}$/.test(value.trim())) {
    throw new StoreError(
      'VALIDATION',
      'Enter a valid payment reference (letters, numbers, and dashes).',
      field
    );
  }
  return value.trim().toUpperCase();
}

function divisionLimit(division) {
  const match = String(division).match(/(\d+)/);
  return match ? Number(match[1]) : 99;
}

function validatePlayer(input, { division, numbers, ignoreNumber = null }) {
  const name = requireText(input.name, 'name', 'Player name', 2);
  const number = Number(input.number);
  if (!Number.isInteger(number) || number < 1 || number > 99) {
    throw new StoreError('VALIDATION', 'Jersey number must be between 1 and 99.', 'number');
  }
  if (number !== ignoreNumber && numbers.includes(number)) {
    throw new StoreError('DUPLICATE_NUMBER', `Jersey number ${number} is already taken.`, 'number');
  }
  if (!POSITIONS.includes(input.position)) {
    throw new StoreError('VALIDATION', 'Choose a valid position.', 'position');
  }
  const age = Number(input.age);
  const limit = divisionLimit(division);
  if (!Number.isInteger(age) || age < 8 || age > limit) {
    throw new StoreError('VALIDATION', `Age must be between 8 and ${limit} for ${division}.`, 'age');
  }
  if (input.guardianConsent !== true) {
    throw new StoreError('VALIDATION', 'Guardian consent is required.', 'guardianConsent');
  }
  const documentStatus = input.documentStatus || 'pending';
  if (!['verified', 'pending', 'missing'].includes(documentStatus)) {
    throw new StoreError('VALIDATION', 'Choose a document status.', 'documentStatus');
  }
  return { name, number, position: input.position, age, guardianConsent: true, documentStatus };
}

const clone = (value) => JSON.parse(JSON.stringify(value));
const isoNow = () => new Date().toISOString();

function nextId(prefix, items) {
  const highest = items.reduce((max, item) => {
    const match = String(item.id).match(new RegExp(`^${prefix}-(\\d+)$`));
    return match ? Math.max(max, Number(match[1])) : max;
  }, 0);
  return `${prefix}-${highest + 1}`;
}

function findTournament(state, tournamentId) {
  const tournament = state.tournaments.find((item) => item.id === tournamentId);
  if (!tournament) throw new StoreError('NOT_FOUND', 'Tournament not found.');
  return tournament;
}

function findTeam(state, teamId) {
  const team = state.teams.find((item) => item.id === teamId);
  if (!team) throw new StoreError('NOT_FOUND', 'Team not found.');
  return team;
}

function findMatch(state, matchId) {
  const match = state.matches.find((item) => item.id === matchId);
  if (!match) throw new StoreError('NOT_FOUND', 'Match not found.');
  return match;
}

function currentUser(state) {
  return state.users.find((user) => user.id === state.currentUserId) || state.users[0];
}

function requirePermission(state, permission) {
  const role = state.role;
  const allowed = PERMISSIONS[role];
  if (!allowed || (!allowed.has('*') && !allowed.has(permission))) {
    throw new StoreError('FORBIDDEN', `The ${role} role cannot perform this action.`);
  }
}

function appendAudit(state, { action, entityType, entityId, summary, changes = null }) {
  const actor = currentUser(state);
  state.audit.unshift({
    id: nextId('audit', state.audit),
    at: isoNow(),
    actorId: actor.id,
    actorRole: actor.role,
    action,
    entityType,
    entityId,
    summary,
    changes,
  });
}

export function createStore({ storage = globalThis.localStorage, seed = SEED } = {}) {
  if (!storage) throw new Error('createStore requires a storage adapter.');

  const expectedSeedVersion = seed.seedVersion ?? SEED_VERSION;

  let state = load();
  const listeners = new Set();

  function freshState() {
    const next = clone(seed);
    next.schemaVersion = SCHEMA_VERSION;
    next.seedVersion = expectedSeedVersion;
    return next;
  }

  function load() {
    const raw = storage.getItem(STORAGE_KEY);
    if (raw) {
      try {
        const parsed = JSON.parse(raw);
        if (parsed.schemaVersion === SCHEMA_VERSION && parsed.seedVersion === expectedSeedVersion) {
          return parsed;
        }
      } catch {
        // Fall through to a clean seed.
      }
    }
    const fresh = freshState();
    storage.setItem(STORAGE_KEY, JSON.stringify(fresh));
    return fresh;
  }

  function commit(next, event = 'update') {
    state = next;
    storage.setItem(STORAGE_KEY, JSON.stringify(state));
    listeners.forEach((listener) => listener(state, event));
    if (typeof document !== 'undefined') {
      document.dispatchEvent(new CustomEvent('hf:store-changed', { detail: { event } }));
    }
  }

  function subscribe(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
  }

  function getState() {
    return clone(state);
  }

  function getRole() {
    return state.role;
  }

  function getCurrentUser() {
    return clone(currentUser(state));
  }

  function can(permission) {
    const allowed = PERMISSIONS[state.role];
    return Boolean(allowed && (allowed.has('*') || allowed.has(permission)));
  }

  function canRole(roles) {
    return roles.includes(state.role);
  }

  function reset() {
    commit(freshState(), 'reset');
  }

  function setRole(role) {
    if (!ROLES.includes(role)) {
      throw new StoreError('INVALID_ROLE', `Unknown role: ${role}.`);
    }
    const next = clone(state);
    const user = next.users.find((item) => item.role === role);
    if (!user) throw new StoreError('INVALID_ROLE', `No demo user exists for ${role}.`);
    next.role = role;
    next.currentUserId = user.id;
    commit(next, 'role');
  }

  function coachOwnsTeam(teamId) {
    if (state.role === 'organizer') return true;
    const user = currentUser(state);
    const team = findTeam(state, teamId);
    const coachName = user.name.replace(/^Coach\s+/i, '').trim();
    return team.coach === coachName || team.coach === user.name;
  }

  function createTournament(input = {}) {
    requirePermission(state, 'tournament.create');
    const name = requireText(input.name, 'name', 'Tournament name', 3);
    const division = requireText(input.division, 'division', 'Division', 3);
    const startDate = requireDate(input.startDate, 'startDate', 'Start date');
    const endDate = requireDate(input.endDate, 'endDate', 'End date');
    if (endDate < startDate) {
      throw new StoreError('VALIDATION', 'End date must be on or after the start date.', 'endDate');
    }
    const slots = Number(input.slots);
    if (![8, 16].includes(slots)) {
      throw new StoreError('VALIDATION', 'Slots must be 8 or 16.', 'slots');
    }
    requireChoice(input.format || 'single-elimination', 'format', 'Format', ['single-elimination']);
    const venue = requireText(input.venue, 'venue', 'Venue', 3);
    const courts = Array.isArray(input.courts)
      ? input.courts.map((court, index) => {
          const courtName = typeof court === 'string' ? court : court?.name;
          return {
            id: `court-${index + 1}`,
            name: requireText(courtName, 'courts', 'Court name', 2),
          };
        })
      : [];
    if (courts.length < 1) {
      throw new StoreError('VALIDATION', 'Add at least one court.', 'courts');
    }
    const slotMinutes = Number(input.slotMinutes ?? 30);
    const restMinutes = Number(input.restMinutes ?? 15);
    if (!Number.isInteger(slotMinutes) || slotMinutes < 10) {
      throw new StoreError('VALIDATION', 'Slot length must be at least 10 minutes.', 'slotMinutes');
    }
    if (!Number.isInteger(restMinutes) || restMinutes < 0) {
      throw new StoreError('VALIDATION', 'Rest must be zero or more minutes.', 'restMinutes');
    }
    const fee = Number(input.fee ?? 0);
    if (!Number.isFinite(fee) || fee < 0) {
      throw new StoreError('VALIDATION', 'Fee must be zero or more.', 'fee');
    }
    const next = clone(state);
    const id = nextId('tournament', next.tournaments);
    next.tournaments.push({
      id,
      name,
      division,
      format: 'single-elimination',
      slots,
      status: 'registration-open',
      startDate,
      endDate,
      venue,
      courts,
      slotMinutes,
      restMinutes,
      fee,
      description: String(input.description || '').trim(),
      registrationOpen: true,
      seedLocked: false,
    });
    appendAudit(next, {
      action: 'tournament.create',
      entityType: 'tournament',
      entityId: id,
      summary: `Created ${name}`,
      changes: { slots, division, fee },
    });
    commit(next, 'tournament.create');
    return id;
  }

  function registerTeam(tournamentId, input = {}) {
    requirePermission(state, 'team.register');
    const tournament = findTournament(state, tournamentId);
    if (!tournament.registrationOpen || tournament.status !== 'registration-open') {
      throw new StoreError('REGISTRATION_CLOSED', 'Registration is not open for this tournament.');
    }
    const name = requireText(input.name, 'name', 'Team name', 3);
    const duplicate = state.teams.some(
      (team) => team.tournamentId === tournamentId && team.name.toLowerCase() === name.toLowerCase()
    );
    if (duplicate) {
      throw new StoreError('DUPLICATE_TEAM', 'A team with that name is already registered.', 'name');
    }
    const shortName = requireText(input.shortName, 'shortName', 'Short name', 2)
      .slice(0, 3)
      .toUpperCase();
    const city = requireText(input.city, 'city', 'City', 2);
    const coach = requireText(
      input.coach || currentUser(state).name.replace(/^Coach\s+/i, ''),
      'coach',
      'Coach name',
      3
    );
    const playersInput = Array.isArray(input.players) ? input.players : [];
    if (playersInput.length < 8 || playersInput.length > 12) {
      throw new StoreError('VALIDATION', 'A roster needs 8 to 12 players.', 'players');
    }
    const numbers = [];
    const players = playersInput.map((player, index) => {
      const clean = validatePlayer(player, { division: tournament.division, numbers });
      if (clean.documentStatus === 'missing') {
        throw new StoreError(
          'VALIDATION',
          `Player ${clean.name} is missing a document.`,
          `players.${index}.documentStatus`
        );
      }
      numbers.push(clean.number);
      return { id: `${tournamentId}-player-${Date.now()}-${index + 1}`, ...clean };
    });
    const method = requireChoice(
      input.payment?.method || 'GCash',
      'payment.method',
      'Payment method',
      PAYMENT_METHODS
    );
    const ref = requirePaymentRef(input.payment?.ref, 'payment.ref');
    const next = clone(state);
    const id = nextId('team', next.teams);
    const submittedAt = isoNow();
    const registeredTeam = {
      id,
      tournamentId,
      name,
      shortName,
      coach,
      city,
      avatarColor: input.avatarColor || '#ea580c',
      seed: null,
      status: 'pending',
      rejectionReason: '',
      submittedAt,
      payment: { status: 'pending', method, ref, amount: tournament.fee, verifiedAt: null },
      eligibility: { documentsComplete: false, consentComplete: false, verifiedAt: null },
      players,
    };
    refreshEligibility(registeredTeam);
    next.teams.push(registeredTeam);
    appendAudit(next, {
      action: 'team.register',
      entityType: 'team',
      entityId: id,
      summary: `Registered ${name} for ${tournament.name}`,
      changes: { players: players.length, ref },
    });
    commit(next, 'team.register');
    return id;
  }

  function reviewTeam(teamId, decision, reason = '') {
    requirePermission(state, 'team.approve');
    const team = findTeam(state, teamId);
    const tournament = findTournament(state, team.tournamentId);
    if (tournament.seedLocked) {
      throw new StoreError('SEED_LOCKED', 'Seeds are locked for this tournament.');
    }
    const action = String(decision || '').toLowerCase();
    if (!['approve', 'reject'].includes(action)) {
      throw new StoreError('VALIDATION', 'Decision must be approve or reject.', 'decision');
    }
    const next = clone(state);
    const target = findTeam(next, teamId);
    if (action === 'reject') {
      target.status = 'rejected';
      target.rejectionReason = requireText(reason, 'reason', 'Rejection reason', 5);
      target.eligibility.verifiedAt = null;
    } else {
      target.status = 'approved';
      target.rejectionReason = '';
      target.eligibility.verifiedAt = isoNow();
    }
    appendAudit(next, {
      action: `team.${action}`,
      entityType: 'team',
      entityId: teamId,
      summary: `${action === 'approve' ? 'Approved' : 'Rejected'} ${target.name}`,
      changes: { status: target.status },
    });
    commit(next, 'team.review');
    return clone(target);
  }

  function verifyPayment(teamId, payment = {}) {
    requirePermission(state, 'payment.verify');
    const team = findTeam(state, teamId);
    const method = requireChoice(payment.method, 'payment.method', 'Payment method', PAYMENT_METHODS);
    const ref = requirePaymentRef(payment.ref);
    const amount = Number(payment.amount ?? team.payment.amount);
    if (!Number.isFinite(amount) || amount <= 0) {
      throw new StoreError('VALIDATION', 'Amount must be greater than zero.', 'payment.amount');
    }
    const next = clone(state);
    const target = findTeam(next, teamId);
    target.payment = {
      status: payment.status === 'partial' ? 'partial' : 'paid',
      method,
      ref,
      amount,
      verifiedAt: isoNow(),
    };
    appendAudit(next, {
      action: 'payment.verify',
      entityType: 'team',
      entityId: teamId,
      summary: `Verified ${method} payment for ${target.name}`,
      changes: { status: target.payment.status, ref, amount },
    });
    commit(next, 'payment.verify');
    return clone(target.payment);
  }

  function publishAnnouncement(input = {}) {
    requirePermission(state, 'announcement.publish');
    const tournamentId = requireText(input.tournamentId, 'tournamentId', 'Tournament');
    findTournament(state, tournamentId);
    const title = requireText(input.title, 'title', 'Title', 4);
    const body = requireText(input.body, 'body', 'Message', 10);
    const category = requireChoice(input.category, 'category', 'Category', ANNOUNCEMENT_CATEGORIES);
    const audience = requireChoice(
      input.audience || 'all',
      'audience',
      'Audience',
      ANNOUNCEMENT_AUDIENCES
    );
    const next = clone(state);
    const id = nextId('announcement', next.announcements);
    const author = currentUser(state);
    next.announcements.push({
      id,
      tournamentId,
      title,
      body,
      category,
      audience,
      pinned: Boolean(input.pinned),
      author: author.organization || author.name,
      createdAt: isoNow(),
    });
    next.meta = { ...(next.meta || {}), lastSeenAnnouncementsAt: isoNow() };
    appendAudit(next, {
      action: 'announcement.publish',
      entityType: 'announcement',
      entityId: id,
      summary: `Published ${title}`,
      changes: { category, audience, pinned: Boolean(input.pinned) },
    });
    commit(next, 'announcement.publish');
    return id;
  }

  function assertRosterEditable(team) {
    if (team.eligibility.verifiedAt) {
      throw new StoreError('ROSTER_LOCKED', 'The roster is locked after verification.');
    }
    if (!coachOwnsTeam(team.id)) {
      throw new StoreError('FORBIDDEN', 'You can only edit your own team.');
    }
  }

  function refreshEligibility(team) {
    team.eligibility.documentsComplete = team.players.every(
      (player) => player.documentStatus === 'verified'
    );
    team.eligibility.consentComplete = team.players.every((player) => player.guardianConsent);
  }

  function addPlayer(teamId, input = {}) {
    requirePermission(state, 'team.edit');
    const team = findTeam(state, teamId);
    assertRosterEditable(team);
    if (team.players.length >= 12) {
      throw new StoreError('ROSTER_FULL', 'A roster can have at most 12 players.');
    }
    const tournament = findTournament(state, team.tournamentId);
    const clean = validatePlayer(input, {
      division: tournament.division,
      numbers: team.players.map((player) => player.number),
    });
    const next = clone(state);
    const target = findTeam(next, teamId);
    const player = { id: `${teamId}-player-${Date.now()}`, ...clean };
    target.players.push(player);
    refreshEligibility(target);
    appendAudit(next, {
      action: 'team.roster.add',
      entityType: 'team',
      entityId: teamId,
      summary: `Added ${player.name} to ${target.name}`,
      changes: { number: player.number },
    });
    commit(next, 'team.roster');
    return clone(player);
  }

  function updatePlayer(teamId, playerId, input = {}) {
    requirePermission(state, 'team.edit');
    const team = findTeam(state, teamId);
    assertRosterEditable(team);
    const existing = team.players.find((player) => player.id === playerId);
    if (!existing) throw new StoreError('NOT_FOUND', 'Player not found.');
    const tournament = findTournament(state, team.tournamentId);
    const clean = validatePlayer(input, {
      division: tournament.division,
      numbers: team.players.filter((player) => player.id !== playerId).map((player) => player.number),
    });
    const next = clone(state);
    const target = findTeam(next, teamId);
    const player = target.players.find((item) => item.id === playerId);
    Object.assign(player, clean);
    refreshEligibility(target);
    appendAudit(next, {
      action: 'team.roster.update',
      entityType: 'team',
      entityId: teamId,
      summary: `Updated ${player.name} on ${target.name}`,
      changes: { number: player.number, documentStatus: player.documentStatus },
    });
    commit(next, 'team.roster');
    return clone(player);
  }

  function removePlayer(teamId, playerId) {
    requirePermission(state, 'team.edit');
    const team = findTeam(state, teamId);
    assertRosterEditable(team);
    const player = team.players.find((item) => item.id === playerId);
    if (!player) throw new StoreError('NOT_FOUND', 'Player not found.');
    if (team.players.length <= 5) {
      throw new StoreError('ROSTER_TOO_SMALL', 'A team must keep at least five players.');
    }
    const next = clone(state);
    const target = findTeam(next, teamId);
    target.players = target.players.filter((item) => item.id !== playerId);
    refreshEligibility(target);
    appendAudit(next, {
      action: 'team.roster.remove',
      entityType: 'team',
      entityId: teamId,
      summary: `Removed ${player.name} from ${target.name}`,
      changes: { number: player.number },
    });
    commit(next, 'team.roster');
  }

  function submitPayment(teamId, input = {}) {
    requirePermission(state, 'payment.submit');
    const team = findTeam(state, teamId);
    if (!coachOwnsTeam(teamId)) {
      throw new StoreError('FORBIDDEN', 'You can only submit payment for your own team.');
    }
    const method = requireChoice(input.method, 'method', 'Payment method', PAYMENT_METHODS);
    const ref = requirePaymentRef(input.ref, 'ref');
    const next = clone(state);
    const target = findTeam(next, teamId);
    target.payment = {
      status: 'pending',
      method,
      ref,
      amount: findTournament(next, target.tournamentId).fee,
      verifiedAt: null,
    };
    appendAudit(next, {
      action: 'payment.submit',
      entityType: 'team',
      entityId: teamId,
      summary: `Submitted ${method} reference for ${target.name}`,
      changes: { ref },
    });
    commit(next, 'payment.submit');
    return clone(target.payment);
  }

  function markAnnouncementsSeen() {
    const next = clone(state);
    next.meta = { ...(next.meta || {}), lastSeenAnnouncementsAt: isoNow() };
    commit(next, 'announcements.seen');
  }

  function generateBracket(tournamentId) {
    requirePermission(state, 'bracket.generate');
    const tournament = findTournament(state, tournamentId);
    const approved = state.teams.filter(
      (team) => team.tournamentId === tournamentId && team.status === 'approved'
    );
    if (approved.length < 2) {
      throw new StoreError('NOT_ENOUGH_TEAMS', 'At least two approved teams are required.');
    }
    if (approved.length > tournament.slots) {
      throw new StoreError('TOO_MANY_TEAMS', 'There are more approved teams than bracket slots.');
    }
    const hasResults = state.matches.some(
      (match) =>
        match.tournamentId === tournamentId &&
        (match.status === 'final' || match.status === 'forfeit')
    );
    if (hasResults) {
      throw new StoreError('BRACKET_LOCKED', 'This bracket already has recorded results.');
    }
    const size = tournament.slots;
    if (![2, 4, 8, 16, 32].includes(size)) {
      throw new StoreError('VALIDATION', 'Bracket size must be 8 or 16.', 'slots');
    }
    const ordered = [...approved].sort((a, b) => (a.seed ?? 999) - (b.seed ?? 999));
    const orderedSlots = seedOrder(size).map((seedNumber) => ordered[seedNumber - 1] || null);
    const next = clone(state);
    next.matches = next.matches.filter((match) => match.tournamentId !== tournamentId);
    const rounds = [];
    for (let roundSize = size; roundSize >= 2; roundSize /= 2) {
      rounds.push({ round: String(roundSize), roundSize });
    }
    const created = [];
    const byRound = new Map();
    rounds.forEach(({ round, roundSize }, roundIndex) => {
      const nextRound = rounds[roundIndex + 1];
      const count = roundSize / 2;
      const roundMatches = [];
      for (let slot = 1; slot <= count; slot += 1) {
        roundMatches.push({
          id: `${tournamentId}-${round}-${slot}`,
          tournamentId,
          round,
          roundLabel: ROUND_LABELS[roundSize],
          bracketSlot: slot,
          team1Id: null,
          team2Id: null,
          score1: null,
          score2: null,
          winnerId: null,
          status: 'scheduled',
          courtId: null,
          scheduledAt: null,
          nextMatchId: nextRound ? `${tournamentId}-${nextRound.round}-${Math.ceil(slot / 2)}` : null,
          nextSlot: nextRound ? (slot % 2 === 1 ? 1 : 2) : null,
          isBye: false,
          correction: null,
          boxScore: { team1: [], team2: [] },
        });
      }
      byRound.set(round, roundMatches);
      created.push(...roundMatches);
    });
    const firstRound = byRound.get(String(size));
    orderedSlots.forEach((team, index) => {
      const match = firstRound[Math.floor(index / 2)];
      if (index % 2 === 0) match.team1Id = team ? team.id : null;
      else match.team2Id = team ? team.id : null;
    });
    firstRound.forEach((match) => {
      if (match.team1Id && !match.team2Id) {
        match.isBye = true;
        match.status = 'final';
        match.winnerId = match.team1Id;
      }
      if (!match.team1Id && match.team2Id) {
        match.isBye = true;
        match.status = 'final';
        match.winnerId = match.team2Id;
      }
      if (!match.winnerId) return;
      const nextMatch = created.find((item) => item.id === match.nextMatchId);
      if (!nextMatch) return;
      if (match.nextSlot === 1) nextMatch.team1Id = match.winnerId;
      else nextMatch.team2Id = match.winnerId;
    });
    next.matches.push(...created);
    const targetTournament = findTournament(next, tournamentId);
    targetTournament.seedLocked = true;
    if (targetTournament.status === 'draft') targetTournament.status = 'upcoming';
    appendAudit(next, {
      action: 'bracket.generate',
      entityType: 'tournament',
      entityId: tournamentId,
      summary: `Generated a ${size}-slot single-elimination bracket`,
      changes: { teams: approved.length, byes: firstRound.filter((match) => match.isBye).length },
    });
    commit(next, 'bracket.generate');
    return {
      matches: created.length,
      byes: firstRound.filter((match) => match.isBye).length,
    };
  }

  function planSchedule(tournamentId, options = {}) {
    const tournament = findTournament(state, tournamentId);
    const matches = state.matches.filter(
      (match) => match.tournamentId === tournamentId && !match.isBye
    );
    if (!matches.length) {
      throw new StoreError('NO_BRACKET', 'Generate a bracket before scheduling.');
    }
    const hasResults = state.matches.some(
      (match) =>
        match.tournamentId === tournamentId &&
        (match.status === 'final' || match.status === 'forfeit')
    );
    if (hasResults) {
      throw new StoreError('SCHEDULE_LOCKED', 'The schedule is locked because results exist.');
    }
    const courts = Array.isArray(options.courtIds) && options.courtIds.length
      ? tournament.courts.filter((court) => options.courtIds.includes(court.id))
      : tournament.courts;
    if (!courts.length) {
      throw new StoreError('VALIDATION', 'Choose at least one court.', 'courtIds');
    }
    const startDate = options.startDate || tournament.startDate;
    const slotMinutes = Number(options.slotMinutes ?? tournament.slotMinutes);
    const restMinutes = Number(options.restMinutes ?? tournament.restMinutes);
    if (!Number.isInteger(slotMinutes) || slotMinutes < 10) {
      throw new StoreError('VALIDATION', 'Slot length must be at least 10 minutes.', 'slotMinutes');
    }
    const rounds = [...new Set(matches.map((match) => match.round))].sort(
      (a, b) => Number(b) - Number(a)
    );
    const courtSlots = new Set();
    const teamTimes = new Map();
    const assignments = [];
    rounds.forEach((round, roundIndex) => {
      const roundMatches = matches
        .filter((match) => match.round === round)
        .sort((a, b) => a.bracketSlot - b.bracketSlot);
      const date = new Date(`${startDate}T00:00:00Z`);
      date.setUTCDate(date.getUTCDate() + roundIndex);
      const dateLabel = date.toISOString().slice(0, 10);
      const baseHour = ROUND_START_HOUR[Number(round)] ?? 9;
      roundMatches.forEach((match, index) => {
        const wave = Math.floor(index / courts.length);
        const court = courts[index % courts.length];
        const totalMinutes = baseHour * 60 + wave * slotMinutes;
        const scheduledAt = `${dateLabel}T${pad(Math.floor(totalMinutes / 60))}:${pad(
          totalMinutes % 60
        )}:00+08:00`;
        const key = `${court.id}|${scheduledAt}`;
        if (courtSlots.has(key)) {
          throw new StoreError('COURT_CONFLICT', `${court.name} is double-booked at ${scheduledAt}.`);
        }
        courtSlots.add(key);
        [match.team1Id, match.team2Id].filter(Boolean).forEach((teamId) => {
          const times = teamTimes.get(teamId) || [];
          const startMs = Date.parse(scheduledAt);
          const clash = times.some(
            (other) => Math.abs(other - startMs) < (slotMinutes + restMinutes) * 60000
          );
          if (clash) {
            throw new StoreError('REST_VIOLATION', 'A team would play without the required rest.');
          }
          times.push(startMs);
          teamTimes.set(teamId, times);
        });
        assignments.push({ matchId: match.id, courtId: court.id, scheduledAt });
      });
    });
    return { assignments, courts, slotMinutes, restMinutes, startDate };
  }

  function generateSchedule(tournamentId, options = {}) {
    requirePermission(state, 'schedule.generate');
    const plan = planSchedule(tournamentId, options);
    const next = clone(state);
    plan.assignments.forEach(({ matchId, courtId, scheduledAt }) => {
      const match = next.matches.find((item) => item.id === matchId);
      if (match) {
        match.courtId = courtId;
        match.scheduledAt = scheduledAt;
      }
    });
    appendAudit(next, {
      action: 'schedule.generate',
      entityType: 'tournament',
      entityId: tournamentId,
      summary: `Scheduled ${plan.assignments.length} matches across ${plan.courts.length} courts`,
      changes: {
        courts: plan.courts.length,
        slotMinutes: plan.slotMinutes,
        restMinutes: plan.restMinutes,
      },
    });
    commit(next, 'schedule.generate');
    return plan.assignments.length;
  }

  function placeWinner(next, match, winnerId) {
    if (!match.nextMatchId) return;
    const nextMatch = next.matches.find((item) => item.id === match.nextMatchId);
    if (!nextMatch) return;
    if (nextMatch.status === 'final' || nextMatch.status === 'forfeit') {
      throw new StoreError('DOWNSTREAM_LOCKED', 'The next-round match already has a result.');
    }
    if (match.nextSlot === 1) nextMatch.team1Id = winnerId;
    else nextMatch.team2Id = winnerId;
  }

  function saveScore(matchId, score1, score2, options = {}) {
    requirePermission(state, 'score.save');
    const match = findMatch(state, matchId);
    if (match.status === 'final' || match.status === 'forfeit') {
      throw new StoreError('MATCH_FINAL', 'Use correction to change a recorded result.');
    }
    if (!match.team1Id || !match.team2Id) {
      throw new StoreError('TEAMS_PENDING', 'Both teams must be known before entering a score.');
    }
    const forfeitTeam = Number(options.forfeitTeam || 0);
    let first = Number(score1);
    let second = Number(score2);
    if (!Number.isInteger(first) || !Number.isInteger(second) || first < 0 || second < 0) {
      throw new StoreError('VALIDATION', 'Scores must be zero or positive whole numbers.', 'score');
    }
    if (forfeitTeam && ![1, 2].includes(forfeitTeam)) {
      throw new StoreError('VALIDATION', 'Choose which team forfeited.', 'forfeitTeam');
    }
    if (!forfeitTeam && first === second) {
      throw new StoreError('TIE', 'Elimination games cannot end in a tie.', 'score');
    }
    const next = clone(state);
    const target = findMatch(next, matchId);
    const team1 = findTeam(next, target.team1Id);
    const team2 = findTeam(next, target.team2Id);
    if (forfeitTeam) {
      first = forfeitTeam === 1 ? 0 : 20;
      second = forfeitTeam === 2 ? 0 : 20;
      target.status = 'forfeit';
      target.winnerId = forfeitTeam === 1 ? team2.id : team1.id;
    } else {
      target.status = 'final';
      target.winnerId = first > second ? team1.id : team2.id;
    }
    target.score1 = first;
    target.score2 = second;
    target.boxScore = {
      team1: buildBoxScore(team1, first),
      team2: buildBoxScore(team2, second),
    };
    placeWinner(next, target, target.winnerId);
    appendAudit(next, {
      action: 'score.save',
      entityType: 'match',
      entityId: matchId,
      summary: `${team1.shortName} ${first}-${second} ${team2.shortName}`,
      changes: { status: target.status, score1: first, score2: second, winnerId: target.winnerId },
    });
    commit(next, 'score.save');
    return clone(target);
  }

  function correctScore(matchId, score1, score2, reason) {
    requirePermission(state, 'score.correct');
    const match = findMatch(state, matchId);
    if (match.status !== 'final' && match.status !== 'forfeit') {
      throw new StoreError('MATCH_NOT_FINAL', 'Only recorded results can be corrected.');
    }
    const cleanReason = requireText(reason, 'reason', 'Correction reason', 5);
    const first = Number(score1);
    const second = Number(score2);
    if (!Number.isInteger(first) || !Number.isInteger(second) || first < 0 || second < 0) {
      throw new StoreError('VALIDATION', 'Scores must be zero or positive whole numbers.', 'score');
    }
    if (first === second) {
      throw new StoreError('TIE', 'Elimination games cannot end in a tie.', 'score');
    }
    const next = clone(state);
    const target = findMatch(next, matchId);
    const team1 = findTeam(next, target.team1Id);
    const team2 = findTeam(next, target.team2Id);
    const previousScore1 = target.score1;
    const previousScore2 = target.score2;
    const previousWinnerId = target.winnerId;
    target.score1 = first;
    target.score2 = second;
    target.winnerId = first > second ? team1.id : team2.id;
    target.status = 'final';
    target.correction = {
      reason: cleanReason,
      previousScore1,
      previousScore2,
      correctedAt: isoNow(),
      correctedBy: currentUser(state).id,
    };
    target.boxScore = {
      team1: buildBoxScore(team1, first),
      team2: buildBoxScore(team2, second),
    };
    if (previousWinnerId !== target.winnerId) {
      placeWinner(next, target, target.winnerId);
    }
    appendAudit(next, {
      action: 'score.correct',
      entityType: 'match',
      entityId: matchId,
      summary: `Corrected ${team1.shortName} ${previousScore1}-${previousScore2} ${team2.shortName} to ${first}-${second}`,
      changes: { previousScore1, previousScore2, score1: first, score2: second, reason: cleanReason },
    });
    commit(next, 'score.correct');
    return clone(target);
  }

  function notificationsFor() {
    const lastSeen = state.meta?.lastSeenAnnouncementsAt || '1970-01-01T00:00:00+08:00';
    const allowed = AUDIENCE_BY_ROLE[getRole()] || ['all'];
    return clone(
      state.announcements.filter(
        (item) => allowed.includes(item.audience) && item.createdAt > lastSeen
      )
    );
  }

  function bracketFor(tournamentId) {
    const matches = state.matches.filter((match) => match.tournamentId === tournamentId);
    const rounds = [...new Set(matches.map((match) => match.round))].sort(
      (a, b) => Number(b) - Number(a)
    );
    const finalMatch = matches.find((match) => !match.nextMatchId) || null;
    const championId =
      finalMatch && (finalMatch.status === 'final' || finalMatch.status === 'forfeit')
        ? finalMatch.winnerId
        : null;
    return {
      hasBracket: matches.length > 0,
      rounds: rounds.map((round) => ({
        round,
        label: matches.find((match) => match.round === round)?.roundLabel || round,
        matches: matches
          .filter((match) => match.round === round)
          .sort((a, b) => a.bracketSlot - b.bracketSlot)
          .map(clone),
      })),
      final: finalMatch ? clone(finalMatch) : null,
      champion: championId
        ? clone(state.teams.find((team) => team.id === championId) || null)
        : null,
    };
  }

  function standingsFor(tournamentId) {
    const tournament = state.tournaments.find((item) => item.id === tournamentId);
    if (!tournament) return [];
    const teams = state.teams.filter(
      (team) => team.tournamentId === tournamentId && team.status === 'approved'
    );
    const rows = teams.map((team) => {
      const played = state.matches.filter(
        (match) =>
          match.tournamentId === tournamentId &&
          !match.isBye &&
          (match.status === 'final' || match.status === 'forfeit') &&
          (match.team1Id === team.id || match.team2Id === team.id)
      );
      let wins = 0;
      let losses = 0;
      let pointsFor = 0;
      let pointsAgainst = 0;
      played.forEach((match) => {
        const isTeam1 = match.team1Id === team.id;
        const own = isTeam1 ? match.score1 : match.score2;
        const other = isTeam1 ? match.score2 : match.score1;
        pointsFor += own ?? 0;
        pointsAgainst += other ?? 0;
        if (match.winnerId === team.id) wins += 1;
        else losses += 1;
      });
      return {
        teamId: team.id,
        team: clone(team),
        wins,
        losses,
        played: wins + losses,
        pointsFor,
        pointsAgainst,
        diff: pointsFor - pointsAgainst,
        seed: team.seed,
      };
    });
    rows.sort(
      (a, b) =>
        b.wins - a.wins ||
        b.diff - a.diff ||
        b.pointsFor - a.pointsFor ||
        (a.seed ?? 999) - (b.seed ?? 999)
    );
    const cut = Math.ceil(tournament.slots / 2);
    return rows.map((row, index) => ({ ...row, rank: index + 1, qualified: index < cut }));
  }

  function kpisFor(tournamentId) {
    const tournament = state.tournaments.find((item) => item.id === tournamentId);
    const teams = state.teams.filter((team) => team.tournamentId === tournamentId);
    const matches = state.matches.filter((match) => match.tournamentId === tournamentId);
    const billable = teams.filter((team) => team.status !== 'rejected');
    const paid = billable.filter((team) => team.payment.status === 'paid');
    const expected = billable.length * (tournament?.fee || 0);
    const collected = paid.reduce((sum, team) => sum + (team.payment.amount || 0), 0);
    return {
      registered: teams.length,
      approved: teams.filter((team) => team.status === 'approved').length,
      pending: teams.filter((team) => team.status === 'pending').length,
      rejected: teams.filter((team) => team.status === 'rejected').length,
      collected,
      outstanding: Math.max(0, expected - collected),
      expected,
      collectionRate: expected ? Math.round((collected / expected) * 100) : 0,
      matchesPlayed: matches.filter(
        (match) => match.status === 'final' || match.status === 'forfeit'
      ).length,
      matchesScheduled: matches.filter((match) => match.status === 'scheduled').length,
      courts: tournament?.courts.length || 0,
      pendingPayments: billable.filter((team) => team.payment.status !== 'paid').length,
    };
  }

  function paymentSummaryFor(tournamentId) {
    const teams = state.teams.filter(
      (team) => team.tournamentId === tournamentId && team.status !== 'rejected'
    );
    const kpis = kpisFor(tournamentId);
    return {
      collected: kpis.collected,
      outstanding: kpis.outstanding,
      expected: kpis.expected,
      rate: kpis.collectionRate,
      paidCount: teams.filter((team) => team.payment.status === 'paid').length,
      pendingCount: teams.filter((team) => team.payment.status === 'pending').length,
      partialCount: teams.filter((team) => team.payment.status === 'partial').length,
    };
  }

  function recentResultsFor(tournamentId, limit = 4) {
    return state.matches
      .filter(
        (match) =>
          match.tournamentId === tournamentId &&
          (match.status === 'final' || match.status === 'forfeit') &&
          !match.isBye
      )
      .sort((a, b) => String(b.scheduledAt || '').localeCompare(String(a.scheduledAt || '')))
      .slice(0, limit)
      .map(clone);
  }

  function upcomingFor(tournamentId, limit = 4) {
    return state.matches
      .filter((match) => match.tournamentId === tournamentId && match.status === 'scheduled')
      .sort((a, b) => {
        if (!a.scheduledAt) return 1;
        if (!b.scheduledAt) return -1;
        return a.scheduledAt.localeCompare(b.scheduledAt);
      })
      .slice(0, limit)
      .map(clone);
  }

  function teamMatchesFor(teamId) {
    return state.matches
      .filter((match) => match.team1Id === teamId || match.team2Id === teamId)
      .sort((a, b) => String(a.scheduledAt || '').localeCompare(String(b.scheduledAt || '')))
      .map(clone);
  }

  function nextMatchFor(teamId) {
    const match = teamMatchesFor(teamId).find((item) => item.status === 'scheduled');
    return match || null;
  }

  function coachTeamsFor() {
    const user = currentUser(state);
    const coachName = user.name.replace(/^Coach\s+/i, '').trim();
    return clone(
      state.teams.filter((team) => team.coach === coachName || team.coach === user.name)
    );
  }

  const select = {
    tournaments: () => clone(state.tournaments),
    tournament: (id) => {
      const tournament = state.tournaments.find((item) => item.id === id);
      return tournament ? clone(tournament) : null;
    },
    teams: (tournamentId, { status } = {}) =>
      clone(
        state.teams.filter(
          (team) =>
            team.tournamentId === tournamentId && (!status || team.status === status)
        )
      ),
    team: (id) => {
      const team = state.teams.find((item) => item.id === id);
      return team ? clone(team) : null;
    },
    matches: (tournamentId) =>
      clone(state.matches.filter((match) => match.tournamentId === tournamentId)),
    match: (id) => {
      const match = state.matches.find((item) => item.id === id);
      return match ? clone(match) : null;
    },
    announcements: (tournamentId) =>
      clone(
        state.announcements
          .filter((item) => !tournamentId || item.tournamentId === tournamentId)
          .sort((a, b) => Number(b.pinned) - Number(a.pinned) || b.createdAt.localeCompare(a.createdAt))
      ),
    audit: ({ entityType, actorId } = {}) =>
      clone(
        state.audit.filter(
          (entry) =>
            (!entityType || entry.entityType === entityType) &&
            (!actorId || entry.actorId === actorId)
        )
      ),
    bracket: (tournamentId) => bracketFor(tournamentId),
    standings: (tournamentId) => standingsFor(tournamentId),
    kpis: (tournamentId) => kpisFor(tournamentId),
    paymentSummary: (tournamentId) => paymentSummaryFor(tournamentId),
    teamRecord: (teamId) => {
      const team = state.teams.find((item) => item.id === teamId);
      if (!team) return null;
      const row = standingsFor(team.tournamentId).find((item) => item.teamId === teamId);
      return row
        ? {
            wins: row.wins,
            losses: row.losses,
            pointsFor: row.pointsFor,
            pointsAgainst: row.pointsAgainst,
            diff: row.diff,
            rank: row.rank,
          }
        : { wins: 0, losses: 0, pointsFor: 0, pointsAgainst: 0, diff: 0, rank: null };
    },
    recentResults: (tournamentId, limit = 4) => recentResultsFor(tournamentId, limit),
    upcomingMatches: (tournamentId, limit = 4) => upcomingFor(tournamentId, limit),
    teamMatches: (teamId) => teamMatchesFor(teamId),
    nextMatch: (teamId) => nextMatchFor(teamId),
    coachTeams: () => coachTeamsFor(),
    notifications: () => notificationsFor(),
  };

  return {
    getState,
    subscribe,
    getRole,
    getCurrentUser,
    can,
    canRole,
    planSchedule,
    select,
    actions: {
      reset,
      setRole,
      createTournament,
      registerTeam,
      reviewTeam,
      verifyPayment,
      publishAnnouncement,
      addPlayer,
      updatePlayer,
      removePlayer,
      submitPayment,
      markAnnouncementsSeen,
      generateBracket,
      generateSchedule,
      saveScore,
      correctScore,
    },
  };
}
