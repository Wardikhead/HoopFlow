import { route } from './routes.js';
import { escapeHtml } from './ui.js';

const NAV = {
  dashboard: { label: 'Dashboard', icon: 'speedometer2', route: 'dashboard' },
  tournaments: { label: 'Tournaments', icon: 'trophy', route: 'tournaments' },
  teams: { label: 'Teams', icon: 'people', route: 'teams' },
  bracket: { label: 'Bracket', icon: 'diagram-3', route: 'bracket' },
  schedule: { label: 'Schedule', icon: 'calendar3', route: 'schedule' },
  standings: { label: 'Standings', icon: 'bar-chart', route: 'standings' },
  announcements: { label: 'Announcements', icon: 'megaphone', route: 'announcements' },
  submissions: { label: 'Submissions', icon: 'clipboard-check', route: 'submissions' },
  payments: { label: 'Payments', icon: 'credit-card', route: 'payments' },
  audit: { label: 'Audit log', icon: 'clock-history', route: 'audit' },
  myTeam: { label: 'My team', icon: 'person-lines-fill', route: 'myTeam' },
  teamRegister: { label: 'Register team', icon: 'plus-circle', route: 'teamRegister' },
  gameStats: { label: 'Game stats', icon: 'graph-up', route: 'gameStats' },
  createTournament: { label: 'Create tournament', icon: 'plus-lg', route: 'tournamentCreate' },
  profile: { label: 'Profile', icon: 'person', route: 'profile' },
};

const PAGE_LABELS = {
  dashboard: 'Matchday desk',
  tournaments: 'Tournaments',
  'tournament-show': 'Tournament',
  'tournament-create': 'Create tournament',
  submissions: 'Submissions',
  payments: 'Payments',
  teams: 'Teams',
  'team-show': 'Team',
  'team-register': 'Register team',
  'my-team': 'My team',
  bracket: 'Bracket',
  schedule: 'Schedule',
  matches: 'Matchups',
  standings: 'Standings',
  'game-stats': 'Game stats',
  announcements: 'Announcements',
  'announcement-create': 'New announcement',
  profile: 'Profile',
  audit: 'Audit log',
};

function activeFor(page, key) {
  if (key === 'tournaments') return ['tournaments', 'tournament-show', 'tournament-create'].includes(page);
  if (key === 'teams') return ['teams', 'team-show', 'team-register', 'my-team'].includes(page);
  if (key === 'schedule') return ['schedule', 'matches', 'game-stats'].includes(page);
  if (key === 'announcements') return ['announcements', 'announcement-create'].includes(page);
  if (key === 'profile') return ['profile', 'audit'].includes(page);
  return page === key;
}

function roleTools(role) {
  if (role === 'organizer') return ['submissions', 'payments', 'audit', 'createTournament', 'gameStats'];
  if (role === 'coach') return ['myTeam', 'teamRegister'];
  return [];
}

function roleTabs(role) {
  const tabs = [
    { key: 'dashboard', label: 'Home', icon: 'house-door', route: 'dashboard' },
    { key: 'bracket', label: 'Bracket', icon: 'diagram-3', route: 'bracket' },
    { key: 'schedule', label: 'Schedule', icon: 'calendar3', route: 'schedule' },
    { key: 'standings', label: 'Standings', icon: 'bar-chart', route: 'standings' },
  ];
  tabs.push(
    role === 'coach'
      ? { key: 'myTeam', label: 'My team', icon: 'people', route: 'myTeam' }
      : { key: 'profile', label: 'Profile', icon: 'person', route: 'profile' }
  );
  return tabs;
}

