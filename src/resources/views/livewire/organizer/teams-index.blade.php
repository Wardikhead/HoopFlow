<div>
    @section('title', 'Teams')

    @php
        $role = $this->role;
        $selectedTournament = $this->selectedTournament;
    @endphp

    @if (! $selectedTournament)
        <header class="hf-page-head hf-mb-3">
            <div>
                <span class="hf-eyebrow">Team directory</span>
                <h1 class="hf-page-title">Select a tournament</h1>
                <p class="hf-page-desc">Choose a tournament to view its teams.</p>
            </div>
        </header>

        @if ($this->tournaments->isEmpty())
            <div class="hf-empty">
                <i class="bi bi-trophy" aria-hidden="true"></i>
                <h3>No tournaments yet</h3>
                <p>The organizer has not created a tournament.</p>
            </div>
        @else
            <div class="hf-grid hf-grid--2" data-testid="team-tournament-picker">
                @foreach ($this->tournaments as $tournamentOption)
                    <a class="hf-card hf-card--flat" href="{{ route('teams', ['tournament' => $tournamentOption->id]) }}" data-testid="team-tournament-{{ $tournamentOption->id }}">
                        <strong>{{ $tournamentOption->name }}</strong>
                        <span class="hf-small hf-muted" style="display:block">
                            {{ $tournamentOption->divisions->first()?->name ?? 'Division' }} · {{ $tournamentOption->status }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    @else
        <header class="hf-page-head hf-mb-3">
            <div>
                <span class="hf-eyebrow">Team directory</span>
                <h1 class="hf-page-title">Teams</h1>
                <p class="hf-page-desc">
                    @if ($role === 'fan')
                        Public team cards show records, coaches, and cities. Payment and eligibility details stay private.
                    @else
                        Teams in {{ $selectedTournament->name }}.
                    @endif
                </p>
            </div>
            <div class="hf-page-actions">
                @if ($role === 'organizer')
                    <a class="hf-btn" href="{{ route('teams') }}" data-testid="teams-change-tournament"><i class="bi bi-arrow-left"></i> Change tournament</a>
                    <a class="hf-btn hf-btn--primary" href="{{ route('submissions') }}" data-testid="teams-review-queue"><i class="bi bi-clipboard-check"></i> Review queue</a>
                @elseif ($role === 'coach')
                    @php $openTournament = $this->tournaments->firstWhere('status', 'registration-open'); @endphp
                    @if ($openTournament)
                        <a class="hf-btn hf-btn--primary" href="{{ route('teamRegister', ['tournament' => $openTournament->id]) }}" data-testid="teams-register"><i class="bi bi-plus-lg"></i> Register team</a>
                    @endif
                @else
                    <a class="hf-btn" href="{{ route('dashboard') }}">Back to dashboard</a>
                @endif
            </div>
        </header>

        <div id="teamKpis" class="hf-grid hf-grid--4 hf-mb-3">
            @if ($role === 'fan')
                <x-kpi-tile label="Public teams" :value="$this->scopedTeams->count()" hint="Approved and visible" tone="primary" testid="team-kpi-teams" />
                <x-kpi-tile label="Divisions" :value="count($this->divisions)" hint="Age groups" tone="slate" testid="team-kpi-divisions" />
                <x-kpi-tile label="Cities" :value="$this->kpis['cities']" hint="Clubs represented" tone="gold" testid="team-kpi-cities" />
                <x-kpi-tile label="Tournaments" :value="$this->tournaments->count()" hint="Seeded venues" tone="success" testid="team-kpi-tournaments" />
            @else
                <x-kpi-tile label="Registered" :value="$this->scopedTeams->count()" :hint="$this->tournaments->count().' tournaments'" tone="primary" testid="team-kpi-registered" />
                <x-kpi-tile label="Approved" :value="$this->kpis['approved']" hint="Eligible for seeding" tone="success" testid="team-kpi-approved" />
                <x-kpi-tile label="Pending" :value="$this->kpis['pending']" hint="Waiting for organizer review" tone="gold" testid="team-kpi-pending" />
                <x-kpi-tile label="Divisions" :value="count($this->divisions)" hint="U12 through open" tone="slate" testid="team-kpi-divisions" />
            @endif
        </div>

        <div class="hf-card hf-mb-3">
            <div class="hf-form-grid">
                <div class="hf-field hf-field--full">
                    <label class="hf-label" for="teamSearch">Search</label>
                    <input class="hf-input" id="teamSearch" type="search" placeholder="Team, coach, or city"
                           wire:model.live.debounce.200ms="search" data-testid="team-search">
                </div>
                @if ($role !== 'fan')
                    <div class="hf-field">
                        <label class="hf-label" for="teamStatus">Status</label>
                        <select class="hf-select" id="teamStatus" wire:model.live="status" data-testid="team-status-filter">
                            <option value="">All statuses</option>
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
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
            @if ($this->teams->isEmpty())
                <div class="hf-empty">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <h3>No teams match</h3>
                    <p>Try another status, division, or search term.</p>
                    <button type="button" class="hf-btn" wire:click="clearFilters" data-testid="team-clear-filters">Clear filters</button>
                </div>
            @else
                <div class="hf-grid hf-grid--2">
                    @foreach ($this->teams as $team)
                        @php
                            $registration = $team->registrations->firstWhere('tournament_id', $this->tournament);
                            $record = app(\App\Services\DashboardService::class)->teamRecord($team, $this->tournament);
                            $isOwn = $role === 'coach' && $team->coach_user_id === auth()->id();
                            $canSeePrivate = $role === 'organizer' || $isOwn;
                            $divisionName = $registration?->division?->name
                                ?? $registration?->tournament?->divisions?->first()?->name
                                ?? '—';
                            $documentsComplete = $team->players->every(fn ($player) => $player->eligibility_status === 'verified');
                            $consentComplete = $team->players->every(fn ($player) => ! is_null($player->consent_at));
                        @endphp
                        <article class="hf-card hf-card--flat" data-testid="team-card-{{ $team->id }}" wire:key="team-{{ $team->id }}">
                            <div class="hf-between">
                                <span class="hf-flex hf-items-center hf-gap-2">
                                    <x-team-shield :team="$team" size="lg" />
                                    <span>
                                        <strong>{{ $team->name }}</strong>
                                        <span class="hf-small hf-muted" style="display:block">
                                            {{ $team->coachUser?->name ?? $team->coach_name ?? 'Coach' }} · {{ $team->city }}
                                        </span>
                                    </span>
                                </span>
                                <span class="hf-flex hf-wrap hf-gap-1">
                                    @if ($registration)
                                        <x-status-chip :status="$registration->status" />
                                    @endif
                                    @if ($canSeePrivate && $registration?->payment)
                                        <x-payment-chip :payment="$registration->payment" />
                                    @endif
                                </span>
                            </div>
                            <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small">
                                <span><span class="hf-kpi-label">Tournament</span><strong style="display:block">{{ $registration?->tournament?->name ?? '—' }}</strong></span>
                                <span><span class="hf-kpi-label">Division</span><strong style="display:block">{{ $divisionName }}</strong></span>
                                <span><span class="hf-kpi-label">Record</span><strong style="display:block">{{ $record['wins'] }}-{{ $record['losses'] }}</strong></span>
                                <span><span class="hf-kpi-label">Roster</span><strong style="display:block">{{ $team->players->count() }}</strong></span>
                            </div>
                            @if ($canSeePrivate)
                                <div class="hf-flex hf-wrap hf-gap-1 hf-mt-2">
                                    @if ($documentsComplete)
                                        <x-status-chip status="verified" label="Documents" />
                                    @else
                                        <x-status-chip status="missing" label="Documents" />
                                    @endif
                                    @if ($consentComplete)
                                        <x-status-chip status="verified" label="Consent" />
                                    @else
                                        <x-status-chip status="missing" label="Consent" />
                                    @endif
                                </div>
                            @endif
                            <div class="hf-btn-row hf-mt-2">
                                <a class="hf-btn hf-btn--sm" href="{{ route('teamShow', array_filter(['team' => $team->id, 'tournament' => $this->tournament])) }}" data-testid="team-open-{{ $team->id }}">Team profile</a>
                                @if ($isOwn)
                                    <a class="hf-btn hf-btn--sm hf-btn--primary" href="{{ route('myTeam', array_filter(['team' => $team->id, 'tournament' => $this->tournament])) }}" data-testid="team-manage-{{ $team->id }}">Manage roster</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
