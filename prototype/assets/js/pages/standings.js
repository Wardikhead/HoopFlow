import { route } from '../routes.js';
import { escapeHtml } from '../ui.js';

const FILTERS = [
  { value: 'all', label: 'All teams' },
  { value: 'qualified', label: 'Playoff cut' },
  { value: 'wins', label: 'With a win' },
  { value: 'winless', label: 'Winless' },
];

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const tournamentId = params.get('id') || 'tournament-1';
  const tournament = store.select.tournament(tournamentId);

  if (!tournament) {
    root.innerHTML = ui.emptyState({
      icon: 'bar-chart',
      title: 'Tournament not found',
      message: 'The standings link is invalid or the demo has been reset.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Back to tournaments</a>`,
    });
    return;
  }

  let filter = FILTERS.some((item) => item.value === params.get('filter'))
    ? params.get('filter')
    : 'all';
  let searchTerm = params.get('q') || '';
  const cut = Math.ceil(tournament.slots / 2);

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: `${tournament.division} · ${tournament.name}`,
      title: 'Standings',
      description: 'Wins, losses, point differential, and the playoff cut — computed from every recorded result.',
      actions: `
        <label class="hf-sr-only" for="standingsTournament">Tournament</label>
        <select class="hf-select" id="standingsTournament" data-testid="standings-tournament" style="width: auto; min-width: 210px">
          ${store
            .select.tournaments()
            .map(
              (item) =>
                `<option value="${escapeHtml(item.id)}" ${item.id === tournamentId ? 'selected' : ''}>${escapeHtml(item.name)}</option>`
            )
            .join('')}
        </select>
        <a class="hf-btn" href="${route('bracket', { id: tournamentId })}" data-testid="standings-bracket"><i class="bi bi-diagram-3"></i> Bracket</a>
        <a class="hf-btn" href="${route('matches', { id: tournamentId })}" data-testid="standings-matches"><i class="bi bi-list-ul"></i> Matchups</a>
      `,
    })}

    <div class="hf-grid hf-grid--4 hf-mb-3" id="standingsKpis" data-testid="standings-kpis"></div>

    <div class="hf-card hf-mb-3">
      <div class="hf-field">
        <label class="hf-label" for="standingsSearch">Search teams</label>
        <input class="hf-input" id="standingsSearch" type="search" placeholder="Team, short name, or city"
               value="${escapeHtml(searchTerm)}" data-testid="standings-search">
      </div>
      <div class="hf-filter-row hf-mt-2" data-standings-filters data-testid="standings-filters">
        ${FILTERS.map(
          (item) => `
            <button type="button" class="hf-chip hf-chip--slate" data-standings-filter="${item.value}"
                    aria-pressed="false" data-testid="standings-filter-${item.value}">
              ${escapeHtml(item.label)}
            </button>
          `
        ).join('')}
      </div>
    </div>

    <div id="standingsBody" data-testid="standings-body"></div>

    <div class="hf-card hf-card--flat hf-mt-3">
      <p class="hf-small hf-muted hf-mb-0">
        <strong>Tiebreakers:</strong> wins, then point differential, then points for, then registration seed.
        The top ${cut} teams sit above the gold qualification line.
      </p>
    </div>
  `;

  const search = root.querySelector('#standingsSearch');

  function paintKpis() {
    const rows = store.select.standings(tournamentId);
    const kpis = store.select.kpis(tournamentId);
    const leader = rows[0] || null;
    const bestDiff = rows.reduce(
      (best, row) => (!best || row.diff > best.diff ? row : best),
      null
    );
    root.querySelector('#standingsKpis').innerHTML = [
      components.kpiTile({
        label: 'Leader',
        value: leader?.team.shortName || 'TBD',
        hint: leader ? `${leader.wins}-${leader.losses} · ${leader.team.name}` : 'No results yet',
        tone: 'gold',
        testid: 'standings-kpi-leader',
      }),
      components.kpiTile({
        label: 'Playoff cut',
        value: `Top ${cut}`,
        hint: `${rows.filter((row) => row.qualified).length} teams above the line`,
        tone: 'primary',
        testid: 'standings-kpi-cut',
      }),
      components.kpiTile({
        label: 'Best diff',
        value: bestDiff ? `${bestDiff.diff > 0 ? '+' : ''}${bestDiff.diff}` : '0',
        hint: bestDiff?.team.shortName || 'No games recorded',
        tone: 'success',
        testid: 'standings-kpi-diff',
      }),
      components.kpiTile({
        label: 'Results',
        value: kpis.matchesPlayed,
        hint: 'Final and forfeit games',
        tone: 'slate',
        testid: 'standings-kpi-results',
      }),
    ].join('');
  }

  function paintFilters() {
    root.querySelectorAll('[data-standings-filter]').forEach((button) => {
      const active = button.dataset.standingsFilter === filter;
      button.classList.toggle('hf-chip--primary', active);
      button.classList.toggle('hf-chip--slate', !active);
      button.setAttribute('aria-pressed', String(active));
    });
  }

  function paintTable() {
    root.querySelector('#standingsBody').innerHTML = components.standingsTable(tournamentId, {
      query: { search: searchTerm, filter },
    });
    paintFilters();
  }

  root.querySelector('#standingsTournament').addEventListener('change', (event) => {
    window.location.href = route('standings', { id: event.target.value });
  });

  root.querySelectorAll('[data-standings-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      filter = button.dataset.standingsFilter;
      paintTable();
    });
  });

  search.addEventListener(
    'input',
    ui.debounce(() => {
      searchTerm = search.value;
      paintTable();
    }, 140)
  );

  paintKpis();
  paintTable();
}