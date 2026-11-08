<div>
@php
    $user = auth()->user();
    $role = 'fan';
    if ($user && $user->hasRole('organizer')) {
        $role = 'organizer';
    } elseif ($user && $user->hasRole('coach')) {
        $role = 'coach';
    }

    $statusMeta = [
        'approved' => ['tone' => 'success', 'label' => 'Approved', 'icon' => 'check-circle-fill'],
        'submitted' => ['tone' => 'warning', 'label' => 'Pending', 'icon' => 'hourglass-split'],
        'rejected' => ['tone' => 'danger', 'label' => 'Rejected', 'icon' => 'x-circle-fill'],
        'paid' => ['tone' => 'success', 'label' => 'Paid', 'icon' => 'cash-coin'],
        'partial' => ['tone' => 'warning', 'label' => 'Partial', 'icon' => 'circle-half'],
        'verified' => ['tone' => 'success', 'label' => 'Verified', 'icon' => 'patch-check-fill'],
        'missing' => ['tone' => 'danger', 'label' => 'Missing', 'icon' => 'file-earmark-x'],
    ];

    $money = fn ($value) => '₱' . number_format((int) ($value ?? 0));

    $coachTeams = $role === 'coach' && $user
        ? app(\App\Services\DashboardService::class)->coachTeams($user)
        : collect();

    $ownsTeam = fn ($team) => $coachTeams->contains('id', $team->id);
    $canSeePrivate = fn ($team) => $role === 'organizer' || ($role === 'coach' && $ownsTeam($team));

    $visibleTeams = $role === 'fan'
        ? $this->teams->filter(fn ($team) => $team->registrations->contains(fn ($registration) => $registration->status === 'approved'))
        : $this->teams;

    $cities = $visibleTeams->flatMap(fn ($team) => [$team->name])->unique()->count();
@endphp

@section('title', 'Teams')

<header class="hf-page-head hf-mb-3">
    <div>
        <span class="hf-eyebrow">Team directory</span>
        <h1 class="hf-page-title">Teams</h1>
        <p class="hf-page-desc">
            @if ($role === 'fan')
                Public team cards show records, coaches, and rosters. Payment and eligibility details stay private.
            @else
                Teams across tournaments. Filter by tournament, status, division, or search.
            @endif
        </p>
    </div>
    <div class="hf-page-actions">
        @if ($role === 'organizer')
            <a class="hf-btn" href="{{ route('teams') }}" data-testid="teams-change-tournament"><i class="bi bi-arrow-left"></i> All teams</a>
            <a class="hf-btn hf-btn--primary" href="{{ route('submissions') }}" data-testid="teams-review-queue"><i class="bi bi-clipboard-check"></i> Review queue</a>
        @elseif ($role === 'coach')
            @php
                $openTournament = $this->tournaments->firstWhere('status', 'registration-open');
            @endphp
            @if ($openTournament)
                <a class="hf-btn hf-btn--primary" href="{{ route('teamRegister', ['tournament' => $openTournament->id]) }}" data-testid="teams-register"><i class="bi bi-plus-lg"></i> Register team</a>
            @endif
            @if ($coachTeams->isNotEmpty())
                <a class="hf-btn" href="{{ route('myTeam', ['team' => $coachTeams->first()->id]) }}" data-testid="teams-my-team"><i class="bi bi-people"></i> My team</a>
            @endif
        @else
            <a class="hf-btn" href="{{ route('dashboard') }}">Back to dashboard</a>
        @endif
    </div>
</header>

