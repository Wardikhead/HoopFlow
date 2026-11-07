import { route } from '../routes.js';
import { escapeHtml, formatDate, formatTime, formatMoney } from '../ui.js';
import { guardRole } from '../guards.js';
import { PAYMENT_METHODS } from '../options.js';
import { POSITIONS } from '../seed.js';

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['coach'] })) return;

  const params = new URLSearchParams(window.location.search);
  let teams = store.select.coachTeams();

  if (!teams.length) {
    root.innerHTML = ui.emptyState({
      icon: 'person-plus',
      title: 'No team attached',
      message: 'Register a team for an open tournament to start the coach journey.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}" data-testid="my-team-browse">Browse tournaments</a>`,
    });
    return;
  }

  let teamId = params.get('team') || teams[0].id;
  if (!teams.some((team) => team.id === teamId)) teamId = teams[0].id;

  function draw() {
    teams = store.select.coachTeams();
    const team = store.select.team(teamId);
    if (!team || !teams.some((item) => item.id === team.id)) {
      root.innerHTML = ui.emptyState({
        icon: 'people',
        title: 'Team not found',
        message: 'Choose one of your registered teams.',
        action: `<a class="hf-btn hf-btn--primary" href="${route('myTeam')}">Open my team</a>`,
      });
      return;
    }

    const tournament = store.select.tournament(team.tournamentId);
    const record = store.select.teamRecord(team.id);
    const next = store.select.nextMatch(team.id);
    const locked = Boolean(team.eligibility.verifiedAt);
    const consentCount = team.players.filter((player) => player.guardianConsent).length;
    const docsCount = team.players.filter((player) => player.documentStatus === 'verified').length;

    const timeline = [
      { title: 'Registration submitted', detail: formatDate(team.submittedAt), done: true },
      {
        title: 'Organizer review',
        detail: team.status === 'pending' ? 'Waiting for review' : `Status: ${team.status}`,
        done: team.status !== 'pending',
      },
      {
        title: 'Payment',
        detail: `${team.payment.status} · ${team.payment.method || 'No method'}${team.payment.ref ? ` · ${team.payment.ref}` : ''}`,
        done: team.payment.status === 'paid',
      },
      {
        title: 'Roster verified',
        detail: team.eligibility.verifiedAt ? `Verified ${formatDate(team.eligibility.verifiedAt)}` : 'Locks after organizer verification',
        done: Boolean(team.eligibility.verifiedAt),
      },
      {
        title: 'Seeding',
        detail: team.seed ? `Seed ${team.seed}` : 'Assigned when the bracket is generated',
        done: Boolean(team.seed),
      },
    ];

    root.innerHTML = `
      ${components.pageHeader({
        eyebrow: `${tournament?.name || 'Tournament'} · ${tournament?.division || ''}`,
        title: team.name,
        description: `${team.coach} · ${team.city} · ${tournament?.venue || ''}`,
        actions: `
          <a class="hf-btn" href="${route('teamShow', { id: team.id })}" data-testid="my-team-public">Public profile</a>
          <a class="hf-btn" href="${route('tournaments')}" data-testid="my-team-register-another"><i class="bi bi-plus-lg"></i> Register another team</a>
        `,
        meta: `
          <span><i class="bi bi-shield-check"></i>${ui.statusChip(team.status)}</span>
          <span><i class="bi bi-cash-coin"></i>${ui.statusChip(team.payment.status)}</span>
          <span><i class="bi bi-people"></i>${team.players.length} players</span>
        `,
      })}

      ${
        teams.length > 1
          ? `<div class="hf-card hf-card--flat hf-mb-3">
               <label class="hf-label" for="myTeamSelect">Switch team</label>
               <select class="hf-select" id="myTeamSelect" data-testid="my-team-select">
                 ${teams.map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === team.id ? 'selected' : ''}>${escapeHtml(item.name)} · ${escapeHtml(item.status)}</option>`).join('')}
               </select>
             </div>`
          : ''
      }

      ${
        team.status === 'rejected'
          ? `<div class="hf-card hf-card--flat hf-mb-3" style="border-color: var(--hf-alert)">
               <span class="hf-chip hf-chip--danger">Rejected</span>
               <h3 class="hf-section-title hf-mt-1">Organizer feedback</h3>
               <p class="hf-small hf-muted hf-mb-0">${escapeHtml(team.rejectionReason || 'Contact the tournament committee for details.')}</p>
             </div>`
          : ''
      }

      <div class="hf-grid hf-grid--4 hf-mb-3">
        ${components.kpiTile({ label: 'Record', value: `${record.wins}-${record.losses}`, hint: 'Wins and losses', tone: 'primary', testid: 'my-team-record' })}
        ${components.kpiTile({ label: 'Roster', value: team.players.length, hint: `${consentCount} consents · ${docsCount} verified`, tone: 'success', testid: 'my-team-roster-kpi' })}
        ${components.kpiTile({ label: 'Seed', value: team.seed ?? 'TBD', hint: team.seed ? 'Locked for the bracket' : 'Assigned after approval', tone: 'gold', testid: 'my-team-seed' })}
        ${components.kpiTile({ label: 'Entry fee', value: formatMoney(tournament?.fee || 0), hint: team.payment.status === 'paid' ? 'Paid' : 'Payment outstanding', tone: 'alert', testid: 'my-team-fee' })}
      </div>

      <div class="hf-split">
        <div>
          ${components.sectionHeader({
            title: `Roster (${team.players.length})`,
            action: locked
              ? '<span class="hf-chip hf-chip--slate"><i class="bi bi-lock"></i> Locked after verification</span>'
              : `<button type="button" class="hf-btn hf-btn--sm hf-btn--primary" data-add-player data-testid="my-team-add-player" ${team.players.length >= 12 ? 'disabled' : ''}><i class="bi bi-plus-lg"></i> Add player</button>`,
          })}
          <div class="hf-card hf-mb-3" data-testid="my-team-roster">
            ${team.players.length
              ? `<div class="hf-table-wrap">
                   <table class="hf-table">
                     <thead>
                       <tr>
                         <th scope="col">#</th>
                         <th scope="col">Player</th>
                         <th scope="col">Position</th>
                         <th scope="col">Age</th>
                         <th scope="col">Consent</th>
                         <th scope="col">Documents</th>
                         ${locked ? '' : '<th scope="col">Actions</th>'}
                       </tr>
                     </thead>
                     <tbody>
                       ${team.players.map((player) => `
                         <tr data-testid="my-team-player-${escapeHtml(player.id)}">
                           <td>${player.number}</td>
                           <td><strong>${escapeHtml(player.name)}</strong></td>
                           <td>${escapeHtml(player.position)}</td>
                           <td>${player.age}</td>
                           <td>${player.guardianConsent ? '<span class="hf-chip hf-chip--success">On file</span>' : '<span class="hf-chip hf-chip--danger">Missing</span>'}</td>
                           <td>${ui.statusChip(player.documentStatus)}</td>
                           ${locked ? '' : `<td>
                             <div class="hf-flex hf-gap-1">
                               <button type="button" class="hf-icon-btn" data-edit-player="${escapeHtml(player.id)}"
                                       aria-label="Edit ${escapeHtml(player.name)}" data-testid="my-team-edit-${escapeHtml(player.id)}">
                                 <i class="bi bi-pencil-square"></i>
                               </button>
                               <button type="button" class="hf-icon-btn" data-remove-player="${escapeHtml(player.id)}"
                                       aria-label="Remove ${escapeHtml(player.name)}" data-testid="my-team-remove-${escapeHtml(player.id)}">
                                 <i class="bi bi-trash3"></i>
                               </button>
                             </div>
                           </td>`}
                         </tr>
                       `).join('')}
                     </tbody>
                   </table>
                 </div>`
              : ui.emptyState({ icon: 'person-plus', title: 'No players yet', message: 'Add eight to twelve players to complete the roster.' })}
          </div>

          ${components.sectionHeader({ title: 'Registration timeline' })}
          <div class="hf-card hf-mb-3">
            <ol class="hf-timeline" data-testid="my-team-timeline">
              ${timeline.map((item) => `
                <li class="hf-timeline-item">
                  <div class="hf-timeline-meta">
                    <span>${item.done ? 'Complete' : 'Pending'}</span>
                    ${item.done ? '<span class="hf-chip hf-chip--success">Done</span>' : '<span class="hf-chip hf-chip--slate">Waiting</span>'}
                  </div>
                  <h3>${escapeHtml(item.title)}</h3>
                  <p>${escapeHtml(item.detail)}</p>
                </li>
              `).join('')}
            </ol>
          </div>
        </div>

        <aside>
          <div class="hf-card hf-mb-3">
            <span class="hf-kpi-label">Eligibility</span>
            <h3 class="hf-section-title hf-mt-1">${locked ? 'Roster locked' : 'Roster editable'}</h3>
            <div class="hf-flex hf-wrap hf-gap-1 hf-mt-1">
              ${consentCount === team.players.length ? ui.statusChip('verified', 'Consent complete') : ui.statusChip('missing', `${team.players.length - consentCount} consent missing`)}
              ${docsCount === team.players.length ? ui.statusChip('verified', 'Documents complete') : ui.statusChip('missing', `${team.players.length - docsCount} documents pending`)}
            </div>
            <p class="hf-small hf-muted hf-mt-2 hf-mb-0">${locked ? 'The organizer verified this roster. Contact the committee to make a change.' : 'Add missing consent and documents before the organizer review.'}</p>
          </div>

          <div class="hf-card hf-mb-3" data-testid="my-team-payment">
            <div class="hf-between">
              <span class="hf-kpi-label">Payment</span>
              ${ui.statusChip(team.payment.status)}
            </div>
            <h3 class="hf-section-title hf-mt-1">${escapeHtml(team.payment.method || 'No method')}</h3>
            <p class="hf-small hf-muted hf-mb-1">${escapeHtml(team.payment.ref || 'No reference')} · ${escapeHtml(formatMoney(team.payment.amount || tournament?.fee || 0))}</p>
            <p class="hf-small hf-muted hf-mb-0"><span class="hf-kpi-label">Send to:</span> ${escapeHtml(tournament?.paymentMethod || 'GCash')} · ${escapeHtml(tournament?.paymentAccount || 'TBD')}</p>
            ${team.payment.verifiedAt ? `<p class="hf-small hf-muted hf-mb-0">Verified ${escapeHtml(formatDate(team.payment.verifiedAt))}</p>` : ''}
            ${team.payment.status !== 'paid' ? `<button type="button" class="hf-btn hf-btn--sm hf-btn--primary hf-mt-2" data-update-payment data-testid="my-team-update-payment">Update payment reference</button>` : ''}
          </div>

          <div class="hf-card hf-mb-3">
            <span class="hf-kpi-label">Next game</span>
            ${next
              ? `<h3 class="hf-section-title hf-mt-1">${escapeHtml(next.roundLabel)}</h3>
                 <p class="hf-small hf-muted hf-mb-1">${escapeHtml(formatDate(next.scheduledAt))} · ${escapeHtml(formatTime(next.scheduledAt))}</p>
                 <p class="hf-small hf-muted hf-mb-1">${escapeHtml(tournament?.courts.find((court) => court.id === next.courtId)?.name || 'Court TBD')}</p>
                 <a class="hf-btn hf-btn--sm" href="${route('gameStats', { match: next.id })}" data-testid="my-team-next-game">Game details</a>`
              : `<p class="hf-small hf-muted hf-mb-0">No game scheduled yet.</p>`}
          </div>

          <div class="hf-card">
            <span class="hf-kpi-label">Tournament</span>
            <h3 class="hf-section-title hf-mt-1">${escapeHtml(tournament?.name || '')}</h3>
            <p class="hf-small hf-muted hf-mb-1">${escapeHtml(tournament?.division || '')} · ${escapeHtml(tournament?.venue || '')}</p>
            <a class="hf-btn hf-btn--sm" href="${route('tournamentShow', { id: team.tournamentId })}" data-testid="my-team-tournament">Tournament desk</a>
          </div>
        </aside>
      </div>
    `;

    attach(team, locked);
  }

  function attach(team, locked) {
    root.querySelector('#myTeamSelect')?.addEventListener('change', (event) => {
      window.location.href = route('myTeam', { team: event.target.value });
    });

    root.querySelector('[data-add-player]')?.addEventListener('click', () => openPlayerDialog(team));

    root.querySelectorAll('[data-edit-player]').forEach((button) => {
      button.addEventListener('click', () => {
        const player = team.players.find((item) => item.id === button.dataset.editPlayer);
        if (player) openPlayerDialog(team, player);
      });
    });

    root.querySelectorAll('[data-remove-player]').forEach((button) => {
      button.addEventListener('click', async () => {
        const player = team.players.find((item) => item.id === button.dataset.removePlayer);
        if (!player) return;
        const ok = await ui.confirm({
          title: `Remove ${player.name}?`,
          message: 'The player is removed from the roster immediately. You can add them again before verification.',
          confirmLabel: 'Remove player',
          tone: 'danger',
        });
        if (!ok) return;
        try {
          store.actions.removePlayer(team.id, player.id);
          ui.toast(`${player.name} removed from the roster.`, { tone: 'success', title: 'Roster updated' });
          draw();
        } catch (error) {
          ui.toast(error.message, { tone: 'danger', title: 'Could not remove player' });
        }
      });
    });

    root.querySelector('[data-update-payment]')?.addEventListener('click', () => openPaymentDialog(team));
  }

  function openPlayerDialog(team, player = null) {
    const editing = Boolean(player);
    const draft = player
      ? { ...player }
      : { name: '', number: '', position: POSITIONS[0], age: '', guardianConsent: false, documentStatus: 'pending' };

    ui.dialog({
      title: editing ? `Edit ${player.name}` : 'Add player',
      body: `
        <div class="hf-form-grid">
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="myPlayerName">Player name</label>
            <input class="hf-input" id="myPlayerName" value="${escapeHtml(draft.name)}" data-testid="my-player-name">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="myPlayerNumber">Jersey number</label>
            <input class="hf-input" id="myPlayerNumber" type="number" min="1" max="99" value="${escapeHtml(String(draft.number))}" data-testid="my-player-number">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="myPlayerPosition">Position</label>
            <select class="hf-select" id="myPlayerPosition" data-testid="my-player-position">
              ${POSITIONS.map((position) => `<option value="${escapeHtml(position)}" ${position === draft.position ? 'selected' : ''}>${escapeHtml(position)}</option>`).join('')}
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="myPlayerAge">Age</label>
            <input class="hf-input" id="myPlayerAge" type="number" min="8" max="99" value="${escapeHtml(String(draft.age))}" data-testid="my-player-age">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="myPlayerDocument">Documents</label>
            <select class="hf-select" id="myPlayerDocument" data-testid="my-player-document">
              <option value="verified" ${draft.documentStatus === 'verified' ? 'selected' : ''}>Verified</option>
              <option value="pending" ${draft.documentStatus === 'pending' ? 'selected' : ''}>Pending review</option>
              <option value="missing" ${draft.documentStatus === 'missing' ? 'selected' : ''}>Missing</option>
            </select>
          </div>
          <div class="hf-field hf-field--full">
            <label class="hf-check">
              <input type="checkbox" id="myPlayerConsent" ${draft.guardianConsent ? 'checked' : ''} data-testid="my-player-consent">
              Guardian consent on file
            </label>
          </div>
        </div>
      `,
      footer: `
        <button type="button" class="hf-btn" data-cancel-player data-testid="my-player-cancel">Cancel</button>
        <button type="button" class="hf-btn hf-btn--primary" data-save-player data-testid="my-player-save">${editing ? 'Save player' : 'Add player'}</button>
      `,
      onMount(element, close) {
        const fields = {
          name: element.querySelector('#myPlayerName'),
          number: element.querySelector('#myPlayerNumber'),
          position: element.querySelector('#myPlayerPosition'),
          age: element.querySelector('#myPlayerAge'),
          documentStatus: element.querySelector('#myPlayerDocument'),
          guardianConsent: element.querySelector('#myPlayerConsent'),
        };
        element.querySelector('[data-cancel-player]').addEventListener('click', close);
        element.querySelector('[data-save-player]').addEventListener('click', () => {
          Object.values(fields).forEach((field) => ui.clearFieldError(field));
          const payload = {
            name: fields.name.value.trim(),
            number: Number(fields.number.value),
            position: fields.position.value,
            age: Number(fields.age.value),
            guardianConsent: fields.guardianConsent.checked,
            documentStatus: fields.documentStatus.value,
          };
          try {
            if (editing) store.actions.updatePlayer(team.id, player.id, payload);
            else store.actions.addPlayer(team.id, payload);
            close();
            ui.toast(editing ? `${payload.name} updated.` : `${payload.name} added to the roster.`, {
              tone: 'success',
              title: 'Roster updated',
            });
            draw();
          } catch (error) {
            const target = fields[error.field];
            if (target) ui.setFieldError(target, error.message);
            ui.toast(error.message, { tone: 'danger', title: 'Could not save player' });
          }
        });
      },
    });
  }

  function openPaymentDialog(team) {
    const tournament = store.select.tournament(team.tournamentId);
    ui.dialog({
      title: 'Update payment reference',
      body: `
        <p class="hf-small hf-muted">Entry fee is ${escapeHtml(formatMoney(tournament?.fee || 0))}. The organizer verifies the reference before seeding.</p>
        <div class="hf-form-grid">
          <div class="hf-field">
            <label class="hf-label" for="myPaymentMethod">Method</label>
            <select class="hf-select" id="myPaymentMethod" data-testid="my-payment-method">
              ${PAYMENT_METHODS.map((method) => `<option value="${escapeHtml(method)}" ${method === (team.payment.method || 'GCash') ? 'selected' : ''}>${escapeHtml(method)}</option>`).join('')}
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="myPaymentRef">Reference</label>
            <input class="hf-input" id="myPaymentRef" value="${escapeHtml(team.payment.ref || '')}" placeholder="GCASH-9283746" data-testid="my-payment-ref">
          </div>
        </div>
      `,
      footer: `
        <button type="button" class="hf-btn" data-cancel-payment data-testid="my-payment-cancel">Cancel</button>
        <button type="button" class="hf-btn hf-btn--primary" data-save-payment data-testid="my-payment-save">Submit reference</button>
      `,
      onMount(element, close) {
        const method = element.querySelector('#myPaymentMethod');
        const ref = element.querySelector('#myPaymentRef');
        element.querySelector('[data-cancel-payment]').addEventListener('click', close);
        element.querySelector('[data-save-payment]').addEventListener('click', () => {
          ui.clearFieldError(ref);
          if (!/^[A-Za-z0-9-]{6,24}$/.test(ref.value.trim())) {
            ui.setFieldError(ref, 'Use 6–24 letters, numbers, or dashes.');
            ref.focus();
            return;
          }
          try {
            store.actions.submitPayment(team.id, { method: method.value, ref: ref.value.trim() });
            close();
            ui.toast('Payment reference submitted for verification.', {
              tone: 'success',
              title: 'Reference saved',
            });
            draw();
          } catch (error) {
            const target = error.field === 'method' ? method : ref;
            ui.setFieldError(target, error.message);
            ui.toast(error.message, { tone: 'danger', title: 'Could not save reference' });
          }
        });
      },
    });
  }

  draw();
}