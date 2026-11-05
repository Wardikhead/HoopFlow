import { route } from '../routes.js';
import { escapeHtml, formatDate, formatTime } from '../ui.js';
import { boxScoreRows, boxScoreTotals, sortBoxScore } from '../stats.js';
import { openCorrectionDialog, openScoreConsole } from '../score-dialogs.js';

const COLUMNS = [
  { key: 'number', label: '#', numeric: true, sortable: false },
  { key: 'name', label: 'Player', numeric: false, sortable: false },
  { key: 'points', label: 'PTS', numeric: true, sortable: true },
  { key: 'assists', label: 'AST', numeric: true, sortable: true },
  { key: 'rebounds', label: 'REB', numeric: true, sortable: true },
];

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const matchId = params.get('match') || '';
  const match = matchId ? store.select.match(matchId) : null;

  if (!match) {
    root.innerHTML = ui.emptyState({
      icon: 'graph-up',
      title: 'Game not found',
      message: 'Open a game from the bracket, schedule, or matchup list.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('matches')}">Browse matchups</a>`,
    });
    return;
  }

  let side = 'all';
  let searchTerm = '';
  let sortKey = 'points';
  let sortDir = 'desc';

  function draw() {
    const current = store.select.match(matchId);
    if (!current) {
      root.innerHTML = ui.emptyState({
        icon: 'graph-up',
        title: 'Game not found',
        message: 'This game disappeared when the demo was reset.',
        action: `<a class="hf-btn hf-btn--primary" href="${route('matches')}">Browse matchups</a>`,
      });
      return;
    }

    const tournament = store.select.tournament(current.tournamentId);
    const team1 = current.team1Id ? store.select.team(current.team1Id) : null;
    const team2 = current.team2Id ? store.select.team(current.team2Id) : null;
    const role = store.getRole();
    const canScore =
      store.can('score.save') &&
      current.status === 'scheduled' &&
      Boolean(team1) &&
      Boolean(team2);
    const canCorrect =
      store.can('score.correct') &&
      (current.status === 'final' || current.status === 'forfeit');
    const court = tournament?.courts.find((item) => item.id === current.courtId);
    const hasTeams = Boolean(team1) && Boolean(team2);
    const hasStats = [team1, team2].some(
      (team) => team && boxScoreRows(current, team).length > 0
    );

    const quarters = current.boxScore?.quarters;
    const hasQuarters =
      quarters &&
      Array.isArray(quarters.team1) &&
      Array.isArray(quarters.team2);

    root.innerHTML = `
      ${components.pageHeader({
        eyebrow: `${tournament?.division || 'Tournament'} · ${tournament?.name || 'HoopFlow'}`,
        title: 'Game stats',
        description: `${current.roundLabel} · ${court?.name || 'Court TBD'} · ${current.scheduledAt ? `${formatDate(current.scheduledAt)} at ${formatTime(current.scheduledAt)}` : 'Tip-off TBD'}.`,
        actions: `
          <a class="hf-btn" href="${route('bracket', { id: current.tournamentId, match: current.id })}" data-testid="stats-bracket"><i class="bi bi-diagram-3"></i> Bracket</a>
          <a class="hf-btn" href="${route('schedule', { id: current.tournamentId })}" data-testid="stats-schedule"><i class="bi bi-calendar3"></i> Schedule</a>
          <a class="hf-btn" href="${route('matches', { id: current.tournamentId })}" data-testid="stats-matches"><i class="bi bi-list-ul"></i> Matchups</a>
        `,
      })}

      ${components.scoreboard(current)}

      ${
        current.correction
          ? `<div class="hf-card hf-card--flat hf-mb-3" data-testid="stats-correction">
               <div class="hf-between hf-wrap">
                 <span class="hf-chip hf-chip--gold"><i class="bi bi-arrow-repeat"></i> Corrected score</span>
                 <span class="hf-small hf-muted">${escapeHtml(formatDate(current.correction.correctedAt))} · ${escapeHtml(formatTime(current.correction.correctedAt))}</span>
               </div>
               <p class="hf-small hf-mt-1 hf-mb-0">
                 <strong>${current.correction.previousScore1}–${current.correction.previousScore2}</strong>
                 →
                 <strong>${current.score1}–${current.score2}</strong>
                 · ${escapeHtml(current.correction.reason)}
               </p>
             </div>`
          : ''
      }

      <div class="hf-card hf-card--flat hf-mb-3" data-testid="stats-controls">
        <div class="hf-between hf-wrap">
          <div>
            <span class="hf-kpi-label">Official result</span>
            <p class="hf-small hf-muted hf-mb-0">
              ${
                current.status === 'scheduled'
                  ? hasTeams
                    ? 'Both teams are set. Enter the final score to advance the winner and generate the box score.'
                    : 'Waiting for both semifinal results before this game can be played.'
                  : `Recorded as ${current.status}. Corrections require a reason and are written to the audit trail.`
              }
            </p>
          </div>
          <div class="hf-btn-row">
            ${canScore ? `<button type="button" class="hf-btn hf-btn--primary" data-open-score data-testid="stats-enter-score"><i class="bi bi-pencil-square"></i> Enter final score</button>` : ''}
            ${canCorrect ? `<button type="button" class="hf-btn hf-btn--danger" data-open-correction data-testid="stats-correct-score"><i class="bi bi-arrow-repeat"></i> Correct score</button>` : ''}
            ${!canScore && !canCorrect ? `<span class="hf-chip hf-chip--slate"><i class="bi bi-eye"></i> Read-only ${escapeHtml(role)} view</span>` : ''}
          </div>
        </div>
      </div>

      ${
        hasQuarters
          ? `<section class="hf-card hf-mb-3" data-testid="stats-quarters">
        <div class="hf-between hf-wrap hf-mb-2">
          <div>
            <span class="hf-eyebrow">Quarter breakdown</span>
            <h2 class="hf-section-title hf-mb-0" data-testid="quarter-breakdown">By quarter</h2>
          </div>
        </div>
        <div class="hf-table-wrap" data-testid="stats-quarter-breakdown">
          <table class="hf-table">
            <thead>
              <tr>
                <th scope="col">Team</th>
                <th scope="col" class="is-num">Q1</th>
                <th scope="col" class="is-num">Q2</th>
                <th scope="col" class="is-num">Q3</th>
                <th scope="col" class="is-num">Q4</th>
                <th scope="col" class="is-num">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr data-testid="stats-quarters-team1">
                <td><strong>${escapeHtml(team1?.name || 'Team 1')}</strong></td>
                ${[0, 1, 2, 3]
                  .map((index) => `<td class="is-num">${escapeHtml(String(Number(quarters.team1[index]) || 0))}</td>`)
                  .join('')}
                <td class="is-num"><strong>${escapeHtml(String(quarters.team1.slice(0, 4).reduce((sum, value) => sum + (Number(value) || 0), 0)))}</strong></td>
              </tr>
              <tr data-testid="stats-quarters-team2">
                <td><strong>${escapeHtml(team2?.name || 'Team 2')}</strong></td>
                ${[0, 1, 2, 3]
                  .map((index) => `<td class="is-num">${escapeHtml(String(Number(quarters.team2[index]) || 0))}</td>`)
                  .join('')}
                <td class="is-num"><strong>${escapeHtml(String(quarters.team2.slice(0, 4).reduce((sum, value) => sum + (Number(value) || 0), 0)))}</strong></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>`
          : ''
      }

      <section class="hf-card hf-mb-3" data-testid="stats-box-score">
        <div class="hf-between hf-wrap hf-mb-2">
          <div>
            <span class="hf-eyebrow">Official box score</span>
            <h2 class="hf-section-title hf-mb-0">Player stats</h2>
          </div>
          <div class="hf-filter-row" data-side-filters data-testid="stats-side-filters">
            <button type="button" class="hf-chip hf-chip--primary" data-side="all" aria-pressed="true" data-testid="stats-side-all">Both teams</button>
            ${team1 ? `<button type="button" class="hf-chip hf-chip--slate" data-side="team1" aria-pressed="false" data-testid="stats-side-team1">${escapeHtml(team1.shortName)}</button>` : ''}
            ${team2 ? `<button type="button" class="hf-chip hf-chip--slate" data-side="team2" aria-pressed="false" data-testid="stats-side-team2">${escapeHtml(team2.shortName)}</button>` : ''}
          </div>
        </div>

        <div class="hf-field hf-mb-2">
          <label class="hf-label" for="statsSearch">Search player</label>
          <input class="hf-input" id="statsSearch" type="search" placeholder="Player name"
                 value="${escapeHtml(searchTerm)}" data-testid="stats-player-search">
        </div>

        <div id="statsBody" data-testid="stats-body"></div>

        ${
          !hasStats && current.status !== 'scheduled'
            ? `<p class="hf-small hf-muted hf-mt-2 hf-mb-0">${hasTeams ? 'No player stats were recorded for this game. The team score is still official.' : 'Player stats appear once both teams are decided and a result is recorded.'}</p>`
            : ''
        }
      </section>
    `;

    const search = root.querySelector('#statsSearch');
    search.addEventListener(
      'input',
      ui.debounce(() => {
        searchTerm = search.value;
        paintStats();
      }, 120)
    );

    root.querySelectorAll('[data-side]').forEach((button) => {
      button.addEventListener('click', () => {
        side = button.dataset.side;
        paintStats();
      });
    });

    paintStats();
  }

  function sortMark(key) {
    if (sortKey !== key) return '<i class="bi bi-arrow-down-up hf-muted" aria-hidden="true"></i>';
    return `<i class="bi bi-caret-${sortDir === 'asc' ? 'up' : 'down'}-fill" aria-hidden="true"></i>`;
  }

  function tableFor(team, sideKey) {
    const current = store.select.match(matchId);
    const term = searchTerm.trim().toLowerCase();
    const rows = sortBoxScore(
      boxScoreRows(current, team).filter((row) => !term || row.name.toLowerCase().includes(term)),
      sortKey,
      sortDir
    );
    if (!rows.length) {
      return `
        <section class="hf-mb-3" data-testid="stats-team-${escapeHtml(team.id)}">
          <h3 class="hf-section-title">${escapeHtml(team.name)}</h3>
          ${ui.emptyState({
            icon: 'person-x',
            title: term ? 'No players match' : 'No player stats',
            message: term
              ? 'Clear the player search to see the full rotation.'
              : 'This team does not have a recorded box score for this game.',
          })}
        </section>
      `;
    }
    const totals = boxScoreTotals(rows);
    return `
      <section class="hf-mb-3" data-testid="stats-team-${escapeHtml(team.id)}">
        <div class="hf-between hf-mb-1">
          <span class="hf-flex hf-items-center hf-gap-2">
            ${components.shield(team)}
            <h3 class="hf-section-title hf-mb-0">${escapeHtml(team.name)}</h3>
          </span>
          <span class="hf-chip hf-chip--slate">${rows.length} players</span>
        </div>
        <div class="hf-table-wrap">
          <table class="hf-table">
            <thead>
              <tr>
                ${COLUMNS.map((column) => {
                  if (!column.sortable) {
                    return `<th scope="col" class="${column.numeric ? 'is-num' : ''}">${escapeHtml(column.label)}</th>`;
                  }
                  return `
                    <th scope="col" class="is-num">
                      <button type="button" class="hf-section-link" data-sort="${column.key}"
                              data-testid="stats-sort-${sideKey}-${column.key}">
                        ${escapeHtml(column.label)} ${sortMark(column.key)}
                      </button>
                    </th>
                  `;
                }).join('')}
              </tr>
            </thead>
            <tbody>
              ${rows
                .map(
                  (row) => `
                    <tr data-testid="stats-row-${sideKey}-${escapeHtml(row.playerId)}">
                      <td class="is-num">${row.number ?? '–'}</td>
                      <td>
                        <strong>${escapeHtml(row.name)}</strong>
                        <span class="hf-small hf-muted" style="display: block">${escapeHtml(row.position || 'Player')}</span>
                      </td>
                      <td class="is-num">${row.points}</td>
                      <td class="is-num">${row.assists}</td>
                      <td class="is-num">${row.rebounds}</td>
                    </tr>
                  `
                )
                .join('')}
            </tbody>
            <tfoot>
              <tr>
                <td colspan="2"><strong>Team totals</strong></td>
                <td class="is-num"><strong>${totals.points}</strong></td>
                <td class="is-num"><strong>${totals.assists}</strong></td>
                <td class="is-num"><strong>${totals.rebounds}</strong></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    `;
  }

  function paintStats() {
    const current = store.select.match(matchId);
    if (!current) return;
    const team1 = current.team1Id ? store.select.team(current.team1Id) : null;
    const team2 = current.team2Id ? store.select.team(current.team2Id) : null;

    root.querySelectorAll('[data-side]').forEach((button) => {
      const active = button.dataset.side === side;
      button.classList.toggle('hf-chip--primary', active);
      button.classList.toggle('hf-chip--slate', !active);
      button.setAttribute('aria-pressed', String(active));
    });

    const body = root.querySelector('#statsBody');
    if (!body) return;

    if (!team1 || !team2) {
      body.innerHTML = ui.emptyState({
        icon: 'hourglass-split',
        title: 'Teams still pending',
        message: 'The box score unlocks when both teams are known.',
      });
      return;
    }

    if (side === 'team1') {
      body.innerHTML = tableFor(team1, 'team1');
      attachSortButtons();
      return;
    }
    if (side === 'team2') {
      body.innerHTML = tableFor(team2, 'team2');
      attachSortButtons();
      return;
    }
    body.innerHTML = tableFor(team1, 'team1') + tableFor(team2, 'team2');
    attachSortButtons();
  }

  function attachSortButtons() {
    root.querySelectorAll('[data-sort]').forEach((button) => {
      button.addEventListener('click', () => {
        const nextKey = button.dataset.sort;
        if (sortKey === nextKey) {
          sortDir = sortDir === 'desc' ? 'asc' : 'desc';
        } else {
          sortKey = nextKey;
          sortDir = 'desc';
        }
        paintStats();
      });
    });
  }

  root.addEventListener('click', (event) => {
    if (event.target.closest('[data-open-score]')) {
      openScoreConsole({ store, ui, matchId, onDone: draw });
      return;
    }
    if (event.target.closest('[data-open-correction]')) {
      openCorrectionDialog({ store, ui, matchId, onDone: draw });
    }
  });

  draw();
}