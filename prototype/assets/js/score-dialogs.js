import { escapeHtml } from './ui.js';

function teamPanel(team, side) {
  return `
    <div class="hf-card hf-card--flat" data-team-panel="${side}">
      <span class="hf-flex hf-items-center hf-gap-2">
        <span class="hf-shield" style="--team-color: ${escapeHtml(team.avatarColor)}" aria-hidden="true">${escapeHtml(team.shortName)}</span>
        <strong>${escapeHtml(team.name)}</strong>
      </span>
      <div class="hf-score" data-score-display="${side}" style="font-size: clamp(48px, 9vw, 78px); line-height: 1">0</div>
      <label class="hf-label" for="scoreInput${side}">Final score</label>
      <input class="hf-input hf-mb-2" id="scoreInput${side}" type="number" min="0" max="199"
             inputmode="numeric" value="0" data-score-input="${side}"
             data-testid="score-input-${side}">
      <div class="hf-btn-row">
        ${[1, 2, 3]
          .map(
            (points) => `
              <button type="button" class="hf-btn" data-add-score data-team="${side}" data-points="${points}"
                      data-testid="score-add-${side}-${points}">+${points}</button>
            `
          )
          .join('')}
      </div>
    </div>
  `;
}

export function openScoreConsole({ store, ui, matchId, onDone }) {
  const match = store.select.match(matchId);
  if (!match) {
    ui.toast('That match no longer exists.', { tone: 'danger', title: 'Match not found' });
    return;
  }
  const team1 = store.select.team(match.team1Id);
  const team2 = store.select.team(match.team2Id);
  if (!team1 || !team2) {
    ui.toast('Both teams must be known before entering a score.', {
      tone: 'warning',
      title: 'Teams pending',
    });
    return;
  }

  let score1 = 0;
  let score2 = 0;
  let forfeitTeam = 0;
  const history = [];
  let errorElement = null;

  ui.dialog({
    title: `${team1.shortName} vs ${team2.shortName}`,
    size: 'wide',
    body: `
      <p class="hf-small hf-muted">${escapeHtml(match.roundLabel)} · Slot ${match.bracketSlot}. Use the score buttons or a forfeit, then finalize. The winner advances immediately.</p>
      <div class="hf-grid hf-grid--2">
        ${teamPanel(team1, 1)}
        ${teamPanel(team2, 2)}
      </div>
      <div class="hf-card hf-card--flat hf-mt-2">
        <label class="hf-label" for="scoreForfeit">Forfeit</label>
        <select class="hf-select" id="scoreForfeit" data-testid="score-forfeit">
          <option value="0">No forfeit</option>
          <option value="1">${escapeHtml(team1.name)} forfeits · ${escapeHtml(team2.shortName)} wins 20–0</option>
          <option value="2">${escapeHtml(team2.name)} forfeits · ${escapeHtml(team1.shortName)} wins 20–0</option>
        </select>
        <p class="hf-small hf-muted hf-mt-1 hf-mb-0" data-forfeit-note hidden>
          FIBA forfeit score is 20–0. Score buttons are disabled while a forfeit is selected.
        </p>
        <p class="hf-field-error hf-mt-1" data-score-error hidden></p>
      </div>
    `,
    footer: `
      <button type="button" class="hf-btn" data-undo-score data-testid="score-undo" disabled>Undo</button>
      <button type="button" class="hf-btn" data-cancel-score data-testid="score-cancel">Cancel</button>
      <button type="button" class="hf-btn hf-btn--primary" data-finalize-score data-testid="score-finalize">
        Finalize score
      </button>
    `,
    onMount(element, close) {
      const forfeit = element.querySelector('#scoreForfeit');
      const undo = element.querySelector('[data-undo-score]');
      const finalize = element.querySelector('[data-finalize-score]');
      const note = element.querySelector('[data-forfeit-note]');
      const inputs = [...element.querySelectorAll('[data-score-input]')];
      errorElement = element.querySelector('[data-score-error]');

      function setError(message) {
        errorElement.hidden = !message;
        errorElement.textContent = message || '';
      }

      function paint() {
        const shown1 = forfeitTeam === 1 ? 0 : forfeitTeam === 2 ? 20 : score1;
        const shown2 = forfeitTeam === 2 ? 0 : forfeitTeam === 1 ? 20 : score2;
        element.querySelector('[data-score-display="1"]').textContent = String(shown1);
        element.querySelector('[data-score-display="2"]').textContent = String(shown2);
        element.querySelector('[data-score-input="1"]').value = String(shown1);
        element.querySelector('[data-score-input="2"]').value = String(shown2);
        element.querySelectorAll('[data-add-score]').forEach((button) => {
          button.disabled = Boolean(forfeitTeam);
        });
        inputs.forEach((input) => {
          input.disabled = Boolean(forfeitTeam);
        });
        undo.disabled = history.length === 0 || Boolean(forfeitTeam);
        note.hidden = !forfeitTeam;
      }

      element.querySelector('[data-cancel-score]').addEventListener('click', close);

      element.querySelectorAll('[data-add-score]').forEach((button) => {
        button.addEventListener('click', () => {
          if (forfeitTeam) return;
          history.push({ score1, score2 });
          const points = Number(button.dataset.points);
          if (button.dataset.team === '1') score1 += points;
          else score2 += points;
          setError('');
          paint();
        });
      });

      inputs.forEach((input) => {
        input.addEventListener('input', () => {
          if (forfeitTeam) return;
          const value = Number(input.value);
          if (!Number.isFinite(value) || value < 0) return;
          const next = Math.min(199, Math.floor(value));
          if (input.dataset.scoreInput === '1') score1 = next;
          else score2 = next;
          element.querySelector(`[data-score-display="${input.dataset.scoreInput}"]`).textContent = String(next);
          setError('');
        });

        input.addEventListener('change', () => {
          const side = input.dataset.scoreInput;
          input.value = String(side === '1' ? score1 : score2);
        });
      });

      undo.addEventListener('click', () => {
        const previous = history.pop();
        if (!previous) return;
        score1 = previous.score1;
        score2 = previous.score2;
        paint();
      });

      forfeit.addEventListener('change', () => {
        forfeitTeam = Number(forfeit.value) || 0;
        setError('');
        paint();
      });

      finalize.addEventListener('click', async () => {
        setError('');
        if (forfeitTeam) {
          const winner = forfeitTeam === 1 ? team2 : team1;
          const loser = forfeitTeam === 1 ? team1 : team2;
          const ok = await ui.confirm({
            title: 'Record forfeit?',
            message: `${loser.name} forfeits. ${winner.name} wins 20–0.`,
            confirmLabel: 'Record forfeit',
            tone: 'danger',
          });
          if (!ok) return;
          try {
            store.actions.saveScore(match.id, 0, 0, { forfeitTeam });
            close();
            ui.toast(`${winner.shortName} advances on a 20–0 forfeit.`, {
              tone: 'success',
              title: 'Forfeit recorded',
            });
            onDone?.();
          } catch (error) {
            setError(error.message);
          }
          return;
        }

        if (score1 === score2) {
          setError('Elimination games cannot end in a tie.');
          return;
        }

        const ok = await ui.confirm({
          title: 'Finalize score?',
          message: `${team1.shortName} ${score1}–${score2} ${team2.shortName}. The winner advances and standings update immediately.`,
          confirmLabel: 'Finalize score',
        });
        if (!ok) return;

        try {
          store.actions.saveScore(match.id, score1, score2);
          close();
          ui.toast(
            `${score1 > score2 ? team1.shortName : team2.shortName} advances to the next round.`,
            { tone: 'success', title: 'Final score recorded' }
          );
          onDone?.();
        } catch (error) {
          setError(error.message);
        }
      });

      paint();
    },
  });
}

