import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney, formatTime } from '../ui.js';

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const tournamentId = params.get('id') || 'tournament-1';
  let tournament = store.select.tournament(tournamentId);

  if (!tournament) {
    root.innerHTML = ui.emptyState({
      icon: 'trophy',
      title: 'Tournament not found',
      message: 'The tournament link is invalid or the demo has been reset.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Back to tournaments</a>`,
    });
    return;
  }

  function draw() {
    tournament = store.select.tournament(tournamentId);
    const role = store.getRole();
    const kpis = store.select.kpis(tournamentId);
    const allTeams = store.select.teams(tournamentId);
    const teams = role === 'fan' ? allTeams.filter((team) => team.status === 'approved') : allTeams;
    const bracket = store.select.bracket(tournamentId);
    const announcements = store
      .select.announcements(tournamentId)
      .filter((item) => role !== 'fan' || item.audience === 'all');
    const coachTeam = role === 'coach'
      ? store.select.coachTeams().find((team) => team.tournamentId === tournamentId)
      : null;
    const approved = teams.filter((team) => team.status === 'approved');
    const canGenerateBracket = role === 'organizer' && !bracket.hasBracket && approved.length >= 2;
    const canGenerateSchedule =
      role === 'organizer' &&
      bracket.hasBracket &&
      !kpis.matchesPlayed &&
      !store.select.matches(tournamentId).some((match) => match.courtId);

    const roleAction =
      role === 'coach'
        ? coachTeam
          ? `<a class="hf-btn" href="${route('myTeam', { team: coachTeam.id })}" data-testid="show-my-team">My team status</a>`
          : tournament.registrationOpen
            ? `<a class="hf-btn hf-btn--primary" href="${route('teamRegister', { tournament: tournamentId })}" data-testid="show-register">Register team</a>`
            : ''
        : '';

    root.innerHTML = `
      <section class="hf-hero hf-mb-3" data-testid="tournament-hero-${escapeHtml(tournament.id)}">
        <span class="hf-eyebrow" style="color: var(--hf-gold)">${escapeHtml(tournament.division)} · ${escapeHtml(tournament.status)}</span>
        <h1>${escapeHtml(tournament.name)}</h1>
        <p>${escapeHtml(tournament.description)}</p>
        <div class="hf-stat-strip">
          <div><span>Dates</span><strong>${escapeHtml(formatDate(tournament.startDate))} – ${escapeHtml(formatDate(tournament.endDate))}</strong></div>
          <div><span>Venue</span><strong>${escapeHtml(tournament.venue)}</strong></div>
          <div><span>Courts</span><strong>${tournament.courts.length}</strong></div>
          <div><span>Entry</span><strong>${escapeHtml(formatMoney(tournament.fee))}</strong></div>
        </div>
        <div class="hf-btn-row hf-mt-3">
          <a class="hf-btn hf-btn--primary" href="${route('bracket', { id: tournamentId })}">Bracket</a>
          <a class="hf-btn" href="${route('schedule', { id: tournamentId })}">Schedule</a>
          <a class="hf-btn" href="${route('standings', { id: tournamentId })}">Standings</a>
          ${roleAction}
          ${role === 'organizer' ? `<a class="hf-btn" href="${route('announcementCreate', { tournament: tournamentId })}" data-testid="show-create-announcement">Announce</a>` : ''}
        </div>
      </section>

      <div class="hf-grid hf-grid--4 hf-mb-3">
        ${
          role === 'fan'
            ? [
                components.kpiTile({ label: 'Teams', value: teams.length, hint: `${tournament.slots} slots`, tone: 'primary', testid: 'show-kpi-registered' }),
                components.kpiTile({ label: 'Matches played', value: kpis.matchesPlayed, hint: `${kpis.matchesScheduled} scheduled`, tone: 'success', testid: 'show-kpi-played' }),
                components.kpiTile({ label: 'Courts', value: kpis.courts, hint: 'Venue capacity', tone: 'gold', testid: 'show-kpi-courts' }),
                components.kpiTile({ label: 'Entry', value: formatMoney(tournament.fee), hint: 'Per team', tone: 'alert', testid: 'show-kpi-entry' }),
              ].join('')
            : [
                components.kpiTile({ label: 'Registered', value: kpis.registered, hint: `${tournament.slots} slots`, tone: 'primary', testid: 'show-kpi-registered' }),
                components.kpiTile({ label: 'Approved', value: kpis.approved, hint: `${kpis.pending} pending`, tone: 'success', testid: 'show-kpi-approved' }),
                components.kpiTile({ label: 'Matches played', value: kpis.matchesPlayed, hint: `${kpis.matchesScheduled} scheduled`, tone: 'gold', testid: 'show-kpi-played' }),
                components.kpiTile({ label: 'Collected', value: formatMoney(kpis.collected), hint: `${kpis.collectionRate}% of expected`, tone: 'alert', testid: 'show-kpi-money' }),
              ].join('')
        }
      </div>

      <div class="hf-split">
        <div>
          ${components.sectionHeader({ title: 'Team list', action: `<a class="hf-section-link" href="${route('teams')}">All teams</a>` })}
          <div class="hf-grid" data-testid="tournament-team-list">
            ${
              teams.length
                ? teams
                    .map((team) => {
                      const showMoney = role === 'organizer' || (role === 'coach' && coachTeam?.id === team.id);
                      return `
                        <a class="hf-card hf-card--flat" href="${route('teamShow', { id: team.id })}" data-testid="tournament-team-${escapeHtml(team.id)}">
                          <div class="hf-between">
                            <span class="hf-flex hf-items-center hf-gap-2">
                              ${components.shield(team)}
                              <span>
                                <strong>${escapeHtml(team.name)}</strong>
                                <span class="hf-small hf-muted" style="display:block">${escapeHtml(team.coach)} · ${escapeHtml(team.city)}</span>
                              </span>
                            </span>
                            <span class="hf-flex hf-wrap hf-gap-1">
                              ${ui.statusChip(team.status)}
                              ${showMoney ? ui.statusChip(team.payment.status) : ''}
                            </span>
                          </div>
                          ${role === 'organizer' && team.status === 'pending' ? `<div class="hf-small hf-muted hf-mt-1">Waiting for review · ${team.players.length} players</div>` : ''}
                        </a>
                      `;
                    })
                    .join('')
                : ui.emptyState({ icon: 'people', title: 'No teams yet', message: 'Coach registrations will appear here.' })
            }
          </div>

          ${components.sectionHeader({ title: 'Announcements', action: `<a class="hf-section-link" href="${route('announcements')}">All announcements</a>` })}
          <div class="hf-grid">
            ${announcements.length ? announcements.map((item) => components.announcementCard(item)).join('') : ui.emptyState({ icon: 'megaphone', title: 'No announcements', message: 'Publish a venue or schedule update for this tournament.' })}
          </div>
        </div>

        <aside>
          ${components.sectionHeader({ title: 'Bracket', action: `<a class="hf-section-link" href="${route('bracket', { id: tournamentId })}">Open</a>` })}
          <div class="hf-card hf-mb-3">
            ${
              bracket.hasBracket
                ? `<div class="hf-between"><span class="hf-chip hf-chip--gold">${bracket.rounds.length} rounds</span><strong>${kpis.matchesPlayed} played</strong></div>
                   <p class="hf-small hf-muted hf-mt-1">${bracket.champion ? `Champion: ${escapeHtml(bracket.champion.name)}` : 'Bracket is live. Winners advance automatically.'}</p>
                   <a class="hf-btn hf-btn--sm" href="${route('bracket', { id: tournamentId })}">Open bracket</a>`
                : `<p class="hf-small hf-muted">${approved.length} approved teams ready to seed.</p>
                   ${
                     canGenerateBracket
                       ? `<button type="button" class="hf-btn hf-btn--primary" data-generate-bracket data-testid="tournament-generate-bracket">Generate bracket</button>`
                       : `<button type="button" class="hf-btn" disabled data-testid="tournament-generate-bracket-disabled">Generate bracket</button>
                          <p class="hf-small hf-muted hf-mt-1">${role === 'organizer' ? 'At least two approved teams are required.' : 'Organizer access required.'}</p>`
                   }`
            }
          </div>

          ${components.sectionHeader({ title: 'Court schedule', action: `<a class="hf-section-link" href="${route('schedule', { id: tournamentId })}">Open</a>` })}
          <div class="hf-card hf-mb-3">
            ${
              !bracket.hasBracket
                ? '<p class="hf-small hf-muted hf-mb-0">Generate the bracket before scheduling courts.</p>'
                : canGenerateSchedule
                  ? `<p class="hf-small hf-muted">${tournament.courts.length} courts · ${tournament.slotMinutes}-minute slots · ${tournament.restMinutes}-minute rest.</p>
                     <button type="button" class="hf-btn hf-btn--primary" data-generate-schedule data-testid="tournament-generate-schedule">Preview schedule</button>`
                  : `<p class="hf-small hf-muted hf-mb-0">${kpis.matchesPlayed ? 'Results are recorded; the schedule is locked.' : 'Schedule already generated.'}</p>
                     <a class="hf-btn hf-btn--sm hf-mt-1" href="${route('schedule', { id: tournamentId })}">Open schedule</a>`
            }
          </div>

          <div class="hf-card">
            <span class="hf-kpi-label">Format</span>
            <h3 class="hf-section-title hf-mt-1">Single elimination</h3>
            <p class="hf-small hf-muted hf-mb-0">${tournament.slots}-team bracket. Round-robin is disabled in v1; the create wizard labels it “v2”.</p>
          </div>
        </aside>
      </div>
    `;

    attach();
  }

  function attach() {
    const bracketButton = root.querySelector('[data-generate-bracket]');
    if (bracketButton) {
      bracketButton.addEventListener('click', async () => {
        const ok = await ui.confirm({
          title: 'Generate bracket?',
          message: 'The approved teams will be seeded into the bracket and the seeds will lock.',
          confirmLabel: 'Generate bracket',
        });
        if (!ok) return;
        ui.setBusy(bracketButton, true, 'Seeding...');
        window.setTimeout(() => {
          try {
            const result = store.actions.generateBracket(tournamentId);
            ui.toast(`${result.matches} matches created with ${result.byes} byes.`, {
              tone: 'success',
              title: 'Bracket generated',
            });
            draw();
          } catch (error) {
            ui.setBusy(bracketButton, false);
            ui.toast(error.message, { tone: 'danger', title: 'Could not generate bracket' });
          }
        }, 450);
      });
    }

    const scheduleButton = root.querySelector('[data-generate-schedule]');
    if (scheduleButton) {
      scheduleButton.addEventListener('click', () => {
        let plan;
        try {
          plan = store.planSchedule(tournamentId);
        } catch (error) {
          ui.toast(error.message, { tone: 'danger', title: 'Could not plan schedule' });
          return;
        }
        const rows = plan.assignments
          .map((assignment) => {
            const match = store.select.match(assignment.matchId);
            const court = plan.courts.find((item) => item.id === assignment.courtId);
            const team1 = match.team1Id ? store.select.team(match.team1Id)?.shortName || 'TBD' : 'TBD';
            const team2 = match.team2Id ? store.select.team(match.team2Id)?.shortName || 'TBD' : 'TBD';
            return `<tr><td>${escapeHtml(match.roundLabel)}</td><td>${escapeHtml(team1)} vs ${escapeHtml(team2)}</td><td>${escapeHtml(court?.name || '')}</td><td>${escapeHtml(formatDate(assignment.scheduledAt))} · ${escapeHtml(formatTime(assignment.scheduledAt))}</td></tr>`;
          })
          .join('');
        ui.dialog({
          title: 'Schedule preview',
          size: 'wide',
          body: `
            <p class="hf-small hf-muted">${plan.assignments.length} matches across ${plan.courts.length} courts. No court double-bookings; every team has at least ${plan.restMinutes} minutes of rest.</p>
            <div class="hf-table-wrap"><table class="hf-table"><thead><tr><th>Round</th><th>Match</th><th>Court</th><th>Slot</th></tr></thead><tbody>${rows}</tbody></table></div>
          `,
          footer: `<button type="button" class="hf-btn" data-cancel-schedule data-testid="schedule-preview-cancel">Cancel</button><button type="button" class="hf-btn hf-btn--primary" data-publish-schedule data-testid="publish-schedule">Publish schedule</button>`,
          onMount(element, close) {
            element.querySelector('[data-cancel-schedule]').addEventListener('click', close);
            element.querySelector('[data-publish-schedule]').addEventListener('click', () => {
              try {
                store.actions.generateSchedule(tournamentId);
                close();
                ui.toast('Court schedule published.', { tone: 'success', title: 'Schedule ready' });
                draw();
              } catch (error) {
                ui.toast(error.message, { tone: 'danger', title: 'Could not publish schedule' });
              }
            });
          },
        });
      });
    }
  }

  draw();
}
