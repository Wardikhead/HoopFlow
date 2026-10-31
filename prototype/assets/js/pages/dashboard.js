import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney } from '../ui.js';

export function render({ store, ui, components, root }) {
  const role = store.getRole();
  const state = store.getState();
  const active = store.select.tournament('tournament-1');
  const pending = state.teams.filter((team) => team.status === 'pending').length;
  const openTournaments = state.tournaments.filter(
    (tournament) => tournament.status === 'registration-open'
  );

  const header = components.pageHeader({
    eyebrow: `Matchday desk · ${role}`,
    title:
      role === 'organizer'
        ? 'Run the tournament'
        : role === 'scorekeeper'
          ? 'Score console'
          : role === 'coach'
            ? 'Coach corner'
            : 'Courtside pass',
    description:
      role === 'organizer'
        ? 'Approvals, payments, bracket progress, and the next action that keeps the matchday moving.'
        : role === 'scorekeeper'
          ? 'Enter final scores, correct recorded results, and open the official box score.'
          : role === 'coach'
            ? 'Your registration status, roster readiness, payment, and next game.'
            : 'Follow the active tournament: bracket, schedule, results, standings, and announcements.',
    actions:
      role === 'organizer'
        ? `<a class="hf-btn hf-btn--primary" href="${route('tournamentCreate')}" data-testid="dashboard-create-tournament"><i class="bi bi-plus-lg"></i> New tournament</a>
           <a class="hf-btn" href="${route('submissions')}" data-testid="dashboard-open-submissions">Review queue</a>`
        : role === 'coach'
          ? `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}" data-testid="dashboard-register-cta"><i class="bi bi-trophy"></i> Find open tournament</a>`
          : `<a class="hf-btn hf-btn--primary" href="${route('bracket')}" data-testid="dashboard-open-bracket"><i class="bi bi-diagram-3"></i> Open bracket</a>`,
    meta: active
      ? `<span><i class="bi bi-calendar3"></i>${escapeHtml(formatDate(active.startDate))} – ${escapeHtml(formatDate(active.endDate))}</span>
         <span><i class="bi bi-geo-alt"></i>${escapeHtml(active.venue)}</span>
         <span><i class="bi bi-people"></i>${active.slots} slots · ${active.courts.length} courts</span>`
      : '',
  });

  root.innerHTML = `${header}<div id="dashboardBody"></div>`;
  const body = root.querySelector('#dashboardBody');
  body.innerHTML =
    role === 'organizer'
      ? organizerDashboard()
      : role === 'scorekeeper'
        ? scorekeeperDashboard()
        : role === 'coach'
          ? coachDashboard()
          : fanDashboard();

  function organizerDashboard() {
    const kpis = store.select.kpis('tournament-1');
    const bracket = store.select.bracket('tournament-1');
    const totalMatches = bracket.rounds.reduce((sum, round) => sum + round.matches.length, 0);
    const progress = totalMatches ? Math.round((kpis.matchesPlayed / totalMatches) * 100) : 0;
    const unpaid = store.select.paymentSummary('tournament-1');
    const recent = store.select.recentResults('tournament-1', 3);
    const announcements = store.select.announcements('tournament-1').slice(0, 2);
    return `
      <div class="hf-grid hf-grid--4 hf-mb-3">
        ${components.kpiTile({ label: 'Tournaments', value: state.tournaments.length, hint: '3 seeded venues', href: route('tournaments'), tone: 'primary', testid: 'kpi-tournaments' })}
        ${components.kpiTile({ label: 'Pending teams', value: pending, hint: 'Waiting for review', href: `${route('submissions')}?status=pending`, tone: 'gold', testid: 'kpi-pending' })}
        ${components.kpiTile({ label: 'Uncollected', value: formatMoney(unpaid.outstanding), hint: `${unpaid.pendingCount} teams unpaid`, href: `${route('payments')}?status=pending`, tone: 'alert', testid: 'kpi-uncollected' })}
        ${components.kpiTile({ label: 'Approved teams', value: kpis.approved, hint: `${kpis.registered} registered`, href: route('teams'), tone: 'success', testid: 'kpi-approved' })}
      </div>

      <div class="hf-split hf-mb-3">
        <div>
          ${components.sectionHeader({ title: 'Next actions', action: `<a class="hf-section-link" href="${route('submissions')}">Open queue</a>` })}
          <div class="hf-grid hf-grid--3 hf-mb-3">
            <a class="hf-card hf-card--flat" href="${route('submissions')}?status=pending" data-testid="dashboard-action-review">
              <span class="hf-chip hf-chip--warning">Review</span>
              <h3 class="hf-section-title hf-mt-2">${pending} pending teams</h3>
              <p class="hf-muted hf-small hf-mb-0">Roster, consent, and documents are waiting for an organizer decision.</p>
            </a>
            <a class="hf-card hf-card--flat" href="${route('payments')}?status=pending" data-testid="dashboard-action-payments">
              <span class="hf-chip hf-chip--danger">Verify</span>
              <h3 class="hf-section-title hf-mt-2">${unpaid.pendingCount} payments</h3>
              <p class="hf-muted hf-small hf-mb-0">Collected ${formatMoney(unpaid.collected)} of ${formatMoney(unpaid.expected)}.</p>
            </a>
            <a class="hf-card hf-card--flat" href="${route('bracket')}?match=qf-3" data-testid="dashboard-action-score">
              <span class="hf-chip hf-chip--primary">Score</span>
              <h3 class="hf-section-title hf-mt-2">${kpis.matchesScheduled} games left</h3>
              <p class="hf-muted hf-small hf-mb-0">Open the quarterfinal console and advance the winner.</p>
            </a>
          </div>

          ${components.sectionHeader({ title: 'Active tournament', action: `<a class="hf-section-link" href="${route('tournamentShow', { id: active.id })}">Open tournament</a>` })}
          <div class="hf-card">
            <div class="hf-between">
              <div>
                <span class="hf-chip hf-chip--primary">${escapeHtml(active.division)}</span>
                <h3 class="hf-page-title hf-mt-1 hf-mb-0" style="font-size: 30px">${escapeHtml(active.name)}</h3>
              </div>
              <div class="hf-text-right">
                <span class="hf-kpi-label">Bracket progress</span>
                <strong class="hf-kpi-value" style="font-size: 32px">${kpis.matchesPlayed}/${totalMatches}</strong>
              </div>
            </div>
            <div class="hf-round-progress hf-mt-2"><span style="width: ${progress}%"></span></div>
            <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2">
              <a class="hf-btn hf-btn--sm" href="${route('bracket')}">Bracket</a>
              <a class="hf-btn hf-btn--sm" href="${route('schedule')}">Schedule</a>
              <a class="hf-btn hf-btn--sm" href="${route('standings')}">Standings</a>
              <a class="hf-btn hf-btn--sm" href="${route('announcements')}">Announcements</a>
            </div>
          </div>

          ${components.sectionHeader({ title: 'Recent results', action: `<a class="hf-section-link" href="${route('matches')}">All matchups</a>` })}
          <div class="hf-grid hf-grid--2">
            ${recent.length ? recent.map((match) => components.matchCard(match, { compact: true, href: route('gameStats', { match: match.id }) })).join('') : ui.emptyState({ icon: 'flag', title: 'No results yet', message: 'Final scores will appear here.' })}
          </div>
        </div>

        <aside>
          ${components.sectionHeader({ title: 'Standings snapshot', action: `<a class="hf-section-link" href="${route('standings')}">Full table</a>` })}
          <div class="hf-mb-3">${components.standingsTable('tournament-1', { limit: 4 })}</div>
          ${components.sectionHeader({ title: 'Announcements', action: `<a class="hf-section-link" href="${route('announcements')}">All</a>` })}
          <div class="hf-grid">${announcements.map((item) => components.announcementCard(item, { compact: true })).join('')}</div>
        </aside>
      </div>
    `;
  }

  function scorekeeperDashboard() {
    const kpis = store.select.kpis('tournament-1');
    const queue = store
      .select.matches('tournament-1')
      .filter((match) => match.status === 'scheduled' && match.team1Id && match.team2Id);
    const recent = store.select.recentResults('tournament-1', 4);
    return `
      <div class="hf-grid hf-grid--3 hf-mb-3">
        ${components.kpiTile({ label: 'Games to score', value: queue.length, hint: 'Both teams known', tone: 'primary', testid: 'kpi-score-queue' })}
        ${components.kpiTile({ label: 'Finals recorded', value: kpis.matchesPlayed, hint: 'Final and forfeit', tone: 'success', testid: 'kpi-finals' })}
        ${components.kpiTile({ label: 'Corrections', value: state.audit.filter((entry) => entry.action === 'score.correct').length, hint: 'Audited changes', tone: 'alert', testid: 'kpi-corrections' })}
      </div>
      ${components.sectionHeader({ title: 'Score queue', action: `<a class="hf-section-link" href="${route('bracket')}">Bracket</a>` })}
      <div class="hf-grid hf-grid--2 hf-mb-3" data-testid="scorekeeper-queue">
        ${queue.length ? queue.map((match) => components.matchCard(match, { href: `${route('bracket')}?match=${match.id}` })).join('') : ui.emptyState({ icon: 'check2-circle', title: 'Queue is clear', message: 'No scheduled game has both teams known.' })}
      </div>
      ${components.sectionHeader({ title: 'Recent finals' })}
      <div class="hf-grid hf-grid--2">
        ${recent.map((match) => components.matchCard(match, { compact: true, href: route('gameStats', { match: match.id }) })).join('')}
      </div>
      <p class="hf-small hf-muted hf-mt-3">Scorekeeper access allows score entry and correction only. Approvals, payments, and roster edits are blocked.</p>
    `;
  }

  function coachDashboard() {
    const teams = store.select.coachTeams();
    const team = teams[0];
    if (!team) {
      return ui.emptyState({
        icon: 'person-plus',
        title: 'No team attached',
        message: 'Register a team for an open tournament to start the coach journey.',
        action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Browse tournaments</a>`,
      });
    }
    const record = store.select.teamRecord(team.id);
    const next = store.select.nextMatch(team.id);
    const tournament = store.select.tournament(team.tournamentId);
    return `
      <section class="hf-hero hf-mb-3" data-testid="coach-hero">
        <span class="hf-eyebrow" style="color: var(--hf-gold)">${escapeHtml(tournament?.name || 'Tournament')} · ${escapeHtml(team.status)}</span>
        <h1>${escapeHtml(team.name)}</h1>
        <p>${escapeHtml(team.coach)} · ${escapeHtml(team.city)} · ${escapeHtml(tournament?.division || '')}</p>
        <div class="hf-stat-strip">
          <div><span>Record</span><strong>${record.wins}-${record.losses}</strong></div>
          <div><span>Seed</span><strong>${team.seed ?? 'TBD'}</strong></div>
          <div><span>Roster</span><strong>${team.players.length}</strong></div>
          <div><span>Payment</span><strong>${escapeHtml(team.payment.status)}</strong></div>
        </div>
      </section>
      <div class="hf-grid hf-grid--3 hf-mb-3">
        <div class="hf-card">
          <span class="hf-kpi-label">Roster readiness</span>
          <strong class="hf-kpi-value">${team.players.filter((player) => player.guardianConsent).length}/${team.players.length}</strong>
          <p class="hf-small hf-muted hf-mb-0">Guardian consent on file.</p>
          <a class="hf-btn hf-btn--sm hf-mt-2" href="${route('myTeam', { team: team.id })}">Manage roster</a>
        </div>
        <div class="hf-card">
          <span class="hf-kpi-label">Next game</span>
          <strong class="hf-section-title hf-mt-1">${next ? escapeHtml(next.roundLabel) : 'No game scheduled'}</strong>
          <p class="hf-small hf-muted hf-mb-0">${next?.scheduledAt ? escapeHtml(formatDate(next.scheduledAt)) : 'Waiting for the bracket.'}</p>
          ${next ? `<a class="hf-btn hf-btn--sm hf-mt-2" href="${route('gameStats', { match: next.id })}">Game details</a>` : ''}
        </div>
        <div class="hf-card">
          <span class="hf-kpi-label">Registration</span>
          <strong class="hf-section-title hf-mt-1">${escapeHtml(team.status)}</strong>
          <p class="hf-small hf-muted hf-mb-0">${escapeHtml(team.rejectionReason || 'Documents and payment are tracked by the organizer.')}</p>
        </div>
      </div>
      ${components.sectionHeader({ title: 'Open tournaments', action: `<a class="hf-section-link" href="${route('tournaments')}">All tournaments</a>` })}
      <div class="hf-grid hf-grid--2">
        ${openTournaments.length ? openTournaments.map((item) => `
          <div class="hf-card">
            <span class="hf-chip hf-chip--success">Registration open</span>
            <h3 class="hf-section-title hf-mt-1">${escapeHtml(item.name)}</h3>
            <p class="hf-small hf-muted">${escapeHtml(item.division)} · ${escapeHtml(item.venue)} · ${formatMoney(item.fee)} entry</p>
            <a class="hf-btn hf-btn--primary" href="${route('teamRegister', { tournament: item.id })}" data-testid="coach-register-${item.id}">Register a team</a>
          </div>
        `).join('') : ui.emptyState({ icon: 'trophy', title: 'No open registration', message: 'Check back after the organizer opens the next tournament.' })}
      </div>
    `;
  }

  function fanDashboard() {
    const upcoming = store.select.upcomingMatches('tournament-1', 4);
    const recent = store.select.recentResults('tournament-1', 4);
    const announcements = store
      .select.announcements('tournament-1')
      .filter((item) => item.audience === 'all')
      .slice(0, 3);
    return `
      <section class="hf-hero hf-mb-3" data-testid="fan-hero">
        <span class="hf-eyebrow" style="color: var(--hf-gold)">Courtside pass · ${escapeHtml(active.status)}</span>
        <h1>${escapeHtml(active.name)}</h1>
        <p>${escapeHtml(active.division)} · ${escapeHtml(active.venue)} · ${escapeHtml(formatDate(active.startDate))} to ${escapeHtml(formatDate(active.endDate))}</p>
        <div class="hf-btn-row hf-mt-3">
          <a class="hf-btn hf-btn--primary" href="${route('bracket')}">Bracket</a>
          <a class="hf-btn" href="${route('schedule')}">Schedule</a>
          <a class="hf-btn" href="${route('standings')}">Standings</a>
        </div>
      </section>
      <div class="hf-split">
        <div>
          ${components.sectionHeader({ title: 'Up next', action: `<a class="hf-section-link" href="${route('schedule')}">Full schedule</a>` })}
          <div class="hf-grid hf-grid--2 hf-mb-3">
            ${upcoming.length ? upcoming.map((match) => components.matchCard(match, { compact: true, href: route('gameStats', { match: match.id }) })).join('') : ui.emptyState({ icon: 'calendar3', title: 'No games scheduled', message: 'The next tip-off will be posted here.' })}
          </div>
          ${components.sectionHeader({ title: 'Recent results' })}
          <div class="hf-grid hf-grid--2">
            ${recent.map((match) => components.matchCard(match, { compact: true, href: route('gameStats', { match: match.id }) })).join('')}
          </div>
        </div>
        <aside>
          ${components.sectionHeader({ title: 'Top of the table', action: `<a class="hf-section-link" href="${route('standings')}">Full table</a>` })}
          <div class="hf-mb-3">${components.standingsTable('tournament-1', { limit: 5 })}</div>
          ${components.sectionHeader({ title: 'Announcements' })}
          <div class="hf-grid">${announcements.map((item) => components.announcementCard(item, { compact: true })).join('')}</div>
        </aside>
      </div>
    `;
  }
}
