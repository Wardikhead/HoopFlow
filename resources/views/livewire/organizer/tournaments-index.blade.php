<div>
@php
    $user = auth()->user();
    $role = 'fan';
    if ($user && $user->hasRole('organizer')) {
        $role = 'organizer';
    } elseif ($user && $user->hasRole('coach')) {
        $role = 'coach';
    }

    $money = fn ($value) => '₱' . number_format((int) ($value ?? 0));
@endphp

@section('title', 'Tournaments')
@section('meta-description', 'Manage tournaments - divisions, brackets, schedules, and live status across your HoopFlow league.')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Tournaments'],
    ]" />
@endsection

<header class="hf-page-head hf-mb-3">
    <div>
        <span class="hf-eyebrow">Tournament desk</span>
        <h1 class="hf-page-title">Tournaments</h1>
        <p class="hf-page-desc">Plan registration, run matchday, and archive every competition from one desk.</p>
    </div>
    <div class="hf-page-actions">
        @if ($role === 'organizer')
            <a class="hf-btn hf-btn--primary" href="{{ route('tournamentCreate') }}" data-testid="tournaments-create"><i class="bi bi-plus-lg" aria-hidden="true"></i> New tournament</a>
        @else
            <a class="hf-btn" href="{{ route('dashboard') }}">Back to dashboard</a>
        @endif
    </div>
</header>

<div class="hf-filter-bar hf-mb-3">
    <div class="hf-filter-bar__search">
        <label class="hf-sr-only" for="tournamentSearch">Search tournaments</label>
        <i class="bi bi-search" aria-hidden="true"></i>
        <input class="hf-input" id="tournamentSearch" type="search" placeholder="Name, venue, or division"
               wire:model.live.debounce.200ms="search" data-testid="tournament-search">
    </div>
    <div class="hf-field">
        <label class="hf-sr-only" for="tournamentStatus">Status</label>
        <select class="hf-select hf-select--compact" id="tournamentStatus" wire:model.live="status" data-testid="tournament-status-filter">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
            <option value="registration-open">Registration open</option>
            <option value="upcoming">Upcoming</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
        </select>
    </div>
    <div class="hf-field">
        <label class="hf-sr-only" for="tournamentDivision">Division</label>
        <select class="hf-select hf-select--compact" id="tournamentDivision" wire:model.live="division" data-testid="tournament-division-filter">
            <option value="">All divisions</option>
            @foreach ($this->divisions as $divisionName)
                <option value="{{ $divisionName }}">{{ $divisionName }}</option>
            @endforeach
        </select>
    </div>
    @if ($search !== '' || $status !== '' || $division !== '')
        <button type="button" class="hf-btn hf-btn--sm" wire:click="clearFilters" wire:loading.class="is-loading" data-testid="tournament-clear-filters-top">
            <i class="bi bi-x-lg" aria-hidden="true"></i> Clear
        </button>
    @endif
</div>

<div class="hf-between hf-mb-2">
    <p class="hf-results-count hf-mb-0">{{ $this->tournaments->count() }} {{ \Illuminate\Support\Str::plural('tournament', $this->tournaments->count()) }}</p>
    <span class="hf-small hf-muted">Sorted by start date</span>
</div>

