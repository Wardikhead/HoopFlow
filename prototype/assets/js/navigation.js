/**
 * Reusable Topbar & Bottom Navigation System for HoopFlow Prototype
 * Adapts automatically to Desktop (Top Navbar) and Mobile (Bottom Tab Bar)
 */
(function() {
  function getRootPrefix() {
    const path = window.location.pathname.replace(/\\/g, '/');
    if (path.includes('/auth/') || 
        path.includes('/dashboard/') || 
        path.includes('/tournaments/') || 
        path.includes('/teams/') || 
        path.includes('/brackets/') || 
        path.includes('/schedule/') || 
        path.includes('/standings/') || 
        path.includes('/games/') || 
        path.includes('/announcements/') || 
        path.includes('/profile/')) {
      return '../';
    }
    return './';
  }

  function renderTopbar() {
    const headerEl = document.getElementById('hfTopbarContainer');
    if (!headerEl) return;

    const root = getRootPrefix();
    const role = window.HF ? window.HF.getRole() : (localStorage.getItem('hoopflow_role') || 'organizer');
    const path = window.location.pathname.toLowerCase().replace(/\\/g, '/');
    const isHome = path.endsWith('/dashboard/index.html') || path.endsWith('/dashboard/');
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

    headerEl.innerHTML = `
      <header class="hf-topbar">
        <div class="d-flex align-items-center gap-2">
          ${!isHome ? `<a href="javascript:history.back()" class="hf-icon-btn d-lg-none" title="Back"><i class="bi bi-chevron-left"></i></a>` : ''}
          <a href="${root}dashboard/index.html" class="d-flex align-items-center text-decoration-none">
            <img src="${root}assets/img/logo.png" alt="HoopFlow" class="hf-brand-logo">
          </a>
        </div>

        <!-- Desktop Navigation Bar Links (Visible on desktop >= 992px) -->
        <nav class="d-none d-lg-flex align-items-center gap-1 mx-auto">
          <a href="${root}dashboard/index.html" class="hf-desktop-link ${path.includes('dashboard') ? 'active' : ''}">
            <i class="bi bi-house-door"></i> Home
          </a>
          <a href="${root}tournaments/index.html" class="hf-desktop-link ${path.includes('tournaments') ? 'active' : ''}">
            <i class="bi bi-trophy"></i> Tournaments
          </a>
          <a href="${root}teams/index.html" class="hf-desktop-link ${path.includes('teams') && !path.includes('my-team') ? 'active' : ''}">
            <i class="bi bi-people"></i> Teams
          </a>
          <a href="${root}brackets/view.html" class="hf-desktop-link ${path.includes('brackets') ? 'active' : ''}">
            <i class="bi bi-diagram-3"></i> Brackets
          </a>
          <a href="${root}schedule/index.html" class="hf-desktop-link ${path.includes('schedule') ? 'active' : ''}">
            <i class="bi bi-calendar3"></i> Schedules
          </a>
          <a href="${root}standings/table.html" class="hf-desktop-link ${path.includes('standings') ? 'active' : ''}">
            <i class="bi bi-bar-chart"></i> Standings
          </a>
          ${role === 'coach' ? `
            <a href="${root}teams/my-team.html" class="hf-desktop-link ${path.includes('my-team') ? 'active' : ''}">
              <i class="bi bi-person-lines-fill"></i> My Team
            </a>
          ` : ''}
          ${role === 'organizer' ? `
            <a href="${root}tournaments/submissions.html" class="hf-desktop-link ${path.includes('submissions') ? 'active' : ''}">
              <i class="bi bi-clipboard-check"></i> Submissions
            </a>
            <a href="${root}tournaments/payments.html" class="hf-desktop-link ${path.includes('payments') ? 'active' : ''}">
              <i class="bi bi-credit-card"></i> Payments
            </a>
          ` : ''}
        </nav>

        <div class="hf-topbar-actions">
          <button type="button" class="hf-icon-btn hf-theme-toggle-btn" id="hfTopbarThemeBtn"
                  title="${isDark ? 'Switch to Daylight Theme' : 'Switch to Night Arena Theme'}">
            <i class="bi ${isDark ? 'bi-sun-fill text-warning' : 'bi-moon-stars-fill'}"></i>
          </button>
          <span class="hf-role-pill">${role}</span>
          <a href="${root}announcements/index.html" class="hf-icon-btn" title="Announcements & Alerts">
            <i class="bi bi-bell"></i>
            <span class="hf-badge-dot"></span>
          </a>
          <a href="${root}profile/index.html" class="hf-icon-btn d-none d-lg-inline-flex" title="My Profile">
            <i class="bi bi-person"></i>
          </a>
        </div>
      </header>
    `;

    // Wire the theme toggle here so the page does not depend on an inline handler
    // existing on window.HF. Falls back to a local toggle if app.js is absent.
    const themeBtn = headerEl.querySelector('#hfTopbarThemeBtn');
    if (themeBtn) {
      themeBtn.addEventListener('click', () => {
        if (window.HF && typeof window.HF.toggleTheme === 'function') {
          window.HF.toggleTheme();
          return;
        }
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('hoopflow_theme', next);
        themeBtn.innerHTML = `<i class="bi ${next === 'dark' ? 'bi-sun-fill text-warning' : 'bi-moon-stars-fill'}"></i>`;
      });
    }

    document.dispatchEvent(new CustomEvent('hf:nav-rendered'));
  }

  function renderBottomNav() {
    const navEl = document.getElementById('hfBottomNavContainer');
    if (!navEl) return;

    const root = getRootPrefix();
    const role = window.HF ? window.HF.getRole() : (localStorage.getItem('hoopflow_role') || 'organizer');
    const path = window.location.pathname.toLowerCase().replace(/\\/g, '/');

    // 5th tab varies by role
    let fifthTab = {
      label: 'Profile',
      icon: 'bi-person',
      url: `${root}profile/index.html`,
      match: 'profile'
    };

    if (role === 'coach') {
      fifthTab = {
        label: 'My Team',
        icon: 'bi-people',
        url: `${root}teams/my-team.html`,
        match: 'my-team'
      };
    }

    const tabs = [
      { label: 'Home', icon: 'bi-house-door', url: `${root}dashboard/index.html`, match: 'dashboard' },
      { label: 'Brackets', icon: 'bi-diagram-3', url: `${root}brackets/view.html`, match: 'bracket' },
      { label: 'Schedules', icon: 'bi-calendar3', url: `${root}schedule/index.html`, match: 'schedule' },
      { label: 'Standings', icon: 'bi-bar-chart', url: `${root}standings/table.html`, match: 'standings' },
      fifthTab
    ];

    let html = `<nav class="hf-bottom-nav d-lg-none">`;
    tabs.forEach(tab => {
      const isActive = path.includes(tab.match) || (tab.match === 'dashboard' && (path.endsWith('/') || path.endsWith('index.html') && !path.includes('/brackets/') && !path.includes('/tournaments/') && !path.includes('/teams/')));
      html += `
        <a href="${tab.url}" class="hf-nav-tab ${isActive ? 'active' : ''}">
          <i class="bi ${tab.icon}${isActive ? '-fill' : ''}"></i>
          <span>${tab.label}</span>
        </a>
      `;
    });
    html += `</nav>`;

    navEl.innerHTML = html;
  }

  document.addEventListener('DOMContentLoaded', () => {
    renderTopbar();
    renderBottomNav();
  });
})();
