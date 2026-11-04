import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney, formatTime } from '../ui.js';

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const teamId = params.get('id') || '';
  const team = store.select.team(teamId);

  if (!team) {
    root.innerHTML = ui.emptyState({
      icon: 'people',
      title: 'Team not found',
      message: 'The team link is invalid or the demo has been reset.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('teams')}">Back to teams</a>`,
    });
    return;
  }

  const role = store.getRole();
  if (role === 'fan' && team.status !== 'approved') {
    root.innerHTML = ui.emptyState({
      icon: 'shield-lock',
      title: 'Team not available',
      message: 'Pending and rejected registrations are visible only to the organizer and the team coach.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('teams')}">Back to public teams</a>`,
    });
    return;
  }
  const tournament = store.select.tournament(team.tournamentId);
  const isOwner = role === 'coach' && store.select.coachTeams().some((item) => item.id === team.id);
  const canSeePrivate = role === 'organizer' || isOwner;
  const record = store.select.teamRecord(team.id);
  const matches = store.select.teamMatches(team.id);
  const played = matches.filter((match) => match.status === 'final' || match.status === 'forfeit');
  const next = store.select.nextMatch(team.id);
  const bracket = store.select.bracket(team.tournamentId);
  const bracketMatch = bracket.rounds
    .flatMap((round) => round.matches)
    .find((match) => match.team1Id === team.id || match.team2Id === team.id);

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: `${tournament?.division || 'Division'} · ${tournament?.name || 'Tournament'}`,
      title: team.name,
      description: `${team.coach} · ${team.city}`,
      actions: `
        <a class="hf-btn" href="${route('teams')}" data-testid="team-back"><i class="bi bi-arrow-left"></i> Teams</a>
        ${isOwner ? `<a class="hf-btn hf-btn--primary" href="${route('myTeam', { team: team.id })}" data-testid="team-manage-roster"><i class="bi bi-person-lines-fill"></i> Manage roster</a>` : ''}
      `,
    })}

    <section class="hf-hero hf-mb-3" data-testid="team-hero-${escapeHtml(team.id)}">
      <div class="hf-flex hf-items-center hf-gap-3">
        ${components.shield(team, 'lg')}
        <div>
          <span class="hf-flex hf-wrap hf-gap-1">
            ${ui.statusChip(team.status)}
            ${canSeePrivate ? ui.statusChip(team.payment.status) : ''}
            ${team.seed ? `<span class="hf-chip hf-chip--slate">Seed ${team.seed}</span>` : ''}
          </span>
          <h1>${escapeHtml(team.name)}</h1>
          <p>${escapeHtml(team.coach)} · ${escapeHtml(team.city)} · ${escapeHtml(tournament?.venue || '')}</p>
        </div>
      </div>
      <div class="hf-stat-strip">
        <div><span>Record</span><strong>${record.wins}-${record.losses}</strong></div>
        <div><span>Points for</span><strong>${record.pointsFor}</strong></div>
        <div><span>Point diff</span><strong>${record.diff > 0 ? '+' : ''}${record.diff}</strong></div>
        <div><span>Roster</span><strong>${team.players.length}</strong></div>
      </div>
    </section>

    <div class="hf-split">
      <div>
        ${components.sectionHeader({ title: `Roster (${team.players.length})` })}
        <div class="hf-card hf-mb-3" data-testid="team-roster">
          ${
            team.players.length
              ? `<div class="hf-table-wrap">
                   <table class="hf-table">
                     <thead>
                       <tr>
                         <th scope="col">#</th>
                         <th scope="col">Player</th>
                         <th scope="col">Position</th>
                         ${role === 'fan' ? '' : '<th scope="col">Age</th>'}
                         ${canSeePrivate ? '<th scope="col">Consent</th><th scope="col">Documents</th>' : ''}
                       </tr>
                     </thead>
                     <tbody>
                       ${team.players
                         .map(
                           (player) => `
                             <tr>
                               <td>${player.number}</td>
                               <td><strong>${escapeHtml(player.name)}</strong></td>
                               <td>${escapeHtml(player.position)}</td>
                               ${role === 'fan' ? '' : `<td>${player.age}</td>`}
                               ${canSeePrivate ? `<td>${player.guardianConsent ? '<span class="hf-chip hf-chip--success">On file</span>' : '<span class="hf-chip hf-chip--danger">Missing</span>'}</td><td>${ui.statusChip(player.documentStatus)}</td>` : ''}
                             </tr>
                           `
                         )
                         .join('')}
                     </tbody>
                   </table>
                 </div>`
              : ui.emptyState({ icon: 'person-plus', title: 'No players yet', message: 'The coach has not added a roster.' })
          }
        </div>

        ${components.sectionHeader({ title: 'Results', action: `<a class="hf-section-link" href="${route('matches', { id: team.tournamentId })}">All matchups</a>` })}
        <div class="hf-grid hf-grid--2 hf-mb-3">
          ${played.length
            ? played.map((match) => components.matchCard(match, { compact: true, href: route('gameStats', { match: match.id }) })).join('')
            : ui.emptyState({ icon: 'flag', title: 'No results yet', message: 'Final scores will appear here.' })}
        </div>
      </div>

      <aside>
        <div class="hf-card hf-mb-3">
          <span class="hf-kpi-label">Next game</span>
          ${
            next
              ? `<h3 class="hf-section-title hf-mt-1">${escapeHtml(next.roundLabel)}</h3>
                 <p class="hf-small hf-muted hf-mb-1">${escapeHtml(formatDate(next.scheduledAt))} · ${escapeHtml(formatTime(next.scheduledAt))}</p>
                 <a class="hf-btn hf-btn--sm" href="${route('gameStats', { match: next.id })}" data-testid="team-next-game">Game details</a>`
              : `<p class="hf-small hf-muted hf-mb-0">No scheduled game. The bracket or schedule may still be in progress.</p>`
          }
        </div>

        <div class="hf-card hf-mb-3">
          <span class="hf-kpi-label">Bracket position</span>
          ${
            bracketMatch
              ? `<h3 class="hf-section-title hf-mt-1">${escapeHtml(bracketMatch.roundLabel)} · Slot ${bracketMatch.bracketSlot}</h3>
                 <p class="hf-small hf-muted hf-mb-1">${ui.statusChip(bracketMatch.status)}</p>
                 <a class="hf-btn hf-btn--sm" href="${route('bracket', { id: team.tournamentId, match: bracketMatch.id })}" data-testid="team-bracket-link">Open bracket</a>`
              : `<p class="hf-small hf-muted hf-mb-0">The bracket has not been generated yet.</p>`
          }
        </div>

        ${
          canSeePrivate
            ? `<div class="hf-card hf-mb-3" data-testid="team-private">
                 <span class="hf-kpi-label">Registration</span>
                 <h3 class="hf-section-title hf-mt-1">${escapeHtml(team.status)}</h3>
                 <p class="hf-small hf-muted">${escapeHtml(team.rejectionReason || 'Documents, consent, and payment are tracked by the organizer.')}</p>
                 <div class="hf-flex hf-wrap hf-gap-1 hf-mb-2">
                   ${team.eligibility.documentsComplete ? ui.statusChip('verified', 'Documents') : ui.statusChip('missing', 'Documents')}
                   ${team.eligibility.consentComplete ? ui.statusChip('verified', 'Consent') : ui.statusChip('missing', 'Consent')}
                 </div>
                 <span class="hf-kpi-label">Payment</span>
                 <p class="hf-small hf-mb-0">${escapeHtml(team.payment.method || 'No method')} · ${escapeHtml(team.payment.ref || 'No reference')} · ${escapeHtml(formatMoney(team.payment.amount || 0))}</p>
               </div>`
            : `<div class="hf-card hf-mb-3">
                 <span class="hf-kpi-label">Public profile</span>
                 <p class="hf-small hf-muted hf-mb-0">Payment and eligibility details are visible only to the organizer and the team's coach.</p>
               </div>`
        }

        <div class="hf-card">
          <span class="hf-kpi-label">Tournament</span>
          <h3 class="hf-section-title hf-mt-1">${escapeHtml(tournament?.name || '')}</h3>
          <p class="hf-small hf-muted hf-mb-1">${escapeHtml(tournament?.division || '')} · ${escapeHtml(tournament?.venue || '')}</p>
          <a class="hf-btn hf-btn--sm" href="${route('tournamentShow', { id: team.tournamentId })}" data-testid="team-tournament-link">Tournament desk</a>
        </div>
      </aside>
    </div>
  `;
}