<div id="teamKpis" class="hf-grid hf-grid--4 hf-mb-3">
    @if ($role === 'fan')
        <div class="hf-card hf-card--flat" data-testid="team-kpi-teams">
            <span class="hf-kpi-label">Public teams</span>
            <strong class="hf-kpi-value">{{ $visibleTeams->count() }}</strong>
            <span class="hf-small hf-muted">Approved and visible</span>
        </div>
        <div class="hf-card hf-card--flat" data-testid="team-kpi-divisions">
            <span class="hf-kpi-label">Divisions</span>
            <strong class="hf-kpi-value">{{ $this->divisions ? count($this->divisions) : 0 }}</strong>
            <span class="hf-small hf-muted">Age groups</span>
        </div>
        <div class="hf-card hf-card--flat" data-testid="team-kpi-cities">
            <span class="hf-kpi-label">Teams</span>
            <strong class="hf-kpi-value">{{ $visibleTeams->count() }}</strong>
            <span class="hf-small hf-muted">Clubs represented</span>
        </div>
        <div class="hf-card hf-card--flat" data-testid="team-kpi-tournaments">
            <span class="hf-kpi-label">Tournaments</span>
            <strong class="hf-kpi-value">{{ $this->tournaments->count() }}</strong>
            <span class="hf-small hf-muted">Seeded venues</span>
        </div>
    @else
        <div class="hf-card hf-card--flat" data-testid="team-kpi-registered">
            <span class="hf-kpi-label">Registered</span>
            <strong class="hf-kpi-value">{{ $this->teams->count() }}</strong>
            <span class="hf-small hf-muted">{{ $this->tournaments->count() }} tournaments</span>
        </div>
        <div class="hf-card hf-card--flat" data-testid="team-kpi-approved">
            <span class="hf-kpi-label">Approved</span>
            <strong class="hf-kpi-value">{{ $this->teams->filter(fn ($team) => $team->registrations->contains(fn ($r) => $r->status === 'approved'))->count() }}</strong>
            <span class="hf-small hf-muted">Eligible for seeding</span>
        </div>
        <div class="hf-card hf-card--flat" data-testid="team-kpi-pending">
            <span class="hf-kpi-label">Pending</span>
            <strong class="hf-kpi-value">{{ $this->teams->filter(fn ($team) => $team->registrations->contains(fn ($r) => $r->status === 'submitted'))->count() }}</strong>
            <span class="hf-small hf-muted">Waiting for organizer review</span>
        </div>
        <div class="hf-card hf-card--flat" data-testid="team-kpi-divisions">
            <span class="hf-kpi-label">Divisions</span>
            <strong class="hf-kpi-value">{{ $this->divisions ? count($this->divisions) : 0 }}</strong>
            <span class="hf-small hf-muted">U12 through open</span>
        </div>
    @endif
</div>

