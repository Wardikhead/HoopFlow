import { route } from '../routes.js';
import { escapeHtml, formatDate, formatDay, formatTime } from '../ui.js';

function keyOf(value) {
  return value ? String(value).slice(0, 10) : '';
}

function parseKey(key) {
  return new Date(`${key}T00:00:00Z`);
}

function isoKey(date) {
  return date.toISOString().slice(0, 10);
}

function addDays(key, days) {
  const date = parseKey(key);
  date.setUTCDate(date.getUTCDate() + days);
  return isoKey(date);
}

function startOfMonth(key) {
  return `${key.slice(0, 7)}-01`;
}

function monthLabel(key) {
  return new Intl.DateTimeFormat('en-PH', {
    timeZone: 'UTC',
    month: 'long',
    year: 'numeric',
  }).format(parseKey(startOfMonth(key)));
}

function weekdayShort(key) {
  return new Intl.DateTimeFormat('en-PH', { timeZone: 'UTC', weekday: 'short' }).format(
    parseKey(key)
  );
}

function dayNumber(key) {
  return parseKey(key).getUTCDate();
}

function monthCells(anchor) {
  const first = parseKey(startOfMonth(anchor));
  const start = addDays(isoKey(first), -first.getUTCDay());
  return Array.from({ length: 42 }, (_, index) => addDays(start, index));
}

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const tournamentId = params.get('id') || 'tournament-1';
  const tournament = store.select.tournament(tournamentId);

  if (!tournament) {
    root.innerHTML = ui.emptyState({
      icon: 'calendar3',
      title: 'Tournament not found',
      message: 'The schedule link is invalid or the demo has been reset.',
      action: `<a class="hf-btn hf-btn--primary" href="${route('tournaments')}">Back to tournaments</a>`,
    });
    return;
  }

  let allMatches = store.select.matches(tournamentId);
  let scheduled = allMatches.filter((match) => match.scheduledAt && match.courtId);
  let matchDays = [...new Set(scheduled.map((match) => keyOf(match.scheduledAt)))].sort();
  let selected = params.get('date') || matchDays[0] || tournament.startDate;
  let month = startOfMonth(selected);
  const courtFilter = new Set(tournament.courts.map((court) => court.id));

  function visibleMatches() {
    return scheduled.filter((match) => courtFilter.has(match.courtId));
  }

  function matchesOn(key) {
    return visibleMatches().filter((match) => keyOf(match.scheduledAt) === key);
  }

  function draw() {
    allMatches = store.select.matches(tournamentId);
    scheduled = allMatches.filter((match) => match.scheduledAt && match.courtId);
    matchDays = [...new Set(scheduled.map((match) => keyOf(match.scheduledAt)))].sort();
    const role = store.getRole();
    const bracket = store.select.bracket(tournamentId);
    const kpis = store.select.kpis(tournamentId);
    const unscheduled = allMatches.filter((match) => !match.scheduledAt && !match.isBye);
    const canGenerate =
      role === 'organizer' &&
      bracket.hasBracket &&
      !kpis.matchesPlayed &&
      !allMatches.some((match) => match.courtId);
    const locked = bracket.hasBracket && kpis.matchesPlayed > 0;

    root.innerHTML = `
      ${components.pageHeader({
        eyebrow: `${tournament.division} · ${tournament.venue}`,
        title: 'Court schedule',
        description: `${formatDate(tournament.startDate)} to ${formatDate(tournament.endDate)} · ${tournament.courts.length} courts · ${tournament.slotMinutes}-minute slots with ${tournament.restMinutes} minutes of rest.`,
        actions: `
          <a class="hf-btn" href="${route('tournamentShow', { id: tournamentId })}" data-testid="schedule-tournament"><i class="bi bi-arrow-left"></i> Tournament</a>
          <a class="hf-btn" href="${route('bracket', { id: tournamentId })}" data-testid="schedule-bracket"><i class="bi bi-diagram-3"></i> Bracket</a>
          <a class="hf-btn" href="${route('matches', { id: tournamentId })}" data-testid="schedule-matches"><i class="bi bi-list-ul"></i> Matchups</a>
          ${role === 'organizer' && allMatches.some((m) => m.courtId) && !kpis.matchesPlayed ? `<button type="button" class="hf-btn" data-regenerate-schedule data-testid="schedule-regenerate">Regenerate schedule</button>` : ''}
          ${role === 'organizer' && allMatches.some((m) => m.courtId) ? `<button type="button" class="hf-btn" data-reschedule-match data-testid="schedule-reschedule">Reschedule match</button>` : ''}
          ${role === 'organizer' && allMatches.some((m) => m.courtId) ? `<button type="button" class="hf-btn" data-manual-manage data-testid="schedule-manual-manage">Manual manage</button>` : ''}
        `,
      })}

      <div class="hf-grid hf-grid--4 hf-mb-3">
        ${components.kpiTile({ label: 'Scheduled', value: scheduled.length, hint: `of ${allMatches.length} bracket games`, tone: 'primary', testid: 'schedule-kpi-scheduled' })}
        ${components.kpiTile({ label: 'Matchdays', value: matchDays.length, hint: 'Days with at least one game', tone: 'gold', testid: 'schedule-kpi-days' })}
        ${components.kpiTile({ label: 'Courts', value: tournament.courts.length, hint: tournament.venue, tone: 'slate', testid: 'schedule-kpi-courts' })}
        ${components.kpiTile({ label: 'Results', value: kpis.matchesPlayed, hint: 'Final and forfeit', tone: 'success', testid: 'schedule-kpi-results' })}
      </div>

      <div class="hf-split">
        <div>
          <div class="hf-card hf-mb-3">
            <div class="hf-between hf-mb-2">
              <button type="button" class="hf-btn hf-btn--sm" data-month-prev data-testid="schedule-month-prev">
                <i class="bi bi-chevron-left"></i> Prev
              </button>
              <strong class="hf-section-title hf-mb-0" data-calendar-label data-testid="schedule-month-label">${escapeHtml(monthLabel(month))}</strong>
              <button type="button" class="hf-btn hf-btn--sm" data-month-next data-testid="schedule-month-next">
                Next <i class="bi bi-chevron-right"></i>
              </button>
            </div>
            <div class="hf-calendar" data-calendar data-testid="schedule-calendar"></div>
            <div class="hf-week-strip" data-week-strip data-testid="schedule-week"></div>
            <div class="hf-between hf-mt-2">
              <button type="button" class="hf-btn hf-btn--sm" data-week-prev data-testid="schedule-week-prev">
                <i class="bi bi-arrow-left"></i> Week
              </button>
              <button type="button" class="hf-btn hf-btn--sm" data-first-day data-testid="schedule-first-day">First matchday</button>
              <button type="button" class="hf-btn hf-btn--sm" data-week-next data-testid="schedule-week-next">
                Week <i class="bi bi-arrow-right"></i>
              </button>
            </div>
          </div>

          <div class="hf-card hf-mb-3">
            <div class="hf-between hf-mb-2">
              <span class="hf-label hf-mb-0">Court filters</span>
              <button type="button" class="hf-section-link" data-all-courts data-testid="schedule-all-courts">All courts</button>
            </div>
            <div class="hf-filter-row" data-court-filters>
              ${tournament.courts
                .map(
                  (court) => `
                    <button type="button" class="hf-chip hf-chip--${courtFilter.has(court.id) ? 'primary' : 'slate'}"
                            data-court-toggle="${escapeHtml(court.id)}" aria-pressed="${courtFilter.has(court.id)}"
                            data-testid="schedule-court-${escapeHtml(court.id)}">
                      <i class="bi bi-geo-alt"></i> ${escapeHtml(court.name)}
                    </button>
                  `
                )
                .join('')}
            </div>
          </div>

          <div id="scheduleList" data-testid="schedule-list"></div>
        </div>

        <aside>
          <div class="hf-card hf-mb-3">
            <span class="hf-kpi-label">Selected day</span>
            <h3 class="hf-section-title hf-mt-1">${escapeHtml(formatDay(selected))}</h3>
            <p class="hf-small hf-muted hf-mb-2" data-day-summary data-testid="schedule-day-summary"></p>
            <a class="hf-btn hf-btn--sm" href="${route('matches', { id: tournamentId, date: selected })}" data-testid="schedule-open-day">Open day list</a>
          </div>

          <div class="hf-card hf-mb-3">
            <span class="hf-kpi-label">Schedule generation</span>
            ${
              !bracket.hasBracket
                ? `<p class="hf-small hf-muted hf-mt-1 hf-mb-0">Generate the bracket before assigning courts.</p>`
                : canGenerate
                  ? `<p class="hf-small hf-muted hf-mt-1">The planner checks court double-bookings and team rest before publishing.</p>
                     <button type="button" class="hf-btn hf-btn--primary" data-generate-schedule data-testid="schedule-generate">Preview schedule</button>`
                  : `<p class="hf-small hf-muted hf-mt-1 hf-mb-0">${locked ? 'Results exist, so the schedule is locked.' : 'The court schedule is already published.'}</p>
                     <a class="hf-btn hf-btn--sm hf-mt-1" href="${route('matches', { id: tournamentId })}" data-testid="schedule-view-all">View all matchups</a>`
            }
          </div>

          <div class="hf-card">
            <span class="hf-kpi-label">Unscheduled</span>
            <h3 class="hf-section-title hf-mt-1">${unscheduled.length} games</h3>
            <p class="hf-small hf-muted hf-mb-0">${unscheduled.length ? 'These bracket games still need a court and time.' : 'Every non-bye game has a court and time.'}</p>
          </div>
        </aside>
      </div>
    `;

    renderCalendar();
    renderWeek();
    renderList();
    const summary = root.querySelector('[data-day-summary]');
    if (summary) {
      const dayMatches = matchesOn(selected);
      const courts = new Set(dayMatches.map((match) => match.courtId)).size;
      summary.textContent = `${dayMatches.length} games · ${courts} courts · first tip-off ${dayMatches.length ? formatTime(dayMatches[0].scheduledAt) : 'TBD'}`;
    }
  }

  function renderCalendar() {
    const calendar = root.querySelector('[data-calendar]');
    if (!calendar) return;
    calendar.innerHTML = `
      ${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => `<div class="hf-calendar-head">${day}</div>`).join('')}
      ${monthCells(month)
        .map((key) => {
          const count = matchesOn(key).length;
          const outside = key.slice(0, 7) !== month.slice(0, 7);
          return `
            <button type="button" class="hf-calendar-cell ${count ? 'has-events' : ''} ${key === selected ? 'is-selected' : ''}"
                    data-day="${key}" data-testid="schedule-day-${key}"
                    style="${outside ? 'opacity: .45' : ''}">
              <span>${dayNumber(key)}</span>
              ${count ? `<span class="hf-calendar-count">${count} games</span>` : ''}
            </button>
          `;
        })
        .join('')}
    `;
  }

  function renderWeek() {
    const strip = root.querySelector('[data-week-strip]');
    if (!strip) return;
    const start = addDays(selected, -3);
    strip.innerHTML = Array.from({ length: 7 }, (_, index) => addDays(start, index))
      .map((key) => {
        const count = matchesOn(key).length;
        return `
          <button type="button" class="${key === selected ? 'is-selected' : ''}" data-day="${key}"
                  data-testid="schedule-week-${key}">
            <small>${weekdayShort(key)}</small>
            <strong>${dayNumber(key)}</strong>
            ${count ? `<span class="hf-calendar-count">${count}</span>` : ''}
          </button>
        `;
      })
      .join('');
  }

  function renderList() {
    const list = root.querySelector('#scheduleList');
    if (!list) return;
    const dayMatches = matchesOn(selected).sort((a, b) =>
      String(a.scheduledAt).localeCompare(String(b.scheduledAt))
    );

    list.innerHTML = `
      ${components.sectionHeader({
        title: dayMatches.length ? `${formatDay(selected)}` : 'No games on this day',
        action: `<span class="hf-chip hf-chip--slate">${dayMatches.length} games</span>`,
      })}
      ${
        dayMatches.length
          ? `<div class="hf-grid hf-grid--2" data-testid="schedule-day-list">${dayMatches
              .map((match) =>
                components.matchCard(match, {
                  href: route('gameStats', { match: match.id }),
                  testid: `schedule-match-${match.id}`,
                })
              )
              .join('')}</div>`
          : ui.emptyState({
              icon: 'calendar3',
              title: 'No games scheduled',
              message: 'Choose another day, re-enable a court filter, or publish a schedule from the tournament desk.',
            })
      }
    `;
  }

  function openPreview(button) {
    let plan;
    try {
      plan = store.planSchedule(tournamentId);
    } catch (error) {
      ui.toast(error.message, { tone: 'danger', title: 'Could not plan schedule' });
      return;
    }

    const rows = plan.assignments
      .map((assignment) => {
        const match = store.select.match(assignment.matchId);
        const court = plan.courts.find((item) => item.id === assignment.courtId);
        const team1 = match.team1Id ? store.select.team(match.team1Id)?.shortName || 'TBD' : 'TBD';
        const team2 = match.team2Id ? store.select.team(match.team2Id)?.shortName || 'TBD' : 'TBD';
        return `
          <tr>
            <td>${escapeHtml(match.roundLabel)}</td>
            <td>${escapeHtml(team1)} vs ${escapeHtml(team2)}</td>
            <td>${escapeHtml(court?.name || '')}</td>
            <td>${escapeHtml(formatDate(assignment.scheduledAt))} · ${escapeHtml(formatTime(assignment.scheduledAt))}</td>
          </tr>
        `;
      })
      .join('');

    ui.dialog({
      title: 'Schedule preview',
      size: 'wide',
      body: `
        <p class="hf-small hf-muted">${plan.assignments.length} games across ${plan.courts.length} courts. No court double-bookings; every team keeps at least ${plan.restMinutes} minutes of rest.</p>
        <div class="hf-table-wrap">
          <table class="hf-table">
            <thead><tr><th scope="col">Round</th><th scope="col">Match</th><th scope="col">Court</th><th scope="col">Slot</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      `,
      footer: `
        <button type="button" class="hf-btn" data-cancel-schedule data-testid="schedule-preview-cancel">Cancel</button>
        <button type="button" class="hf-btn hf-btn--primary" data-publish-schedule data-testid="schedule-preview-publish">Publish schedule</button>
      `,
      onMount(element, close) {
        element.querySelector('[data-cancel-schedule]').addEventListener('click', close);
        element.querySelector('[data-publish-schedule]').addEventListener('click', () => {
          ui.setBusy(button, true, 'Publishing...');
          try {
            const count = store.actions.generateSchedule(tournamentId);
            close();
            const first = plan.assignments[0]?.scheduledAt;
            if (first) {
              selected = keyOf(first);
              month = startOfMonth(selected);
            }
            ui.toast(`${count} games scheduled across ${plan.courts.length} courts.`, {
              tone: 'success',
              title: 'Schedule published',
            });
            draw();
          } catch (error) {
            ui.setBusy(button, false);
            ui.toast(error.message, { tone: 'danger', title: 'Could not publish schedule' });
          }
        });
      },
    });
  }

  root.addEventListener('click', (event) => {
    const dayButton = event.target.closest('[data-day]');
    if (dayButton) {
      selected = dayButton.dataset.day;
      month = startOfMonth(selected);
      draw();
      return;
    }

    if (event.target.closest('[data-month-prev]')) {
      month = startOfMonth(addDays(month, -1));
      selected = month;
      draw();
      return;
    }
    if (event.target.closest('[data-month-next]')) {
      month = startOfMonth(addDays(startOfMonth(month), 32));
      selected = month;
      draw();
      return;
    }
    if (event.target.closest('[data-week-prev]')) {
      selected = addDays(selected, -7);
      month = startOfMonth(selected);
      draw();
      return;
    }
    if (event.target.closest('[data-week-next]')) {
      selected = addDays(selected, 7);
      month = startOfMonth(selected);
      draw();
      return;
    }
    if (event.target.closest('[data-first-day]')) {
      selected = matchDays[0] || tournament.startDate;
      month = startOfMonth(selected);
      draw();
      return;
    }

    const courtButton = event.target.closest('[data-court-toggle]');
    if (courtButton) {
      const id = courtButton.dataset.courtToggle;
      if (courtFilter.has(id)) courtFilter.delete(id);
      else courtFilter.add(id);
      draw();
      return;
    }
    if (event.target.closest('[data-all-courts]')) {
      tournament.courts.forEach((court) => courtFilter.add(court.id));
      draw();
      return;
    }

    const generate = event.target.closest('[data-generate-schedule]');
    if (generate) {
      openPreview(generate);
      return;
    }
    const regenerate = event.target.closest('[data-regenerate-schedule]');
    if (regenerate) {
      ui.confirm({
        title: 'Regenerate schedule?',
        message: 'This wipes the current schedule and rebuilds. Recorded results will be lost.',
        confirmLabel: 'Regenerate',
        tone: 'danger',
      }).then((ok) => {
        if (!ok) return;
        ui.setBusy(regenerate, true, 'Regenerating...');
        try {
          const count = store.actions.generateSchedule(tournamentId);
          ui.setBusy(regenerate, false);
          ui.toast(`${count} games scheduled.`, { tone: 'success', title: 'Schedule regenerated' });
          draw();
        } catch (error) {
          ui.setBusy(regenerate, false);
          ui.toast(error.message, { tone: 'danger', title: 'Could not regenerate' });
        }
      });
      return;
    }
    const reschedule = event.target.closest('[data-reschedule-match]');
    if (reschedule) {
      const scheduledMatches = allMatches.filter((m) => m.courtId && m.scheduledAt);
      if (!scheduledMatches.length) return;
      const options = scheduledMatches
        .map((m) => `<option value="${escapeHtml(m.id)}">${escapeHtml(m.roundLabel)} · ${escapeHtml(m.team1Id || '?')} vs ${escapeHtml(m.team2Id || '?')}</option>`)
        .join('');
      const courtOptions = tournament.courts
        .map((c) => `<option value="${escapeHtml(c.id)}">${escapeHtml(c.name)}</option>`)
        .join('');
      const { element, close } = ui.dialog({
        title: 'Reschedule match',
        size: 'wide',
        body: `
          <div class="hf-form-grid">
            <div class="hf-field hf-field--full">
              <label class="hf-label" for="rescheduleMatch">Match</label>
              <select id="rescheduleMatch" class="hf-select" data-reschedule-match-select>${options}</select>
            </div>
            <div class="hf-field">
              <label class="hf-label" for="rescheduleCourt">Court</label>
              <select id="rescheduleCourt" class="hf-select" data-reschedule-court>${courtOptions}</select>
            </div>
            <div class="hf-field">
              <label class="hf-label" for="rescheduleTime">Date & time</label>
              <input id="rescheduleTime" class="hf-input" type="datetime-local" data-reschedule-time>
            </div>
          </div>
          <p class="hf-field-error hf-mt-1" data-reschedule-error hidden></p>
        `,
        footer: `
          <button type="button" class="hf-btn" data-reschedule-cancel>Cancel</button>
          <button type="button" class="hf-btn hf-btn--primary" data-reschedule-save>Save</button>
        `,
      });
      const matchSelect = element.querySelector('[data-reschedule-match-select]');
      const courtSelect = element.querySelector('[data-reschedule-court]');
      const timeInput = element.querySelector('[data-reschedule-time]');
      const errorEl = element.querySelector('[data-reschedule-error]');
      const syncFromMatch = () => {
        const m = scheduledMatches.find((x) => x.id === matchSelect.value);
        if (m) {
          courtSelect.value = m.courtId || tournament.courts[0]?.id || '';
          if (m.scheduledAt) {
            const d = new Date(m.scheduledAt);
            const pad = (n) => String(n).padStart(2, '0');
            timeInput.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
          }
        }
      };
      matchSelect.addEventListener('change', syncFromMatch);
      syncFromMatch();
      element.querySelector('[data-reschedule-cancel]')?.addEventListener('click', close);
      element.querySelector('[data-reschedule-save]')?.addEventListener('click', () => {
        const matchId = matchSelect.value;
        const courtId = courtSelect.value;
        const local = timeInput.value;
        const scheduledAt = local ? `${local}:00+08:00` : null;
        try {
          store.actions.rescheduleMatch(matchId, { courtId, scheduledAt });
          ui.toast('Match rescheduled.', { tone: 'success', title: 'Schedule updated' });
          close();
          draw();
        } catch (error) {
          if (errorEl) { errorEl.hidden = false; errorEl.textContent = error.message; }
          ui.toast(error.message, { tone: 'danger', title: 'Could not reschedule' });
        }
      });
    }
    const manual = event.target.closest('[data-manual-manage]');
    if (manual) {
      ui.toast('Manual manage mode: edit courts/times per match (coming soon).', { tone: 'info', title: 'Manual manage' });
    }
  });

  draw();
}