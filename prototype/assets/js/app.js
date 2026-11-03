import { createStore } from './store.js';
import { createUI } from './ui.js';
import { createComponents } from './components.js';
import { renderShell } from './shell.js';
import { route } from './routes.js';

const THEME_KEY = 'hoopflow_theme';
const store = createStore();
const ui = createUI({ store });
const components = createComponents({ store, ui });

function applyTheme(value) {
  const next = value === 'dark' ? 'dark' : 'light';
  document.documentElement.dataset.theme = next;
  document.documentElement.style.colorScheme = next;
}

const theme = {
  get: () => (localStorage.getItem(THEME_KEY) === 'dark' ? 'dark' : 'light'),
  set: (value) => {
    const next = value === 'dark' ? 'dark' : 'light';
    localStorage.setItem(THEME_KEY, next);
    applyTheme(next);
    document.dispatchEvent(new CustomEvent('hf:theme-changed', { detail: { theme: next } }));
  },
};

applyTheme(theme.get());
renderShell({
  store,
  ui,
  root: document.getElementById('hfShell'),
  theme,
});

document.addEventListener('hf:role-change', (event) => {
  try {
    store.actions.setRole(event.detail.role);
  } catch (error) {
    ui.toast(error.message, { tone: 'danger', title: 'Role unavailable' });
    return;
  }
  ui.toast(`Now viewing as ${event.detail.role}.`, {
    tone: 'info',
    title: 'Demo role switched',
  });
  window.setTimeout(() => {
    window.location.href = route('dashboard');
  }, 250);
});

window.addEventListener('storage', (event) => {
  if (event.key === 'hoopflow_db') window.location.reload();
});

const page = document.body.dataset.page;
const root = document.getElementById('main');
if (page && root) {
  import(`./pages/${page}.js`)
    .then((module) => module.render({ store, ui, components, root, theme }))
    .catch((error) => {
      console.error(error);
      root.innerHTML = `
        <div class="hf-empty">
          <i class="bi bi-cone-striped" aria-hidden="true"></i>
          <h3>Page failed to load</h3>
          <p>${error.message}</p>
        </div>
      `;
    });
}