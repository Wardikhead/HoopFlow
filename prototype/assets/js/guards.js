import { route } from './routes.js';

const ROLE_LABELS = {
  organizer: 'organizer',
  coach: 'coach',
  fan: 'fan',
};

export function guardRole({ store, ui, root, roles, message = '' }) {
  const role = store.getRole();
  if (roles.includes(role)) return true;
  const detail =
    message || `The ${ROLE_LABELS[role] || role} demo role cannot open this page.`;
  root.innerHTML = ui.emptyState({
    icon: 'lock',
    title: 'Restricted area',
    message: detail,
    action: `<a class="hf-btn hf-btn--primary" href="${route('dashboard')}" data-testid="restricted-back">Back to dashboard</a>`,
  });
  ui.toast(detail, { tone: 'warning', title: 'Restricted area' });
  const windowRef = globalThis.window;
  const schedule = windowRef?.setTimeout?.bind(windowRef) || setTimeout;
  schedule(() => {
    if (windowRef?.location) windowRef.location.href = route('dashboard');
  }, 1200);
  return false;
}
