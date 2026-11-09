<div
    x-data="{
        toasts: [],
        pushToast(detail) {
            const toast = {
                id: Date.now() + Math.random(),
                title: (detail && detail.title) || '',
                message: (detail && detail.message) || '',
                tone: (detail && detail.tone) || 'primary'
            };
            this.toasts.push(toast);
            setTimeout(() => this.dismissToast(toast.id), 4200);
        },
        dismissToast(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
        toastIcon(tone) {
            return tone === 'danger' ? 'x-circle-fill' : tone === 'warning' ? 'exclamation-triangle-fill' : tone === 'success' ? 'check-circle-fill' : 'info-circle-fill';
        }
    }"
    x-on:hf-toast.window="pushToast($event.detail)"
    @if ($flashToast)
        x-init="pushToast(@js($flashToast))"
    @endif
>
    @php
        $announcements = $this->announcements;
        $pinned = $announcements->filter(fn ($announcement) => $announcement->pinned);
        $rest = $announcements->reject(fn ($announcement) => $announcement->pinned);
        $createUrl = route('announcementCreate', $tournament !== 'all' ? ['id' => $tournament] : []);
    @endphp

    @section('title', 'Announcements')

    <header class="hf-page-head hf-mb-3">
        <div>
            <span class="hf-eyebrow">Matchday desk · Bulletin</span>
            <h1 class="hf-page-title">Announcements</h1>
            <p class="hf-page-desc">
                @if (auth()->user()?->hasRole('fan'))
                    Official venue, schedule, and eligibility notices from the tournament desk.
                @else
                    Venue, schedule, and eligibility bulletins, filtered to what this role can see.
                @endif
            </p>
        </div>
        @if (auth()->user()?->hasRole('organizer'))
            <div class="hf-page-actions">
                <a class="hf-btn hf-btn--primary" href="{{ $createUrl }}" data-testid="announcement-new">
                    <i class="bi bi-megaphone"></i> New announcement
                </a>
            </div>
        @endif
    </header>

    <div id="announcementKpis" class="hf-grid hf-grid--4 hf-mb-3">
        @include('components.kpi-tile', [
            'label' => 'Visible notices',
            'value' => $this->kpis['visible'],
            'hint' => 'For this role and filter',
            'tone' => 'primary',
            'testid' => 'announcement-kpi-visible',
        ])
        @include('components.kpi-tile', [
            'label' => 'Pinned',
            'value' => $this->kpis['pinned'],
            'hint' => 'Lead the feed',
            'tone' => 'gold',
            'testid' => 'announcement-kpi-pinned',
        ])
        @include('components.kpi-tile', [
            'label' => 'Tournaments',
            'value' => $this->kpis['tournaments'],
            'hint' => 'With visible notices',
            'tone' => 'slate',
            'testid' => 'announcement-kpi-tournaments',
        ])
        @include('components.kpi-tile', [
            'label' => 'Categories',
            'value' => $this->kpis['categories'],
            'hint' => 'Schedule, venue, eligibility',
            'tone' => 'success',
            'testid' => 'announcement-kpi-categories',
        ])
    </div>

    <div class="hf-card hf-mb-3" data-testid="announcement-filters">
        <div class="hf-form-grid">
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="announcementSearch">Search</label>
                <input class="hf-input" id="announcementSearch" type="search"
                       placeholder="Headline, message, or author"
                       wire:model.live.debounce.150ms="search"
                       data-testid="announcement-search">
            </div>
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="announcementTournament">Tournament</label>
                <select class="hf-select" id="announcementTournament" wire:model.live="tournament" data-testid="announcement-tournament-filter">
                    <option value="all">All tournaments</option>
                    @foreach ($this->tournaments as $tournamentOption)
                        <option value="{{ $tournamentOption->id }}">{{ $tournamentOption->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="hf-flex hf-wrap hf-items-center hf-gap-2 hf-mt-2">
            <span class="hf-label">Category</span>
            <div class="hf-filter-row hf-mb-0" data-category-filters>
                @foreach ($this->categories as $value => $label)
                    <button type="button"
                            class="hf-filter {{ $category === $value ? 'is-active' : '' }}"
                            wire:click="$set('category', '{{ $value }}')"
                            data-category="{{ $value }}"
                            aria-pressed="{{ $category === $value ? 'true' : 'false' }}"
                            data-testid="announcement-category-{{ $value }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div id="announcementFeed" data-testid="announcement-feed">
        @if ($announcements->isEmpty())
            <div class="hf-empty" data-testid="announcement-empty">
                <i class="bi bi-megaphone" aria-hidden="true"></i>
                <h3>No announcements match</h3>
                <p>Try another tournament, category, or search term.</p>
                @if (auth()->user()?->hasRole('organizer'))
                    <a class="hf-btn hf-btn--primary" href="{{ $createUrl }}" data-testid="announcement-empty-create">Write an announcement</a>
                @else
                    <button type="button" class="hf-btn" wire:click="clearFilters" data-testid="announcement-clear-filters">Clear filters</button>
                @endif
            </div>
        @else
            @if ($pinned->isNotEmpty())
                <div class="hf-section-head">
                    <h2 class="hf-section-title">Pinned</h2>
                </div>
                <div class="hf-grid hf-grid--2 hf-mb-3">
                    @foreach ($pinned as $announcement)
                        <article class="hf-card hf-card--flat hf-announcement--pinned"
                                 style="cursor: pointer"
                                 role="button"
                                 tabindex="0"
                                 wire:key="announcement-pinned-{{ $announcement->id }}"
                                 wire:click="openAnnouncement({{ $announcement->id }})"
                                 wire:keydown.enter="openAnnouncement({{ $announcement->id }})"
                                 aria-haspopup="dialog"
                                 data-testid="announcement-{{ $announcement->id }}">
                            <div class="hf-between">
                                <span class="hf-chip hf-chip--slate">{{ \App\Models\Announcement::categoryLabel($announcement->category) }}</span>
                                <span class="hf-chip hf-chip--gold"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i>Pinned</span>
                            </div>
                            <h3 class="hf-section-title hf-mt-1">{{ $announcement->title }}</h3>
                            <p class="hf-muted hf-small hf-mb-0">{{ $announcement->body }}</p>
                            <div class="hf-match-head hf-mt-2">
                                <span>{{ $announcement->createdBy?->name ?? 'Unknown' }}</span>
                                <span>{{ ($announcement->published_at ?? $announcement->created_at)?->format('M d, Y') ?? '' }}</span>
                            </div>
                            <div class="hf-match-head">
                                <span>Audience: {{ \App\Models\Announcement::audienceLabel($announcement->audience) }}</span>
                                <span>{{ $announcement->tournament?->name ?? 'Tournament' }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($rest->isNotEmpty())
                <div class="hf-section-head">
                    <h2 class="hf-section-title">{{ $pinned->isNotEmpty() ? 'Latest updates' : 'All announcements' }}</h2>
                </div>
                <div class="hf-grid hf-grid--2">
                    @foreach ($rest as $announcement)
                        <article class="hf-card hf-card--flat"
                                 style="cursor: pointer"
                                 role="button"
                                 tabindex="0"
                                 wire:key="announcement-{{ $announcement->id }}"
                                 wire:click="openAnnouncement({{ $announcement->id }})"
                                 wire:keydown.enter="openAnnouncement({{ $announcement->id }})"
                                 aria-haspopup="dialog"
                                 data-testid="announcement-{{ $announcement->id }}">
                            <div class="hf-between">
                                <span class="hf-chip hf-chip--slate">{{ \App\Models\Announcement::categoryLabel($announcement->category) }}</span>
                            </div>
                            <h3 class="hf-section-title hf-mt-1">{{ $announcement->title }}</h3>
                            <p class="hf-muted hf-small hf-mb-0">{{ $announcement->body }}</p>
                            <div class="hf-match-head hf-mt-2">
                                <span>{{ $announcement->createdBy?->name ?? 'Unknown' }}</span>
                                <span>{{ ($announcement->published_at ?? $announcement->created_at)?->format('M d, Y') ?? '' }}</span>
                            </div>
                            <div class="hf-match-head">
                                <span>Audience: {{ \App\Models\Announcement::audienceLabel($announcement->audience) }}</span>
                                <span>{{ $announcement->tournament?->name ?? 'Tournament' }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    @php $viewing = $this->viewingAnnouncement; @endphp

    @if ($viewing)
        <dialog class="hf-dialog hf-dialog--wide"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.closeAnnouncement()"
                x-on:click="if ($event.target === $el) $wire.closeAnnouncement()"
                wire:key="announcement-dialog-{{ $viewing->id }}"
                data-testid="announcement-dialog">
            <div class="hf-dialog__head">
                <h2 class="hf-dialog__title">{{ $viewing->title }}</h2>
                <button type="button" class="hf-icon-btn" wire:click="closeAnnouncement()" aria-label="Close dialog" data-testid="dialog-close">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="hf-dialog__body">
                <div class="hf-flex hf-wrap hf-gap-2 hf-mb-2">
                    <span class="hf-chip hf-chip--slate">{{ \App\Models\Announcement::categoryLabel($viewing->category) }}</span>
                    @if ($viewing->pinned)
                        <span class="hf-chip hf-chip--gold"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i>Pinned</span>
                    @endif
                    <span class="hf-chip hf-chip--slate">Audience: {{ \App\Models\Announcement::audienceLabel($viewing->audience) }}</span>
                </div>
                <p class="hf-small hf-muted hf-mb-2">
                    {{ $viewing->createdBy?->name ?? 'Unknown' }}
                    · {{ ($viewing->published_at ?? $viewing->created_at)?->format('M d, Y') ?? '' }}
                    · {{ $viewing->tournament?->name ?? 'Tournament' }}
                </p>
                <p class="hf-mb-0">{{ $viewing->body }}</p>
            </div>
            <div class="hf-dialog__foot">
                @if (auth()->user()?->hasRole('organizer'))
                    <button type="button" class="hf-btn" wire:click="confirmDelete" data-testid="announcement-delete">Delete</button>
                    <button type="button" class="hf-btn" wire:click="togglePin" data-testid="announcement-pin">{{ $viewing->pinned ? 'Unpin' : 'Pin' }}</button>
                @endif
                <button type="button" class="hf-btn hf-btn--primary" wire:click="closeAnnouncement()" data-testid="announcement-close">Close</button>
            </div>
        </dialog>
    @endif

    @if ($confirmingDelete && $viewing)
        <dialog class="hf-dialog"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.cancelDelete()"
                x-on:click="if ($event.target === $el) $wire.cancelDelete()"
                wire:key="announcement-delete-dialog"
                data-testid="confirm-dialog">
            <div class="hf-dialog__head">
                <h2 class="hf-dialog__title">Delete announcement?</h2>
                <button type="button" class="hf-icon-btn" wire:click="cancelDelete()" aria-label="Close dialog" data-testid="dialog-close">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="hf-dialog__body">
                <p class="hf-mb-0">This removes the announcement permanently.</p>
            </div>
            <div class="hf-dialog__foot">
                <button type="button" class="hf-btn" wire:click="cancelDelete()" data-testid="confirm-cancel">Cancel</button>
                <button type="button" class="hf-btn hf-btn--danger" wire:click="deleteAnnouncement" data-testid="confirm-ok">Delete</button>
            </div>
        </dialog>
    @endif

    <div class="hf-toast-region" aria-live="polite" aria-atomic="false">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="hf-toast" x-bind:class="'hf-toast--' + toast.tone" x-bind:role="toast.tone === 'danger' ? 'alert' : 'status'">
                <i class="bi" x-bind:class="'bi-' + toastIcon(toast.tone)" aria-hidden="true"></i>
                <div>
                    <strong x-show="toast.title !== ''" x-text="toast.title"></strong>
                    <p x-text="toast.message"></p>
                </div>
                <button type="button" aria-label="Dismiss notification" data-testid="toast-dismiss" x-on:click="dismissToast(toast.id)">&times;</button>
            </div>
        </template>
    </div>
</div>
