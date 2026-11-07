import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney } from '../ui.js';
import { guardRole } from '../guards.js';
import { PAYMENT_METHODS } from '../options.js';

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['organizer'] })) return;

  const params = new URLSearchParams(window.location.search);
  let statusFilter = params.get('status') || 'pending';
  let searchTerm = params.get('q') || '';
  let tournamentFilter = params.get('tournament') || 'all';

  const tournaments = store.select.tournaments();

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Organizer desk · Finance',
      title: 'Payments',
      description: 'Verify entry fees, track the collection rate, and keep every reference in the audit trail.',
      actions: `<a class="hf-btn" href="${route('submissions')}" data-testid="payments-open-submissions"><i class="bi bi-clipboard-check"></i> Submissions</a>`,
    })}
    <div id="paymentKpis" class="hf-grid hf-grid--4 hf-mb-3"></div>

    <div id="paymentDestination" data-testid="payment-destination" class="hf-card hf-card--flat hf-mb-3"></div>

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="paymentSearch">Search</label>
          <input class="hf-input" id="paymentSearch" type="search" placeholder="Team, reference, or coach"
                 value="${escapeHtml(searchTerm)}" data-testid="payment-search">
        </div>
        <div class="hf-field">
          <label class="hf-label" for="paymentStatus">Status</label>
          <select class="hf-select" id="paymentStatus" data-testid="payment-status-filter">
            <option value="pending">Pending</option>
            <option value="partial">Partial</option>
            <option value="paid">Paid</option>
            <option value="unpaid">Pending or partial</option>
            <option value="all">All payments</option>
          </select>
        </div>
        <div class="hf-field">
          <label class="hf-label" for="paymentTournament">Tournament</label>
          <select class="hf-select" id="paymentTournament" data-testid="payment-tournament-filter">
            <option value="all">All tournaments</option>
            ${tournaments.map((tournament) => `<option value="${escapeHtml(tournament.id)}">${escapeHtml(tournament.name)}</option>`).join('')}
          </select>
        </div>
      </div>
    </div>

    <div id="paymentList" data-testid="payment-list"></div>
  `;

  root.querySelector('#paymentStatus').value = statusFilter;
  root.querySelector('#paymentTournament').value = tournamentFilter;

  const search = root.querySelector('#paymentSearch');
  const status = root.querySelector('#paymentStatus');
  const tournamentSelect = root.querySelector('#paymentTournament');
  const list = root.querySelector('#paymentList');
  const kpis = root.querySelector('#paymentKpis');

  function scopedTeams() {
    return store
      .getState()
      .teams.filter(
        (team) =>
          team.status !== 'rejected' &&
          (tournamentSelect.value === 'all' || team.tournamentId === tournamentSelect.value)
      );
  }

  function summaryFor(teams) {
    const billable = teams.filter((team) => team.status !== 'rejected');
    const expected = billable.reduce(
      (sum, team) => sum + (store.select.tournament(team.tournamentId)?.fee || 0),
      0
    );
    const collected = billable
      .filter((team) => team.payment.status === 'paid')
      .reduce((sum, team) => sum + (team.payment.amount || 0), 0);
    return {
      expected,
      collected,
      outstanding: Math.max(0, expected - collected),
      rate: expected ? Math.round((collected / expected) * 100) : 0,
      paidCount: billable.filter((team) => team.payment.status === 'paid').length,
      pendingCount: billable.filter((team) => team.payment.status === 'pending').length,
      partialCount: billable.filter((team) => team.payment.status === 'partial').length,
    };
  }

  function renderKpis() {
    const summary = summaryFor(scopedTeams());
    kpis.innerHTML = [
      components.kpiTile({ label: 'Collected', value: formatMoney(summary.collected), hint: `${summary.paidCount} teams paid`, tone: 'success', testid: 'payment-kpi-collected' }),
      components.kpiTile({ label: 'Outstanding', value: formatMoney(summary.outstanding), hint: `${summary.pendingCount} pending · ${summary.partialCount} partial`, tone: 'alert', testid: 'payment-kpi-outstanding' }),
      components.kpiTile({ label: 'Expected', value: formatMoney(summary.expected), hint: 'Entry fees for billable teams', tone: 'slate', testid: 'payment-kpi-expected' }),
      components.kpiTile({ label: 'Collection rate', value: `${summary.rate}%`, hint: 'Collected against expected', tone: 'primary', testid: 'payment-kpi-rate' }),
    ].join('');
  }

  function matchesStatus(team) {
    if (status.value === 'all') return true;
    if (status.value === 'unpaid') return team.payment.status !== 'paid';
    return team.payment.status === status.value;
  }

  function paymentRow(team) {
    const tournament = store.select.tournament(team.tournamentId);
    return `
      <article class="hf-card hf-card--flat" data-testid="payment-row-${escapeHtml(team.id)}">
        <div class="hf-between">
          <span class="hf-flex hf-items-center hf-gap-2">
            ${components.shield(team)}
            <span>
              <strong>${escapeHtml(team.name)}</strong>
              <span class="hf-small hf-muted" style="display:block">${escapeHtml(tournament?.name || '')} · ${escapeHtml(team.coach)}</span>
            </span>
          </span>
          ${ui.statusChip(team.payment.status)}
        </div>
        <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small">
          <span><span class="hf-kpi-label">Method</span><strong style="display:block">${escapeHtml(team.payment.method || '—')}</strong></span>
          <span><span class="hf-kpi-label">Reference</span><strong style="display:block">${escapeHtml(team.payment.ref || '—')}</strong></span>
          <span><span class="hf-kpi-label">Amount</span><strong style="display:block">${escapeHtml(formatMoney(team.payment.amount || tournament?.fee || 0))}</strong></span>
          <span><span class="hf-kpi-label">Verified</span><strong style="display:block">${team.payment.verifiedAt ? escapeHtml(formatDate(team.payment.verifiedAt)) : '—'}</strong></span>
        </div>
        <div class="hf-btn-row hf-mt-2">
          <button type="button" class="hf-btn hf-btn--sm ${team.payment.status === 'paid' ? '' : 'hf-btn--primary'}"
                  data-verify-payment="${escapeHtml(team.id)}"
                  data-testid="payment-verify-${escapeHtml(team.id)}">
            ${team.payment.status === 'paid' ? 'Review record' : 'Verify payment'}
          </button>
          <a class="hf-btn hf-btn--sm" href="${route('teamShow', { id: team.id })}" data-testid="payment-team-${escapeHtml(team.id)}">Team detail</a>
        </div>
      </article>
    `;
  }

  function renderList() {
    const destination = root.querySelector('#paymentDestination');
    if (destination) {
      const tid = tournamentSelect.value;
      const t = tid !== 'all' ? store.select.tournament(tid) : null;
      destination.innerHTML = t
        ? `<div class="hf-between"><span class="hf-kpi-label">Send payments to</span><strong>${escapeHtml(t.paymentMethod || 'GCash')} · ${escapeHtml(t.paymentAccount || 'TBD')}</strong></div>`
        : `<span class="hf-small hf-muted">Select a tournament to see the payment destination.</span>`;
    }
    const term = search.value.trim().toLowerCase();
    const filtered = scopedTeams()
      .filter((team) => {
        const haystack = `${team.name} ${team.coach} ${team.payment.ref} ${team.payment.method}`.toLowerCase();
        return matchesStatus(team) && (!term || haystack.includes(term));
      })
      .sort((a, b) => {
        const rank = { pending: 0, partial: 1, paid: 2 };
        return (rank[a.payment.status] ?? 3) - (rank[b.payment.status] ?? 3) ||
          a.name.localeCompare(b.name);
      });

    list.innerHTML = filtered.length
      ? `<div class="hf-grid">${filtered.map(paymentRow).join('')}</div>`
      : ui.emptyState({
          icon: 'cash-stack',
          title: 'No payments match',
          message: 'Try another status, tournament, or search term.',
          action: `<button type="button" class="hf-btn" data-clear-payment-filters data-testid="payment-clear-filters">Clear filters</button>`,
        });

    list.querySelector('[data-clear-payment-filters]')?.addEventListener('click', () => {
      search.value = '';
      status.value = 'pending';
      tournamentSelect.value = 'all';
      renderKpis();
      renderList();
    });

    list.querySelectorAll('[data-verify-payment]').forEach((button) => {
      button.addEventListener('click', () => openVerify(button.dataset.verifyPayment));
    });
  }

  function openVerify(teamId) {
    const team = store.select.team(teamId);
    if (!team) return;
    const tournament = store.select.tournament(team.tournamentId);
    ui.dialog({
      title: `Verify payment · ${team.name}`,
      body: `
        <p class="hf-small hf-muted">Entry fee is ${escapeHtml(formatMoney(tournament?.fee || 0))}. A saved verification updates collected totals and writes an audit entry.</p>
        <div class="hf-form-grid">
          <div class="hf-field">
            <label class="hf-label" for="verifyMethod">Method</label>
            <select class="hf-select" id="verifyMethod" data-testid="payment-verify-method">
              ${PAYMENT_METHODS.map((method) => `<option value="${escapeHtml(method)}" ${method === (team.payment.method || 'GCash') ? 'selected' : ''}>${escapeHtml(method)}</option>`).join('')}
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="verifyStatus">Status</label>
            <select class="hf-select" id="verifyStatus" data-testid="payment-verify-status">
              <option value="paid" ${team.payment.status !== 'partial' ? 'selected' : ''}>Paid in full</option>
              <option value="partial" ${team.payment.status === 'partial' ? 'selected' : ''}>Partial payment</option>
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="verifyRef">Reference</label>
            <input class="hf-input" id="verifyRef" value="${escapeHtml(team.payment.ref || '')}"
                   placeholder="GCASH-9283746" data-testid="payment-verify-ref">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="verifyAmount">Amount (PHP)</label>
            <input class="hf-input" id="verifyAmount" type="number" min="1" step="100"
                   value="${team.payment.amount || tournament?.fee || 1500}" data-testid="payment-verify-amount">
          </div>
        </div>
      `,
      footer: `
        <button type="button" class="hf-btn" data-cancel-verify data-testid="payment-verify-cancel">Cancel</button>
        <button type="button" class="hf-btn hf-btn--primary" data-save-verify data-testid="payment-verify-save">Save verification</button>
      `,
      onMount(element, close) {
        const method = element.querySelector('#verifyMethod');
        const state = element.querySelector('#verifyStatus');
        const ref = element.querySelector('#verifyRef');
        const amount = element.querySelector('#verifyAmount');

        element.querySelector('[data-cancel-verify]').addEventListener('click', close);
        element.querySelector('[data-save-verify]').addEventListener('click', () => {
          [method, ref, amount].forEach((field) => ui.clearFieldError(field));
          let valid = true;
          if (!ref.value.trim()) {
            ui.setFieldError(ref, 'Enter the payment reference.');
            valid = false;
          }
          if (!Number.isFinite(Number(amount.value)) || Number(amount.value) <= 0) {
            ui.setFieldError(amount, 'Amount must be greater than zero.');
            valid = false;
          }
          if (!valid) return;

          try {
            store.actions.verifyPayment(teamId, {
              method: method.value,
              ref: ref.value.trim(),
              amount: Number(amount.value),
              status: state.value,
            });
            close();
            ui.toast(`${team.name} payment recorded.`, { tone: 'success', title: 'Payment verified' });
            renderKpis();
            renderList();
          } catch (error) {
            const map = {
              'payment.method': method,
              'payment.ref': ref,
              'payment.amount': amount,
            };
            const target = map[error.field];
            if (target) ui.setFieldError(target, error.message);
            ui.toast(error.message, { tone: 'danger', title: 'Could not verify payment' });
          }
        });
      },
    });
  }

  search.addEventListener('input', ui.debounce(renderList, 120));
  status.addEventListener('change', () => {
    renderKpis();
    renderList();
  });
  tournamentSelect.addEventListener('change', () => {
    renderKpis();
    renderList();
  });

  renderKpis();
  renderList();
}