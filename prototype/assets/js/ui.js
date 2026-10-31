const STATUS_MAP = {
  approved: { tone: 'success', label: 'Approved', icon: 'check-circle-fill' },
  pending: { tone: 'warning', label: 'Pending', icon: 'hourglass-split' },
  rejected: { tone: 'danger', label: 'Rejected', icon: 'x-circle-fill' },
  paid: { tone: 'success', label: 'Paid', icon: 'cash-coin' },
  partial: { tone: 'warning', label: 'Partial', icon: 'circle-half' },
  final: { tone: 'success', label: 'Final', icon: 'flag-fill' },
  forfeit: { tone: 'danger', label: 'Forfeit', icon: 'shield-x' },
  scheduled: { tone: 'slate', label: 'Scheduled', icon: 'clock' },
  draft: { tone: 'slate', label: 'Draft', icon: 'pencil' },
  'registration-open': { tone: 'success', label: 'Registration open', icon: 'unlock' },
  active: { tone: 'primary', label: 'Active', icon: 'broadcast' },
  upcoming: { tone: 'slate', label: 'Upcoming', icon: 'calendar-event' },
  verified: { tone: 'success', label: 'Verified', icon: 'patch-check-fill' },
  missing: { tone: 'danger', label: 'Missing', icon: 'file-earmark-x' },
  unknown: { tone: 'slate', label: 'Unknown', icon: 'circle' },
};

const MONEY = new Intl.NumberFormat('en-PH', {
  style: 'currency',
  currency: 'PHP',
  maximumFractionDigits: 0,
});

export function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

export function initials(name) {
  const parts = String(name || '?')
    .trim()
    .split(/\s+/)
    .filter(Boolean);
  if (!parts.length) return '?';
  return parts
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase();
}

export function formatMoney(value) {
  return MONEY.format(Number(value) || 0);
}

export function formatDate(value, options = {}) {
  if (!value) return 'TBD';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'TBD';
  return new Intl.DateTimeFormat('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    ...options,
  }).format(date);
}

export function formatShortDate(value) {
  return formatDate(value, { year: undefined });
}

export function formatTime(value) {
  if (!value) return 'TBD';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'TBD';
  return new Intl.DateTimeFormat('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
  }).format(date);
}

export function formatDateTime(value) {
  if (!value) return 'TBD';
  return `${formatDate(value)} · ${formatTime(value)}`;
}

export function formatDay(value) {
  if (!value) return 'TBD';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'TBD';
  return new Intl.DateTimeFormat('en-PH', {
    timeZone: 'Asia/Manila',
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  }).format(date);
}

export function statusMeta(status) {
  return STATUS_MAP[status] || {
    tone: 'slate',
    label: String(status || 'Unknown').replaceAll('-', ' '),
    icon: 'circle',
  };
}

function ensureToastRegion() {
  let region = document.querySelector('.hf-toast-region');
  if (!region) {
    region = document.createElement('div');
    region.className = 'hf-toast-region';
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'false');
    document.body.appendChild(region);
  }
  return region;
}

function ensureLiveRegion() {
  let region = document.getElementById('hfLiveRegion');
  if (!region) {
    region = document.createElement('div');
    region.id = 'hfLiveRegion';
    region.className = 'hf-sr-only';
    region.setAttribute('aria-live', 'polite');
    document.body.appendChild(region);
  }
  return region;
}

