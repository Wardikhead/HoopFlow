import { route } from '../routes.js';
import { escapeHtml } from '../ui.js';
import { ANNOUNCEMENT_CATEGORIES } from '../options.js';
import { AUDIENCE_BY_ROLE } from '../store.js';

export function render({ store, ui, components, root }) {
  const params = new URLSearchParams(window.location.search);
  const role = store.getRole();
  const tournaments = store.select.tournaments();
  const allowedAudiences = AUDIENCE_BY_ROLE[role] || ['all'];
  let tournamentFilter = params.get('id') || 'all';
  let categoryFilter = params.get('category') || 'all';

  root.innerHTML = `
    ${components.pageHeader({
      eyebrow: 'Matchday desk · Bulletin',
      title: 'Announcements',
      description:
        role === 'fan'
          ? 'Official venue, schedule, and eligibility notices from the tournament desk.'
          : 'Venue, schedule, and eligibility bulletins, filtered to what this role can see.',
      actions:
        role === 'organizer'
          ? `<a class="hf-btn hf-btn--primary" href="${route('announcementCreate', tournamentFilter === 'all' ? {} : { id: tournamentFilter })}" data-testid="announcement-new"><i class="bi bi-megaphone"></i> New announcement</a>`
          : '',
    })}

    <div id="announcementKpis" class="hf-grid hf-grid--4 hf-mb-3"></div>

    <div class="hf-card hf-mb-3">
      <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="announcementSearch">Search</label>
          <input class="hf-input" id="announcementSearch" type="search"
                 placeholder="Headline, message, or author"
                 value="${escapeHtml(params.get('q') || '')}" data-testid="announcement-search">
        </div>
        <div class="hf-field hf-field--full">
          <label class="hf-label" for="announcementTournament">Tournament</label>
          <select class="hf-select" id="announcementTournament" data-testid="announcement-tournament-filter">
            <option value="all">All tournaments</option>
            ${tournaments.map((tournament) => `<option value="${escapeHtml(tournament.id)}">${escapeHtml(tournament.name)}</option>`).join('')}
          </select>
        </div>
      </div>
      <div class="hf-flex hf-wrap hf-items-center hf-gap-2 hf-mt-2">
        <span class="hf-label">Category</span>
        <div class="hf-filter-row hf-mb-0" data-category-filters>
          ${[{ value: 'all', label: 'All' }, ...ANNOUNCEMENT_CATEGORIES]
            .map(
              (option) => `
                <button type="button" class="hf-filter ${categoryFilter === option.value ? 'is-active' : ''}"
                        data-category="${escapeHtml(option.value)}" aria-pressed="${categoryFilter === option.value}"
                        data-testid="announcement-category-${escapeHtml(option.value)}">
                  ${escapeHtml(option.label)}
                </button>
              `
            )
            .join('')}
        </div>
      </div>
    </div>

    <div id="announcementFeed" data-testid="announcement-feed"></div>
  `;

  const search = root.querySelector('#announcementSearch');
  const tournamentSelect = root.querySelector('#announcementTournament');
  const feed = root.querySelector('#announcementFeed');
  const kpis = root.querySelector('#announcementKpis');

  tournamentSelect.value = tournamentFilter;

  function visibleItems() {
    const term = search.value.trim().toLowerCase();
    return store
      .select.announcements(tournamentFilter === 'all' ? undefined : tournamentFilter)
      .filter((item) => allowedAudiences.includes(item.audience))
      .filter((item) => categoryFilter === 'all' || item.category === categoryFilter)
      .filter((item) => {
        if (!term) return true;
        return `${item.title} ${item.body} ${item.author}`.toLowerCase().includes(term);
      });
  }

  function renderKpis() {
    const items = visibleItems();
    const pinned = items.filter((item) => item.pinned).length;
    const tournamentCount = new Set(items.map((item) => item.tournamentId)).size;
    const categoryCount = new Set(items.map((item) => item.category)).size;
    kpis.innerHTML = [
      components.kpiTile({ label: 'Visible notices', value: items.length, hint: 'For this role and filter', tone: 'primary', testid: 'announcement-kpi-visible' }),
      components.kpiTile({ label: 'Pinned', value: pinned, hint: 'Lead the feed', tone: 'gold', testid: 'announcement-kpi-pinned' }),
      components.kpiTile({ label: 'Tournaments', value: tournamentCount, hint: 'With visible notices', tone: 'slate', testid: 'announcement-kpi-tournaments' }),
      components.kpiTile({ label: 'Categories', value: categoryCount, hint: 'Schedule, venue, eligibility', tone: 'success', testid: 'announcement-kpi-categories' }),
    ].join('');
  }

  function renderFeed() {
    renderKpis();
    const items = visibleItems();
    if (!items.length) {
      feed.innerHTML = ui.emptyState({
        icon: 'megaphone',
        title: 'No announcements match',
        message: 'Try another tournament, category, or search term.',
        action:
          role === 'organizer'
            ? `<a class="hf-btn hf-btn--primary" href="${route('announcementCreate', tournamentFilter === 'all' ? {} : { id: tournamentFilter })}" data-testid="announcement-empty-create">Write an announcement</a>`
            : `<button type="button" class="hf-btn" data-clear-announcement-filters data-testid="announcement-clear-filters">Clear filters</button>`,
      });
    } else {
      const pinned = items.filter((item) => item.pinned);
      const rest = items.filter((item) => !item.pinned);
      feed.innerHTML = `
        ${
          pinned.length
            ? `${components.sectionHeader({ title: 'Pinned' })}
               <div class="hf-grid hf-grid--2 hf-mb-3">${pinned.map((item) => components.announcementCard(item)).join('')}</div>`
            : ''
        }
        ${
          rest.length
            ? `${components.sectionHeader({ title: pinned.length ? 'Latest updates' : 'All announcements' })}
               <div class="hf-grid hf-grid--2">${rest.map((item) => components.announcementCard(item)).join('')}</div>`
            : ''
        }
      `;
    }

    feed.querySelector('[data-clear-announcement-filters]')?.addEventListener('click', () => {
      search.value = '';
      tournamentFilter = 'all';
      categoryFilter = 'all';
      tournamentSelect.value = 'all';
      paintCategories();
      renderFeed();
    });
  }

  function paintCategories() {
    root.querySelectorAll('[data-category]').forEach((button) => {
      const active = button.dataset.category === categoryFilter;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
    });
  }

  function markSeen() {
    const state = store.getState();
    const latest = state.announcements.reduce(
      (max, item) => Math.max(max, new Date(item.createdAt).getTime()),
      0
    );
    const seen = new Date(state.meta?.lastSeenAnnouncementsAt || 0).getTime();
    if (latest > seen) {
      try {
        store.actions.markAnnouncementsSeen();
      } catch {
        // Clearing the notification dot is non-blocking.
      }
    }
  }

  search.addEventListener('input', ui.debounce(renderFeed, 120));
  tournamentSelect.addEventListener('change', () => {
    tournamentFilter = tournamentSelect.value;
    renderFeed();
  });
  root.querySelectorAll('[data-category]').forEach((button) => {
    button.addEventListener('click', () => {
      categoryFilter = button.dataset.category;
      paintCategories();
      renderFeed();
    });
  });

  paintCategories();
  renderFeed();
  markSeen();
}