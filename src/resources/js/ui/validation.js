/**
 * Field for.
 *
 * @param {*} error The error.
 * @returns {*} The result.
 */
function fieldFor(error) {
    const field = error.closest('.hf-field');
    return field?.querySelector('input, select, textarea') ?? null;
}

/**
 * Ensure error ids.
 * @returns {*} The result.
 */
function ensureErrorIds() {
    document.querySelectorAll('.hf-field-error').forEach((error, index) => {
        if (!error.id) error.id = `hf-error-${index}-${Math.random().toString(36).slice(2, 8)}`;
        error.setAttribute('role', 'alert');

        const field = fieldFor(error);
        if (!field) return;
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', error.id);
    });
}

/**
 * Build summary.
 * @returns {*} The result.
 */
function buildSummary() {
    const errors = [...document.querySelectorAll('.hf-field-error')].filter((error) => error.textContent.trim() !== '');
    if (!errors.length || document.querySelector('[data-hf-error-summary]')) return;

    const host = document.querySelector('[data-hf-form]') ?? document.querySelector('form') ?? document.querySelector('[wire\\:id]');
    if (!host) return;

    const summary = document.createElement('div');
    summary.className = 'hf-callout hf-callout--danger hf-error-summary';
    summary.setAttribute('role', 'alert');
    summary.setAttribute('tabindex', '-1');
    summary.setAttribute('data-hf-error-summary', '');

    const count = errors.length;
    const list = errors.map((error) => {
        const field = fieldFor(error);
        const id = field?.id || error.id;
        return `<li><a href="#${id}">${error.textContent.trim()}</a></li>`;
    }).join('');

    summary.innerHTML = `
        <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
        <div>
            <strong>Please fix ${count} ${count === 1 ? 'field' : 'fields'}</strong>
            <ul>${list}</ul>
        </div>
    `;

    host.prepend(summary);
}

/**
 * Focus first invalid.
 * @returns {*} The result.
 */
function focusFirstInvalid() {
    const summary = document.querySelector('[data-hf-error-summary]');
    if (!summary) return;
    const field = document.querySelector('[aria-invalid="true"]');
    if (field) {
        field.focus({ preventScroll: false });
        field.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
}

let bound = false;

/**
 * Refresh validation.
 * @returns {*} The result.
 */
export function refreshValidation() {
    ensureErrorIds();
    buildSummary();
}

/**
 * Init validation.
 * @returns {*} The result.
 */
export function initValidation() {
    refreshValidation();

    if (document.querySelector('[data-hf-error-summary]')) {
        window.requestAnimationFrame(focusFirstInvalid);
    }

    if (bound) return;
    bound = true;

    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morphed', () => {
            refreshValidation();
            focusFirstInvalid();
        });
    });

    document.addEventListener('submit', () => {
        window.setTimeout(refreshValidation, 0);
    });
}
