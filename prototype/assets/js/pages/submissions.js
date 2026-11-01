import { route } from '../routes.js';
import { escapeHtml, formatDate } from '../ui.js';
import { guardRole } from '../guards.js';

export function render({ store, ui, components, root }) {
  if (
    !guardRole({
      store,
      ui,
      root,
      roles: ['organizer'],
      message: 'Only the organizer can review team submissions. Switch roles from the Demo menu.',
    })
  )
    return;

  const params = new URLSearchParams(window.location.search);
  let statusFilter = params.get('status') || 'pending';
  let searchTerm = params.get('q') || '';
  let tournamentFilter = params.get('tournament') || 'all';

  const tournaments = store.select.tournaments();

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Organizer desk · Eligibility',
      title: 'Submissions',
      description: 'Review rosters, consent, documents, and payment references before a team enters the bracket.',
      actions: `<a class="hf-btn" href="${route('payments')}" data-testid="submissions-open-payments"><i class="bi bi-credit-card"></i> Payments</a>`,
    })}
    <div id="submissionKpis" class="hf-grid hf-grid--4 hf-mb-3"></div>

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="submissionSearch">Search</label>
          <input class="hf-input" id="submissionSearch" type="search" placeholder="Team, coach, or city"
                 value="${escapeHtml(searchTerm)}" data-testid="submission-search">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="submissionStatus">Status</label>
          <select class="hf-select" id="submissionStatus" data-testid="submission-status-filter">
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="all">All statuses</option>
          </select>
        </div>
        <div class="hf-field">
          <label class="hf-label" for="submissionTournament">Tournament</label>
          <select class="hf-select" id="submissionTournament" data-testid="submission-tournament-filter">
            <option value="all">All tournaments</option>
            ${tournaments.map((tournament) => `<option value="${escapeHtml(tournament.id)}">${escapeHtml(tournament.name)}</option>`).join('')}
          </select>
        </div>
      </div>
    </div>

    <div id="submissionList" data-testid="submission-list"></div>
  `;

  root.querySelector('#submissionStatus').value = statusFilter;
  root.querySelector('#submissionTournament').value = tournamentFilter;

  const search = root.querySelector('#submissionSearch');
  const status = root.querySelector('#submissionStatus');
  const tournamentSelect = root.querySelector('#submissionTournament');
  const list = root.querySelector('#submissionList');
  const kpis = root.querySelector('#submissionKpis');

  function allTeams() {
    return store.getState().teams;
  }

  function renderKpis() {
    const teams = allTeams();
    const pending = teams.filter((team) => team.status === 'pending').length;
    const approved = teams.filter((team) => team.status === 'approved').length;
    const rejected = teams.filter((team) => team.status === 'rejected').length;
    const open = tournaments.filter((tournament) => tournament.registrationOpen).length;
    kpis.innerHTML = [
      components.kpiTile({ label: 'Pending review', value: pending, hint: 'Across all tournaments', tone: 'gold', testid: 'submission-kpi-pending' }),
      components.kpiTile({ label: 'Approved', value: approved, hint: 'Eligible for seeding', tone: 'success', testid: 'submission-kpi-approved' }),
      components.kpiTile({ label: 'Rejected', value: rejected, hint: 'Archived from the bracket', tone: 'alert', testid: 'submission-kpi-rejected' }),
      components.kpiTile({ label: 'Open registration', value: open, hint: 'Tournaments accepting teams', tone: 'primary', testid: 'submission-kpi-open' }),
    ].join('');
  }

  function eligibilityChips(team) {
    const items = [];
    items.push(
      team.eligibility.documentsComplete
        ? ui.statusChip('verified', 'Documents')
        : ui.statusChip('missing', 'Documents')
    );
    items.push(
      team.eligibility.consentComplete
        ? ui.statusChip('verified', 'Consent')
        : ui.statusChip('missing', 'Consent')
    );
    return items.join(' ');
  }

  function teamRow(team) {
    const tournament = store.select.tournament(team.tournamentId);
    return `
      <article class="hf-card hf-card--flat" data-testid="submission-row-${escapeHtml(team.id)}">
        <div class="hf-between">
          <span class="hf-flex hf-items-center hf-gap-2">
            ${components.shield(team, 'lg')}
            <span>
              <strong>${escapeHtml(team.name)}</strong>
              <span class="hf-small hf-muted" style="display:block">
                ${escapeHtml(team.coach)} · ${escapeHtml(team.city)} · ${escapeHtml(tournament?.name || 'Tournament')}
              </span>
            </span>
          </span>
          <span class="hf-flex hf-wrap hf-gap-1">
            ${ui.statusChip(team.status)}
            ${ui.statusChip(team.payment.status)}
          </span>
        </div>
        <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2 hf-small">
          <span class="hf-chip hf-chip--slate">${team.players.length} players</span>
          ${eligibilityChips(team)}
          <span class="hf-chip hf-chip--slate">${escapeHtml(tournament?.division || '')}</span>
          <span class="hf-chip hf-chip--slate">Submitted ${escapeHtml(formatDate(team.submittedAt))}</span>
        </div>
        ${team.rejectionReason ? `<p class="hf-small hf-muted hf-mt-1 hf-mb-0">Reason: ${escapeHtml(team.rejectionReason)}</p>` : ''}
        <div class="hf-btn-row hf-mt-2">
          <button type="button" class="hf-btn hf-btn--sm" data-review-team="${escapeHtml(team.id)}"
                  data-testid="submission-open-${escapeHtml(team.id)}">Review detail</button>
          ${team.status === 'pending' ? `
            <button type="button" class="hf-btn hf-btn--sm hf-btn--primary" data-approve-team="${escapeHtml(team.id)}"
                    data-testid="submission-approve-${escapeHtml(team.id)}">Approve</button>
            <button type="button" class="hf-btn hf-btn--sm hf-btn--danger" data-reject-team="${escapeHtml(team.id)}"
                    data-testid="submission-reject-${escapeHtml(team.id)}">Reject</button>
          ` : ''}
        </div>
      </article>
    `;
  }

  function renderList() {
    const term = search.value.trim().toLowerCase();
    const filtered = allTeams()
      .filter((team) => {
        const tournament = store.select.tournament(team.tournamentId);
        const haystack = `${team.name} ${team.coach} ${team.city} ${tournament?.name || ''}`.toLowerCase();
        return (
          (status.value === 'all' || team.status === status.value) &&
          (tournamentSelect.value === 'all' || team.tournamentId === tournamentSelect.value) &&
          (!term || haystack.includes(term))
        );
      })
      .sort((a, b) => String(b.submittedAt).localeCompare(String(a.submittedAt)));

    list.innerHTML = filtered.length
      ? `<div class="hf-grid">${filtered.map(teamRow).join('')}</div>`
      : ui.emptyState({
          icon: 'clipboard-check',
          title: 'No submissions match',
          message: 'Try another status, tournament, or search term.',
          action: `<button type="button" class="hf-btn" data-clear-submission-filters data-testid="submission-clear-filters">Clear filters</button>`,
        });

    list.querySelector('[data-clear-submission-filters]')?.addEventListener('click', () => {
      search.value = '';
      status.value = 'pending';
      tournamentSelect.value = 'all';
      renderList();
    });

    list.querySelectorAll('[data-review-team]').forEach((button) => {
      button.addEventListener('click', () => openDetail(button.dataset.reviewTeam));
    });
    list.querySelectorAll('[data-approve-team]').forEach((button) => {
      button.addEventListener('click', () => approve(button.dataset.approveTeam));
    });
    list.querySelectorAll('[data-reject-team]').forEach((button) => {
      button.addEventListener('click', () => openReject(button.dataset.rejectTeam));
    });
  }

  function rosterTable(team) {
    return `
      <div class="hf-table-wrap">
        <table class="hf-table">
          <thead>
            <tr><th scope="col">#</th><th scope="col">Player</th><th scope="col">Position</th><th scope="col">Age</th><th scope="col">Consent</th><th scope="col">Documents</th></tr>
          </thead>
          <tbody>
            ${team.players
              .map(
                (player) => `
                  <tr>
                    <td>${player.number}</td>
                    <td>${escapeHtml(player.name)}</td>
                    <td>${escapeHtml(player.position)}</td>
                    <td>${player.age}</td>
                    <td>${player.guardianConsent ? '<span class="hf-chip hf-chip--success">On file</span>' : '<span class="hf-chip hf-chip--danger">Missing</span>'}</td>
                    <td>${ui.statusChip(player.documentStatus)}</td>
                  </tr>
                `
              )
              .join('')}
          </tbody>
        </table>
      </div>
    `;
  }

  function openDetail(teamId) {
    const team = store.select.team(teamId);
    if (!team) return;
    const tournament = store.select.tournament(team.tournamentId);
    ui.dialog({
      title: team.name,
      size: 'wide',
      body: `
        <div class="hf-flex hf-items-center hf-gap-2 hf-mb-2">
          ${components.shield(team, 'lg')}
          <div>
            <strong>${escapeHtml(team.coach)} · ${escapeHtml(team.city)}</strong>
            <span class="hf-small hf-muted" style="display:block">${escapeHtml(tournament?.name || '')} · ${escapeHtml(tournament?.division || '')}</span>
          </div>
          <span class="hf-ml-auto hf-flex hf-gap-1">${ui.statusChip(team.status)}${ui.statusChip(team.payment.status)}</span>
        </div>

        <div class="hf-grid hf-grid--2 hf-mb-3">
          <div class="hf-card hf-card--flat">
            <span class="hf-kpi-label">Eligibility</span>
            <div class="hf-flex hf-wrap hf-gap-1 hf-mt-1">${eligibilityChips(team)}</div>
            <p class="hf-small hf-muted hf-mt-1 hf-mb-0">${team.eligibility.verifiedAt ? `Verified ${escapeHtml(formatDate(team.eligibility.verifiedAt))}` : 'Verification is recorded when you approve the team.'}</p>
          </div>
          <div class="hf-card hf-card--flat">
            <span class="hf-kpi-label">Payment reference</span>
            <p class="hf-mb-0"><strong>${escapeHtml(team.payment.method || 'No method')}</strong> · ${escapeHtml(team.payment.ref || 'No reference')}</p>
            <p class="hf-small hf-muted hf-mb-0">Amount ${escapeHtml(String(team.payment.amount || 0))} · ${escapeHtml(team.payment.status)}</p>
          </div>
        </div>

        <h3 class="hf-section-title">Roster (${team.players.length})</h3>
        ${rosterTable(team)}
        <p class="hf-small hf-muted hf-mt-2 hf-mb-0">Submitted ${escapeHtml(formatDate(team.submittedAt))}. Approval verifies the team's eligibility snapshot; the roster locks for the coach.</p>
      `,
      footer: `
        <button type="button" class="hf-btn" data-close-detail data-testid="submission-detail-close">Close</button>
        ${team.status === 'pending' ? `
          <button type="button" class="hf-btn hf-btn--danger" data-detail-reject data-testid="submission-detail-reject">Reject</button>
          <button type="button" class="hf-btn hf-btn--primary" data-detail-approve data-testid="submission-detail-approve">Approve team</button>
        ` : ''}
      `,
      onMount(element, close) {
        element.querySelector('[data-close-detail]').addEventListener('click', close);
        element.querySelector('[data-detail-approve]')?.addEventListener('click', () => {
          close();
          approve(teamId);
        });
        element.querySelector('[data-detail-reject]')?.addEventListener('click', () => {
          close();
          openReject(teamId);
        });
      },
    });
  }

  async function approve(teamId) {
    const team = store.select.team(teamId);
    if (!team) return;
    const ok = await ui.confirm({
      title: `Approve ${team.name}?`,
      message: 'The team becomes eligible for seeding and its roster locks for the coach.',
      confirmLabel: 'Approve team',
    });
    if (!ok) return;
    try {
      store.actions.reviewTeam(teamId, 'approve');
      ui.toast(`${team.name} is approved.`, { tone: 'success', title: 'Team approved' });
      renderKpis();
      renderList();
    } catch (error) {
      ui.toast(error.message, { tone: 'danger', title: 'Could not approve team' });
    }
  }

  function openReject(teamId) {
    const team = store.select.team(teamId);
    if (!team) return;
    ui.dialog({
      title: `Reject ${team.name}`,
      body: `
        <p class="hf-small hf-muted">Tell the coach what needs to change. The reason is shown on the coach's status page and written to the audit log.</p>
        <div class="hf-field">
          <label class="hf-label" for="rejectReason">Reason</label>
          <textarea class="hf-textarea" id="rejectReason" rows="4" placeholder="Missing PSA birth certificate for two players."
                    data-testid="submission-reject-reason"></textarea>
        </div>
      `,
      footer: `
        <button type="button" class="hf-btn" data-cancel-reject data-testid="submission-reject-cancel">Cancel</button>
        <button type="button" class="hf-btn hf-btn--danger" data-confirm-reject data-testid="submission-reject-confirm">Reject team</button>
      `,
      onMount(element, close) {
        const reason = element.querySelector('#rejectReason');
        element.querySelector('[data-cancel-reject]').addEventListener('click', close);
        element.querySelector('[data-confirm-reject]').addEventListener('click', () => {
          ui.clearFieldError(reason);
          if (reason.value.trim().length < 5) {
            ui.setFieldError(reason, 'Enter at least 5 characters.');
            reason.focus();
            return;
          }
          try {
            store.actions.reviewTeam(teamId, 'reject', reason.value.trim());
            close();
            ui.toast(`${team.name} was rejected with a reason.`, { tone: 'success', title: 'Team rejected' });
            renderKpis();
            renderList();
          } catch (error) {
            ui.setFieldError(reason, error.message);
            ui.toast(error.message, { tone: 'danger', title: 'Could not reject team' });
          }
        });
      },
    });
  }

  search.addEventListener('input', ui.debounce(renderList, 120));
  status.addEventListener('change', renderList);
  tournamentSelect.addEventListener('change', renderList);

  renderKpis();
  renderList();
}