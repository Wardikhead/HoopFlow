import { route } from '../routes.js';
import { escapeHtml, formatDate, formatTime } from '../ui.js';
import { guardRole } from '../guards.js';
// Note: sortBoxScore is intentionally not used here. The in-game stats tables are
// editable forms indexed by player position, so reordering rows would scramble the
// input-to-player mapping. Sorting remains available on the read-only game-stats page.

function normalizeQuarters(value) {
  const arr = Array.isArray(value) ? value : [];
  const normalized = arr.map((entry) => {
    const number = Number(entry);
    return Number.isFinite(number) && number >= 0 ? Math.floor(number) : 0;
  });
  const out = [...normalized];
  while (out.length < 4) out.push(0);
  return out.slice(0, 9);
}

function padArray(value, length) {
  const out = [...value];
  while (out.length < length) out.push(0);
  return out.slice(0, length);
}

function defaultStats(team, total) {
  const count = (team.players || []).length || 1;
  const safeTotal = Number.isFinite(Number(total)) ? Number(total) : 0;
  const base = Math.floor(safeTotal / count);
  const remainder = safeTotal % count;
  return (team.players || []).map((player, index) => ({
    playerId: player.id,
    points: base + (index < remainder ? 1 : 0),
    fgm: 0, fga: 0, tpm: 0, tpa: 0, ftm: 0, fta: 0,
    reb: (index * 2 + 2) % 9,
    ast: (index * 3 + 1) % 7,
    stl: 0, blk: 0, to: 0, pf: 0,
  }));
}

function normalizeStats(existing, team, total) {
  if (Array.isArray(existing) && existing.length === (team.players || []).length && existing.length > 0) {
    return existing.map((entry, index) => {
      const player = (team.players || [])[index];
      return {
        playerId: entry?.playerId || player?.id,
        points: Number(entry?.points) || 0,
        fgm: Number(entry?.fgm) || 0,
        fga: Number(entry?.fga) || 0,
        tpm: Number(entry?.tpm) || 0,
        tpa: Number(entry?.tpa) || 0,
        ftm: Number(entry?.ftm) || 0,
        fta: Number(entry?.fta) || 0,
        reb: Number(entry?.reb ?? entry?.rebounds) || 0,
        ast: Number(entry?.ast ?? entry?.assists) || 0,
        stl: Number(entry?.stl) || 0,
        blk: Number(entry?.blk) || 0,
        to: Number(entry?.to) || 0,
        pf: Number(entry?.pf) || 0,
        quarters: Array.isArray(entry?.quarters) ? entry.quarters : [],
      };
    });
  }
  return defaultStats(team, total);
}

function toInt(value) {
  const number = Number(value);
  if (!Number.isFinite(number) || !Number.isInteger(number) || number < 0) return null;
  return number;
}

function statInput(side, index, field, value, qIndex = null) {
  const idSuffix = qIndex === null ? `${side}-${index}` : `pq-${side}-${index}-${qIndex}`;
  const dataAttrs = qIndex === null
    ? `data-team="${side}" data-idx="${index}" data-stat="${field}" data-field="${field}"`
    : `data-pq-side="${side}" data-pq-player="${index}" data-pq-q="${qIndex}" data-pq-stat="${field}" data-field="${field}"`;
  const testId = qIndex === null
    ? `ingame-${field}-${side}-${index}`
    : `ingame-pq-${field}-${side}-${index}-${qIndex}`;
  return `<input class="hf-input" type="number" min="0" value="${escapeHtml(value)}"
    id="ingame-${idSuffix}-${field}"
    ${dataAttrs}
    data-testid="${testId}">`;
}

