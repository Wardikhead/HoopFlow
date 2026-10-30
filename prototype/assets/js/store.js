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
    next.teams.push({
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
      eligibility: { documentsComplete: true, consentComplete: true, verifiedAt: null },
      players,
    });
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
    next.meta.lastSeenAnnouncementsAt = isoNow();
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
    next.meta.lastSeenAnnouncementsAt = isoNow();
    commit(next, 'announcements.seen');
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
    notifications: () => notificationsFor(),
  };

  return {
    getState,
    subscribe,
    getRole,
    getCurrentUser,
    can,
    canRole,
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
    },
  };
}