export function openCorrectionDialog({ store, ui, matchId, onDone }) {
  const match = store.select.match(matchId);
  if (!match) {
    ui.toast('That match no longer exists.', { tone: 'danger', title: 'Match not found' });
    return;
  }
  if (match.status !== 'final' && match.status !== 'forfeit') {
    ui.toast('Only a recorded result can be corrected.', {
      tone: 'warning',
      title: 'Not final yet',
    });
    return;
  }
  const team1 = store.select.team(match.team1Id);
  const team2 = store.select.team(match.team2Id);

  ui.dialog({
    title: `Correct score · ${team1.shortName} vs ${team2.shortName}`,
    size: 'wide',
    body: `
      <p class="hf-small hf-muted">Current result: ${match.score1}–${match.score2}${match.correction ? ` (corrected from ${match.correction.previousScore1}–${match.correction.previousScore2})` : ''}. A correction requires a reason and rewrites the downstream bracket if the winner changes.</p>
      <div class="hf-form-grid">
        <div class="hf-field">
          <label class="hf-label" for="correctionScore1">${escapeHtml(team1.name)}</label>
          <input class="hf-input" id="correctionScore1" type="number" min="0" value="${match.score1}"
                 data-testid="correction-score-1">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="correctionScore2">${escapeHtml(team2.name)}</label>
          <input class="hf-input" id="correctionScore2" type="number" min="0" value="${match.score2}"
                 data-testid="correction-score-2">
        </div>
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="correctionReason">Reason</label>
          <textarea class="hf-textarea" id="correctionReason" rows="3"
                    placeholder="Shot-clock correction after referee review."
                    data-testid="correction-reason"></textarea>
        </div>
      </div>
      <p class="hf-field-error hf-mt-1" data-correction-error hidden></p>
    `,
    footer: `
      <button type="button" class="hf-btn" data-cancel-correction data-testid="correction-cancel">Cancel</button>
      <button type="button" class="hf-btn hf-btn--danger" data-save-correction data-testid="correction-save">
        Save correction
      </button>
    `,
    onMount(element, close) {
      const score1Input = element.querySelector('#correctionScore1');
      const score2Input = element.querySelector('#correctionScore2');
      const reasonInput = element.querySelector('#correctionReason');
      const errorElement = element.querySelector('[data-correction-error]');

      function setError(message, target = null) {
        errorElement.hidden = !message;
        errorElement.textContent = message || '';
        if (target) {
          ui.setFieldError(target, message);
          target.focus();
        }
      }

      element.querySelector('[data-cancel-correction]').addEventListener('click', close);
      element.querySelector('[data-save-correction]').addEventListener('click', async () => {
        [score1Input, score2Input, reasonInput].forEach((field) => ui.clearFieldError(field));
        errorElement.hidden = true;

        const score1 = Number(score1Input.value);
        const score2 = Number(score2Input.value);
        if (!Number.isInteger(score1) || !Number.isInteger(score2) || score1 < 0 || score2 < 0) {
          setError('Scores must be zero or positive whole numbers.', score1Input);
          return;
        }
        if (score1 === score2) {
          setError('Elimination games cannot end in a tie.', score2Input);
          return;
        }
        if (reasonInput.value.trim().length < 5) {
          setError('Enter at least 5 characters for the correction reason.', reasonInput);
          return;
        }

        const ok = await ui.confirm({
          title: 'Apply correction?',
          message: `Change ${team1.shortName} ${match.score1}–${match.score2} ${team2.shortName} to ${score1}–${score2}. The audit log keeps the previous values.`,
          confirmLabel: 'Apply correction',
          tone: 'danger',
        });
        if (!ok) return;

        try {
          store.actions.correctScore(match.id, score1, score2, reasonInput.value.trim());
          close();
          ui.toast('Correction saved and the bracket path was recalculated.', {
            tone: 'success',
            title: 'Score corrected',
          });
          onDone?.();
        } catch (error) {
          setError(error.message);
        }
      });
    },
  });
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

function normalizeQuarters(value) {
  const arr = Array.isArray(value) ? value : [];
  return [0, 1, 2, 3].map((index) => {
    const number = Number(arr[index]);
    return Number.isFinite(number) && number >= 0 ? Math.floor(number) : 0;
  });
}

function toInt(value) {
  const number = Number(value);
  if (!Number.isFinite(number) || !Number.isInteger(number) || number < 0) return null;
  return number;
}