<div class="hf-card hf-mb-3">
    <div class="hf-form-grid">
        <div class="hf-field hf-field--full">
            <label class="hf-label" for="teamSearch">Search</label>
            <input class="hf-input" id="teamSearch" type="search" placeholder="Team, coach, or city"
                   wire:model.live.debounce.200ms="search" data-testid="team-search">
        </div>
        <div class="hf-field">
            <label class="hf-label" for="teamTournament">Tournament</label>
            <select class="hf-select" id="teamTournament" wire:model.live="tournament" data-testid="team-tournament-filter">
                <option value="">All tournaments</option>
                @foreach ($this->tournaments as $tournamentOption)
                    <option value="{{ $tournamentOption->id }}">{{ $tournamentOption->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($role !== 'fan')
            <div class="hf-field">
                <label class="hf-label" for="teamStatus">Status</label>
                <select class="hf-select" id="teamStatus" wire:model.live="status" data-testid="team-status-filter">
                    <option value="">All statuses</option>
                    <option value="approved">Approved</option>
                    <option value="submitted">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        @endif
        <div class="hf-field">
            <label class="hf-label" for="teamDivision">Division</label>
            <select class="hf-select" id="teamDivision" wire:model.live="division" data-testid="team-division-filter">
                <option value="">All divisions</option>
                @foreach ($this->divisions as $divisionName)
                    <option value="{{ $divisionName }}">{{ $divisionName }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div id="teamList" data-testid="team-list">
    @if ($visibleTeams->isEmpty())
        <div class="hf-empty">
            <i class="bi bi-search" aria-hidden="true"></i>
            <h3>No teams match</h3>
            <p>Try another status, division, or search term.</p>
            <button type="button" class="hf-btn" wire:click="clearFilters" data-testid="team-clear-filters">Clear filters</button>
        </div>
    @else
        <div class="hf-grid hf-grid--2">
            @foreach ($visibleTeams as $team)
                @php
                    $registration = $team->registrations->firstWhere('tournament_id', $this->tournament)
                        ?? $team->registrations->first();
                    $tournament = $registration?->tournament;
                    $record = app(\App\Services\DashboardService::class)->teamRecord($team);
                    $isOwn = $ownsTeam($team);
                    $private = $canSeePrivate($team);
                    $meta = $statusMeta[$registration?->status ?? 'approved'] ?? ['tone' => 'slate', 'label' => ucwords(str_replace('-', ' ', (string) ($registration?->status ?? 'approved'))), 'icon' => 'circle'];
                @endphp
                <article class="hf-card hf-card--flat" data-testid="team-card-{{ $team->id }}" wire:key="team-{{ $team->id }}">
                    <div class="hf-between">
                        <span class="hf-flex hf-items-center hf-gap-2">
                            <span class="hf-shield hf-shield--lg">{{ strtoupper(substr($team->name, 0, 1)) }}</span>
                            <span>
                                <strong>{{ $team->name }}</strong>
                                <span class="hf-small hf-muted" style="display:block">
                                    {{ $team->coachUser?->first_name ?? 'Coach' }} {{ $team->coachUser?->last_name ?? '' }} · {{ $team->players->count() }} players
                                </span>
                            </span>
                        </span>
                        <span class="hf-flex hf-wrap hf-gap-1">
                            <span class="hf-chip hf-chip--{{ $meta['tone'] }}"><i class="bi bi-{{ $meta['icon'] }}" aria-hidden="true"></i>{{ $meta['label'] }}</span>
                            @if ($private && $registration?->payment)
                                @php
                                    $paymentMeta = $statusMeta[$registration->payment->status === 'verified' ? 'verified' : 'missing'] ?? ['tone' => 'slate', 'label' => ucfirst($registration->payment->status), 'icon' => 'circle'];
                                @endphp
                                <span class="hf-chip hf-chip--{{ $registration->payment->status === 'verified' ? 'success' : 'warning' }}">
                                    {{ $registration->payment->status === 'verified' ? 'Paid' : 'Unpaid' }}
                                </span>
                            @endif
                        </span>
                    </div>
                    <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small">
                        <span><span class="hf-kpi-label">Tournament</span><strong style="display:block">{{ $tournament?->name ?? '—' }}</strong></span>
                        <span><span class="hf-kpi-label">Division</span><strong style="display:block">{{ $registration?->division?->name ?? ($tournament?->divisions?->first()?->name ?? '—') }}</strong></span>
                        <span><span class="hf-kpi-label">Record</span><strong style="display:block">{{ $record['wins'] }}-{{ $record['losses'] }}</strong></span>
                        <span><span class="hf-kpi-label">Roster</span><strong style="display:block">{{ $team->players->count() }}</strong></span>
                    </div>
                    @if ($private)
                        <div class="hf-flex hf-wrap hf-gap-1 hf-mt-2">
                            @php
                                $docsComplete = $team->players->every(fn ($player) => $player->eligibility_status === 'verified');
                                $consentComplete = $team->players->every(fn ($player) => ! is_null($player->consent_at));
                            @endphp
                            <span class="hf-chip hf-chip--{{ $docsComplete ? 'success' : 'danger' }}">{{ $docsComplete ? 'Documents' : 'Missing docs' }}</span>
                            <span class="hf-chip hf-chip--{{ $consentComplete ? 'success' : 'danger' }}">{{ $consentComplete ? 'Consent' : 'Missing consent' }}</span>
                        </div>
                    @endif
                    <div class="hf-btn-row hf-mt-2">
                        <a class="hf-btn hf-btn--sm" href="{{ route('teamShow', ['team' => $team->id]) }}" data-testid="team-open-{{ $team->id }}">Team profile</a>
                        @if ($isOwn)
                            <a class="hf-btn hf-btn--sm hf-btn--primary" href="{{ route('myTeam', ['team' => $team->id]) }}" data-testid="team-manage-{{ $team->id }}">Manage roster</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
</div>
