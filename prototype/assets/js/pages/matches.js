import { route } from '../routes.js';
import { escapeHtml, formatDay } from '../ui.js';

function keyOf(value) {
  return value ? String(value).slice(0, 10) : '';
}

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const tournamentId = params.get('id') || 'tournament-1';
  const tournament = store.select.tournament(tournamentId);

  if (!tournament) {
    root.innerHTML = ui.emptyState({
      icon: 'list-ul',
      title: 'Tournament not found',
      message: 'The matchup link is invalid or the demo has been reset.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Back to tournaments</a>`,
    });
    return;
  }

  let searchTerm = params.get('q') || '';
  let dateFilter = params.get('date') || '';
  let courtFilter = params.get('court') || 'all';
  let statusFilter = params.get('status') || 'all';

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: `${tournament.division} · ${tournament.name}`,
      title: 'Matchups',
      description: 'Every bracket game grouped by matchday, with court, status, and a link to the official box score.',
      actions: `
        <a class="hf-btn" href="${route('schedule', { id: tournamentId })}" data-testid="matches-schedule"><i class="bi bi-calendar3"></i> Schedule</a>
        <a class="hf-btn" href="${route('bracket', { id: tournamentId })}" data-testid="matches-bracket"><i class="bi bi-diagram-3"></i> Bracket</a>
      `,
    })}

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="matchSearch">Search</label>
          <input class="hf-input" id="matchSearch" type="search" placeholder="Team or round"
                 value="${escapeHtml(searchTerm)}" data-testid="match-search">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="matchDate">Day</label>
          <input class="hf-input" id="matchDate" type="date" value="${escapeHtml(dateFilter)}" data-testid="match-date-filter">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="matchCourt">Court</label>
          <select class="hf-select" id="matchCourt" data-testid="match-court-filter">
            <option value="all">All courts</option>
            ${tournament.courts.map((court) => `<option value="${escapeHtml(court.id)}">${escapeHtml(court.name)}</option>`).join('')}
          </select>
        </div>
        <div class="hf-field">
          <label class="hf-label" for="matchStatus">Status</label>
          <select class="hf-select" id="matchStatus" data-testid="match-status-filter">
            <option value="all">All statuses</option>
            <option value="scheduled">Scheduled</option>
            <option value="final">Final</option>
            <option value="forfeit">Forfeit</option>
          </select>
        </div>
      </div>
    </div>

    <div id="matchList" data-testid="match-list"></div>
  `;

  root.querySelector('#matchCourt').value = courtFilter;
  root.querySelector('#matchStatus').value = statusFilter;

  const search = root.querySelector('#matchSearch');
  const date = root.querySelector('#matchDate');
  const court = root.querySelector('#matchCourt');
  const status = root.querySelector('#matchStatus');
  const list = root.querySelector('#matchList');

  function renderList() {
    const term = search.value.trim().toLowerCase();
    const filtered = store
      .select.matches(tournamentId)
      .filter((match) => {
        const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
        const team2 = match.team2Id ? store.select.team(match.team2Id) : null;
        const haystack = `${team1?.name || ''} ${team2?.name || ''} ${match.roundLabel}`.toLowerCase();
        return (
          (court.value === 'all' || match.courtId === court.value) &&
          (status.value === 'all' || match.status === status.value) &&
          (!date.value || keyOf(match.scheduledAt) === date.value) &&
          (!term || haystack.includes(term))
        );
      })
      .sort((a, b) => String(a.scheduledAt || '9999').localeCompare(String(b.scheduledAt || '9999')));

    if (!filtered.length) {
      list.innerHTML = ui.emptyState({
        icon: 'search',
        title: 'No matchups match',
        message: 'Clear the day, court, or status filter and try again.',
        action: `<button type="button" class="hf-btn" data-clear-match-filters data-testid="match-clear-filters">Clear filters</button>`,
      });
      list.querySelector('[data-clear-match-filters]')?.addEventListener('click', () => {
        search.value = '';
        date.value = '';
        court.value = 'all';
        status.value = 'all';
        renderList();
      });
      return;
    }

    const groups = new Map();
    filtered.forEach((match) => {
      const key = keyOf(match.scheduledAt) || 'unscheduled';
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(match);
    });

    list.innerHTML = [...groups.entries()]
      .map(([key, matches]) => `
        <section class="hf-mb-3" data-testid="match-day-${escapeHtml(key)}">
          <div class="hf-section-head">
            <h2 class="hf-section-title">${key === 'unscheduled' ? 'Unscheduled' : escapeHtml(formatDay(key))}</h2>
            <span class="hf-chip hf-chip--slate">${matches.length} games</span>
          </div>
          <div class="hf-grid hf-grid--2">
            ${matches
              .map((match) =>
                components.matchCard(match, {
                  href: route('gameStats', { match: match.id }),
                  testid: `match-card-${match.id}`,
                })
              )
              .join('')}
          </div>
        </section>
      `)
      .join('');
  }

  search.addEventListener('input', ui.debounce(renderList, 120));
  date.addEventListener('change', renderList);
  court.addEventListener('change', renderList);
  status.addEventListener('change', renderList);

  renderList();
}