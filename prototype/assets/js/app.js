/**
 * HoopFlow Core Interactions & Micro-Features
 * - Arena Dark Mode Toggle
 * - Global Toast Notifications System
 * - Utility helpers
 */

(function() {
  // 1. Initialize Theme
  const savedTheme = localStorage.getItem('hoopflow_theme') || 'light';
  document.documentElement.setAttribute('data-theme', savedTheme);

  // 2. Global Toast System
  function ensureToastContainer() {
    let container = document.querySelector('.hf-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'hf-toast-container';
      document.body.appendChild(container);
    }
    return container;
  }

  function showToast(title, message = '', type = 'success') {
    const container = ensureToastContainer();
    const toast = document.createElement('div');
    toast.className = `hf-toast ${type}`;

    let icon = 'bi-check-circle-fill text-success';
    if (type === 'warning') icon = 'bi-exclamation-triangle-fill text-warning';
    if (type === 'danger') icon = 'bi-x-circle-fill text-danger';
    if (type === 'info') icon = 'bi-info-circle-fill text-info';

    toast.innerHTML = `
      <i class="bi ${icon} fs-5"></i>
      <div style="flex: 1;">
        <div class="fw-bold small mb-0">${title}</div>
        ${message ? `<div class="small opacity-75" style="font-size: 0.75rem;">${message}</div>` : ''}
      </div>
      <button type="button" class="btn btn-sm btn-link text-white p-0 opacity-50" onclick="this.parentElement.remove()">
        <i class="bi bi-x"></i>
      </button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  }

  // 3. Theme Toggle Function
  function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('hoopflow_theme', next);
    updateThemeButtons(next);
    showToast(next === 'dark' ? '🌙 Night Arena Dark Mode Enabled' : '☀️ Daylight Court Mode Enabled', '', 'info');
  }

  function updateThemeButtons(theme) {
    document.querySelectorAll('.hf-theme-toggle-btn').forEach(btn => {
      btn.innerHTML = theme === 'dark' 
        ? `<i class="bi bi-sun-fill text-warning"></i>` 
        : `<i class="bi bi-moon-stars-fill text-secondary"></i>`;
      btn.title = theme === 'dark' ? 'Switch to Daylight Theme' : 'Switch to Night Arena Theme';
    });
  }

  // Expose to window.HF
  window.HF = window.HF || {};
  window.HF.toast = showToast;
  window.HF.toggleTheme = toggleTheme;

  // Deferred: navigation.js and role-switcher.js build their DOM inside their own
  // DOMContentLoaded handlers, which run after this one. Yield a tick so the
  // toolbar and topbar exist before we decorate them.
  document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
      updateThemeButtons(document.documentElement.getAttribute('data-theme') || 'light');

      // Attach theme toggle button into floating toolbar if present
      const toolbar = document.querySelector('.hoopflow-toolbar');
      if (toolbar && !document.getElementById('hfThemeToggleBtn')) {
        const themeBtn = document.createElement('button');
        themeBtn.type = 'button';
        themeBtn.id = 'hfThemeToggleBtn';
        themeBtn.className = 'btn-toggle-wide hf-theme-toggle-btn ms-1';
        themeBtn.title = 'Toggle Dark Mode';
        themeBtn.onclick = toggleTheme;
        toolbar.appendChild(themeBtn);
        updateThemeButtons(document.documentElement.getAttribute('data-theme') || 'light');
      }
    }, 0);

    // Re-sync whenever another script finishes rendering chrome.
    document.addEventListener('hf:nav-rendered', () => {
      updateThemeButtons(document.documentElement.getAttribute('data-theme') || 'light');
    });
  });
})();

