import { route } from '../routes.js';
import { escapeHtml } from '../ui.js';

export function render({ store, ui, components, root }) {
  const role = store.getRole();
  const params = new URLSearchParams(window.location.search);
  let statusFilter = role === 'fan' ? 'approved' : params.get('status') || 'all';
  let divisionFilter = params.get('division') || 'all';
  let searchTerm = params.get('q') || '';

  const tournaments = store.select.tournaments();
  const divisions = [...new Set(tournaments.map((tournament) => tournament.division))].sort();
  const openTournament = tournaments.find((tournament) => tournament.registrationOpen);

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Team directory',
      title: 'Teams',
      description:
        role === 'fan'
          ? 'Public team cards show records, coaches, and cities. Payment and eligibility details stay private.'
          : 'Every registered team, scoped to its tournament, with record, roster size, and review state.',
      actions:
        role === 'organizer'
          ? `<a class="hf-btn hf-btn--primary" href="${route('submissions')}" data-testid="teams-review-queue"><i class="bi bi-clipboard-check"></i> Review queue</a>`
          : role === 'coach'
            ? openTournament
              ? `<a class="hf-btn hf-btn--primary" href="${route('teamRegister', { tournament: openTournament.id })}" data-testid="teams-register"><i class="bi bi-plus-lg"></i> Register team</a>`
              : ''
            : `<a class="hf-btn" href="${route('dashboard')}">Back to dashboard</a>`,
    })}

    <div id="teamKpis" class="hf-grid hf-grid--4 hf-mb-3"></div>

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="teamSearch">Search</label>
          <input class="hf-input" id="teamSearch" type="search" placeholder="Team, coach, or city"
                 value="${escapeHtml(searchTerm)}" data-testid="team-search">
        </div>
         ${
           role === 'fan'
             ? ''
             : `<div class="hf-field">
                  <label class="hf-label" for="teamStatus">Status</label>
                  <select class="hf-select" id="teamStatus" data-testid="team-status-filter">
                    <option value="all">All statuses</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                  </select>
                </div>`
         }
        <div class="hf-field">
          <label class="hf-label" for="teamDivision">Division</label>
          <select class="hf-select" id="teamDivision" data-testid="team-division-filter">
            <option value="all">All divisions</option>
            ${divisions.map((division) => `<option value="${escapeHtml(division)}">${escapeHtml(division)}</option>`).join('')}
          </select>
        </div>
      </div>
    </div>

    <div id="teamList" data-testid="team-list"></div>
  `;

  const search = root.querySelector('#teamSearch');
  const status = root.querySelector('#teamStatus');
  const division = root.querySelector('#teamDivision');
  const list = root.querySelector('#teamList');
  const kpis = root.querySelector('#teamKpis');
  if (status) status.value = statusFilter;
  division.value = divisionFilter;

  const coachTeams = role === 'coach' ? store.select.coachTeams() : [];
  const ownsTeam = (team) => coachTeams.some((item) => item.id === team.id);
  const canSeePrivate = (team) =>
    role === 'organizer' || (role === 'coach' && ownsTeam(team));

  function visibleTeams() {
    const teams = store.getState().teams;
    return role === 'fan'
      ? teams.filter((team) => team.status === 'approved')
      : teams;
  }

  function renderKpis() {
    const teams = visibleTeams();
    kpis.innerHTML = role === 'fan'
      ? [
          components.kpiTile({ label: 'Public teams', value: teams.length, hint: 'Approved and visible', tone: 'primary', testid: 'team-kpi-teams' }),
          components.kpiTile({ label: 'Divisions', value: divisions.length, hint: 'Age groups', tone: 'slate', testid: 'team-kpi-divisions' }),
          components.kpiTile({ label: 'Cities', value: new Set(teams.map((team) => team.city)).size, hint: 'Clubs represented', tone: 'gold', testid: 'team-kpi-cities' }),
          components.kpiTile({ label: 'Tournaments', value: tournaments.length, hint: 'Seeded venues', tone: 'success', testid: 'team-kpi-tournaments' }),
        ].join('')
      : [
          components.kpiTile({ label: 'Registered', value: teams.length, hint: `${tournaments.length} tournaments`, tone: 'primary', testid: 'team-kpi-registered' }),
          components.kpiTile({ label: 'Approved', value: teams.filter((team) => team.status === 'approved').length, hint: 'Eligible for seeding', tone: 'success', testid: 'team-kpi-approved' }),
          components.kpiTile({ label: 'Pending', value: teams.filter((team) => team.status === 'pending').length, hint: 'Waiting for organizer review', tone: 'gold', testid: 'team-kpi-pending' }),
          components.kpiTile({ label: 'Divisions', value: divisions.length, hint: 'U12 through open', tone: 'slate', testid: 'team-kpi-divisions' }),
        ].join('');
  }

  function teamCard(team) {
    const tournament = store.select.tournament(team.tournamentId);
    const record = store.select.teamRecord(team.id);
    const isOwn = ownsTeam(team);
    return `
      <article class="hf-card hf-card--flat" data-testid="team-card-${escapeHtml(team.id)}">
        <div class="hf-between">
          <span class="hf-flex hf-items-center hf-gap-2">
            ${components.shield(team, 'lg')}
            <span>
              <strong>${escapeHtml(team.name)}</strong>
              <span class="hf-small hf-muted" style="display:block">${escapeHtml(team.coach)} · ${escapeHtml(team.city)}</span>
            </span>
          </span>
          <span class="hf-flex hf-wrap hf-gap-1">
            ${ui.statusChip(team.status)}
            ${canSeePrivate(team) ? ui.statusChip(team.payment.status) : ''}
          </span>
        </div>
        <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small">
          <span><span class="hf-kpi-label">Tournament</span><strong style="display:block">${escapeHtml(tournament?.name || '')}</strong></span>
          <span><span class="hf-kpi-label">Division</span><strong style="display:block">${escapeHtml(tournament?.division || '')}</strong></span>
          <span><span class="hf-kpi-label">Record</span><strong style="display:block">${record.wins}-${record.losses}</strong></span>
          <span><span class="hf-kpi-label">Roster</span><strong style="display:block">${team.players.length}</strong></span>
        </div>
        ${canSeePrivate(team) ? `<div class="hf-flex hf-wrap hf-gap-1 hf-mt-2">${
          team.eligibility.documentsComplete ? ui.statusChip('verified', 'Documents') : ui.statusChip('missing', 'Documents')
        }${team.eligibility.consentComplete ? ui.statusChip('verified', 'Consent') : ui.statusChip('missing', 'Consent')}</div>` : ''}
        <div class="hf-btn-row hf-mt-2">
          <a class="hf-btn hf-btn--sm" href="${route('teamShow', { id: team.id })}" data-testid="team-open-${escapeHtml(team.id)}">Team profile</a>
          ${isOwn ? `<a class="hf-btn hf-btn--sm hf-btn--primary" href="${route('myTeam', { team: team.id })}" data-testid="team-manage-${escapeHtml(team.id)}">Manage roster</a>` : ''}
        </div>
      </article>
    `;
  }

  function renderList() {
    const term = search.value.trim().toLowerCase();
    const statusValue = status ? status.value : 'approved';
    const filtered = visibleTeams()
      .filter((team) => {
        const tournament = store.select.tournament(team.tournamentId);
        const haystack = `${team.name} ${team.coach} ${team.city} ${tournament?.name || ''}`.toLowerCase();
        return (
          (statusValue === 'all' || team.status === statusValue) &&
          (division.value === 'all' || tournament?.division === division.value) &&
          (!term || haystack.includes(term))
        );
      })
      .sort((a, b) => {
        const rank = { pending: 0, approved: 1, rejected: 2 };
        return (rank[a.status] ?? 3) - (rank[b.status] ?? 3) || a.name.localeCompare(b.name);
      });

    list.innerHTML = filtered.length
      ? `<div class="hf-grid hf-grid--2">${filtered.map(teamCard).join('')}</div>`
      : ui.emptyState({
          icon: 'people',
          title: 'No teams match',
          message: 'Try another status, division, or search term.',
          action: `<button type="button" class="hf-btn" data-clear-team-filters data-testid="team-clear-filters">Clear filters</button>`,
        });

    list.querySelector('[data-clear-team-filters]')?.addEventListener('click', () => {
      search.value = '';
      if (status) status.value = 'all';
      division.value = 'all';
      renderList();
    });
  }

  search.addEventListener('input', ui.debounce(renderList, 120));
  status?.addEventListener('change', renderList);
  division.addEventListener('change', renderList);

  renderKpis();
  renderList();
}