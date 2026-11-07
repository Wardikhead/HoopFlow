import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney } from '../ui.js';
import { guardRole } from '../guards.js';
import { DIVISIONS } from '../options.js';

const STEPS = ['Basics', 'Format & courts', 'Review'];

const STEP_FOR_FIELD = {
  name: 0,
  division: 0,
  venue: 0,
  startDate: 0,
  endDate: 0,
  description: 0,
  format: 1,
  slots: 1,
  courts: 1,
  slotMinutes: 1,
  restMinutes: 1,
  fee: 1,
};

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['organizer'] })) return;

  let step = 0;
  let courtIndex = 2;

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Organizer desk · Setup',
      title: 'Create a tournament',
      description: 'Name the competition, choose the court plan, then publish a registration page in three steps.',
      actions: `<a class="hf-btn" href="${route('tournaments')}" data-testid="create-cancel"><i class="bi bi-arrow-left"></i> Tournaments</a>`,
    })}

    <ol class="hf-stepper" data-testid="create-stepper">
      ${STEPS.map(
        (label, index) => `
          <li class="hf-step ${index === 0 ? 'is-active' : ''}"
              data-step-indicator="${index}" data-testid="create-step-${index + 1}">
            <span>${index + 1}</span>${escapeHtml(label)}
          </li>
        `
      ).join('')}
    </ol>

    <form id="createForm" class="hf-card" novalidate data-testid="create-form">
      <section data-step-panel="0">
        <div class="hf-form-grid">
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="createName">Tournament name</label>
            <input class="hf-input" id="createName" name="name" value="HoopFlow Metro Invitational"
                   placeholder="HoopFlow Summer Cup" data-testid="create-name">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createDivision">Division</label>
            <select class="hf-select" id="createDivision" name="division" data-testid="create-division">
              ${DIVISIONS.map((division) => `<option value="${escapeHtml(division)}">${escapeHtml(division)}</option>`).join('')}
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createVenue">Venue</label>
            <input class="hf-input" id="createVenue" name="venue" value="San Antonio Central Sports Complex"
                   placeholder="Ynares Sports Arena" data-testid="create-venue">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createStart">Start date</label>
            <input class="hf-input" id="createStart" name="startDate" type="date" value="2026-08-01"
                   data-testid="create-start-date">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createEnd">End date</label>
            <input class="hf-input" id="createEnd" name="endDate" type="date" value="2026-08-08"
                   data-testid="create-end-date">
          </div>
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="createDescription">Description</label>
            <textarea class="hf-textarea" id="createDescription" name="description" rows="3"
                      data-testid="create-description">Pre-season invitational for barangay youth teams, with accredited referees and a single-elimination bracket.</textarea>
          </div>
        </div>
      </section>

      <section data-step-panel="1" hidden>
        <div class="hf-form-grid">
          <div class="hf-field hf-field--full">
            <span class="hf-label">Format</span>
            <div class="hf-grid hf-grid--3">
              <label class="hf-card hf-card--flat hf-flex hf-items-center hf-gap-2">
                <input type="radio" name="format" value="single-elimination" checked data-testid="create-format-elimination">
                <span><strong>Single elimination</strong><span class="hf-small hf-muted" style="display:block">One loss ends the run.</span></span>
              </label>
              <label class="hf-card hf-card--flat hf-flex hf-items-center hf-gap-2">
                <input type="radio" name="format" value="double-elimination" data-testid="create-format-double-elimination">
                <span><strong>Double elimination</strong><span class="hf-small hf-muted" style="display:block">Two losses to be eliminated.</span></span>
              </label>
              <label class="hf-card hf-card--flat hf-flex hf-items-center hf-gap-2">
                <input type="radio" name="format" value="round-robin" data-testid="create-format-round-robin">
                <span><strong>Round robin</strong><span class="hf-small hf-muted" style="display:block">Everyone plays everyone.</span></span>
              </label>
            </div>
          </div>

          <div class="hf-field">
            <label class="hf-label" for="createSlots">Bracket slots</label>
            <select class="hf-select" id="createSlots" name="slots" data-testid="create-slots">
              <option value="8" selected>8 teams</option>
              <option value="16">16 teams</option>
            </select>
            <p class="hf-small hf-muted hf-mb-0" data-testid="create-slots-hint">Single/double elimination: 2, 4, 8, 16, or 32. Round robin: 2 to 16.</p>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createFee">Entry fee (PHP)</label>
            <input class="hf-input" id="createFee" name="fee" type="number" min="0" step="100" value="1500"
                   data-testid="create-fee">
          </div>

          <div class="hf-field hf-field--full">
            <span class="hf-label">Courts</span>
            <div data-court-list></div>
            <button type="button" class="hf-btn hf-btn--sm hf-mt-2" data-add-court data-testid="create-add-court">
              <i class="bi bi-plus-lg"></i> Add court
            </button>
          </div>

          <div class="hf-field">
            <label class="hf-label" for="createSlotMinutes">Slot length (minutes)</label>
            <input class="hf-input" id="createSlotMinutes" name="slotMinutes" type="number" min="10" step="5" value="30"
                   data-testid="create-slot-minutes">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="createRestMinutes">Rest between games (minutes)</label>
            <input class="hf-input" id="createRestMinutes" name="restMinutes" type="number" min="0" step="5" value="15"
                   data-testid="create-rest-minutes">
          </div>
        </div>
      </section>

      <section data-step-panel="2" hidden>
        <div id="createSummary" data-testid="create-summary"></div>
        <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
          Publishing opens registration immediately. You can review teams, collect payments, then generate the bracket and court schedule from the tournament desk.
        </p>
      </section>

      <div class="hf-between hf-mt-4">
        <button type="button" class="hf-btn" data-back data-testid="create-back" hidden>
          <i class="bi bi-arrow-left"></i> Back
        </button>
        <span></span>
        <div class="hf-btn-row">
          <button type="button" class="hf-btn hf-btn--primary" data-next data-testid="create-next">
            Continue <i class="bi bi-arrow-right"></i>
          </button>
          <button type="submit" class="hf-btn hf-btn--primary" data-publish data-testid="create-publish" hidden>
            <i class="bi bi-broadcast"></i> Publish tournament
          </button>
        </div>
      </div>
    </form>
  `;

  const form = root.querySelector('#createForm');
  const back = root.querySelector('[data-back]');
  const next = root.querySelector('[data-next]');
  const publish = root.querySelector('[data-publish]');
  const courtList = root.querySelector('[data-court-list]');
  const summary = root.querySelector('#createSummary');

  const input = (name) => form.querySelector(`[name="${name}"]`);
  const courtInputs = () => [...form.querySelectorAll('[data-court-input]')];

  function addCourt(value = '') {
    const index = courtIndex++;
    courtList.insertAdjacentHTML(
      'beforeend',
      `
        <div class="hf-flex hf-items-center hf-gap-2 hf-mt-1" data-court-row>
          <input class="hf-input" name="court-${index}" data-court-input
                 value="${escapeHtml(value)}" placeholder="Court name"
                 data-testid="create-court-${index}">
          <button type="button" class="hf-icon-btn" data-remove-court
                  aria-label="Remove court" data-testid="create-remove-court-${index}">
            <i class="bi bi-trash3" aria-hidden="true"></i>
          </button>
        </div>
      `
    );
  }

  addCourt('Court 1');
  addCourt('Court 2');

  function showStep(index, { focus = true } = {}) {
    step = Math.max(0, Math.min(STEPS.length - 1, index));
    root.querySelectorAll('[data-step-panel]').forEach((panel) => {
      panel.hidden = Number(panel.dataset.stepPanel) !== step;
    });
    root.querySelectorAll('[data-step-indicator]').forEach((item, position) => {
      item.classList.toggle('is-active', position === step);
      item.classList.toggle('is-done', position < step);
      if (position === step) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
    });
    back.hidden = step === 0;
    next.hidden = step === STEPS.length - 1;
    publish.hidden = step !== STEPS.length - 1;
    if (step === STEPS.length - 1) syncSummary();
    if (focus) {
      const target = root.querySelector(
        `[data-step-panel="${step}"] input, [data-step-panel="${step}"] select, [data-step-panel="${step}"] textarea`
      );
      target?.focus();
    }
  }

  function validateBasics() {
    ['name', 'division', 'venue', 'startDate', 'endDate'].forEach((name) =>
      ui.clearFieldError(input(name))
    );
    let ok = true;
    if (input('name').value.trim().length < 3) {
      ui.setFieldError(input('name'), 'Enter at least 3 characters.');
      ok = false;
    }
    if (!input('division').value) {
      ui.setFieldError(input('division'), 'Choose a division.');
      ok = false;
    }
    if (input('venue').value.trim().length < 3) {
      ui.setFieldError(input('venue'), 'Enter at least 3 characters.');
      ok = false;
    }
    if (!input('startDate').value) {
      ui.setFieldError(input('startDate'), 'Choose a start date.');
      ok = false;
    }
    if (!input('endDate').value) {
      ui.setFieldError(input('endDate'), 'Choose an end date.');
      ok = false;
    }
    if (
      input('startDate').value &&
      input('endDate').value &&
      input('endDate').value < input('startDate').value
    ) {
      ui.setFieldError(input('endDate'), 'End date must be on or after the start date.');
      ok = false;
    }
    return ok;
  }

  function validateFormat() {
    ['slots', 'slotMinutes', 'restMinutes', 'fee'].forEach((name) =>
      ui.clearFieldError(input(name))
    );
    courtInputs().forEach((court) => ui.clearFieldError(court));
    let ok = true;

    const filled = courtInputs().filter((court) => court.value.trim());
    if (!filled.length) {
      ui.setFieldError(courtInputs()[0], 'Add at least one court.');
      ok = false;
    }
    filled.forEach((court) => {
      if (court.value.trim().length < 2) {
        ui.setFieldError(court, 'Court name must be at least 2 characters.');
        ok = false;
      }
    });

    const format = form.querySelector('[name="format"]:checked')?.value || 'single-elimination';
    const slots = Number(input('slots').value);
    const isRR = format === 'round-robin';
    const validSlots = isRR
      ? (slots >= 2 && slots <= 16)
      : [2, 4, 8, 16, 32].includes(slots);
    if (!validSlots) {
      ui.setFieldError(input('slots'), isRR ? 'Choose 2 to 16 teams.' : 'Choose 2, 4, 8, 16, or 32 slots.');
      ok = false;
    }
    const slotMinutes = Number(input('slotMinutes').value);
    if (!Number.isInteger(slotMinutes) || slotMinutes < 10) {
      ui.setFieldError(input('slotMinutes'), 'Use at least 10 minutes.');
      ok = false;
    }
    const restMinutes = Number(input('restMinutes').value);
    if (!Number.isInteger(restMinutes) || restMinutes < 0) {
      ui.setFieldError(input('restMinutes'), 'Rest must be zero or more minutes.');
      ok = false;
    }
    const fee = Number(input('fee').value);
    if (!Number.isFinite(fee) || fee < 0) {
      ui.setFieldError(input('fee'), 'Fee must be zero or more.');
      ok = false;
    }
    return ok;
  }

  function syncSummary() {
    const courts = courtInputs()
      .map((court) => court.value.trim())
      .filter(Boolean);
    const slots = Number(input('slots').value) || 16;
    const firstRound = slots / 2;
    const format = form.querySelector('[name="format"]:checked')?.value || 'single-elimination';
    const formatLabel = (f) => (f === 'double-elimination' ? 'Double elimination' : f === 'round-robin' ? 'Round robin' : 'Single elimination');
    summary.innerHTML = `
      <div class="hf-grid hf-grid--3 hf-mb-3">
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Competition</span>
          <strong class="hf-section-title hf-mt-1">${escapeHtml(input('name').value || 'Untitled')}</strong>
          <p class="hf-small hf-muted hf-mb-0">${escapeHtml(input('division').value)} · ${escapeHtml(input('venue').value)}</p>
        </div>
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Window</span>
          <strong class="hf-section-title hf-mt-1">${escapeHtml(formatDate(input('startDate').value))}</strong>
          <p class="hf-small hf-muted hf-mb-0">to ${escapeHtml(formatDate(input('endDate').value))}</p>
        </div>
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Entry</span>
          <strong class="hf-kpi-value" style="font-size: 30px">${escapeHtml(formatMoney(input('fee').value))}</strong>
          <p class="hf-small hf-muted hf-mb-0">per team</p>
        </div>
      </div>
      <div class="hf-card hf-card--flat">
        <div class="hf-between">
          <span><strong>${slots}-team ${escapeHtml(formatLabel(format))}</strong><span class="hf-small hf-muted" style="display:block">${format === 'round-robin' ? 'Full round robin: every team plays every team.' : `${firstRound} first-round games, then quarterfinals, semifinals, and the final.`}</span></span>
          <span class="hf-chip hf-chip--gold">${courts.length} courts</span>
        </div>
        <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2 hf-small hf-muted">
          <span><i class="bi bi-clock"></i> ${escapeHtml(input('slotMinutes').value)}-minute slots</span>
          <span><i class="bi bi-hourglass-split"></i> ${escapeHtml(input('restMinutes').value)}-minute rest</span>
          <span><i class="bi bi-geo-alt"></i> ${escapeHtml(courts.join(' · ') || 'No courts yet')}</span>
        </div>
      </div>
    `;
  }

  function handleError(error) {
    const targetStep = STEP_FOR_FIELD[error.field] ?? 0;
    showStep(targetStep, { focus: false });
    const target =
      error.field === 'courts' ? courtInputs()[0] : input(error.field || '');
    if (target) {
      ui.setFieldError(target, error.message);
      target.focus();
    }
    ui.toast(error.message, { tone: 'danger', title: 'Could not create tournament' });
  }

  back.addEventListener('click', () => showStep(step - 1));

  function updateSlotsOptions() {
    const format = form.querySelector('[name="format"]:checked')?.value || 'single-elimination';
    const select = input('slots');
    if (!select) return;
    const isRR = format === 'round-robin';
    const values = isRR ? [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16] : [2, 4, 8, 16, 32];
    const current = Number(select.value);
    select.innerHTML = values
      .map((v) => `<option value="${v}" ${v === (values.includes(current) ? current : values[0]) ? 'selected' : ''}>${v} ${isRR ? 'teams' : 'teams'}</option>`)
      .join('');
  }
  form.querySelectorAll('[name="format"]').forEach((radio) => {
    radio.addEventListener('change', updateSlotsOptions);
  });
  updateSlotsOptions();

  next.addEventListener('click', () => {
    if (step === 0 && !validateBasics()) return;
    if (step === 1 && !validateFormat()) return;
    showStep(step + 1);
  });

  root.querySelector('[data-add-court]').addEventListener('click', () => {
    addCourt('');
    const inputs = courtInputs();
    inputs[inputs.length - 1].focus();
  });

  courtList.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-court]');
    if (!button) return;
    if (courtList.querySelectorAll('[data-court-row]').length <= 1) {
      ui.toast('Keep at least one court for the schedule.', { tone: 'warning', title: 'Court required' });
      return;
    }
    button.closest('[data-court-row]').remove();
  });

  form.addEventListener('input', () => {
    if (step === 2) syncSummary();
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const basics = validateBasics();
    const format = validateFormat();
    if (!basics) {
      showStep(0);
      return;
    }
    if (!format) {
      showStep(1);
      return;
    }

    const payload = {
      name: input('name').value.trim(),
      division: input('division').value,
      venue: input('venue').value.trim(),
      startDate: input('startDate').value,
      endDate: input('endDate').value,
      description: input('description').value.trim(),
      format: form.querySelector('[name="format"]:checked')?.value || 'single-elimination',
      slots: Number(input('slots').value),
      courts: courtInputs()
        .map((court) => court.value.trim())
        .filter(Boolean),
      slotMinutes: Number(input('slotMinutes').value),
      restMinutes: Number(input('restMinutes').value),
      fee: Number(input('fee').value),
    };

    ui.setBusy(publish, true, 'Publishing...');
    try {
      const id = store.actions.createTournament(payload);
      ui.toast('Registration is open. Share the tournament desk with coaches.', {
        tone: 'success',
        title: 'Tournament created',
      });
      window.setTimeout(() => {
        window.location.href = route('tournamentShow', { id });
      }, 300);
    } catch (error) {
      ui.setBusy(publish, false);
      handleError(error);
    }
  });

  showStep(0, { focus: false });
}