<div id="tournamentList" data-testid="tournament-list" data-reveal wire:loading.class="is-updating" wire:loading.attr="aria-busy">
    <div class="hf-updating-pill" wire:loading wire:target="search,status,division">
        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Updating…
    </div>
    @forelse ($this->tournaments as $tournament)
        @php
            $division = $tournament->divisions->first();
            $approved = $tournament->registrations->where('status', 'approved')->count();
            $pending = $tournament->registrations->whereIn('status', ['submitted', 'pending'])->count();
            $matchesPlayed = $tournament->matches->whereIn('status', ['final', 'forfeit'])->count();
            $courts = $tournament->courtCount();
            $seedLocked = $tournament->seedsLocked();

            $coachTeam = null;
            if ($role === 'coach' && $user) {
                $coachTeam = \App\Models\Team::query()
                    ->where('coach_user_id', $user->id)
                    ->whereHas('registrations', fn ($query) => $query->where('tournament_id', $tournament->id))
                    ->first();
            }
        @endphp
        <article class="hf-card hf-mb-2" data-testid="tournament-card-{{ $tournament->id }}" wire:key="tournament-{{ $tournament->id }}">
            <div class="hf-between">
                <div class="hf-min-w-0">
                    <span class="hf-flex hf-wrap hf-gap-1">
                        <x-status-chip :status="$tournament->status" />
                        <span class="hf-chip hf-chip--slate">{{ $division?->name ?? 'Division' }}</span>
                        @if ($tournament->isArchived())
                            <span class="hf-chip hf-chip--gold" data-testid="tournament-archived-{{ $tournament->id }}"><i class="bi bi-archive-fill" aria-hidden="true"></i>Archived</span>
                        @endif
                        @if ($seedLocked)
                            <span class="hf-chip hf-chip--gold">Seeds locked</span>
                        @endif
                    </span>
                    <h2 class="hf-section-title hf-mt-1 hf-mb-0">{{ $tournament->name }}</h2>
                    <p class="hf-small hf-muted hf-mb-0">
                        {{ $tournament->venueName() }} · {{ $tournament->start_date?->format('M d, Y') ?? 'TBD' }} to {{ $tournament->end_date?->format('M d, Y') ?? 'TBD' }}
                    </p>
                </div>
                <div class="hf-text-right">
                    <span class="hf-kpi-label">Teams</span>
                    <strong class="hf-kpi-value hf-kpi-value--sm">{{ $approved }}/{{ $tournament->slotCount() }}</strong>
                </div>
            </div>
            <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small hf-muted">
                <span><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $courts }} courts</span>
                <span><i class="bi bi-cash" aria-hidden="true"></i> {{ $money($tournament->entryFee()) }} entry</span>
                <span><i class="bi bi-diagram-3" aria-hidden="true"></i> {{ $matchesPlayed }} played</span>
                <span><i class="bi bi-hourglass-split" aria-hidden="true"></i> {{ $pending }} pending</span>
            </div>
            <div class="hf-btn-row hf-mt-2">
                @if ($role === 'coach' && $tournament->status === 'registration-open' && ! $coachTeam)
                    <a class="hf-btn hf-btn--primary" href="{{ route('teamRegister', ['tournament' => $tournament->id]) }}" data-testid="tournament-register-{{ $tournament->id }}">Register team</a>
                @elseif ($coachTeam)
                    <a class="hf-btn" href="{{ route('myTeam', ['team' => $coachTeam->id, 'tournament' => $tournament->id]) }}" data-testid="tournament-my-team-{{ $tournament->id }}">My team</a>
                @else
                    <a class="hf-btn" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}" data-testid="tournament-open-{{ $tournament->id }}">Open desk</a>
                @endif
                <a class="hf-btn" href="{{ route('bracket', ['id' => $tournament->id]) }}">Bracket</a>
                <a class="hf-btn" href="{{ route('schedule', ['id' => $tournament->id]) }}">Schedule</a>
                @if ($role === 'organizer')
                    <details
                        class="hf-card-menu"
                        data-testid="tournament-manage-{{ $tournament->id }}"
                        x-data="{
                            up: false,
                            place() {
                                const panel = this.$el.querySelector('.hf-card-menu__panel');
                                const nav = document.querySelector('.hf-bottom-nav');
                                const reserve = nav && getComputedStyle(nav).display !== 'none' ? nav.offsetHeight : 0;
                                const bottom = this.$el.getBoundingClientRect().bottom + (panel?.offsetHeight ?? 96) + 12;
                                this.up = this.$el.open && bottom > window.innerHeight - reserve;
                            },
                        }"
                        x-on:toggle="place()"
                        x-bind:class="{ 'is-up': up }">
                        <summary class="hf-btn hf-btn--sm">
                            <i class="bi bi-sliders" aria-hidden="true"></i> Manage
                        </summary>
                        <div class="hf-card-menu__panel" role="menu" aria-label="Tournament actions" x-data x-on:click="if ($event.target.closest('button')) $el.closest('details').open = false">
                            @if ($tournament->isArchived())
                                <button type="button" class="hf-card-menu__item" role="menuitem" wire:click="requestRestore({{ $tournament->id }})" data-testid="tournament-restore-{{ $tournament->id }}">
                                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Restore
                                </button>
                            @else
                                <button type="button" class="hf-card-menu__item" role="menuitem" wire:click="requestArchive({{ $tournament->id }})" data-testid="tournament-archive-{{ $tournament->id }}">
                                    <i class="bi bi-archive" aria-hidden="true"></i> Archive
                                </button>
                            @endif
                            <button type="button" class="hf-card-menu__item hf-card-menu__item--danger" role="menuitem" wire:click="requestDelete({{ $tournament->id }})" data-testid="tournament-delete-{{ $tournament->id }}">
                                <i class="bi bi-trash3" aria-hidden="true"></i> Delete
                            </button>
                        </div>
                    </details>
                @endif
            </div>
        </article>
    @empty
        <div class="hf-empty">
            <i class="bi bi-search" aria-hidden="true"></i>
            <h2>{{ $status === 'archived' ? 'No archived tournaments' : 'No tournaments match' }}</h2>
            <p>{{ $status === 'archived' ? 'Archive a tournament from its manage menu to keep it out of the default list.' : 'Clear the search or choose another status/division.' }}</p>
            <button type="button" class="hf-btn" wire:click="clearFilters" wire:loading.class="is-loading" data-testid="tournament-clear-filters">Clear filters</button>
        </div>
    @endforelse
</div>

@if ($showConfirm)
    <dialog
        class="hf-dialog"
        x-data
        x-init="if (! $el.open) $el.showModal()"
        x-on:cancel.prevent="$wire.cancelConfirm()"
        x-on:click="if ($event.target === $el) $wire.cancelConfirm()"
        wire:key="tournament-index-confirm-dialog"
        data-testid="tournament-confirm-dialog"
        aria-labelledby="tournamentIndexConfirmDialogTitle">
        <div class="hf-dialog__head">
            <h2 class="hf-dialog__title" id="tournamentIndexConfirmDialogTitle">{{ $confirmTitle }}</h2>
            <button type="button" class="hf-icon-btn" wire:click="cancelConfirm" aria-label="Close dialog" data-testid="dialog-close">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <div class="hf-dialog__body">
            <p class="hf-mb-0">{{ $confirmMessage }}</p>
        </div>
        <div class="hf-dialog__foot">
            <button type="button" class="hf-btn" wire:click="cancelConfirm" data-testid="confirm-cancel">Cancel</button>
            <button type="button" class="hf-btn hf-btn--{{ $confirmTone }}" wire:click="confirm" data-testid="confirm-ok" wire:loading.attr="disabled" wire:loading.class="is-loading">{{ $confirmLabel }}</button>
        </div>
    </dialog>
@endif
</div>
