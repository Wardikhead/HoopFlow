import { route } from '../routes.js';
import { escapeHtml, formatDate, initials } from '../ui.js';

const ROLE_COPY = {
  organizer:
    'Full tournament operations: approvals, payments, brackets, schedules, scores, announcements, and audit.',
  scorekeeper:
    'Score entry, score corrections, and official box scores. Approvals, payments, and roster edits are blocked.',
  coach:
    'Register a team, manage its roster and eligibility, submit a payment reference, and follow its status.',
  fan:
    'Read-only public pass: bracket, schedule, results, standings, and announcements. No private payment or eligibility data.',
};

const ROLE_LINKS = {
  organizer: [
    ['dashboard', 'Dashboard'],
    ['submissions', 'Review queue'],
    ['payments', 'Payments'],
    ['audit', 'Audit log'],
  ],
  scorekeeper: [
    ['bracket', 'Score queue'],
    ['gameStats', 'Game stats'],
  ],
  coach: [
    ['myTeam', 'My team'],
    ['tournaments', 'Tournaments'],
  ],
  fan: [
    ['dashboard', 'Courtside pass'],
    ['bracket', 'Bracket'],
    ['schedule', 'Schedule'],
  ],
};

export function render({ store, ui, components, root, theme }) {
  const role = store.getRole();
  const user = store.getCurrentUser();
  const state = store.getState();
  const team = user.teamId ? store.select.team(user.teamId) : null;
  const tournament = team ? store.select.tournament(team.tournamentId) : null;
  const links = ROLE_LINKS[role] || [];

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Account',
      title: 'Profile',
      description:
        'Your demo identity, role scope, appearance, and the controls that keep a live run recoverable.',
      actions:
        role === 'organizer'
          ? `<a class="hf-btn" href="${route('audit')}" data-testid="profile-open-audit"><i class="bi bi-clock-history"></i> Audit log</a>`
          : '',
    })}

    <section class="hf-card hf-profile-head hf-mb-3" data-testid="profile-identity">
      <span class="hf-avatar hf-avatar--xl">${escapeHtml(initials(user.name))}</span>
      <div class="hf-profile-meta">
        <span class="hf-flex hf-wrap hf-gap-1">
          <span class="hf-chip hf-chip--primary" style="text-transform: capitalize">${escapeHtml(role)}</span>
          <span class="hf-chip hf-chip--slate">${escapeHtml(state.seedVersion)}</span>
        </span>
        <h2>${escapeHtml(user.name)}</h2>
        <p>${escapeHtml(user.email)}</p>
        <p class="hf-small hf-muted hf-mb-0">${escapeHtml(user.organization || 'No organization on file')}</p>
      </div>
    </section>

    <div class="hf-grid hf-grid--2 hf-mb-3">
      <section class="hf-card" data-testid="profile-role-card">
        <span class="hf-kpi-label">Role context</span>
        <h2 class="hf-section-title hf-mt-1" style="text-transform: capitalize">${escapeHtml(role)}</h2>
        <p class="hf-small hf-muted">${escapeHtml(ROLE_COPY[role] || '')}</p>
        <div class="hf-btn-row hf-mt-2">
          ${links
            .map(
              ([key, label], index) => `
                <a class="hf-btn hf-btn--sm ${index === 0 ? 'hf-btn--primary' : ''}"
                   href="${route(key)}" data-testid="profile-link-${key}">${escapeHtml(label)}</a>
              `
            )
            .join('')}
        </div>
      </section>

      <section class="hf-card" data-testid="profile-appearance">
        <span class="hf-kpi-label">Appearance</span>
        <h2 class="hf-section-title hf-mt-1">Theme</h2>
        <p class="hf-small hf-muted">The light paper theme is the demo default. The floodlit navy theme is easier to read in a dark room.</p>
        <div class="hf-btn-row" role="group" aria-label="Color theme">
          <button type="button" class="hf-btn" data-profile-theme="light"
                  aria-pressed="${theme.get() === 'light'}" data-testid="profile-theme-light">
            <i class="bi bi-sun" aria-hidden="true"></i> Light
          </button>
          <button type="button" class="hf-btn" data-profile-theme="dark"
                  aria-pressed="${theme.get() === 'dark'}" data-testid="profile-theme-dark">
            <i class="bi bi-moon-stars" aria-hidden="true"></i> Dark
          </button>
        </div>
      </section>
    </div>

    <div class="hf-grid hf-grid--2">
      ${
        team
          ? `<section class="hf-card" data-testid="profile-team">
               <span class="hf-kpi-label">Team context</span>
               <h2 class="hf-section-title hf-mt-1">${escapeHtml(team.name)}</h2>
               <p class="hf-small hf-muted">${escapeHtml(tournament?.name || '')} · ${escapeHtml(tournament?.division || '')}</p>
               <p class="hf-small hf-muted">Status ${escapeHtml(team.status)} · submitted ${escapeHtml(formatDate(team.submittedAt))}</p>
               <a class="hf-btn hf-btn--sm" href="${route('myTeam', { team: team.id })}" data-testid="profile-open-team">Open My Team</a>
             </section>`
          : ''
      }

      <section class="hf-card" data-testid="profile-demo-data">
        <span class="hf-kpi-label">Demo data</span>
        <div class="hf-setting">
          <span><strong>Seed version</strong><span>Deterministic demo fixture</span></span>
          <span class="hf-mono">${escapeHtml(state.seedVersion)}</span>
        </div>
        <div class="hf-setting">
          <span><strong>Schema</strong><span>Normalized store version</span></span>
          <span class="hf-mono">v${state.schemaVersion}</span>
        </div>
        <div class="hf-setting">
          <span><strong>Storage</strong><span>Browser localStorage only</span></span>
          <span class="hf-chip hf-chip--slate">Local</span>
        </div>
        <p class="hf-small hf-muted">Reset restores the seeded tournaments, teams, matches, payments, and audit trail. Use it between live runs.</p>
        <button type="button" class="hf-btn hf-btn--danger" data-profile-reset data-testid="profile-reset">
          <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reset demo
        </button>
      </section>
    </div>
  `;

  const themeButtons = [...root.querySelectorAll('[data-profile-theme]')];
  const syncThemeButtons = () => {
    themeButtons.forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.profileTheme === theme.get()));
    });
  };

  themeButtons.forEach((button) => {
    button.addEventListener('click', () => {
      theme.set(button.dataset.profileTheme);
      syncThemeButtons();
    });
  });
  syncThemeButtons();

  root.querySelector('[data-profile-reset]').addEventListener('click', async () => {
    const ok = await ui.confirm({
      title: 'Reset demo?',
      message: 'This restores the exact seeded tournaments, teams, matches, and audit trail.',
      confirmLabel: 'Reset demo',
      tone: 'danger',
    });
    if (!ok) return;
    store.actions.reset();
    ui.toast('Demo reset to the seeded matchday.', { tone: 'success', title: 'Reset complete' });
    window.setTimeout(() => {
      window.location.href = route('dashboard');
    }, 250);
  });
}