import { route } from '../routes.js';
import { escapeHtml, formatDate, formatTime } from '../ui.js';
import { guardRole } from '../guards.js';

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

function statInput(side, index, field, value) {
  return `<input class="hf-input" type="number" min="0" value="${escapeHtml(value)}"
    id="ingame-${field}-${side}-${index}"
    data-team="${side}" data-idx="${index}" data-stat="${field}" data-field="${field}"
    data-testid="ingame-${field}-${side}-${index}">`;
}

export function render({ store, ui, components, root, theme }) {
  if (!guardRole({ store, ui, root, roles: ['organizer'] })) return;

  const params = new URLSearchParams(window.location.search);
  const matchId = params.get('match') || '';

  if (!matchId) {
    root.innerHTML = ui.emptyState({
      icon: 'clipboard',
      title: 'Select a match to manage',
      message: 'Choose a matchup to enter quarter scores and player stats.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('matches')}" data-testid="manage-select-match">Browse matchups</a>`,
    });
    return;
  }

  const match = store.select.match(matchId);
  if (!match) {
    root.innerHTML = ui.emptyState({
      icon: 'clipboard',
      title: 'Select a match to manage',
      message: 'That match could not be found. Choose another matchup.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('matches')}" data-testid="manage-select-match">Browse matchups</a>`,
    });
    return;
  }

  const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
  const team2 = match.team2Id ? store.select.team(match.team2Id) : null;

  if (!team1 || !team2) {
    root.innerHTML = ui.emptyState({
      icon: 'hourglass-split',
      title: 'Teams still pending',
      message: 'Both teams must be known before managing this game.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('matches')}">Browse matchups</a>`,
    });
    return;
  }

  let quarters1 = normalizeQuarters(match.boxScore?.quarters?.team1);
  let quarters2 = normalizeQuarters(match.boxScore?.quarters?.team2);
  const maxLen = Math.max(quarters1.length, quarters2.length, 4);
  quarters1 = padArray(quarters1, maxLen);
  quarters2 = padArray(quarters2, maxLen);

  const stats1 = normalizeStats(match.boxScore?.team1, team1, match.score1);
  const stats2 = normalizeStats(match.boxScore?.team2, team2, match.score2);

  const final1Init = Number.isFinite(Number(match.score1))
    ? Number(match.score1)
    : quarters1.reduce((sum, value) => sum + value, 0);
  const final2Init = Number.isFinite(Number(match.score2))
    ? Number(match.score2)
    : quarters2.reduce((sum, value) => sum + value, 0);

  let search1 = '';
  let search2 = '';

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

  function teamStatsTable(side, team, stats) {
    return `
      <section class="hf-card hf-mb-3" data-testid="ingame-stats-${side}">
        <h3 class="hf-section-title hf-mb-2">${escapeHtml(team.name)} player stats</h3>
        <div class="hf-field hf-mb-2">
          <label class="hf-label" for="ingameSearch${side}">Search player</label>
          <input class="hf-input" id="ingameSearch${side}" type="search" placeholder="Player name"
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
                <th>PTS</th>
              </tr>
            </thead>
            <tbody>
              ${stats
                .map((entry, index) => {
                  const player = (team.players || [])[index];
                  const points = entry.fgm * 2 + entry.tpm * 3 + entry.ftm;
                  return `
                    <tr data-ingame-row="${side}" data-player-name="${escapeHtml(String(player?.name || '').toLowerCase())}" data-testid="ingame-stat-row-${side}-${index}">
                      <td><strong>${escapeHtml(player?.name || 'Unknown player')}</strong></td>
                      <td>
                        ${statInput(side, index, 'fgm', entry.fgm)} / ${statInput(side, index, 'fga', entry.fga)}
                      </td>
                      <td>
                        ${statInput(side, index, 'tpm', entry.tpm)} / ${statInput(side, index, 'tpa', entry.tpa)}
                      </td>
                      <td>
                        ${statInput(side, index, 'ftm', entry.ftm)} / ${statInput(side, index, 'fta', entry.fta)}
                      </td>
                      <td>${statInput(side, index, 'reb', entry.reb)}</td>
                      <td>${statInput(side, index, 'ast', entry.ast)}</td>
                      <td>${statInput(side, index, 'stl', entry.stl)}</td>
                      <td>${statInput(side, index, 'blk', entry.blk)}</td>
                      <td>${statInput(side, index, 'to', entry.to)}</td>
                      <td>${statInput(side, index, 'pf', entry.pf)}</td>
                      <td class="is-num"><span data-points="${side}-${index}">${points}</span></td>
                    </tr>
                  `;
                })
                .join('')}
            </tbody>
          </table>
        </div>
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
    const team = side === 1 ? team1 : team2;
    const players = team.players || [];
    const values = [];
    for (let index = 0; index < players.length; index += 1) {
      const fields = ['fgm', 'fga', 'tpm', 'tpa', 'ftm', 'fta', 'reb', 'ast', 'stl', 'blk', 'to', 'pf'];
      const entry = { playerId: players[index].id, points: 0 };
      for (const field of fields) {
        const input = root.querySelector(
          `[data-team="${side}"][data-idx="${index}"][data-stat="${field}"]`
        );
        ui.clearFieldError(input);
        const value = toInt(input?.value);
        if (value === null) {
          ui.setFieldError(input, 'Enter a whole number.');
          return null;
        }
        entry[field] = value;
      }
      if (entry.fgm > entry.fga) {
        const input = root.querySelector(`[data-team="${side}"][data-idx="${index}"][data-stat="fgm"]`);
        ui.setFieldError(input, 'Made cannot exceed attempted (FG).');
        return null;
      }
      if (entry.tpm > entry.tpa) {
        const input = root.querySelector(`[data-team="${side}"][data-idx="${index}"][data-stat="tpm"]`);
        ui.setFieldError(input, 'Made cannot exceed attempted (3PT).');
        return null;
      }
      if (entry.ftm > entry.fta) {
        const input = root.querySelector(`[data-team="${side}"][data-idx="${index}"][data-stat="ftm"]`);
        ui.setFieldError(input, 'Made cannot exceed attempted (FT).');
        return null;
      }
      entry.points = entry.fgm * 2 + entry.tpm * 3 + entry.ftm;
      values.push(entry);
    }
    return values;
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

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: `${team1.name} vs ${team2.name}`,
      title: 'In-game score & player stats',
      description: `${escapeHtml(match.roundLabel)} · Slot ${match.bracketSlot}. Enter quarter scores, final scores, and player stats. Save keeps a draft; Publish finalizes and advances the winner.`,
      actions: `
        <a class="hf-btn" href="${route('bracket', { id: match.tournamentId })}" data-testid="ingame-bracket">Bracket</a>
        <a class="hf-btn" href="${route('matches', { id: match.tournamentId })}" data-testid="ingame-matches">Matchups</a>
      `,
    })}

    <div class="hf-grid hf-grid--2 hf-mb-3">
      ${teamQuarterPanel(1, team1, quarters1, final1Init)}
      ${teamQuarterPanel(2, team2, quarters2, final2Init)}
    </div>

    <div class="hf-btn-row hf-mb-3">
      <button type="button" class="hf-btn" data-add-overtime data-testid="ingame-add-overtime">Add overtime</button>
      <button type="button" class="hf-btn" data-remove-overtime data-testid="ingame-remove-overtime">Remove last OT</button>
    </div>

    ${teamStatsTable(1, team1, stats1)}
    ${teamStatsTable(2, team2, stats2)}

    <p class="hf-field-error hf-mt-1" data-ingame-error hidden></p>

    <div class="hf-btn-row hf-mt-2">
      <button type="button" class="hf-btn" data-save-ingame data-testid="ingame-save">Save</button>
      <button type="button" class="hf-btn hf-btn--primary" data-publish-ingame data-testid="ingame-publish">Publish result</button>
      <a class="hf-btn" href="${route('bracket', { id: match.tournamentId })}" data-testid="ingame-cancel">Cancel</a>
    </div>
  `;

  // Bind quarter inputs
  bindQuarterInputs();

  // Bind stat inputs for PTS
  root.querySelectorAll('[data-stat]').forEach((input) => {
    if (input.dataset.bound === '1') return;
    input.dataset.bound = '1';
    input.addEventListener('input', () => {
      const side = input.dataset.team;
      const index = input.dataset.idx;
      const fgm = Number(root.querySelector(`[data-team="${side}"][data-idx="${index}"][data-stat="fgm"]`)?.value) || 0;
      const tpm = Number(root.querySelector(`[data-team="${side}"][data-idx="${index}"][data-stat="tpm"]`)?.value) || 0;
      const ftm = Number(root.querySelector(`[data-team="${side}"][data-idx="${index}"][data-stat="ftm"]`)?.value) || 0;
      const points = root.querySelector(`[data-points="${side}-${index}"]`);
      if (points) points.textContent = String(fgm * 2 + tpm * 3 + ftm);
      setError('');
    });
  });

  // Bind search inputs
  [1, 2].forEach((side) => {
    const input = root.querySelector(`[data-player-search="${side}"]`);
    if (!input) return;
    const handler = ui.debounce(() => {
      if (side === 1) search1 = input.value;
      else search2 = input.value;
      const term = input.value.trim().toLowerCase();
      root.querySelectorAll(`tr[data-ingame-row="${side}"]`).forEach((row) => {
        const name = row.dataset.playerName || '';
        row.hidden = Boolean(term) && !name.includes(term);
      });
    }, 120);
    input.addEventListener('input', handler);
  });

  // OT buttons
  root.querySelector('[data-add-overtime]')?.addEventListener('click', addOvertime);
  root.querySelector('[data-remove-overtime]')?.addEventListener('click', removeLastOvertime);

  // Save
  const save = root.querySelector('[data-save-ingame]');
  const publish = root.querySelector('[data-publish-ingame]');
  const errorElement = root.querySelector('[data-ingame-error]');

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
      ui.toast('Result published.', { tone: 'success', title: 'Score finalized' });
      render({ store, ui, components, root, theme });
    } catch (error) {
      ui.setBusy(publish, false);
      applyStoreError(error);
      ui.toast(error?.message || 'Publish failed.', { tone: 'danger' });
    }
  });
}
