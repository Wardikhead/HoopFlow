import { route } from '../routes.js';

export function render({ store, ui, root }) {
  root.innerHTML = `
    <div class="hf-auth-card" data-testid="register-card">
      <span class="hf-eyebrow">New account</span>
      <h1 class="hf-auth-title">Create account</h1>
      <p class="hf-auth-sub">Prototype registration creates a local demo session only.</p>
      <form id="registerForm" novalidate>
        <div class="hf-form-grid">
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="registerName">Full name</label>
            <input class="hf-input" id="registerName" name="name" autocomplete="name"
                   placeholder="Juan Dela Cruz" data-testid="register-name">
          </div>
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="registerEmail">Email</label>
            <input class="hf-input" id="registerEmail" name="email" type="email"
                   autocomplete="email" placeholder="you@club.ph" data-testid="register-email">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="registerPassword">Password</label>
            <input class="hf-input" id="registerPassword" name="password" type="password"
                   autocomplete="new-password" data-testid="register-password">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="registerRole">Role</label>
            <select class="hf-select" id="registerRole" name="role" data-testid="register-role">
              <option value="coach">Coach</option>
              <option value="fan">Fan</option>
            </select>
          </div>
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="registerOrganization">Club or organization</label>
            <input class="hf-input" id="registerOrganization" name="organization"
                   placeholder="Barangay San Antonio Youth Club" data-testid="register-organization">
          </div>
        </div>
        <button type="submit" class="hf-btn hf-btn--primary hf-full hf-mt-3" data-testid="register-submit">
          Create demo account
        </button>
      </form>
      <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
        Already have an account? <a href="${route('login')}">Sign in</a>.
      </p>
    </div>
  `;

  const form = root.querySelector('#registerForm');
  const fields = {
    name: root.querySelector('#registerName'),
    email: root.querySelector('#registerEmail'),
    password: root.querySelector('#registerPassword'),
    role: root.querySelector('#registerRole'),
    organization: root.querySelector('#registerOrganization'),
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    let valid = true;
    if (fields.name.value.trim().length < 2) {
      ui.setFieldError(fields.name, 'Enter your full name.');
      valid = false;
    } else ui.clearFieldError(fields.name);
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fields.email.value.trim())) {
      ui.setFieldError(fields.email, 'Enter a valid email address.');
      valid = false;
    } else ui.clearFieldError(fields.email);
    if (fields.password.value.length < 6) {
      ui.setFieldError(fields.password, 'Use at least 6 characters.');
      valid = false;
    } else ui.clearFieldError(fields.password);
    if (!valid) return;
    store.actions.setRole(fields.role.value);
    ui.toast('Local demo account created.', { tone: 'success', title: 'Welcome to HoopFlow' });
    window.setTimeout(() => {
      window.location.href = route('dashboard');
    }, 250);
  });
}