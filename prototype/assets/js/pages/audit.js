import { route } from '../routes.js';
import { escapeHtml, formatDateTime } from '../ui.js';
import { guardRole } from '../guards.js';

const ACTION_LABELS = {
  'tournament.create': 'Tournament created',
  'team.register': 'Team registered',
  'team.approve': 'Team approved',
  'team.reject': 'Team rejected',
  'team.roster.add': 'Player added',
  'team.roster.update': 'Player updated',
  'team.roster.remove': 'Player removed',
  'payment.submit': 'Payment reference submitted',
  'payment.verify': 'Payment verified',
  'bracket.generate': 'Bracket generated',
  'schedule.generate': 'Schedule generated',
  'score.save': 'Score saved',
  'score.correct': 'Score corrected',
  'announcement.publish': 'Announcement published',
};

const ENTITY_LABELS = {
  tournament: 'Tournament',
  team: 'Team',
  match: 'Match',
  announcement: 'Announcement',
};

function humanize(key) {
  return String(key)
    .replace(/([A-Z])/g, ' $1')
    .replaceAll('_', ' ')
    .replace(/^./, (character) => character.toUpperCase());
}

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['organizer'] })) return;

  const state = store.getState();
  const params = new URLSearchParams(window.location.search);
  const entries = store
    .select.audit()
    .sort((a, b) => String(b.at).localeCompare(String(a.at)));
  const users = state.users;
  const entities = [...new Set(entries.map((entry) => entry.entityType))].sort();
  const actors = [...new Set(entries.map((entry) => entry.actorId))]
    .map((id) => users.find((user) => user.id === id) || { id, name: id })
    .sort((a, b) => a.name.localeCompare(b.name));

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Operations',
      title: 'Audit log',
      description:
        'A reverse-chronological record of every approval, payment, bracket, schedule, score, and announcement. Corrections keep their previous values.',
      actions: `<a class="hf-btn" href="${route('profile')}" data-testid="audit-back-profile"><i class="bi bi-person"></i> Profile</a>`,
    })}

    <div class="hf-grid hf-grid--4 hf-mb-3">
      ${components.kpiTile({ label: 'Entries', value: entries.length, hint: 'Seed plus this session', tone: 'primary', testid: 'audit-kpi-total' })}
      ${components.kpiTile({ label: 'Corrections', value: entries.filter((entry) => entry.action === 'score.correct').length, hint: 'Old values retained', tone: 'alert', testid: 'audit-kpi-corrections' })}
      ${components.kpiTile({ label: 'Approvals', value: entries.filter((entry) => ['team.approve', 'team.reject'].includes(entry.action)).length, hint: 'Team decisions', tone: 'gold', testid: 'audit-kpi-approvals' })}
      ${components.kpiTile({ label: 'Payments', value: entries.filter((entry) => entry.action === 'payment.verify').length, hint: 'Verified references', tone: 'success', testid: 'audit-kpi-payments' })}
    </div>

    <section class="hf-card hf-mb-3" aria-label="Audit filters">
      <div class="hf-form-grid">
        <div class="hf-field">
          <label class="hf-label" for="auditEntity">Entity</label>
          <select class="hf-select" id="auditEntity" data-testid="audit-entity-filter">
            <option value="all">All entities</option>
            ${entities
              .map(
                (entity) =>
                  `<option value="${escapeHtml(entity)}">${escapeHtml(ENTITY_LABELS[entity] || humanize(entity))}</option>`
              )
              .join('')}
          </select>
        </div>
        <div class="hf-field">
          <label class="hf-label" for="auditActor">Actor</label>
          <select class="hf-select" id="auditActor" data-testid="audit-actor-filter">
            <option value="all">All actors</option>
            ${actors
              .map(
                (actor) =>
                  `<option value="${escapeHtml(actor.id)}">${escapeHtml(actor.name)}</option>`
              )
              .join('')}
          </select>
        </div>
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="auditSearch">Search</label>
          <input class="hf-input" id="auditSearch" type="search"
                 placeholder="Summary, action, actor, or entity id"
                 data-testid="audit-search">
        </div>
      </div>
      <div class="hf-between hf-mt-2">
        <span class="hf-small hf-muted" id="auditCount" data-testid="audit-count"></span>
        <button type="button" class="hf-btn hf-btn--sm" data-audit-clear data-testid="audit-clear">Clear filters</button>
      </div>
    </section>

    <div id="auditList" data-testid="audit-list"></div>
  `;

  const entity = root.querySelector('#auditEntity');
  const actor = root.querySelector('#auditActor');
  const search = root.querySelector('#auditSearch');
  const count = root.querySelector('#auditCount');
  const list = root.querySelector('#auditList');

  entity.value = params.get('entity') || 'all';
  actor.value = params.get('actor') || 'all';
  search.value = params.get('q') || '';

  const userById = (id) => users.find((user) => user.id === id) || { name: id };

  function entityLink(entry) {
    if (entry.entityType === 'team') {
      const team = store.select.team(entry.entityId);
      return team
        ? `<a class="hf-section-link" href="${route('teamShow', { id: team.id })}" data-testid="audit-team-link-${escapeHtml(entry.id)}">${escapeHtml(team.name)}</a>`
        : `<span class="hf-mono">${escapeHtml(entry.entityId)}</span>`;
    }
    if (entry.entityType === 'match') {
      const match = store.select.match(entry.entityId);
      if (!match) return `<span class="hf-mono">${escapeHtml(entry.entityId)}</span>`;
      const team1 = store.select.team(match.team1Id);
      const team2 = store.select.team(match.team2Id);
      const label = `${team1?.shortName || 'TBD'} vs ${team2?.shortName || 'TBD'}`;
      return `<a class="hf-section-link" href="${route('gameStats', { match: match.id })}" data-testid="audit-match-link-${escapeHtml(entry.id)}">${escapeHtml(label)}</a>`;
    }
    if (entry.entityType === 'tournament') {
      const tournament = store.select.tournament(entry.entityId);
      return tournament
        ? `<a class="hf-section-link" href="${route('tournamentShow', { id: tournament.id })}" data-testid="audit-tournament-link-${escapeHtml(entry.id)}">${escapeHtml(tournament.name)}</a>`
        : `<span class="hf-mono">${escapeHtml(entry.entityId)}</span>`;
    }
    if (entry.entityType === 'announcement') {
      const announcement = state.announcements.find((item) => item.id === entry.entityId);
      return announcement
        ? `<a class="hf-section-link" href="${route('announcements', { id: announcement.tournamentId })}" data-testid="audit-announcement-link-${escapeHtml(entry.id)}">${escapeHtml(announcement.title)}</a>`
        : `<span class="hf-mono">${escapeHtml(entry.entityId)}</span>`;
    }
    return `<span class="hf-mono">${escapeHtml(entry.entityId)}</span>`;
  }

  function changeChips(entry) {
    if (!entry.changes) return '';
    if (entry.action === 'score.correct') {
      const changes = entry.changes;
      return `<span class="hf-change">${escapeHtml(String(changes.previousScore1))}–${escapeHtml(String(changes.previousScore2))} → ${escapeHtml(String(changes.score1))}–${escapeHtml(String(changes.score2))}</span>`;
    }
    return Object.entries(entry.changes)
      .map(([key, value]) => {
        const formatted = typeof value === 'object' ? JSON.stringify(value) : String(value);
        return `<span class="hf-change">${escapeHtml(humanize(key))}: ${escapeHtml(formatted)}</span>`;
      })
      .join('');
  }

  function auditEntry(entry) {
    const author = userById(entry.actorId);
    return `
      <article class="hf-timeline-item" data-testid="audit-entry-${escapeHtml(entry.id)}">
        <div class="hf-timeline-meta">
          <span><i class="bi bi-clock" aria-hidden="true"></i> ${escapeHtml(formatDateTime(entry.at))}</span>
          <span>${escapeHtml(author.name)}</span>
          <span class="hf-chip hf-chip--slate" style="text-transform: capitalize">${escapeHtml(entry.actorRole)}</span>
        </div>
        <h3>${escapeHtml(ACTION_LABELS[entry.action] || humanize(entry.action))}</h3>
        <p>${escapeHtml(entry.summary)}</p>
        <div class="hf-flex hf-wrap hf-gap-1 hf-mt-1">
          ${entityLink(entry)}
          ${changeChips(entry)}
        </div>
      </article>
    `;
  }

  function renderList() {
    const term = search.value.trim().toLowerCase();
    const filtered = entries.filter((entry) => {
      const author = userById(entry.actorId);
      const haystack = `${entry.summary} ${entry.action} ${entry.entityId} ${author.name} ${entry.actorRole}`.toLowerCase();
      return (
        (entity.value === 'all' || entry.entityType === entity.value) &&
        (actor.value === 'all' || entry.actorId === actor.value) &&
        (!term || haystack.includes(term))
      );
    });

    count.textContent = `${filtered.length} of ${entries.length} entries`;
    list.innerHTML = filtered.length
      ? `<div class="hf-timeline">${filtered.map(auditEntry).join('')}</div>`
      : ui.emptyState({
          icon: 'clock-history',
          title: 'No audit entries match',
          message: 'Try another entity, actor, or search term.',
          action: `<button type="button" class="hf-btn" data-audit-clear-inline data-testid="audit-clear-inline">Clear filters</button>`,
        });

    list.querySelector('[data-audit-clear-inline]')?.addEventListener('click', clearFilters);
  }

  function clearFilters() {
    entity.value = 'all';
    actor.value = 'all';
    search.value = '';
    renderList();
  }

  entity.addEventListener('change', renderList);
  actor.addEventListener('change', renderList);
  search.addEventListener('input', ui.debounce(renderList, 120));
  root.querySelector('[data-audit-clear]').addEventListener('click', clearFilters);

  renderList();
}