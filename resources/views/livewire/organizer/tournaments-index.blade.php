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

<header class="hf-page-head hf-mb-3">
    <div>
        <span class="hf-eyebrow">Tournament directory</span>
        <h1 class="hf-page-title">Tournaments</h1>
        <p class="hf-page-desc">Three seeded competitions cover registration, a live bracket, and a draft that can generate a bracket and schedule during the demo.</p>
    </div>
    <div class="hf-page-actions">
        @if ($role === 'organizer')
            <a class="hf-btn hf-btn--primary" href="{{ route('tournamentCreate') }}" data-testid="tournaments-create"><i class="bi bi-plus-lg"></i> New tournament</a>
        @else
            <a class="hf-btn" href="{{ route('dashboard') }}">Back to dashboard</a>
        @endif
    </div>
</header>

<div class="hf-card hf-mb-3">
    <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
            <label class="hf-label" for="tournamentSearch">Search</label>
            <input class="hf-input" id="tournamentSearch" type="search" placeholder="Name, venue, or division"
                   wire:model.live.debounce.200ms="search" data-testid="tournament-search">
        </div>
        <div class="hf-field">
            <label class="hf-label" for="tournamentStatus">Status</label>
            <select class="hf-select" id="tournamentStatus" wire:model.live="status" data-testid="tournament-status-filter">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="registration-open">Registration open</option>
                <option value="upcoming">Upcoming</option>
                <option value="draft">Draft</option>
            </select>
        </div>
        <div class="hf-field">
            <label class="hf-label" for="tournamentDivision">Division</label>
            <select class="hf-select" id="tournamentDivision" wire:model.live="division" data-testid="tournament-division-filter">
                <option value="">All divisions</option>
                @foreach ($this->divisions as $divisionName)
                    <option value="{{ $divisionName }}">{{ $divisionName }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div id="tournamentList" data-testid="tournament-list">
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
                <div>
                    <span class="hf-flex hf-wrap hf-gap-1">
                        <x-status-chip :status="$tournament->status" />
                        <span class="hf-chip hf-chip--slate">{{ $division?->name ?? 'Division' }}</span>
                        @if ($seedLocked)
                            <span class="hf-chip hf-chip--gold">Seeds locked</span>
                        @endif
                    </span>
                    <h2 class="hf-section-title hf-mt-1">{{ $tournament->name }}</h2>
                    <p class="hf-small hf-muted hf-mb-0">
                        {{ $tournament->venueName() }} · {{ $tournament->start_date?->format('M d, Y') ?? 'TBD' }} to {{ $tournament->end_date?->format('M d, Y') ?? 'TBD' }}
                    </p>
                </div>
                <div class="hf-text-right">
                    <span class="hf-kpi-label">Teams</span>
                    <strong class="hf-kpi-value" style="font-size: 32px">{{ $approved }}/{{ $tournament->slotCount() }}</strong>
                </div>
            </div>
            <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small hf-muted">
                <span><i class="bi bi-geo-alt"></i> {{ $courts }} courts</span>
                <span><i class="bi bi-cash"></i> {{ $money($tournament->entryFee()) }} entry</span>
                <span><i class="bi bi-diagram-3"></i> {{ $matchesPlayed }} played</span>
                <span><i class="bi bi-hourglass-split"></i> {{ $pending }} pending</span>
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
            </div>
        </article>
    @empty
        <div class="hf-empty">
            <i class="bi bi-search" aria-hidden="true"></i>
            <h3>No tournaments match</h3>
            <p>Clear the search or choose another status/division.</p>
            <button type="button" class="hf-btn" wire:click="clearFilters" data-testid="tournament-clear-filters">Clear filters</button>
        </div>
    @endforelse
</div>
</div>
