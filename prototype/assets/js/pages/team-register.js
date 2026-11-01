import { route } from '../routes.js';
import { escapeHtml, formatDate, formatMoney } from '../ui.js';
import { guardRole } from '../guards.js';
import { POSITIONS } from '../seed.js';

const STEPS = ['Team', 'Roster', 'Eligibility & payment', 'Review'];
const COLORS = [
  { value: '#ea580c', label: 'Orange' },
  { value: '#2563eb', label: 'Blue' },
  { value: '#059669', label: 'Green' },
  { value: '#7c3aed', label: 'Violet' },
  { value: '#dc2626', label: 'Red' },
  { value: '#475569', label: 'Slate' },
];

const DEMO_PLAYERS = [
  ['Jayson Ramos', 4, 14], ['Kobe Cruz', 7, 15], ['Carl Gomez', 10, 14],
  ['Kai Fajardo', 11, 15], ['June Mar Diaz', 13, 14], ['Gabe Santos', 21, 15],
  ['CJ Reyes', 24, 14], ['Dwight Bautista', 30, 15],
];

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['coach'] })) return;

  const params = new URLSearchParams(window.location.search);
  const state = store.getState();
  const tournamentId =
    params.get('tournament') ||
    state.tournaments.find((tournament) => tournament.registrationOpen)?.id ||
    '';
  const tournament = store.select.tournament(tournamentId);

  if (!tournament) {
    root.innerHTML = ui.emptyState({
      icon: 'trophy',
      title: 'Tournament not found',
      message: 'Choose an open tournament from the directory.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Browse tournaments</a>`,
    });
    return;
  }

  if (!tournament.registrationOpen || tournament.status !== 'registration-open') {
    root.innerHTML = ui.emptyState({
      icon: 'lock',
      title: 'Registration is closed',
      message: `${tournament.name} is not accepting new teams.`,
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Find an open tournament</a>`,
    });
    return;
  }

  const existing = store.select.coachTeams().find((team) => team.tournamentId === tournamentId);
  if (existing) {
    root.innerHTML = ui.emptyState({
      icon: 'check2-circle',
      title: 'Team already registered',
      message: `${existing.name} is already in ${tournament.name} with status “${existing.status}”.`,
      action: `<a class="hf-btn hf-btn--primary" href="${route('myTeam', { team: existing.id })}">Open my team</a>`,
    });
    return;
  }

  const user = store.getCurrentUser();
  const defaultCoach = user.name.replace(/^Coach\s+/i, '');
  const divisionLimit = Number((tournament.division.match(/(\d+)/) || [])[1] || 99);

  let step = 0;
  const roster = Array.from({ length: 8 }, () => ({
    name: '',
    number: '',
    position: POSITIONS[0],
    age: '',
    guardianConsent: false,
    documentStatus: 'pending',
  }));

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: `${tournament.division} · Registration open`,
      title: 'Register a team',
      description: `${tournament.name} · ${tournament.venue} · ${formatDate(tournament.startDate)} to ${formatDate(tournament.endDate)} · ${formatMoney(tournament.fee)} entry.`,
      actions: `<a class="hf-btn" href="${route('tournamentShow', { id: tournamentId })}" data-testid="register-cancel"><i class="bi bi-arrow-left"></i> Tournament</a>`,
    })}

    <ol class="hf-stepper" data-testid="register-stepper">
      ${STEPS.map(
        (label, index) => `
          <li class="hf-step ${index === 0 ? 'is-active' : ''}"
              data-step-indicator="${index}" data-testid="register-step-${index + 1}">
            <span>${index + 1}</span>${escapeHtml(label)}
          </li>
        `
      ).join('')}
    </ol>

    <form id="registerForm" class="hf-card" novalidate data-testid="register-form">
      <section data-step-panel="0">
        <div class="hf-between hf-mb-2">
          <p class="hf-small hf-muted hf-mb-0">Coach account: ${escapeHtml(user.name)} · ${escapeHtml(user.organization || '')}</p>
          <button type="button" class="hf-btn hf-btn--sm" data-fill-demo data-testid="register-fill-demo">
            <i class="bi bi-magic"></i> Fill demo data
          </button>
        </div>
        <div class="hf-form-grid">
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="registerTeamName">Team name</label>
            <input class="hf-input" id="registerTeamName" name="name" placeholder="San Antonio Vipers U17"
                   data-testid="register-team-name">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="registerShortName">Short name (2–3 letters)</label>
            <input class="hf-input" id="registerShortName" name="shortName" maxlength="3" placeholder="VPR"
                   data-testid="register-short-name">
          </div>
          <div class="hf-field">
            <label class="hf-label" for="registerCity">City</label>
            <input class="hf-input" id="registerCity" name="city" placeholder="Pasig City"
                   data-testid="register-city">
          </div>
          <div class="hf-field hf-field--full">
            <label class="hf-label" for="registerCoach">Coach</label>
            <input class="hf-input" id="registerCoach" name="coach" value="${escapeHtml(defaultCoach)}"
                   data-testid="register-coach">
          </div>
          <div class="hf-field hf-field--full">
            <span class="hf-label">Team color</span>
            <div class="hf-flex hf-wrap hf-gap-2">
              ${COLORS.map(
                (color, index) => `
                  <label class="hf-check">
                    <input type="radio" name="avatarColor" value="${color.value}" ${index === 0 ? 'checked' : ''}
                           data-testid="register-color-${index}">
                    <span class="hf-shield" style="--team-color: ${color.value}" aria-hidden="true"></span>
                    ${escapeHtml(color.label)}
                  </label>
                `
              ).join('')}
            </div>
          </div>
        </div>
      </section>

      <section data-step-panel="1" hidden>
        <div class="hf-between hf-mb-2">
          <div>
            <p class="hf-small hf-muted hf-mb-0">A roster needs 8 to 12 players. Jersey numbers must be unique and every player needs guardian consent.</p>
            <p class="hf-small hf-muted hf-mb-0">Age limit for ${escapeHtml(tournament.division)}: 8 to ${divisionLimit}.</p>
          </div>
          <button type="button" class="hf-btn hf-btn--sm" data-add-player data-testid="register-add-player">
            <i class="bi bi-plus-lg"></i> Add player
          </button>
        </div>
        <div id="rosterList" data-testid="register-roster"></div>
      </section>

      <section data-step-panel="2" hidden>
        <div class="hf-grid hf-grid--2">
          <div class="hf-card hf-card--flat">
            <span class="hf-eyebrow">Eligibility checklist</span>
            <ul class="hf-small hf-muted hf-mt-1">
              <li>8–12 players on the roster</li>
              <li>Unique jersey numbers from 1 to 99</li>
              <li>Guardian consent for every player</li>
              <li>PSA birth certificate reviewed by the organizer</li>
            </ul>
          </div>
          <div class="hf-card hf-card--flat">
            <span class="hf-eyebrow">Entry fee</span>
            <strong class="hf-kpi-value" style="font-size: 34px">${escapeHtml(formatMoney(tournament.fee))}</strong>
            <p class="hf-small hf-muted hf-mb-0">Submit a reference now; the organizer verifies the payment before seeding.</p>
          </div>
        </div>
        <div class="hf-form-grid hf-mt-3">
          <div class="hf-field">
            <label class="hf-label" for="registerMethod">Payment method</label>
            <select class="hf-select" id="registerMethod" name="registerMethod" data-testid="register-payment-method">
              <option value="GCash">GCash</option>
              <option value="Maya">Maya</option>
              <option value="Bank transfer">Bank transfer</option>
              <option value="Cash">Cash</option>
            </select>
          </div>
          <div class="hf-field">
            <label class="hf-label" for="registerRef">Reference</label>
            <input class="hf-input" id="registerRef" name="registerRef" placeholder="GCASH-9283746" data-testid="register-payment-ref">
          </div>
        </div>
      </section>

      <section data-step-panel="3" hidden>
        <div id="registerSummary" data-testid="register-summary"></div>
      </section>

      <div class="hf-between hf-mt-4">
        <button type="button" class="hf-btn" data-back data-testid="register-back" hidden>
          <i class="bi bi-arrow-left"></i> Back
        </button>
        <span></span>
        <div class="hf-btn-row">
          <button type="button" class="hf-btn hf-btn--primary" data-next data-testid="register-next">
            Continue <i class="bi bi-arrow-right"></i>
          </button>
          <button type="submit" class="hf-btn hf-btn--primary" data-submit data-testid="register-submit" hidden>
            <i class="bi bi-send"></i> Submit registration
          </button>
        </div>
      </div>
    </form>
  `;

  const form = root.querySelector('#registerForm');
  const back = root.querySelector('[data-back]');
  const next = root.querySelector('[data-next]');
  const submit = root.querySelector('[data-submit]');
  const rosterList = root.querySelector('#rosterList');
  const summary = root.querySelector('#registerSummary');
  const input = (name) => form.querySelector(`[name="${name}"]`);

  function renderRoster() {
    rosterList.innerHTML = roster
      .map(
        (player, index) => `
          <div class="hf-card hf-card--flat hf-mb-2" data-player-row="${index}" data-testid="register-player-${index}">
            <div class="hf-between hf-mb-1">
              <strong class="hf-small">Player ${index + 1}</strong>
              <button type="button" class="hf-btn hf-btn--sm" data-remove-player="${index}"
                      ${roster.length <= 8 ? 'disabled' : ''} data-testid="register-remove-player-${index}">
                Remove
              </button>
            </div>
            <div class="hf-form-grid">
              <div class="hf-field hf-field--full">
                <label class="hf-label" for="playerName${index}">Player name</label>
                <input class="hf-input" id="playerName${index}" data-player-name value="${escapeHtml(player.name)}"
                       placeholder="Full name" data-testid="register-player-name-${index}">
              </div>
              <div class="hf-field">
                <label class="hf-label" for="playerNumber${index}">Jersey number</label>
                <input class="hf-input" id="playerNumber${index}" data-player-number type="number" min="1" max="99"
                       value="${escapeHtml(String(player.number))}" data-testid="register-player-number-${index}">
              </div>
              <div class="hf-field">
                <label class="hf-label" for="playerPosition${index}">Position</label>
                <select class="hf-select" id="playerPosition${index}" data-player-position
                        data-testid="register-player-position-${index}">
                  ${POSITIONS.map((position) => `<option value="${escapeHtml(position)}" ${position === player.position ? 'selected' : ''}>${escapeHtml(position)}</option>`).join('')}
                </select>
              </div>
              <div class="hf-field">
                <label class="hf-label" for="playerAge${index}">Age</label>
                <input class="hf-input" id="playerAge${index}" data-player-age type="number" min="8" max="${divisionLimit}"
                       value="${escapeHtml(String(player.age))}" data-testid="register-player-age-${index}">
              </div>
              <div class="hf-field">
                <label class="hf-label" for="playerDocument${index}">Documents</label>
                <select class="hf-select" id="playerDocument${index}" data-player-document
                        data-testid="register-player-document-${index}">
                  <option value="verified" ${player.documentStatus === 'verified' ? 'selected' : ''}>Verified</option>
                  <option value="pending" ${player.documentStatus === 'pending' ? 'selected' : ''}>Pending review</option>
                </select>
              </div>
              <div class="hf-field hf-field--full">
                <label class="hf-check">
                  <input type="checkbox" data-player-consent ${player.guardianConsent ? 'checked' : ''}
                         data-testid="register-player-consent-${index}">
                  Guardian consent on file
                </label>
              </div>
            </div>
          </div>
        `
      )
      .join('');
  }

  function syncRoster() {
    rosterList.querySelectorAll('[data-player-row]').forEach((row) => {
      const index = Number(row.dataset.playerRow);
      if (!roster[index]) return;
      roster[index].name = row.querySelector('[data-player-name]').value;
      roster[index].number = row.querySelector('[data-player-number]').value;
      roster[index].position = row.querySelector('[data-player-position]').value;
      roster[index].age = row.querySelector('[data-player-age]').value;
      roster[index].guardianConsent = row.querySelector('[data-player-consent]').checked;
      roster[index].documentStatus = row.querySelector('[data-player-document]').value;
    });
  }

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
    submit.hidden = step !== STEPS.length - 1;
    if (step === STEPS.length - 1) syncSummary();
    if (focus) {
      root.querySelector(`[data-step-panel="${step}"] input, [data-step-panel="${step}"] select`)?.focus();
    }
  }

  function validateTeam() {
    ['name', 'shortName', 'city', 'coach'].forEach((name) => ui.clearFieldError(input(name)));
    let ok = true;
    if (input('name').value.trim().length < 3) {
      ui.setFieldError(input('name'), 'Enter at least 3 characters.');
      ok = false;
    }
    const shortName = input('shortName').value.trim();
    if (shortName.length < 2) {
      ui.setFieldError(input('shortName'), 'Use 2 or 3 letters.');
      ok = false;
    }
    if (input('city').value.trim().length < 2) {
      ui.setFieldError(input('city'), 'Enter a city.');
      ok = false;
    }
    if (input('coach').value.trim().length < 3) {
      ui.setFieldError(input('coach'), 'Enter the coach name.');
      ok = false;
    }
    return ok;
  }

  function validateRoster() {
    syncRoster();
    let ok = true;
    let firstInvalid = null;
    const numbers = new Set();

    roster.forEach((player, index) => {
      const row = rosterList.querySelector(`[data-player-row="${index}"]`);
      const nameInput = row.querySelector('[data-player-name]');
      const numberInput = row.querySelector('[data-player-number]');
      const ageInput = row.querySelector('[data-player-age]');
      const consentInput = row.querySelector('[data-player-consent]');
      [nameInput, numberInput, ageInput, consentInput].forEach((field) => ui.clearFieldError(field));

      if (player.name.trim().length < 2) {
        ui.setFieldError(nameInput, 'Enter the player name.');
        ok = false;
        firstInvalid ||= nameInput;
      }
      const number = Number(player.number);
      if (!Number.isInteger(number) || number < 1 || number > 99) {
        ui.setFieldError(numberInput, 'Use 1–99.');
        ok = false;
        firstInvalid ||= numberInput;
      } else if (numbers.has(number)) {
        ui.setFieldError(numberInput, `Jersey ${number} is already taken.`);
        ok = false;
        firstInvalid ||= numberInput;
      } else {
        numbers.add(number);
      }
      const age = Number(player.age);
      if (!Number.isInteger(age) || age < 8 || age > divisionLimit) {
        ui.setFieldError(ageInput, `Age must be 8–${divisionLimit}.`);
        ok = false;
        firstInvalid ||= ageInput;
      }
      if (!player.guardianConsent) {
        ui.setFieldError(consentInput, 'Guardian consent is required.');
        ok = false;
        firstInvalid ||= consentInput;
      }
    });

    if (roster.length < 8 || roster.length > 12) {
      ui.toast('A roster needs 8 to 12 players.', { tone: 'warning', title: 'Roster size' });
      ok = false;
    }
    if (!ok) {
      showStep(1, { focus: false });
      firstInvalid?.focus();
    }
    return ok;
  }

  function validatePayment() {
    const ref = input('registerRef');
    ui.clearFieldError(ref);
    if (!/^[A-Za-z0-9-]{6,24}$/.test(ref.value.trim())) {
      ui.setFieldError(ref, 'Use 6–24 letters, numbers, or dashes.');
      ref.focus();
      return false;
    }
    return true;
  }

  function syncSummary() {
    syncRoster();
    const players = roster.filter((player) => player.name.trim());
    summary.innerHTML = `
      <div class="hf-grid hf-grid--3 hf-mb-3">
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Team</span>
          <strong class="hf-section-title hf-mt-1">${escapeHtml(input('name').value || 'Untitled')}</strong>
          <p class="hf-small hf-muted hf-mb-0">${escapeHtml(input('city').value)} · ${escapeHtml(input('coach').value)}</p>
        </div>
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Roster</span>
          <strong class="hf-kpi-value" style="font-size: 34px">${players.length}</strong>
          <p class="hf-small hf-muted hf-mb-0">players · ${roster.filter((player) => player.guardianConsent).length} with consent</p>
        </div>
        <div class="hf-card hf-card--flat">
          <span class="hf-kpi-label">Payment</span>
          <strong class="hf-section-title hf-mt-1">${escapeHtml(input('registerMethod').value)}</strong>
          <p class="hf-small hf-muted hf-mb-0">${escapeHtml(input('registerRef').value || 'No reference')} · ${escapeHtml(formatMoney(tournament.fee))}</p>
        </div>
      </div>
      <div class="hf-card hf-card--flat">
        <div class="hf-table-wrap">
          <table class="hf-table">
            <thead><tr><th scope="col">#</th><th scope="col">Player</th><th scope="col">Position</th><th scope="col">Age</th><th scope="col">Documents</th></tr></thead>
            <tbody>
              ${players.map((player) => `
                <tr>
                  <td>${escapeHtml(String(player.number))}</td>
                  <td><strong>${escapeHtml(player.name)}</strong></td>
                  <td>${escapeHtml(player.position)}</td>
                  <td>${escapeHtml(String(player.age))}</td>
                  <td>${ui.statusChip(player.documentStatus)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
        <p class="hf-small hf-muted hf-mt-2 hf-mb-0">Submitting creates a pending team. The organizer reviews eligibility before seeding.</p>
      </div>
    `;
  }

  function handleError(error) {
    const field = error.field || '';
    if (field.startsWith('players')) {
      showStep(1, { focus: false });
      const match = field.match(/^players\.(\d+)(?:\.(.+))?/);
      const row = rosterList.querySelector(`[data-player-row="${match ? match[1] : 0}"]`);
      const suffixMap = {
        name: 'name',
        number: 'number',
        position: 'position',
        age: 'age',
        guardianConsent: 'consent',
        documentStatus: 'document',
      };
      const suffix = suffixMap[match?.[2]] || 'name';
      const target =
        row?.querySelector(`[data-player-${suffix}]`) ||
        row?.querySelector('[data-player-name]');
      if (target) {
        ui.setFieldError(target, error.message);
        target.focus();
      }
    } else if (field.startsWith('payment')) {
      showStep(2);
      const target = field === 'payment.method' ? input('registerMethod') : input('registerRef');
      ui.setFieldError(target, error.message);
    } else {
      showStep(0);
      const target = input(field);
      if (target) {
        ui.setFieldError(target, error.message);
        target.focus();
      }
    }
    ui.toast(error.message, { tone: 'danger', title: 'Could not submit registration' });
  }

  root.querySelector('[data-add-player]').addEventListener('click', () => {
    syncRoster();
    if (roster.length >= 12) {
      ui.toast('A roster can have at most 12 players.', { tone: 'warning', title: 'Roster full' });
      return;
    }
    roster.push({
      name: '',
      number: '',
      position: POSITIONS[0],
      age: '',
      guardianConsent: false,
      documentStatus: 'pending',
    });
    renderRoster();
    rosterList.lastElementChild?.querySelector('[data-player-name]')?.focus();
  });

  rosterList.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-player]');
    if (!button) return;
    syncRoster();
    if (roster.length <= 8) {
      ui.toast('Keep at least 8 players on the roster.', { tone: 'warning', title: 'Roster minimum' });
      return;
    }
    roster.splice(Number(button.dataset.removePlayer), 1);
    renderRoster();
  });

  root.querySelector('[data-fill-demo]').addEventListener('click', () => {
    input('name').value = 'San Antonio Vipers U17';
    input('shortName').value = 'V17';
    input('city').value = 'Pasig City';
    input('coach').value = defaultCoach;
    roster.splice(
      0,
      roster.length,
      ...DEMO_PLAYERS.map(([name, number, age], index) => ({
        name,
        number,
        position: POSITIONS[index % POSITIONS.length],
        age,
        guardianConsent: true,
        documentStatus: 'verified',
      }))
    );
    renderRoster();
    input('registerRef').value = 'GCASH-9090909';
    ui.toast('Demo team, roster, and payment reference filled in. Review before submitting.', {
      tone: 'info',
      title: 'Demo data loaded',
    });
  });

  back.addEventListener('click', () => showStep(step - 1));

  next.addEventListener('click', () => {
    if (step === 0 && !validateTeam()) return;
    if (step === 1 && !validateRoster()) return;
    if (step === 2 && !validatePayment()) return;
    showStep(step + 1);
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const teamOk = validateTeam();
    const rosterOk = validateRoster();
    const paymentOk = validatePayment();
    if (!teamOk) {
      showStep(0);
      return;
    }
    if (!rosterOk) return;
    if (!paymentOk) {
      showStep(2);
      return;
    }

    syncRoster();
    const payload = {
      name: input('name').value.trim(),
      shortName: input('shortName').value.trim().toUpperCase(),
      city: input('city').value.trim(),
      coach: input('coach').value.trim(),
      avatarColor: form.querySelector('[name="avatarColor"]:checked')?.value || '#ea580c',
      players: roster.map((player) => ({
        name: player.name.trim(),
        number: Number(player.number),
        position: player.position,
        age: Number(player.age),
        guardianConsent: Boolean(player.guardianConsent),
        documentStatus: player.documentStatus,
      })),
      payment: {
        method: input('registerMethod').value,
        ref: input('registerRef').value.trim(),
      },
    };

    ui.setBusy(submit, true, 'Submitting...');
    try {
      const teamId = store.actions.registerTeam(tournamentId, payload);
      ui.toast('Registration submitted. The organizer will review the roster and payment.', {
        tone: 'success',
        title: 'Team submitted',
      });
      window.setTimeout(() => {
        window.location.href = route('myTeam', { team: teamId });
      }, 350);
    } catch (error) {
      ui.setBusy(submit, false);
      handleError(error);
    }
  });

  renderRoster();
  showStep(0, { focus: false });
}