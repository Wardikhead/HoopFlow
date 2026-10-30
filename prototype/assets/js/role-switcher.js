/**
 * Floating Demo Toolbar & Role Switcher
 */
(function() {
  document.addEventListener('DOMContentLoaded', () => {
    const currentRole = window.HF ? window.HF.getRole() : (localStorage.getItem('hoopflow_role') || 'organizer');
    
    // Inject floating toolbar
    const toolbar = document.createElement('div');
    toolbar.className = 'hoopflow-toolbar d-print-none';
    toolbar.innerHTML = `
      <div class="d-flex align-items-center gap-1">
        <i class="bi bi-shield-lock text-warning"></i>
        <span class="d-none d-sm-inline fw-semibold">Role:</span>
      </div>
      <select id="hfRoleSwitcher" aria-label="Role Switcher">
        <option value="organizer" ${currentRole === 'organizer' ? 'selected' : ''}>Organizer (Admin)</option>
        <option value="coach" ${currentRole === 'coach' ? 'selected' : ''}>Coach (Team Mgr)</option>
        <option value="fan" ${currentRole === 'fan' ? 'selected' : ''}>Fan / Public</option>
      </select>
    `;
    document.body.appendChild(toolbar);

    // Event handlers
    const select = document.getElementById('hfRoleSwitcher');
    select.addEventListener('change', (e) => {
      const newRole = e.target.value;
      if (window.HF) {
        window.HF.setRole(newRole);
      } else {
        localStorage.setItem('hoopflow_role', newRole);
        window.location.reload();
      }
    });

    // Apply role attribute to body and filter role-conditional elements
    document.body.setAttribute('data-current-role', currentRole);
    applyRoleVisibility(currentRole);
  });

  function applyRoleVisibility(role) {
    document.querySelectorAll('[data-role-required]').forEach(el => {
      const allowedRoles = el.getAttribute('data-role-required').split(',').map(r => r.trim());
      if (allowedRoles.includes(role) || allowedRoles.includes('*')) {
        el.style.display = '';
      } else {
        el.style.display = 'none';
      }
    });
  }
})();