export function render({ store, ui, components, root, theme }) {
  if (!guardRole({ store, ui, root, roles: ['organizer'] })) return;

  function renderMatchPicker() {
    const tournaments = store.select.tournaments();
    root.innerHTML = `
      ${components.pageHeader({
        eyebrow: 'In-game management',
        title: 'Select a match to manage',
        description: 'Filter matchups by tournament, status, and team, then choose one to enter quarter scores and player stats.',
      })}

      <div class="hf-card hf-mb-3">
        <div class="hf-form-grid">
          <div class="hf-field">
            <label class="hf-label" for="gameManageTournamentFilter">Tournament</label>
            <select id="gameManageTournamentFilter" class="hf-select"
                    data-testid="ingame-tournament-filter" data-tournament-filter data-match-tournament-filter>
              <option value="all">All tournaments</option>
              ${tournaments.map((tournament) => `<option value="${escapeHtml(tournament.id)}">${escapeHtml(tournament.name)}</option>`).join('')}
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="gameManageStatusFilter">Status</label>
            <select id="gameManageStatusFilter" class="hf-select"
                    data-testid="ingame-status-filter" data-status-filter data-match-status-filter>
              <option value="all">All</option>
              <option value="scheduled">Scheduled</option>
              <option value="final">Final</option>
            </select>
          </div>
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="gameManageTeamSearch">Team</label>
            <input id="gameManageTeamSearch" class="hf-input" type="search" placeholder="Team name"
                   data-testid="ingame-team-search" data-team-search data-match-team-search>
          </div>
        </div>
      </div>

      <div id="gameManageMatchList" data-testid="ingame-match-list" data-match-list></div>
    `;

    const tournamentFilter = root.querySelector('#gameManageTournamentFilter');
    const statusFilter = root.querySelector('#gameManageStatusFilter');
    const teamSearch = root.querySelector('#gameManageTeamSearch');
    const list = root.querySelector('#gameManageMatchList');

    function renderList() {
      const tournamentValue = tournamentFilter.value;
      const statusValue = statusFilter.value;
      const term = teamSearch.value.trim().toLowerCase();
      const filtered = tournaments
        .filter((tournament) => tournamentValue === 'all' || tournament.id === tournamentValue)
        .flatMap((tournament) => store.select.matches(tournament.id))
        .filter((match) => {
          const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
          const team2 = match.team2Id ? store.select.team(match.team2Id) : null;
          const haystack = `${team1?.name || ''} ${team2?.name || ''}`.toLowerCase();
          return (
            (statusValue === 'all' || match.status === statusValue) &&
            (!term || haystack.includes(term))
          );
        })
        .sort((a, b) => String(a.scheduledAt || '9999').localeCompare(String(b.scheduledAt || '9999')));

      if (!filtered.length) {
        list.innerHTML = ui.emptyState({
          icon: 'search',
          title: 'No matches match',
          message: 'Clear the tournament, status, or team filter and try again.',
        });
        return;
      }

      list.innerHTML = `<div class="hf-grid hf-grid--2">${filtered
        .map((match) => components.matchCard(match, { href: route('gameManage', { match: match.id }) }))
        .join('')}</div>`;
    }

    tournamentFilter.addEventListener('change', renderList);
    statusFilter.addEventListener('change', renderList);
    teamSearch.addEventListener('input', ui.debounce(renderList, 120));
    renderList();
  }

  const params = new URLSearchParams(window.location.search);
  const matchId = params.get('match') || '';

  if (!matchId) {
    renderMatchPicker();
    return;
  }

  let quarters1 = [];
  let quarters2 = [];
  let stats1 = [];
  let stats2 = [];
  let team1 = null;
  let team2 = null;
  let match = null;

  let search1 = '';
  let search2 = '';
  let teamFilter = 'all';
  let activeQuarter = 0;
  let loaded = false;

  function normalizeTeamFilterValue(value) {
    const v = String(value || '').trim().toLowerCase();
    if (v === '1' || v === 'team-1' || v === 'team1') return 'team1';
    if (v === '2' || v === 'team-2' || v === 'team2') return 'team2';
    return 'all';
  }

  function applySearch(side) {
    const term = (side === 1 ? search1 : search2).trim().toLowerCase();
    const input = root.querySelector(`[data-player-search="${side}"]`);
    if (input) input.value = side === 1 ? search1 : search2;
    root.querySelectorAll(`tr[data-ingame-row="${side}"]`).forEach((row) => {
      const name = row.dataset.playerName || '';
      row.hidden = Boolean(term) && !name.includes(term);
    });
  }

  function applyTeamFilter() {
    const show1 = teamFilter === 'all' || teamFilter === 'team1';
    const show2 = teamFilter === 'all' || teamFilter === 'team2';
    [1, 2].forEach((side) => {
      const section = root.querySelector(`[data-testid="ingame-stats-${side}"]`);
      if (!section) return;
      const hide = side === 1 ? !show1 : !show2;
      section.hidden = hide;
    });

    const select = root.querySelector('[data-ingame-team-filter], [data-team-filter]');
    if (select) {
      select.value = teamFilter === 'team1' ? 'team1' : teamFilter === 'team2' ? 'team2' : 'all';
    }
    root.querySelectorAll('[data-side]').forEach((button) => {
      const active = normalizeTeamFilterValue(button.dataset.side) === teamFilter;
      button.classList.toggle('hf-chip--primary', active);
      button.classList.toggle('hf-chip--slate', !active);
      button.setAttribute('aria-pressed', String(active));
    });
  }

  function setTeamFilter(value) {
    teamFilter = normalizeTeamFilterValue(value);
    applyTeamFilter();
  }

  function teamQuarterPanel(side, team, quarters, finalScore) {
    const labels = quarters.map((_, index) => {
      if (index < 4) return `Q${index + 1}`;
      return `OT${index - 3}`;
    });
    return `
      <div class="hf-card hf-card--flat" data-team-panel="${side}">
        <span class="hf-flex hf-items-center hf-gap-2">
          <span class="hf-shield" style="--team-color: ${escapeHtml(team.avatarColor)}" aria-hidden="true">${escapeHtml(team.shortName)}</span>
          <strong>${escapeHtml(team.name)}</strong>
        </span>
        <div class="hf-form-grid hf-mt-2" data-qgrid="${side}">
          ${quarters
            .map(
              (quarter, index) => `
                <div class="hf-field">
                  <label class="hf-label" for="ingameQ${side}_${index}">${escapeHtml(labels[index])}</label>
                  <input class="hf-input" id="ingameQ${side}_${index}" type="number" min="0"
                         value="${escapeHtml(quarter)}" data-q="${side}" data-qi="${index}"
                         data-testid="ingame-q-${side}-${index}">
                </div>
              `
            )
            .join('')}
        </div>
        <p class="hf-small hf-mt-1 hf-mb-1">Running total: <strong data-qtotal="${side}">${quarters.reduce((sum, value) => sum + value, 0)}</strong></p>
        <div class="hf-field">
          <label class="hf-label" for="ingameFinal${side}">Final score</label>
          <input class="hf-input" id="ingameFinal${side}" type="number" min="0"
                 value="${escapeHtml(finalScore)}" data-final="${side}"
                 data-testid="ingame-final-${side}">
        </div>
      </div>
    `;
  }

  function teamStatsTable(side, team, stats, quarters) {
    const quarterCount = Math.max(4, quarters?.length || 4);
    const qLabel = (i) => (i < 4 ? `Q${i + 1}` : `OT${i - 3}`);
    const activeQ = Math.min(activeQuarter, quarterCount - 1);
    const activeLabel = qLabel(activeQ);

    const rowsHtml = stats
      .map((entry, index) => {
        const player = (team.players || [])[index];
        const qStats = Array.isArray(entry.quarters) && entry.quarters.length
          ? entry.quarters
          : [];
        const q = qStats[activeQ] || { fgm: 0, fga: 0, tpm: 0, tpa: 0, ftm: 0, fta: 0, reb: 0, ast: 0, stl: 0, blk: 0, to: 0, pf: 0, points: 0 };
        const pts = (Number(q.fgm) || 0) * 2 + (Number(q.tpm) || 0) * 3 + (Number(q.ftm) || 0);
        const totalPts = (Number(entry.fgm) || 0) * 2 + (Number(entry.tpm) || 0) * 3 + (Number(entry.ftm) || 0);
        return `
          <tr data-ingame-row="${side}" data-player-name="${escapeHtml(String(player?.name || '').toLowerCase())}" data-testid="ingame-stat-row-${side}-${index}">
            <td><strong>${escapeHtml(player?.name || 'Unknown player')}</strong></td>
            <td>
              ${statInput(side, index, 'fgm', q.fgm, activeQ)} / ${statInput(side, index, 'fga', q.fga, activeQ)}
            </td>
            <td>
              ${statInput(side, index, 'tpm', q.tpm, activeQ)} / ${statInput(side, index, 'tpa', q.tpa, activeQ)}
            </td>
            <td>
              ${statInput(side, index, 'ftm', q.ftm, activeQ)} / ${statInput(side, index, 'fta', q.fta, activeQ)}
            </td>
            <td>${statInput(side, index, 'reb', q.reb, activeQ)}</td>
            <td>${statInput(side, index, 'ast', q.ast, activeQ)}</td>
            <td>${statInput(side, index, 'stl', q.stl, activeQ)}</td>
            <td>${statInput(side, index, 'blk', q.blk, activeQ)}</td>
            <td>${statInput(side, index, 'to', q.to, activeQ)}</td>
            <td>${statInput(side, index, 'pf', q.pf, activeQ)}</td>
            <td class="is-num"><span data-points="${side}-${index}">${pts}</span></td>
            <td class="is-num hf-muted">${totalPts}</td>
          </tr>
        `;
      })
      .join('');

    return `
      <section class="hf-card hf-mb-3" data-testid="ingame-stats-${side}">
        <div class="hf-between hf-wrap hf-mb-2">
          <h3 class="hf-section-title hf-mb-0">${escapeHtml(team.name)} player stats — ${escapeHtml(activeLabel)}</h3>
        </div>
        <div class="hf-field hf-mb-2">
          <label class="hf-label" for="ingameSearch${side}">Search player</label>
          <input class="hf-input" id="ingameSearch${side}" type="search" placeholder="Player name"
                 value="${escapeHtml(side === 1 ? search1 : search2)}"
                 data-testid="ingame-player-search-${side}" data-player-search="${side}" data-ingame-search="${side}">
        </div>
        <div class="hf-table-wrap">
          <table class="hf-table">
            <thead>
              <tr>
                <th>Player</th>
                <th>FG (M/A)</th>
                <th>3PT (M/A)</th>
                <th>FT (M/A)</th>
                <th>REB</th>
                <th>AST</th>
                <th>STL</th>
                <th>BLK</th>
                <th>TO</th>
                <th>PF</th>
                <th>PTS (${escapeHtml(activeLabel)})</th>
                <th>Totals</th>
              </tr>
            </thead>
            <tbody>
              ${rowsHtml}
            </tbody>
          </table>
        </div>
        <p class="hf-small hf-muted hf-mt-1">Totals are the sum of all quarters. Editing a total field switches to manual mode.</p>
      </section>
    `;
  }
  function setError(message) {
    const errorElement = root.querySelector('[data-ingame-error]');
    if (!errorElement) return;
    errorElement.hidden = !message;
    errorElement.textContent = message || '';
  }

  function fieldToInput(field) {
    if (!field) return null;
    const match = String(field).match(/^team([12])\.(\d+)\.(\w+)$/);
    if (match) {
      return root.querySelector(
        `[data-team="${match[1]}"][data-idx="${match[2]}"][data-stat="${match[3]}"]`
      );
    }
    if (field === 'quarters') return root.querySelector('[data-q="1"][data-qi="0"]');
    if (field === 'score') return root.querySelector('[data-final="1"]');
    return null;
  }

  function applyStoreError(error) {
    const input = fieldToInput(error?.field);
    if (input) {
      ui.setFieldError(input, error.message);
      input.focus();
    }
    setError(error?.message || 'Save failed.');
  }

  function collectQuarters(side) {
    const inputs = [...root.querySelectorAll(`[data-q="${side}"]`)].sort(
      (a, b) => Number(a.dataset.qi) - Number(b.dataset.qi)
    );
    const values = [];
    for (const input of inputs) {
      ui.clearFieldError(input);
      const value = toInt(input?.value);
      if (value === null) {
        ui.setFieldError(input, 'Enter a whole number.');
        return null;
      }
      values.push(value);
    }
    return values;
  }

  function collectFinal(side) {
    const input = root.querySelector(`[data-final="${side}"]`);
    ui.clearFieldError(input);
    const value = toInt(input?.value);
    if (value === null) {
      ui.setFieldError(input, 'Enter a whole number.');
      return null;
    }
    return value;
  }

  function collectStats(side) {
    const arr = side === 1 ? stats1 : stats2;
    // validate each entry
    for (let index = 0; index < arr.length; index += 1) {
      const s = arr[index];
      for (const k of ['fgm','fga','tpm','tpa','ftm','fta','reb','ast','stl','blk','to','pf']) {
        const v = Number(s?.[k] ?? 0);
        if (!Number.isInteger(v) || v < 0) {
          const input = root.querySelector(`[data-pq-side="${side}"][data-pq-player="${index}"][data-pq-q="0"][data-pq-stat="${k}"]`);
          if (input) ui.setFieldError(input, 'Enter a whole number.');
          setError('Stats must be whole numbers.');
          return null;
        }
      }
      if ((s.fgm||0) > (s.fga||0)) { setError('Made cannot exceed attempted (FG).'); return null; }
      if ((s.tpm||0) > (s.tpa||0)) { setError('Made cannot exceed attempted (3PT).'); return null; }
      if ((s.ftm||0) > (s.fta||0)) { setError('Made cannot exceed attempted (FT).'); return null; }
      if (Array.isArray(s?.quarters)) {
        for (let qi = 0; qi < s.quarters.length; qi += 1) {
          const q = s.quarters[qi] || {};
          for (const k of ['fgm','fga','tpm','tpa','ftm','fta','reb','ast','stl','blk','to','pf']) {
            const v = Number(q?.[k] ?? 0);
            if (!Number.isInteger(v) || v < 0) { setError('Quarter stats must be whole numbers.'); return null; }
          }
          if ((q.fgm||0) > (q.fga||0) || (q.tpm||0) > (q.tpa||0) || (q.ftm||0) > (q.fta||0)) {
            setError('Made cannot exceed attempted.'); return null;
          }
        }
      }
    }
    // return deep clone
    return arr.map((s) => ({
      ...s,
      quarters: Array.isArray(s.quarters) ? s.quarters.map((q) => ({ ...q })) : [],
    }));
  }
  function updateQuarterTotals(side) {
    const inputs = [...root.querySelectorAll(`[data-q="${side}"]`)];
    const sum = inputs.reduce((acc, input) => {
      const value = Number(input.value);
      return acc + (Number.isFinite(value) && value >= 0 ? Math.floor(value) : 0);
    }, 0);
    const total = root.querySelector(`[data-qtotal="${side}"]`);
    if (total) total.textContent = String(sum);
    const finalInput = root.querySelector(`[data-final="${side}"]`);
    if (finalInput) finalInput.value = String(sum);
  }

  function rebuildQuarterGrid(side, quarters) {
    const grid = root.querySelector(`[data-qgrid="${side}"]`);
    if (!grid) return;
    const labels = quarters.map((_, index) => (index < 4 ? `Q${index + 1}` : `OT${index - 3}`));
    grid.innerHTML = quarters
      .map(
        (quarter, index) => `
          <div class="hf-field">
            <label class="hf-label" for="ingameQ${side}_${index}">${escapeHtml(labels[index])}</label>
            <input class="hf-input" id="ingameQ${side}_${index}" type="number" min="0"
                   value="${escapeHtml(quarter)}" data-q="${side}" data-qi="${index}"
                   data-testid="ingame-q-${side}-${index}">
          </div>
        `
      )
      .join('');
  }

  function bindQuarterInputs() {
    root.querySelectorAll('[data-q]').forEach((input) => {
      if (input.dataset.bound === '1') return;
      input.dataset.bound = '1';
      input.addEventListener('input', () => {
        const side = input.dataset.q;
        updateQuarterTotals(side);
        setError('');
      });
    });
  }

  function bindPlayerQuarterInputs() {
    root.querySelectorAll('[data-pq-stat]').forEach((input) => {
      if (input.dataset.bound === '1') return;
      input.dataset.bound = '1';
      input.addEventListener('input', () => {
        const side = input.dataset.pqSide;
        const playerIndex = input.dataset.pqPlayer;
        const qi = input.dataset.pqQ;
        // recompute that quarter's PTS
        const fgm = Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${playerIndex}"][data-pq-q="${qi}"][data-pq-stat="fgm"]`)?.value) || 0;
        const tpm = Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${playerIndex}"][data-pq-q="${qi}"][data-pq-stat="tpm"]`)?.value) || 0;
        const ftm = Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${playerIndex}"][data-pq-q="${qi}"][data-pq-stat="ftm"]`)?.value) || 0;
        const ptsEl = root.querySelector(`[data-pq-points="${side}-${playerIndex}-${qi}"]`);
        if (ptsEl) ptsEl.textContent = String(fgm * 2 + tpm * 3 + ftm);
        // re-sum player totals from quarters
        recomputePlayerTotals(side, playerIndex);
        setError('');
      });
    });
  }

  function recomputePlayerTotals(side, playerIndex) {
    const team = side === 1 ? team1 : team2;
    const players = team?.players || [];
    const statsArr = side === 1 ? stats1 : stats2;
    const entry = statsArr[playerIndex];
    // update in-memory entry.quarters[activeQuarter] from DOM (already done in input handler, but recompute totals)
    if (entry && Array.isArray(entry.quarters) && entry.quarters[activeQuarter]) {
      const q = entry.quarters[activeQuarter];
      q.points = (Number(q.fgm) || 0) * 2 + (Number(q.tpm) || 0) * 3 + (Number(q.ftm) || 0);
    }
    // recompute player totals from all quarters in memory
    if (entry && Array.isArray(entry.quarters) && entry.quarters.length) {
      const d = sumPlayerQuarters(entry.quarters);
      entry.points = d.points;
      entry.fgm = d.fgm; entry.fga = d.fga;
      entry.tpm = d.tpm; entry.tpa = d.tpa;
      entry.ftm = d.ftm; entry.fta = d.fta;
      entry.reb = d.reb; entry.ast = d.ast;
      entry.stl = d.stl; entry.blk = d.blk;
      entry.to = d.to; entry.pf = d.pf;
    }
    // update this player's PTS display (active quarter)
    const pqFgm = Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${playerIndex}"][data-pq-q="${activeQuarter}"][data-pq-stat="fgm"]`)?.value) || 0;
    const pqTpm = Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${playerIndex}"][data-pq-q="${activeQuarter}"][data-pq-stat="tpm"]`)?.value) || 0;
    const pqFtm = Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${playerIndex}"][data-pq-q="${activeQuarter}"][data-pq-stat="ftm"]`)?.value) || 0;
    const pointsEl = root.querySelector(`[data-points="${side}-${playerIndex}"]`);
    if (pointsEl) pointsEl.textContent = String(pqFgm * 2 + pqTpm * 3 + pqFtm);

    // recompute active quarter's team score from all players
    let qFgm = 0, qTpm = 0, qFtm = 0;
    (team?.players || []).forEach((_, pIdx) => {
      qFgm += Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${pIdx}"][data-pq-q="${activeQuarter}"][data-pq-stat="fgm"]`)?.value) || 0;
      qTpm += Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${pIdx}"][data-pq-q="${activeQuarter}"][data-pq-stat="tpm"]`)?.value) || 0;
      qFtm += Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${pIdx}"][data-pq-q="${activeQuarter}"][data-pq-stat="ftm"]`)?.value) || 0;
    });
    const qSum = qFgm * 2 + qTpm * 3 + qFtm;
    const qInput = root.querySelector(`[data-q="${side}"][data-qi="${activeQuarter}"]`);
    if (qInput) qInput.value = String(qSum);

    updateQuarterTotals(side);
  }

  function currentQuarters(side) {
    return [...root.querySelectorAll(`[data-q="${side}"]`)]
      .sort((a, b) => Number(a.dataset.qi) - Number(b.dataset.qi))
      .map((input) => {
        const value = Number(input.value);
        return Number.isFinite(value) && value >= 0 ? Math.floor(value) : 0;
      });
  }

  async function addOvertime() {
    const q1 = currentQuarters(1);
    const q2 = currentQuarters(2);
    if (q1.length >= 9) {
      ui.toast('Maximum of 5 overtime periods.', { tone: 'warning', title: 'Overtime limit' });
      return;
    }
    q1.push(0);
    q2.push(0);
    rebuildQuarterGrid(1, q1);
    rebuildQuarterGrid(2, q2);
    bindQuarterInputs();
    updateQuarterTotals(1);
    updateQuarterTotals(2);
  }

  async function removeLastOvertime() {
    const q1 = currentQuarters(1);
    const q2 = currentQuarters(2);
    if (q1.length <= 4) return;
    const last1 = q1[q1.length - 1] || 0;
    const last2 = q2[q2.length - 1] || 0;
    if (last1 !== 0 || last2 !== 0) {
      const ok = await ui.confirm({
        title: 'Remove last overtime?',
        message: 'The last overtime period has a non-zero score. Remove it anyway?',
        confirmLabel: 'Remove',
        tone: 'danger',
      });
      if (!ok) return;
    }
    q1.pop();
    q2.pop();
    rebuildQuarterGrid(1, q1);
    rebuildQuarterGrid(2, q2);
    bindQuarterInputs();
    updateQuarterTotals(1);
    updateQuarterTotals(2);
  }

  function draw() {
    match = store.select.match(matchId);
    if (!match) {
      root.innerHTML = ui.emptyState({
        icon: 'clipboard',
        title: 'Select a match to manage',
        message: 'That match could not be found. Choose another matchup.',
        action: `<a class="hf-btn hf-btn--primary" href="${route('gameManage')}" data-testid="manage-select-match">Browse matchups</a>`,
      });
      return;
    }

    team1 = match.team1Id ? store.select.team(match.team1Id) : null;
    team2 = match.team2Id ? store.select.team(match.team2Id) : null;

    if (!team1 || !team2) {
      root.innerHTML = ui.emptyState({
        icon: 'hourglass-split',
        title: 'Teams still pending',
        message: 'Both teams must be known before managing this game.',
        action: `<a class="hf-btn hf-btn--primary" href="${route('matches')}">Browse matchups</a>`,
      });
      return;
    }

    if (!loaded) {
      const quarters1Init = normalizeQuarters(match.boxScore?.quarters?.team1);
      const quarters2Init = normalizeQuarters(match.boxScore?.quarters?.team2);
      const maxLen = Math.max(quarters1Init.length, quarters2Init.length, 4);
      quarters1 = padArray(quarters1Init, maxLen);
      quarters2 = padArray(quarters2Init, maxLen);

      stats1 = normalizeStats(match.boxScore?.team1, team1, match.score1);
      stats2 = normalizeStats(match.boxScore?.team2, team2, match.score2);
      loaded = true;
    }

    const final1Init = Number.isFinite(Number(match.score1))
      ? Number(match.score1)
      : quarters1.reduce((sum, value) => sum + value, 0);
    const final2Init = Number.isFinite(Number(match.score2))
      ? Number(match.score2)
      : quarters2.reduce((sum, value) => sum + value, 0);

    root.innerHTML = `
      ${components.pageHeader({
        eyebrow: `${team1.name} vs ${team2.name}`,
        title: 'In-game score & player stats',
        description: `${escapeHtml(match.roundLabel)} · Slot ${match.bracketSlot}. Enter quarter scores, final scores, and player stats. Save keeps a draft; Publish finalizes and advances the winner.`,
        actions: `
          <a class="hf-btn" href="${route('bracket', { id: match.tournamentId })}" data-testid="ingame-bracket">Bracket</a>
          <a class="hf-btn" href="${route('matches', { id: match.tournamentId })}" data-testid="ingame-matches">Matchups</a>
          <button type="button" class="hf-btn" data-change-match data-testid="ingame-change-match" data-href="${route('gameManage')}">Change match</button>
        `,
      })}

      <div class="hf-card hf-mb-3" data-testid="ingame-quarter-tabs">
        <div class="hf-between hf-wrap">
          <span class="hf-kpi-label">Editing quarter</span>
          <div class="hf-btn-row">
            ${quarters1.map((_, index) => {
              const label = index < 4 ? `Q${index + 1}` : `OT${index - 3}`;
              const active = index === activeQuarter;
              return `<button type="button" class="hf-btn hf-btn--sm ${active ? 'hf-btn--primary' : ''}" data-quarter-tab="${index}" data-testid="ingame-quarter-tab-${index}" aria-pressed="${active}">${escapeHtml(label)}</button>`;
            }).join('')}
          </div>
        </div>
      </div>

      <div class="hf-grid hf-grid--2 hf-mb-3">
        ${teamQuarterPanel(1, team1, quarters1, final1Init)}
        ${teamQuarterPanel(2, team2, quarters2, final2Init)}
      </div>

      <div class="hf-btn-row hf-mb-3">
        <button type="button" class="hf-btn" data-add-overtime data-testid="ingame-add-overtime">Add overtime</button>
        <button type="button" class="hf-btn" data-remove-overtime data-testid="ingame-remove-overtime">Remove last OT</button>
      </div>

      <div class="hf-card hf-mb-3">
        <div class="hf-filter-row" data-side-filters data-testid="ingame-side-filters">
          <button type="button" class="hf-chip ${teamFilter === 'all' ? 'hf-chip--primary' : 'hf-chip--slate'}" data-side="all" aria-pressed="${teamFilter === 'all'}" data-testid="ingame-side-all">All</button>
          <button type="button" class="hf-chip ${teamFilter === 'team1' ? 'hf-chip--primary' : 'hf-chip--slate'}" data-side="team1" aria-pressed="${teamFilter === 'team1'}" data-testid="ingame-side-team1">Team 1</button>
          <button type="button" class="hf-chip ${teamFilter === 'team2' ? 'hf-chip--primary' : 'hf-chip--slate'}" data-side="team2" aria-pressed="${teamFilter === 'team2'}" data-testid="ingame-side-team2">Team 2</button>
        </div>
        <div class="hf-field hf-mb-2">
          <label class="hf-label" for="ingameTeamFilter">Team filter</label>
          <select id="ingameTeamFilter" class="hf-select" data-testid="ingame-team-filter" data-team-filter data-ingame-team-filter>
            <option value="all" ${teamFilter === 'all' ? 'selected' : ''}>All</option>
            <option value="team1" ${teamFilter === 'team1' ? 'selected' : ''}>Team 1</option>
            <option value="team2" ${teamFilter === 'team2' ? 'selected' : ''}>Team 2</option>
          </select>
        </div>
      </div>

      ${teamStatsTable(1, team1, stats1, quarters1)}
      ${teamStatsTable(2, team2, stats2, quarters2)}

      <p class="hf-field-error hf-mt-1" data-ingame-error hidden></p>

      <div class="hf-btn-row hf-mt-2">
        <button type="button" class="hf-btn" data-save-ingame data-testid="ingame-save">Save</button>
        <button type="button" class="hf-btn hf-btn--primary" data-publish-ingame data-testid="ingame-publish">Publish result</button>
        <a class="hf-btn" href="${route('bracket', { id: match.tournamentId })}" data-testid="ingame-cancel">Cancel</a>
      </div>
    `;

    // Change match
    root.querySelector('[data-change-match]')?.addEventListener('click', () => {
      window.location.href = route('gameManage');
    });

    // Bind quarter tabs
    root.querySelectorAll('[data-quarter-tab]').forEach((button) => {
      button.addEventListener('click', () => {
        const idx = Number(button.dataset.quarterTab);
        if (Number.isFinite(idx)) {
          activeQuarter = idx;
          draw();
        }
      });
    });

    // Bind quarter inputs
    bindQuarterInputs();
    bindPlayerQuarterInputs();

    // Bind stat inputs for PTS
    root.querySelectorAll('[data-pq-stat]').forEach((input) => {
      if (input.dataset.bound === '1') return;
      input.dataset.bound = '1';
      input.addEventListener('input', () => {
        const side = input.dataset.pqSide;
        const index = input.dataset.pqPlayer;
        const qi = input.dataset.pqQ;
        const stat = input.dataset.pqStat;
        const value = toInt(input.value) ?? 0;
        const statsArr = side === '1' ? stats1 : stats2;
        const entry = statsArr[Number(index)];
        if (entry) {
          if (!Array.isArray(entry.quarters)) entry.quarters = [];
          while (entry.quarters.length <= Number(qi)) entry.quarters.push({ fgm:0,fga:0,tpm:0,tpa:0,ftm:0,fta:0,reb:0,ast:0,stl:0,blk:0,to:0,pf:0,points:0 });
          entry.quarters[Number(qi)][stat] = value;
        }
        const fgm = Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${index}"][data-pq-q="${qi}"][data-pq-stat="fgm"]`)?.value) || 0;
        const tpm = Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${index}"][data-pq-q="${qi}"][data-pq-stat="tpm"]`)?.value) || 0;
        const ftm = Number(root.querySelector(`[data-pq-side="${side}"][data-pq-player="${index}"][data-pq-q="${qi}"][data-pq-stat="ftm"]`)?.value) || 0;
        const points = root.querySelector(`[data-pq-points="${side}-${index}-${qi}"]`);
        if (points) points.textContent = String(fgm * 2 + tpm * 3 + ftm);
        // re-sum this player's totals + team quarter + final
        recomputePlayerTotals(side, index);
        setError('');
      });
    });

    // Bind search inputs
    [1, 2].forEach((side) => {
      const input = root.querySelector(`[data-player-search="${side}"]`);
      if (!input) return;
      input.value = side === 1 ? search1 : search2;
      const handler = ui.debounce(() => {
        if (side === 1) search1 = input.value;
        else search2 = input.value;
        applySearch(side);
      }, 120);
      input.addEventListener('input', handler);
    });

    // Team filter controls
    const teamFilterSelect = root.querySelector('[data-ingame-team-filter], [data-team-filter]');
    if (teamFilterSelect) {
      teamFilterSelect.value = teamFilter === 'team1' ? 'team1' : teamFilter === 'team2' ? 'team2' : 'all';
      teamFilterSelect.addEventListener('change', () => setTeamFilter(teamFilterSelect.value));
    }
    root.querySelectorAll('[data-side]').forEach((button) => {
      button.addEventListener('click', () => setTeamFilter(button.dataset.side));
    });

    // OT buttons
    root.querySelector('[data-add-overtime]')?.addEventListener('click', addOvertime);
    root.querySelector('[data-remove-overtime]')?.addEventListener('click', removeLastOvertime);

    // Save
    const save = root.querySelector('[data-save-ingame]');
    const publish = root.querySelector('[data-publish-ingame]');

    save?.addEventListener('click', () => {
      setError('');
      const q1 = collectQuarters(1);
      if (!q1) return;
      const q2 = collectQuarters(2);
      if (!q2) return;
      const s1 = collectStats(1);
      if (!s1) return;
      const s2 = collectStats(2);
      if (!s2) return;

      try {
        store.actions.saveGameStats(matchId, {
          quarters: { team1: q1, team2: q2 },
          stats: { team1: s1, team2: s2 },
        });
        ui.toast('Saved', { tone: 'success' });
      } catch (error) {
        applyStoreError(error);
        ui.toast(error?.message || 'Save failed.', { tone: 'danger' });
      }
    });

    publish?.addEventListener('click', async () => {
      setError('');
      const q1 = collectQuarters(1);
      if (!q1) return;
      const q2 = collectQuarters(2);
      if (!q2) return;
      const final1 = collectFinal(1);
      if (final1 === null) return;
      const final2 = collectFinal(2);
      if (final2 === null) return;
      const s1 = collectStats(1);
      if (!s1) return;
      const s2 = collectStats(2);
      if (!s2) return;

      const sum1 = q1.reduce((sum, value) => sum + value, 0);
      const sum2 = q2.reduce((sum, value) => sum + value, 0);
      if (sum1 !== final1) {
        const input = root.querySelector('[data-final="1"]');
        ui.setFieldError(input, 'Final score must equal the sum of the quarters.');
        ui.toast('Quarter totals must match final scores.', { tone: 'danger' });
        return;
      }
      if (sum2 !== final2) {
        const input = root.querySelector('[data-final="2"]');
        ui.setFieldError(input, 'Final score must equal the sum of the quarters.');
        ui.toast('Quarter totals must match final scores.', { tone: 'danger' });
        return;
      }

      ui.setBusy(publish, true, 'Publishing...');
      try {
        store.actions.saveGameStats(matchId, {
          quarters: { team1: q1, team2: q2 },
          stats: { team1: s1, team2: s2 },
        });
        store.actions.saveScore(matchId, sum1, sum2, {
          boxScore: {
            team1: s1,
            team2: s2,
            quarters: { team1: q1, team2: q2 },
          },
        });
        ui.setBusy(publish, false);
        loaded = false;
        ui.toast('Result published.', { tone: 'success', title: 'Score finalized' });
        draw();
      } catch (error) {
        ui.setBusy(publish, false);
        applyStoreError(error);
        ui.toast(error?.message || 'Publish failed.', { tone: 'danger' });
      }
    });

    // Re-apply current filters after render.
    applySearch(1);
    applySearch(2);
    applyTeamFilter();
  }

  draw();

}
