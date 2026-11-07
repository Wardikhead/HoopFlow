import { route } from '../routes.js';
import { escapeHtml, formatDate, formatTime } from '../ui.js';
import { openCorrectionDialog, openScoreConsole } from '../score-dialogs.js';

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const tournamentId = params.get('id') || 'tournament-1';
  const requestedMatch = params.get('match') || '';
  let mobileRound = 0;
  let openedFromQuery = false;

  function draw() {
    const tournament = store.select.tournament(tournamentId);
    if (!tournament) {
      root.innerHTML = ui.emptyState({
        icon: 'trophy',
        title: 'Tournament not found',
        message: 'The bracket link is invalid or the demo has been reset.',
        action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Back to tournaments</a>`,
      });
      return;
    }

    const role = store.getRole();
    const bracket = store.select.bracket(tournamentId);
    const approved = store.select.teams(tournamentId, { status: 'approved' });
    const kpis = store.select.kpis(tournamentId);
    const canGenerate = role === 'organizer' && approved.length >= 2;
    const canScore = role === 'organizer';

    const header = components.pageHeader({
      eyebrow: `${tournament.division} · ${tournament.status}`,
      title: bracket.hasBracket ? 'Bracket' : 'Build the bracket',
      description: bracket.hasBracket
        ? `${tournament.name} · ${tournament.format} · ${bracket.rounds.length} rounds`
        : `${tournament.name} · ${approved.length} approved teams ready to seed into a ${tournament.slots}-slot ${tournament.format} bracket.`,
      actions: `
        <a class="hf-btn" href="${route('tournamentShow', { id: tournamentId })}" data-testid="bracket-tournament"><i class="bi bi-arrow-left"></i> Tournament</a>
        <a class="hf-btn" href="${route('schedule', { id: tournamentId })}" data-testid="bracket-schedule"><i class="bi bi-calendar3"></i> Schedule</a>
        <a class="hf-btn" href="${route('standings', { id: tournamentId })}" data-testid="bracket-standings"><i class="bi bi-bar-chart"></i> Standings</a>
        ${role === 'organizer' && bracket.hasBracket && !kpis.matchesPlayed ? `<button type="button" class="hf-btn" data-regenerate-bracket data-testid="bracket-regenerate">Regenerate bracket</button>` : ''}
      `,
    });

    if (!bracket.hasBracket) {
      root.innerHTML = `
        ${header}
        <div class="hf-grid hf-grid--3 hf-mb-3">
          ${components.kpiTile({ label: 'Approved teams', value: approved.length, hint: `${tournament.slots} bracket slots`, tone: 'primary', testid: 'bracket-kpi-approved' })}
          ${components.kpiTile({ label: 'First-round games', value: tournament.slots / 2, hint: 'Byes fill empty slots', tone: 'gold', testid: 'bracket-kpi-games' })}
          ${components.kpiTile({ label: 'Courts', value: tournament.courts.length, hint: tournament.venue, tone: 'slate', testid: 'bracket-kpi-courts' })}
        </div>
        <div class="hf-card" data-testid="bracket-empty">
          <h2 class="hf-section-title">Seed preview</h2>
          <p class="hf-small hf-muted">Teams are seeded by their registration seed, then the bracket pairs 1–16, 8–9, 5–12, 4–13, and so on. Empty slots become byes.</p>
          <div class="hf-grid hf-grid--2 hf-mt-2">
            ${approved.length
              ? approved
                  .slice()
                  .sort((a, b) => (a.seed ?? 999) - (b.seed ?? 999))
                  .map(
                    (team) => `
                      <div class="hf-flex hf-items-center hf-gap-2 hf-card hf-card--flat">
                        ${components.shield(team)}
                        <span><strong>${escapeHtml(team.name)}</strong><span class="hf-small hf-muted" style="display:block">${escapeHtml(team.city)} · seed ${team.seed ?? 'TBD'}</span></span>
                      </div>
                    `
                  )
                  .join('')
              : ui.emptyState({ icon: 'people', title: 'No approved teams', message: 'Approve at least two teams before generating the bracket.' })}
          </div>
          <div class="hf-btn-row hf-mt-3">
            ${
              canGenerate
                ? `<button type="button" class="hf-btn hf-btn--primary" data-generate-bracket data-testid="bracket-generate">Generate bracket</button>`
                : `<button type="button" class="hf-btn" disabled data-testid="bracket-generate-disabled">Generate bracket</button>
                   <p class="hf-small hf-muted hf-mb-0">${role === 'organizer' ? 'At least two approved teams are required.' : 'Organizer access required.'}</p>`
            }
          </div>
        </div>
      `;
      attachGeneration();
      return;
    }

    const rounds = bracket.rounds;
    mobileRound = Math.min(mobileRound, rounds.length - 1);
    const champion = bracket.champion;

    root.innerHTML = `
      ${header}

      ${
        champion
          ? `<section class="hf-bracket-champion hf-mb-3" data-testid="bracket-champion">
               <span class="hf-eyebrow" style="color: var(--hf-gold)">Champion</span>
               <div class="hf-flex hf-items-center hf-gap-3 hf-mt-1">
                 ${components.shield(champion, 'lg')}
                 <div>
                   <h1>${escapeHtml(champion.name)}</h1>
                   <p class="hf-mb-0">${escapeHtml(champion.coach)} · ${escapeHtml(champion.city)}</p>
                 </div>
               </div>
             </section>`
          : ''
      }

      <div class="hf-grid hf-grid--4 hf-mb-3">
        ${components.kpiTile({ label: 'Rounds', value: rounds.length, hint: `${tournament.slots}-team bracket`, tone: 'primary', testid: 'bracket-kpi-rounds' })}
        ${components.kpiTile({ label: 'Played', value: kpis.matchesPlayed, hint: `${kpis.matchesScheduled} scheduled`, tone: 'success', testid: 'bracket-kpi-played' })}
        ${components.kpiTile({ label: 'Champion', value: champion ? champion.shortName : 'TBD', hint: champion ? champion.name : 'Final not played', tone: 'gold', testid: 'bracket-kpi-champion' })}
        ${components.kpiTile({ label: 'Corrections', value: store.select.audit({ entityType: 'match' }).filter((entry) => entry.action === 'score.correct').length, hint: 'Audited score changes', tone: 'alert', testid: 'bracket-kpi-corrections' })}
      </div>

      <div class="hf-flex hf-wrap hf-gap-2 hf-mb-2 hf-small hf-muted">
        <span class="hf-chip hf-chip--slate"><i class="bi bi-clock"></i> Scheduled</span>
        <span class="hf-chip hf-chip--success"><i class="bi bi-flag-fill"></i> Final</span>
        <span class="hf-chip hf-chip--danger"><i class="bi bi-shield-x"></i> Forfeit</span>
        <span>${canScore ? 'Select a match to enter or correct a score.' : 'Public bracket view. Scores are read-only for this role.'}</span>
      </div>

      ${
        tournament.format === 'round-robin'
          ? `<div class="hf-card hf-mb-3" data-testid="bracket-matchdays">
              <h2 class="hf-section-title">Matchdays</h2>
              <div class="hf-grid hf-grid--1">
                ${rounds
                  .map(
                    (round) => `
                      <div class="hf-card hf-card--flat hf-mb-2">
                        <div class="hf-between hf-mb-1">
                          <strong>${escapeHtml(round.label)}</strong>
                          <span class="hf-small hf-muted">${round.matches.length} games</span>
                        </div>
                        <div class="hf-grid hf-grid--2">
                          ${round.matches
                            .map((match) => components.matchCard(match, { href: route('gameStats', { match: match.id }) }))
                            .join('')}
                        </div>
                      </div>
                    `
                  )
                  .join('')}
              </div>
              <div class="hf-btn-row hf-mt-2">
                <a class="hf-btn" href="${route('standings', { id: tournamentId })}" data-testid="bracket-rr-standings">Standings</a>
              </div>
            </div>`
          : tournament.format === 'double-elimination'
            ? `<div class="hf-bracket" data-testid="bracket-tree">
                <div class="hf-bracket-round">
                  <div class="hf-bracket-round-title">Winners bracket</div>
                  ${rounds
                    .filter((r) => r.round.startsWith('W'))
                    .map(
                      (round) => `
                        <div class="hf-bracket-round">
                          <div class="hf-bracket-round-title">${escapeHtml(round.label)} <span class="hf-small hf-muted">${round.matches.length}</span></div>
                          ${round.matches.map((match) => components.bracketMatch(match)).join('')}
                        </div>
                      `
                    )
                    .join('')}
                </div>
                <div class="hf-bracket-round">
                  <div class="hf-bracket-round-title">Losers bracket</div>
                  ${rounds
                    .filter((r) => r.round.startsWith('L'))
                    .map(
                      (round) => `
                        <div class="hf-bracket-round">
                          <div class="hf-bracket-round-title">${escapeHtml(round.label)} <span class="hf-small hf-muted">${round.matches.length}</span></div>
                          ${round.matches.map((match) => components.bracketMatch(match)).join('')}
                        </div>
                      `
                    )
                    .join('')}
                </div>
                ${rounds
                  .filter((r) => r.round === 'GF')
                  .map(
                    (round) => `
                      <div class="hf-bracket-round">
                        <div class="hf-bracket-round-title">Grand Final</div>
                        ${round.matches.map((match) => components.bracketMatch(match)).join('')}
                      </div>
                    `
                  )
                  .join('')}
              </div>`
            : `<div class="hf-bracket" data-testid="bracket-tree">
                ${rounds
                  .map(
                    (round) => `
                      <div class="hf-bracket-round">
                        <div class="hf-bracket-round-title">${escapeHtml(round.label)} <span class="hf-small hf-muted">${round.matches.length}</span></div>
                        ${round.matches.map((match) => components.bracketMatch(match)).join('')}
                      </div>
                    `
                  )
                  .join('')}
              </div>`
      }

      <div class="hf-bracket-mobile" data-testid="bracket-mobile">
        <div class="hf-round-pager" data-round-pager>
          ${rounds
            .map(
              (round, index) => `
                <button type="button" class="${index === mobileRound ? 'is-active' : ''}" data-mobile-round="${index}"
                        data-testid="bracket-round-tab-${index}">${escapeHtml(round.label)}</button>
              `
            )
            .join('')}
        </div>
        <div class="hf-round-progress"><span data-mobile-progress style="width: ${((mobileRound + 1) / rounds.length) * 100}%"></span></div>
        <div id="mobileRoundBody" data-testid="bracket-mobile-body"></div>
        <div class="hf-between hf-mt-2">
          <button type="button" class="hf-btn hf-btn--sm" data-mobile-prev data-testid="bracket-prev-round" ${mobileRound === 0 ? 'disabled' : ''}>
            <i class="bi bi-arrow-left"></i> Back
          </button>
          <span class="hf-small hf-muted" data-mobile-count>${mobileRound + 1} of ${rounds.length}</span>
          <button type="button" class="hf-btn hf-btn--sm" data-mobile-next data-testid="bracket-next-round" ${mobileRound === rounds.length - 1 ? 'disabled' : ''}>
            Next <i class="bi bi-arrow-right"></i>
          </button>
        </div>
      </div>

      <div class="hf-card hf-card--flat hf-mt-3">
        <p class="hf-small hf-muted hf-mb-0">Bracket progress is computed from match results. A correction that changes a winner rewrites the next-round slot, team record, standings, and audit trail in one action.</p>
      </div>
    `;

    attachBracket(rounds);
    attachRegenerate();
    if (requestedMatch && !openedFromQuery) {
      openedFromQuery = true;
      openMatch(requestedMatch);
    }
  }

  function paintMobileRound(rounds) {
    const body = root.querySelector('#mobileRoundBody');
    if (!body) return;
    body.innerHTML = rounds[mobileRound].matches
      .map((match) => components.bracketMatch(match))
      .join('');
    root.querySelectorAll('[data-mobile-round]').forEach((button, index) => {
      button.classList.toggle('is-active', index === mobileRound);
    });
    const progress = root.querySelector('[data-mobile-progress]');
    if (progress) progress.style.width = `${((mobileRound + 1) / rounds.length) * 100}%`;
    const prev = root.querySelector('[data-mobile-prev]');
    const next = root.querySelector('[data-mobile-next]');
    if (prev) prev.disabled = mobileRound === 0;
    if (next) next.disabled = mobileRound === rounds.length - 1;
    const label = root.querySelector('[data-mobile-count]');
    if (label) label.textContent = `${mobileRound + 1} of ${rounds.length}`;
    attachMatchButtons(body);
  }

  function attachGeneration() {
    const button = root.querySelector('[data-generate-bracket]');
    if (!button) return;
    button.addEventListener('click', async () => {
      const ok = await ui.confirm({
        title: 'Generate bracket?',
        message: 'Approved teams are seeded, byes fill the empty slots, and the seeds lock.',
        confirmLabel: 'Generate bracket',
      });
      if (!ok) return;
      ui.setBusy(button, true, 'Seeding...');
      window.setTimeout(() => {
        try {
          const result = store.actions.generateBracket(tournamentId);
          ui.toast(`${result.matches} matches created with ${result.byes} byes.`, {
            tone: 'success',
            title: 'Bracket generated',
          });
          draw();
        } catch (error) {
          ui.setBusy(button, false);
          ui.toast(error.message, { tone: 'danger', title: 'Could not generate bracket' });
        }
      }, 450);
    });
  }

  function attachRegenerate() {
    const button = root.querySelector('[data-regenerate-bracket]');
    if (!button) return;
    button.addEventListener('click', async () => {
      const ok = await ui.confirm({
        title: 'Regenerate bracket?',
        message: 'This wipes the current bracket and rebuilds from approved teams. Recorded results will be lost.',
        confirmLabel: 'Regenerate',
        tone: 'danger',
      });
      if (!ok) return;
      ui.setBusy(button, true, 'Regenerating...');
      try {
        const result = store.actions.regenerateBracket(tournamentId);
        ui.toast(`${result.matches} matches regenerated.`, { tone: 'success', title: 'Bracket regenerated' });
        draw();
      } catch (error) {
        ui.setBusy(button, false);
        ui.toast(error.message, { tone: 'danger', title: 'Could not regenerate' });
      }
    });
  }

  function attachMatchButtons(scope) {
    scope.querySelectorAll('[data-match-id]').forEach((element) => {
      element.addEventListener('click', () => openMatch(element.dataset.matchId));
      element.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openMatch(element.dataset.matchId);
        }
      });
    });
  }

  function attachBracket(rounds) {
    attachMatchButtons(root.querySelector('[data-testid="bracket-tree"]'));
    paintMobileRound(rounds);

    root.querySelectorAll('[data-mobile-round]').forEach((button) => {
      button.addEventListener('click', () => {
        mobileRound = Number(button.dataset.mobileRound);
        paintMobileRound(rounds);
      });
    });
    root.querySelector('[data-mobile-prev]').addEventListener('click', () => {
      if (mobileRound > 0) {
        mobileRound -= 1;
        paintMobileRound(rounds);
      }
    });
    root.querySelector('[data-mobile-next]').addEventListener('click', () => {
      if (mobileRound < rounds.length - 1) {
        mobileRound += 1;
        paintMobileRound(rounds);
      }
    });
  }

  function openMatch(matchId) {
    const match = store.select.match(matchId);
    if (!match) return;
    const team1 = match.team1Id ? store.select.team(match.team1Id) : null;
    const team2 = match.team2Id ? store.select.team(match.team2Id) : null;
    const tournament = store.select.tournament(match.tournamentId);
    const court = tournament?.courts.find((item) => item.id === match.courtId);
    const role = store.getRole();
    const canScore =
      role === 'organizer' &&
      match.status === 'scheduled' &&
      match.team1Id &&
      match.team2Id &&
      !match.isBye;
    const canCorrect =
      role === 'organizer' &&
      (match.status === 'final' || match.status === 'forfeit');

    ui.dialog({
      title: `${match.roundLabel} · Slot ${match.bracketSlot}`,
      size: 'wide',
      body: `
        ${match.isBye ? '<p class="hf-small hf-muted">This is a bye. The seeded team advances automatically.</p>' : ''}
        <div class="hf-card hf-card--flat" data-testid="bracket-match-detail">
          <div class="hf-between hf-match-team ${match.winnerId === match.team1Id ? 'is-winner' : ''}">
            <span class="hf-flex hf-items-center hf-gap-2">
              ${team1 ? components.shield(team1) : ''}
              <strong>${escapeHtml(team1?.name || 'TBD')}</strong>
            </span>
            <span class="hf-score">${escapeHtml(String(match.score1 ?? '–'))}</span>
          </div>
          <div class="hf-between hf-match-team ${match.winnerId === match.team2Id ? 'is-winner' : ''}">
            <span class="hf-flex hf-items-center hf-gap-2">
              ${team2 ? components.shield(team2) : ''}
              <strong>${escapeHtml(team2?.name || 'TBD')}</strong>
            </span>
            <span class="hf-score">${escapeHtml(String(match.score2 ?? '–'))}</span>
          </div>
          <div class="hf-stat-strip hf-mt-2">
            <div><span>Status</span><strong>${escapeHtml(match.status)}</strong></div>
            <div><span>Court</span><strong>${escapeHtml(court?.name || 'TBD')}</strong></div>
            <div><span>Date</span><strong>${escapeHtml(match.scheduledAt ? formatDate(match.scheduledAt) : 'TBD')}</strong></div>
            <div><span>Tip-off</span><strong>${escapeHtml(match.scheduledAt ? formatTime(match.scheduledAt) : 'TBD')}</strong></div>
          </div>
        </div>
        ${
          match.correction
            ? `<div class="hf-card hf-card--flat hf-mt-2">
                 <span class="hf-chip hf-chip--danger">Corrected</span>
                 <p class="hf-small hf-muted hf-mt-1 hf-mb-0">${escapeHtml(match.correction.reason)} · previous ${match.correction.previousScore1}–${match.correction.previousScore2}</p>
               </div>`
            : ''
        }
      `,
      footer: `
        <button type="button" class="hf-btn" data-close-match data-testid="bracket-match-close">Close</button>
        <a class="hf-btn" href="${route('gameStats', { match: match.id })}" data-testid="bracket-match-stats">Game stats</a>
        ${canScore ? `<button type="button" class="hf-btn hf-btn--primary" data-open-score data-testid="bracket-enter-score">Enter final score</button>` : ''}
        ${canCorrect ? `<button type="button" class="hf-btn hf-btn--danger" data-open-correction data-testid="bracket-correct-score">Correct score</button>` : ''}
        ${role === 'organizer' ? `<a class="hf-btn" href="${route('gameManage', { match: match.id })}" data-testid="match-in-game">In-game score & player stats</a>` : ''}
      `,
      onMount(element, close) {
        element.querySelector('[data-close-match]').addEventListener('click', close);
        element.querySelector('[data-open-score]')?.addEventListener('click', () => {
          close();
          openScoreConsole({ store, ui, matchId: match.id, onDone: draw });
        });
        element.querySelector('[data-open-correction]')?.addEventListener('click', () => {
          close();
          openCorrectionDialog({ store, ui, matchId: match.id, onDone: draw });
        });
      },
    });
  }

  draw();
}