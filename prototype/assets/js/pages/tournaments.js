import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney } from '../ui.js';

export function render({ store, ui, components, root }) {
  const role = store.getRole();
  const params = new URLSearchParams(window.location.search);
  const tournaments = store.select.tournaments();
  const divisions = [...new Set(tournaments.map((tournament) => tournament.division))].sort();

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Tournament directory',
      title: 'Tournaments',
      description: 'Three seeded competitions cover registration, a live bracket, and a draft that can generate a bracket and schedule during the demo.',
      actions: role === 'organizer'
        ? `<a class="hf-btn hf-btn--primary" href="${route('tournamentCreate')}" data-testid="tournaments-create"><i class="bi bi-plus-lg"></i> New tournament</a>`
        : `<a class="hf-btn" href="${route('dashboard')}">Back to dashboard</a>`,
    })}

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="tournamentSearch">Search</label>
          <input class="hf-input" id="tournamentSearch" type="search"
                 placeholder="Name, venue, or division" value="${escapeHtml(params.get('q') || '')}"
                 data-testid="tournament-search">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="tournamentStatus">Status</label>
          <select class="hf-select" id="tournamentStatus" data-testid="tournament-status-filter">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="registration-open">Registration open</option>
            <option value="upcoming">Upcoming</option>
            <option value="draft">Draft</option>
          </select>
        </div>
        <div class="hf-field">
          <label class="hf-label" for="tournamentDivision">Division</label>
          <select class="hf-select" id="tournamentDivision" data-testid="tournament-division-filter">
            <option value="">All divisions</option>
            ${divisions.map((division) => `<option value="${escapeHtml(division)}">${escapeHtml(division)}</option>`).join('')}
          </select>
        </div>
      </div>
    </div>

    <div id="tournamentList" data-testid="tournament-list"></div>
  `;

  const search = root.querySelector('#tournamentSearch');
  const status = root.querySelector('#tournamentStatus');
  const division = root.querySelector('#tournamentDivision');
  const list = root.querySelector('#tournamentList');
  status.value = params.get('status') || '';

  const card = (tournament) => {
    const kpis = store.select.kpis(tournament.id);
    const coachTeam = role === 'coach'
      ? store.select.coachTeams().find((team) => team.tournamentId === tournament.id)
      : null;
    const action = role === 'coach' && tournament.status === 'registration-open' && !coachTeam
      ? `<a class="hf-btn hf-btn--primary" href="${route('teamRegister', { tournament: tournament.id })}" data-testid="tournament-register-${tournament.id}">Register team</a>`
      : coachTeam
        ? `<a class="hf-btn" href="${route('myTeam', { team: coachTeam.id })}" data-testid="tournament-my-team-${tournament.id}">My team</a>`
        : `<a class="hf-btn" href="${route('tournamentShow', { id: tournament.id })}" data-testid="tournament-open-${tournament.id}">Open desk</a>`;
    return `
      <article class="hf-card hf-mb-2" data-testid="tournament-card-${tournament.id}">
        <div class="hf-between">
          <div>
            <span class="hf-flex hf-wrap hf-gap-1">
              ${ui.statusChip(tournament.status)}
              <span class="hf-chip hf-chip--slate">${escapeHtml(tournament.division)}</span>
              ${tournament.seedLocked ? '<span class="hf-chip hf-chip--gold">Seeds locked</span>' : ''}
            </span>
            <h2 class="hf-section-title hf-mt-1">${escapeHtml(tournament.name)}</h2>
            <p class="hf-small hf-muted hf-mb-0">
              ${escapeHtml(tournament.venue)} · ${escapeHtml(formatDate(tournament.startDate))} to ${escapeHtml(formatDate(tournament.endDate))}
            </p>
          </div>
          <div class="hf-text-right">
            <span class="hf-kpi-label">Teams</span>
            <strong class="hf-kpi-value" style="font-size: 32px">${kpis.approved}/${tournament.slots}</strong>
          </div>
        </div>
        <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small hf-muted">
          <span><i class="bi bi-geo-alt"></i> ${tournament.courts.length} courts</span>
          <span><i class="bi bi-cash"></i> ${escapeHtml(formatMoney(tournament.fee))} entry</span>
          <span><i class="bi bi-diagram-3"></i> ${kpis.matchesPlayed} played</span>
          <span><i class="bi bi-hourglass-split"></i> ${kpis.pending} pending</span>
        </div>
        <div class="hf-btn-row hf-mt-2">
          ${action}
          <a class="hf-btn" href="${route('bracket', { id: tournament.id })}">Bracket</a>
          <a class="hf-btn" href="${route('schedule', { id: tournament.id })}">Schedule</a>
        </div>
      </article>
    `;
  };

  const renderList = () => {
    const term = search.value.trim().toLowerCase();
    const statusValue = status.value;
    const divisionValue = division.value;
    const filtered = tournaments.filter((tournament) => {
      const haystack = `${tournament.name} ${tournament.venue} ${tournament.division}`.toLowerCase();
      return (
        (!term || haystack.includes(term)) &&
        (!statusValue || tournament.status === statusValue) &&
        (!divisionValue || tournament.division === divisionValue)
      );
    });
    list.innerHTML = filtered.length
      ? filtered.map(card).join('')
      : ui.emptyState({
          icon: 'search',
          title: 'No tournaments match',
          message: 'Clear the search or choose another status/division.',
          action: `<button type="button" class="hf-btn" data-clear-filters data-testid="tournament-clear-filters">Clear filters</button>`,
        });
    list.querySelector('[data-clear-filters]')?.addEventListener('click', () => {
      search.value = '';
      status.value = '';
      division.value = '';
      renderList();
    });
  };

  search.addEventListener('input', ui.debounce(renderList, 120));
  status.addEventListener('change', renderList);
  division.addEventListener('change', renderList);
  renderList();
}