import { SEED, SEED_VERSION } from './seed.js';

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
    },
  };
}