export function createUI() {
  function toast(message, { title = '', tone = 'primary', duration = 4200 } = {}) {
    const region = ensureToastRegion();
    const element = document.createElement('div');
    const icon =
      tone === 'danger'
        ? 'x-circle-fill'
        : tone === 'warning'
          ? 'exclamation-triangle-fill'
          : tone === 'success'
            ? 'check-circle-fill'
            : 'info-circle-fill';
    element.className = `hf-toast hf-toast--${tone}`;
    element.setAttribute('role', tone === 'danger' ? 'alert' : 'status');
    element.innerHTML = `
      <i class="bi bi-${icon}" aria-hidden="true"></i>
      <div>
        ${title ? `<strong>${escapeHtml(title)}</strong>` : ''}
        <p>${escapeHtml(message)}</p>
      </div>
      <button type="button" aria-label="Dismiss notification" data-testid="toast-dismiss">&times;</button>
    `;
    region.appendChild(element);
    const remove = () => element.remove();
    element.querySelector('button').addEventListener('click', remove);
    if (duration > 0) window.setTimeout(remove, duration);
    return remove;
  }

  function dialog({ title, body, footer = '', size = 'normal', onMount, onClose } = {}) {
    const trigger = document.activeElement;
    const element = document.createElement('dialog');
    element.className = `hf-dialog${size === 'wide' ? ' hf-dialog--wide' : ''}`;
    element.innerHTML = `
      <div class="hf-dialog__head">
        <h2 class="hf-dialog__title">${escapeHtml(title)}</h2>
        <button type="button" class="hf-icon-btn" data-dialog-close aria-label="Close dialog" data-testid="dialog-close">
          <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
      </div>
      <div class="hf-dialog__body">${body}</div>
      ${footer ? `<div class="hf-dialog__foot">${footer}</div>` : ''}
    `;
    document.body.appendChild(element);
    const close = () => {
      if (element.open) element.close();
    };
    element.querySelector('[data-dialog-close]')?.addEventListener('click', close);
    element.addEventListener('click', (event) => {
      if (event.target === element) close();
    });
    element.addEventListener(
      'close',
      () => {
        element.remove();
        if (trigger instanceof HTMLElement) trigger.focus();
        onClose?.();
      },
      { once: true }
    );
    element.showModal();
    onMount?.(element, close);
    return { element, close };
  }

  function confirm({
    title = 'Confirm action',
    message = '',
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    tone = 'primary',
  } = {}) {
    return new Promise((resolve) => {
      let settled = false;
      const finish = (value) => {
        if (settled) return;
        settled = true;
        resolve(value);
      };
      const { element, close } = dialog({
        title,
        body: `<p class="hf-mb-0">${escapeHtml(message)}</p>`,
        footer: `
          <button type="button" class="hf-btn" data-confirm-cancel data-testid="confirm-cancel">${escapeHtml(cancelLabel)}</button>
          <button type="button" class="hf-btn hf-btn--${tone}" data-confirm-ok data-testid="confirm-ok">${escapeHtml(confirmLabel)}</button>
        `,
        onClose: () => finish(false),
      });
      element.querySelector('[data-confirm-cancel]').addEventListener('click', () => {
        finish(false);
        close();
      });
      element.querySelector('[data-confirm-ok]').addEventListener('click', () => {
        finish(true);
        close();
      });
    });
  }

  function statusChip(status, label = null) {
    const meta = statusMeta(status);
    return `<span class="hf-chip hf-chip--${meta.tone}"><i class="bi bi-${meta.icon}" aria-hidden="true"></i>${escapeHtml(label || meta.label)}</span>`;
  }

  function emptyState({ icon = 'inbox', title = 'Nothing here yet', message = '', action = '' } = {}) {
    return `
      <div class="hf-empty">
        <i class="bi bi-${icon}" aria-hidden="true"></i>
        <h3>${escapeHtml(title)}</h3>
        ${message ? `<p>${escapeHtml(message)}</p>` : ''}
        ${action}
      </div>
    `;
  }

  function setFieldError(input, message) {
    if (!input) return;
    input.setAttribute('aria-invalid', 'true');
    const errorId = `${input.id || input.name || 'field'}-error`;
    let error = document.getElementById(errorId);
    if (!error) {
      error = document.createElement('div');
      error.id = errorId;
      error.className = 'hf-field-error';
      input.insertAdjacentElement('afterend', error);
    }
    error.textContent = message;
    input.setAttribute('aria-describedby', errorId);
  }

  function clearFieldError(input) {
    if (!input) return;
    input.removeAttribute('aria-invalid');
    input.removeAttribute('aria-describedby');
    document.getElementById(`${input.id || input.name || 'field'}-error`)?.remove();
  }

  function setBusy(button, busy, label = 'Working...') {
    if (!button) return;
    if (busy) {
      button.dataset.originalHtml = button.innerHTML;
      button.disabled = true;
      button.innerHTML = `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ${escapeHtml(label)}`;
    } else {
      button.disabled = false;
      if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
    }
  }

  function announce(message) {
    const region = ensureLiveRegion();
    region.textContent = '';
    window.setTimeout(() => {
      region.textContent = message;
    }, 20);
  }

  function focusMain() {
    const main = document.getElementById('main');
    if (main) main.focus({ preventScroll: true });
  }

  function reducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function debounce(fn, wait = 180) {
    let timer = 0;
    return (...args) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => fn(...args), wait);
    };
  }

  return {
    toast,
    dialog,
    confirm,
    statusChip,
    statusMeta,
    emptyState,
    setFieldError,
    clearFieldError,
    setBusy,
    announce,
    focusMain,
    reducedMotion,
    debounce,
  };
}
