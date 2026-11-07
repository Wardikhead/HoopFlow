import { route } from '../routes.js';
import { escapeHtml } from '../ui.js';
import { guardRole } from '../guards.js';
import { ANNOUNCEMENT_AUDIENCES, ANNOUNCEMENT_CATEGORIES } from '../options.js';

function labelOf(options, value) {
  return options.find((option) => option.value === value)?.label || value;
}

export function render({ store, ui, components, root }) {
  if (!guardRole({ store, ui, root, roles: ['organizer'] })) return;

  const params = new URLSearchParams(window.location.search);
  const tournaments = store.select.tournaments();
  if (!tournaments.length) {
    root.innerHTML = ui.emptyState({
      icon: 'megaphone',
      title: 'No tournaments yet',
      message: 'Create a tournament before publishing a bulletin.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournamentCreate')}">Create tournament</a>`,
    });
    return;
  }

  const defaultTournament =
    params.get('id') ||
    params.get('tournament') ||
    tournaments.find((tournament) => tournament.status === 'active')?.id ||
    tournaments[0].id;
  const user = store.getCurrentUser();
  const author = user.organization || user.name;

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Matchday desk · Bulletin',
      title: 'New announcement',
      description: 'Publish a venue, schedule, or eligibility notice to the right audience.',
      actions: `<a class="hf-btn" href="${route('announcements')}" data-testid="announcement-composer-back"><i class="bi bi-arrow-left"></i> Announcements</a>`,
    })}

    <div class="hf-split">
      <form id="announcementForm" novalidate>
        <div class="hf-card hf-mb-3">
          <div class="hf-form-grid">
            <div class="hf-field hf-field--full">
              <label class="hf-label" for="announcementTournament">Tournament</label>
              <select class="hf-select" id="announcementTournament" data-testid="announcement-tournament">
                ${tournaments
                  .map(
                    (tournament) => `
                      <option value="${escapeHtml(tournament.id)}" ${tournament.id === defaultTournament ? 'selected' : ''}>
                        ${escapeHtml(tournament.name)} · ${escapeHtml(tournament.division)}
                      </option>
                    `
                  )
                  .join('')}
              </select>
            </div>
            <div class="hf-field hf-field--full">
              <label class="hf-label" for="announcementTitle">Headline</label>
              <input class="hf-input" id="announcementTitle" name="title" maxlength="90"
                     placeholder="Championship week moves to Court 1" data-testid="announcement-title">
              <span class="hf-help">Keep it short enough for the top of the feed.</span>
            </div>
            <div class="hf-field hf-field--full">
              <label class="hf-label" for="announcementBody">Message</label>
              <textarea class="hf-textarea" id="announcementBody" name="body" maxlength="320" rows="5"
                        placeholder="Gate opens 45 minutes before tip-off. All semifinals and finals are on Court 1."
                        data-testid="announcement-body"></textarea>
              <span class="hf-help">Up to 320 characters. Coaches and fans see plain text.</span>
            </div>
            <div class="hf-field">
              <label class="hf-label" for="announcementCategory">Category</label>
              <select class="hf-select" id="announcementCategory" data-testid="announcement-category">
                ${ANNOUNCEMENT_CATEGORIES.map(
                  (option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`
                ).join('')}
              </select>
            </div>
            <div class="hf-field">
              <label class="hf-label" for="announcementAudience">Audience</label>
              <select class="hf-select" id="announcementAudience" data-testid="announcement-audience">
                ${ANNOUNCEMENT_AUDIENCES.map(
                  (option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`
                ).join('')}
              </select>
            </div>
            <div class="hf-field hf-field--full" id="announcementTargetUsers" hidden data-testid="announcement-target-users">
              <label class="hf-label">Target users</label>
              <div class="hf-card hf-card--flat" style="max-height: 180px; overflow: auto;">
                ${store
                  .getState()
                  .users.map(
                    (user) => `
                      <label class="hf-flex hf-items-center hf-gap-2 hf-small" style="padding: 4px 0;">
                        <input type="checkbox" value="${escapeHtml(user.id)}" data-target-user>
                        <span>${escapeHtml(user.name)} <span class="hf-muted">(${escapeHtml(user.role)})</span></span>
                      </label>
                    `
                  )
                  .join('')}
              </div>
            </div>
            <label class="hf-check hf-field--full">
              <input type="checkbox" id="announcementPinned" data-testid="announcement-pinned">
              <span>
                <strong>Pin to the top</strong>
                <span class="hf-help" style="display:block">Pinned notices lead the feed and the dashboard.</span>
              </span>
            </label>
          </div>
        </div>

        <div class="hf-btn-row">
          <a class="hf-btn" href="${route('announcements')}" data-testid="announcement-cancel">Cancel</a>
          <button type="submit" class="hf-btn hf-btn--primary" data-testid="announcement-publish">
            <i class="bi bi-send-fill"></i> Publish announcement
          </button>
        </div>
      </form>

      <aside>
        <div class="hf-card hf-mb-3">
          <span class="hf-kpi-label">Preview</span>
          <article class="hf-card hf-card--flat hf-mt-1" data-preview data-testid="announcement-preview"></article>
        </div>
        <div class="hf-card">
          <span class="hf-kpi-label">Before publishing</span>
          <ul class="hf-small hf-muted hf-mt-1 hf-mb-0">
            <li>Choose the tournament the notice belongs to.</li>
            <li>Coaches see “Coaches” notices; fans only see “Everyone”.</li>
            <li>Pinned notices lead the feed and the dashboard.</li>
          </ul>
        </div>
      </aside>
    </div>
  `;

  const form = root.querySelector('#announcementForm');
  const tournamentSelect = root.querySelector('#announcementTournament');
  const title = root.querySelector('#announcementTitle');
  const body = root.querySelector('#announcementBody');
  const categorySelect = root.querySelector('#announcementCategory');
  const audienceSelect = root.querySelector('#announcementAudience');
  const pinned = root.querySelector('#announcementPinned');
  const preview = root.querySelector('[data-preview]');
  const publish = root.querySelector('[data-testid="announcement-publish"]');

  function paintPreview() {
    const selected = tournaments.find((tournament) => tournament.id === tournamentSelect.value);
    preview.classList.toggle('hf-announcement--pinned', pinned.checked);
    preview.innerHTML = `
      <div class="hf-between">
        <span class="hf-chip hf-chip--slate">${escapeHtml(labelOf(ANNOUNCEMENT_CATEGORIES, categorySelect.value))}</span>
        ${pinned.checked ? '<span class="hf-chip hf-chip--gold"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i>Pinned</span>' : ''}
      </div>
      <h3 class="hf-section-title hf-mt-1">${escapeHtml(title.value.trim() || 'Your headline')}</h3>
      <p class="hf-small hf-muted hf-mb-0">${escapeHtml(body.value.trim() || 'The message coaches and fans will see.')}</p>
      <div class="hf-match-head hf-mt-2">
        <span>${escapeHtml(author)}</span>
        <span>Just now</span>
      </div>
      <div class="hf-match-head">
        <span>Audience: ${escapeHtml(labelOf(ANNOUNCEMENT_AUDIENCES, audienceSelect.value))}</span>
        <span>${escapeHtml(selected?.name || 'No tournament')}</span>
      </div>
    `;
  }

  function validate() {
    let valid = true;
    if (!tournamentSelect.value) {
      ui.setFieldError(tournamentSelect, 'Choose a tournament.');
      valid = false;
    } else {
      ui.clearFieldError(tournamentSelect);
    }
    if (title.value.trim().length < 4) {
      ui.setFieldError(title, 'Use at least 4 characters.');
      valid = false;
    } else {
      ui.clearFieldError(title);
    }
    if (body.value.trim().length < 10) {
      ui.setFieldError(body, 'Write at least 10 characters.');
      valid = false;
    } else {
      ui.clearFieldError(body);
    }
    return valid;
  }

  const targetUsersSection = root.querySelector('#announcementTargetUsers');
  function updateTargetUsersVisibility() {
    if (!targetUsersSection) return;
    targetUsersSection.hidden = audienceSelect.value !== 'specific';
  }
  audienceSelect.addEventListener('change', updateTargetUsersVisibility);
  updateTargetUsersVisibility();

  form.addEventListener('input', (event) => {
    if (event.target.matches('input, textarea, select')) ui.clearFieldError(event.target);
    paintPreview();
  });
  form.addEventListener('change', paintPreview);

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!validate()) return;

    const payload = {
      tournamentId: tournamentSelect.value,
      title: title.value.trim(),
      body: body.value.trim(),
      category: categorySelect.value,
      audience: audienceSelect.value,
      targetUserIds:
        audienceSelect.value === 'specific'
          ? [...root.querySelectorAll('[data-target-user]:checked')].map((cb) => cb.value)
          : [],
      pinned: pinned.checked,
    };

    ui.setBusy(publish, true, 'Publishing...');
    window.setTimeout(() => {
      try {
        store.actions.publishAnnouncement(payload);
        ui.toast('The bulletin is live for its audience.', {
          tone: 'success',
          title: 'Announcement published',
        });
        window.setTimeout(() => {
          window.location.href = route('announcements', { id: payload.tournamentId });
        }, 300);
      } catch (error) {
        ui.setBusy(publish, false);
        const fields = {
          tournamentId: tournamentSelect,
          title,
          body,
          category: categorySelect,
          audience: audienceSelect,
        };
        if (error.field && fields[error.field]) {
          ui.setFieldError(fields[error.field], error.message);
          fields[error.field].focus();
        }
        ui.toast(error.message, { tone: 'danger', title: 'Could not publish' });
      }
    }, 350);
  });

  paintPreview();
}