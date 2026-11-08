<div>
@php
    $announcements = $this->announcements;
@endphp

@section('title', 'Announcements')

<div class="hf-page-header hf-mb-3">
    <span class="hf-eyebrow">Matchday desk · Bulletin</span>
    <h1 class="hf-page-title">Announcements</h1>
    <p class="hf-muted">
        @if(auth()->user()?->hasRole('fan'))
            Official venue, schedule, and eligibility notices from the tournament desk.
        @else
            Venue, schedule, and eligibility bulletins, filtered to what this role can see.
        @endif
    </p>
    @if(auth()->user()?->hasRole('organizer'))
        <div class="hf-btn-row hf-mt-2">
            <a class="hf-btn hf-btn--primary" href="{{ url('/announcements/create') }}" data-testid="announcement-new">
                <i class="bi bi-megaphone"></i> New announcement
            </a>
        </div>
    @endif
</div>

<div class="hf-card hf-mb-3">
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
                <option value="">All tournaments</option>
                @foreach ($this->tournaments as $tournamentOption)
                    <option value="{{ $tournamentOption->id }}">{{ $tournamentOption->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="hf-flex hf-wrap hf-items-center hf-gap-2 hf-mt-2">
        <span class="hf-label">Category</span>
        <div class="hf-filter-row hf-mb-0" data-category-filters>
            @foreach ($this->categories as $option)
                <button type="button"
                        class="hf-filter {{ $category === $option['value'] ? 'is-active' : '' }}"
                        wire:click="$set('category', '{{ $option['value'] }}')"
                        data-category="{{ $option['value'] }}"
                        aria-pressed="{{ $category === $option['value'] ? 'true' : 'false' }}"
                        data-testid="announcement-category-{{ $option['value'] }}">
                    {{ $option['label'] }}
                </button>
            @endforeach
        </div>
    </div>
    <div class="hf-btn-row hf-mt-2">
        <button type="button" class="hf-btn hf-btn--sm" wire:click="clearFilters" data-testid="announcement-clear-filters">Clear filters</button>
    </div>
</div>

<div id="announcementFeed" data-testid="announcement-feed">
    @if ($announcements->isEmpty())
        <div class="hf-empty" data-testid="announcement-empty">
            <i class="bi bi-megaphone" aria-hidden="true"></i>
            <h3>No announcements match</h3>
            <p>Try another tournament, category, or search term.</p>
            @if(auth()->user()?->hasRole('organizer'))
                <a class="hf-btn hf-btn--primary" href="{{ url('/announcements/create') }}" data-testid="announcement-empty-create">Write an announcement</a>
            @else
                <button type="button" class="hf-btn" wire:click="clearFilters" data-testid="announcement-clear-filters">Clear filters</button>
            @endif
        </div>
    @else
        @php
            $pinned = $announcements->filter(fn ($a) => (bool) ($a->pinned ?? false));
            $rest = $announcements->reject(fn ($a) => (bool) ($a->pinned ?? false));
        @endphp
        @if ($pinned->isNotEmpty())
            <div class="hf-section-head">
                <h2 class="hf-section-title">Pinned</h2>
            </div>
            <div class="hf-grid hf-grid--2 hf-mb-3">
                @foreach ($pinned as $announcement)
                    <article class="hf-card" data-testid="announcement-{{ $announcement->id }}">
                        <div class="hf-flex hf-wrap hf-gap-2 hf-mb-2">
                            <span class="hf-chip hf-chip--slate">{{ $announcement->category ?? 'general' }}</span>
                            <span class="hf-chip hf-chip--gold"><i class="bi bi-pin-angle-fill"></i> Pinned</span>
                            <span class="hf-chip hf-chip--slate">Audience: {{ $announcement->audience }}</span>
                        </div>
                        <h3 class="hf-card-title">{{ $announcement->title }}</h3>
                        <p class="hf-small hf-muted hf-mb-2">{{ $announcement->createdBy?->name ?? 'Unknown' }} · {{ $announcement->published_at?->format('M d, Y') ?? '' }} · {{ $announcement->tournament?->name ?? '' }}</p>
                        <p class="hf-mb-0">{{ $announcement->body }}</p>
                    </article>
                @endforeach
            </div>
        @endif
        <div class="hf-section-head">
            <h2 class="hf-section-title">{{ $pinned->isNotEmpty() ? 'Latest updates' : 'All announcements' }}</h2>
        </div>
        <div class="hf-grid hf-grid--2">
            @foreach ($rest as $announcement)
                <article class="hf-card" data-testid="announcement-{{ $announcement->id }}">
                    <div class="hf-flex hf-wrap hf-gap-2 hf-mb-2">
                        <span class="hf-chip hf-chip--slate">{{ $announcement->category ?? 'general' }}</span>
                        <span class="hf-chip hf-chip--slate">Audience: {{ $announcement->audience }}</span>
                    </div>
                    <h3 class="hf-card-title">{{ $announcement->title }}</h3>
                    <p class="hf-small hf-muted hf-mb-2">{{ $announcement->createdBy?->name ?? 'Unknown' }} · {{ $announcement->published_at?->format('M d, Y') ?? '' }} · {{ $announcement->tournament?->name ?? '' }}</p>
                    <p class="hf-mb-0">{{ $announcement->body }}</p>
                </article>
            @endforeach
        </div>
    @endif
</div>
</div>
