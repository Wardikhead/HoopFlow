import { route } from '../routes.js';

const ACCOUNTS = {
  'organizer@hoopflow.ph': 'organizer',
  'coach.marcus@hoopflow.ph': 'coach',
  'fan.juan@hoopflow.ph': 'fan',
};

const QUICK = [
  ['organizer', 'Organizer', 'Tournament operations'],
  ['coach', 'Coach', 'San Antonio Vipers'],
  ['fan', 'Fan', 'Public view'],
];

export function render({ store, ui, root }) {
  root.innerHTML = `
    <div class="hf-auth-card" data-testid="login-card">
      <span class="hf-eyebrow">Welcome back</span>
      <h1 class="hf-auth-title">Sign in</h1>
      <p class="hf-auth-sub">Demo accounts only. Passwords are not checked in this prototype.</p>
      <form id="loginForm" novalidate>
        <div class="hf-field">
          <label class="hf-label" for="loginEmail">Email</label>
          <input class="hf-input" id="loginEmail" name="email" type="email"
                 autocomplete="username" value="organizer@hoopflow.ph" data-testid="login-email">
        </div>
        <div class="hf-field hf-mt-2">
          <label class="hf-label" for="loginPassword">Password</label>
          <div class="hf-inline-form">
            <input class="hf-input" id="loginPassword" name="password" type="password"
                   autocomplete="current-password" value="hoopflow" data-testid="login-password">
            <button type="button" class="hf-btn hf-btn--sm" data-toggle-password
                    aria-pressed="false" data-testid="login-password-toggle">Show</button>
          </div>
        </div>
        <button type="submit" class="hf-btn hf-btn--primary hf-full hf-mt-3" data-testid="login-submit">
          Sign in
        </button>
      </form>

      <p class="hf-demo-title hf-mt-3">Quick-fill accounts</p>
      <div class="hf-quick-accounts">
        ${QUICK.map(
          ([role, label, note]) => `
            <button type="button" data-quick-role="${role}" data-testid="quick-login-${role}">
              <strong>${label}</strong>
              <span>${note}</span>
            </button>
          `
        ).join('')}
      </div>
      <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
        No account? <a href="${route('register')}">Create one</a>.
      </p>
    </div>
  `;

  const form = root.querySelector('#loginForm');
  const emailInput = root.querySelector('#loginEmail');
  const passwordInput = root.querySelector('#loginPassword');
  const toggle = root.querySelector('[data-toggle-password]');

  toggle.addEventListener('click', () => {
    const show = passwordInput.type === 'password';
    passwordInput.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
    toggle.setAttribute('aria-pressed', String(show));
  });

  const enter = (role, message) => {
    store.actions.setRole(role);
    ui.toast(message, { tone: 'success', title: 'Signed in' });
    window.setTimeout(() => {
      window.location.href = route('dashboard');
    }, 250);
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const email = emailInput.value.trim().toLowerCase();
    let valid = true;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      ui.setFieldError(emailInput, 'Enter a valid email address.');
      valid = false;
    } else {
      ui.clearFieldError(emailInput);
    }
    if (passwordInput.value.length < 6) {
      ui.setFieldError(passwordInput, 'Use at least 6 characters.');
      valid = false;
    } else {
      ui.clearFieldError(passwordInput);
    }
    if (!valid) return;
    enter(ACCOUNTS[email] || 'fan', `Signed in as ${ACCOUNTS[email] || 'fan'}.`);
  });

  root.querySelectorAll('[data-quick-role]').forEach((button) => {
    button.addEventListener('click', () => {
      enter(button.dataset.quickRole, `Signed in as ${button.dataset.quickRole}.`);
    });
  });
}