export function renderShell({ store, ui, root, theme }) {
  const page = document.body.dataset.page || 'dashboard';
  const shell = document.body.dataset.shell || 'app';

  if (shell === 'auth') {
    root.innerHTML = `
      <header class="hf-auth-topbar">
        <a class="hf-auth-brand" href="${route('splash')}">
          <img src="../assets/img/logo.png" alt="">
          HoopFlow
        </a>
        <a class="hf-btn hf-btn--ghost hf-btn--sm" href="${route('login')}">Sign in</a>
      </header>
    `;
    return;
  }

  const role = store.getRole();
  const user = store.getCurrentUser();
  const activeTournament = store.select.tournament('tournament-1');
  const pending = store.getState().teams.filter((team) => team.status === 'pending').length;
  const unpaid = store
    .getState()
    .teams.filter((team) => team.status !== 'rejected' && team.payment.status !== 'paid').length;
  const unread = store.select.notifications().length;

  const railLink = (key, count = 0) => {
    const item = NAV[key];
    const active = activeFor(page, key) ? ' is-active' : '';
    const badge = item.route === 'submissions' || item.route === 'payments'
      ? `<span class="hf-rail-badge" data-shell-badge="${item.route}" ${count ? '' : 'hidden'}>${count}</span>`
      : '';
    return `
      <a class="hf-rail-link${active}" href="${route(item.route)}" data-testid="nav-${key}">
        <i class="bi bi-${item.icon}" aria-hidden="true"></i>
        <span>${escapeHtml(item.label)}</span>
        ${badge}
      </a>
    `;
  };

  const demoMenu = (up = false, scope = 'rail') => `
    <div class="hf-demo-menu" data-demo-menu="${scope}">
      <details>
        <summary aria-label="Demo controls"><i class="bi bi-sliders2" aria-hidden="true"></i><span>Demo</span></summary>
        <div class="hf-demo-panel ${up ? 'hf-demo-panel--up' : ''}">
          <p class="hf-demo-title">Demo role</p>
          <div class="hf-demo-roles">
            ${['organizer', 'coach', 'fan']
              .map(
                (item) => `
                  <button type="button" class="${role === item ? 'is-active' : ''}"
                          data-demo-role="${item}" data-testid="demo-role-${item}-${scope}">
                    ${item}
                  </button>
                `
              )
              .join('')}
          </div>
          <div class="hf-demo-row">
            <span>Theme</span>
            <span class="hf-btn-row">
              <button type="button" class="hf-btn hf-btn--sm" data-demo-theme="light"
                      aria-pressed="${theme.get() === 'light'}" data-testid="demo-theme-light-${scope}">Light</button>
              <button type="button" class="hf-btn hf-btn--sm" data-demo-theme="dark"
                      aria-pressed="${theme.get() === 'dark'}" data-testid="demo-theme-dark-${scope}">Dark</button>
            </span>
          </div>
          <div class="hf-demo-row">
            <span>Seed</span>
            <span class="hf-demo-seed">${escapeHtml(store.getState().seedVersion)}</span>
          </div>
          <div class="hf-demo-row">
            <span>Reset demo</span>
            <button type="button" class="hf-btn hf-btn--sm hf-btn--danger"
                    data-demo-reset data-testid="demo-reset-${scope}">Reset</button>
          </div>
        </div>
      </details>
    </div>
  `;

  root.innerHTML = `
    <aside class="hf-rail" aria-label="Primary navigation">
      <a class="hf-rail-brand" href="${route('dashboard')}">
        <img src="../assets/img/logo.png" alt="">
        <span>
          <strong>HoopFlow</strong>
          <small>Tournament ops</small>
        </span>
      </a>
      <span class="hf-rail-label">Matchday</span>
      ${['dashboard', 'tournaments', 'teams', 'bracket', 'schedule', 'standings', 'announcements']
        .map((key) => railLink(key))
        .join('')}
      ${roleTools(role).length ? '<span class="hf-rail-label">Workspace</span>' : ''}
      ${roleTools(role)
        .map((key) =>
          railLink(key, key === 'submissions' ? pending : key === 'payments' ? unpaid : 0)
        )
        .join('')}
      <div class="hf-rail-footer">
        ${demoMenu(true, 'rail')}
        <div class="hf-demo-row" style="border: 0; color: var(--hf-rail-muted)">
          <span>${escapeHtml(user.name)}</span>
          <span style="text-transform: capitalize">${escapeHtml(role)}</span>
        </div>
      </div>
    </aside>

    <header class="hf-topbar">
      <div class="hf-topbar-context">
        <h1 class="hf-topbar-title">${escapeHtml(PAGE_LABELS[page] || 'HoopFlow')}</h1>
        <div class="hf-topbar-sub">${escapeHtml(activeTournament?.name || 'No active tournament')} · ${escapeHtml(role)}</div>
      </div>
      <div class="hf-topbar-actions">
        <button type="button" class="hf-icon-btn" data-theme-toggle
                aria-label="Toggle color theme" data-testid="theme-toggle">
          <i class="bi bi-${theme.get() === 'dark' ? 'sun' : 'moon-stars'}" aria-hidden="true"></i>
        </button>
        <a class="hf-icon-btn" href="${route('announcements')}" aria-label="Announcements"
           data-testid="topbar-announcements">
          <i class="bi bi-bell" aria-hidden="true"></i>
          <span class="hf-notification-dot" data-shell-notification ${unread ? '' : 'hidden'}></span>
        </a>
        ${demoMenu(false, 'topbar')}
        <a class="hf-user-chip" href="${route('profile')}" data-testid="topbar-profile">
          <span class="hf-avatar">${escapeHtml(user.name.split(/\s+/).map((part) => part[0]).slice(0, 2).join(''))}</span>
          <span class="hf-user-meta">
            <strong>${escapeHtml(user.name)}</strong>
            <span>${escapeHtml(role)}</span>
          </span>
        </a>
      </div>
    </header>

    <nav class="hf-bottom-nav" aria-label="Mobile navigation">
      ${roleTabs(role)
        .map(
          (tab) => `
            <a class="hf-nav-tab ${activeFor(page, tab.key) ? 'is-active' : ''}"
               href="${route(tab.route)}" data-testid="mobile-nav-${tab.key}">
              <i class="bi bi-${tab.icon}" aria-hidden="true"></i>
              <span>${escapeHtml(tab.label)}</span>
            </a>
          `
        )
        .join('')}
    </nav>
  `;

  const syncTheme = () => {
    document.querySelectorAll('[data-theme-toggle] i').forEach((icon) => {
      icon.className = `bi bi-${theme.get() === 'dark' ? 'sun' : 'moon-stars'}`;
    });
    document.querySelectorAll('[data-demo-theme]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.demoTheme === theme.get()));
    });
  };

  root.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      theme.set(theme.get() === 'dark' ? 'light' : 'dark');
      syncTheme();
    });
  });

  root.querySelectorAll('[data-demo-theme]').forEach((button) => {
    button.addEventListener('click', () => {
      theme.set(button.dataset.demoTheme);
      syncTheme();
    });
  });

  root.querySelectorAll('[data-demo-role]').forEach((button) => {
    button.addEventListener('click', () => {
      document.dispatchEvent(
        new CustomEvent('hf:role-change', { detail: { role: button.dataset.demoRole } })
      );
    });
  });

  root.querySelectorAll('[data-demo-reset]').forEach((button) => {
    button.addEventListener('click', async () => {
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
  });

  const demoMenus = [...root.querySelectorAll('.hf-demo-menu details')];
  const closeDemoMenus = () => demoMenus.forEach((details) => details.removeAttribute('open'));

  document.addEventListener('pointerdown', (event) => {
    if (!demoMenus.some((details) => details.contains(event.target))) closeDemoMenus();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeDemoMenus();
  });

  const refreshBadges = () => {
    const state = store.getState();
    const nextPending = state.teams.filter((team) => team.status === 'pending').length;
    const nextUnpaid = state.teams.filter(
      (team) => team.status !== 'rejected' && team.payment.status !== 'paid'
    ).length;
    const nextUnread = store.select.notifications().length;
    document.querySelectorAll('[data-shell-badge="submissions"]').forEach((element) => {
      element.textContent = nextPending;
      element.hidden = !nextPending;
    });
    document.querySelectorAll('[data-shell-badge="payments"]').forEach((element) => {
      element.textContent = nextUnpaid;
      element.hidden = !nextUnpaid;
    });
    document.querySelectorAll('[data-shell-notification]').forEach((element) => {
      element.hidden = !nextUnread;
    });
  };

  store.subscribe(refreshBadges);
  document.addEventListener('hf:theme-changed', syncTheme);
  syncTheme();
  refreshBadges();
}