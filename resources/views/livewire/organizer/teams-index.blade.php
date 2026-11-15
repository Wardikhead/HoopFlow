<div>
    @section('title', 'Teams')

    @php
        $role = $this->role;
        $selectedTournament = $this->selectedTournament;
    @endphp

    @section('breadcrumbs')
        <x-breadcrumbs :items="array_values(array_filter([
            ['label' => 'Home', 'href' => route('dashboard')],
            ['label' => 'Teams', 'href' => route('teams')],
            $selectedTournament ? ['label' => $selectedTournament->name] : null,
        ]))" />
    @endsection

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
                        <span class="hf-small hf-muted hf-block">
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
                    <a class="hf-btn" href="{{ route('teams') }}" data-testid="teams-change-tournament"><i class="bi bi-arrow-left" aria-hidden="true"></i> Change tournament</a>
                    <a class="hf-btn hf-btn--primary" href="{{ route('submissions') }}" data-testid="teams-review-queue"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Review queue</a>
                @elseif ($role === 'coach')
                    @php $openTournament = $this->openTournamentForCoach; @endphp
                    @if ($openTournament)
                        <a class="hf-btn hf-btn--primary" href="{{ route('teamRegister', ['tournament' => $openTournament->id]) }}" data-testid="teams-register"><i class="bi bi-plus-lg" aria-hidden="true"></i> Register team</a>
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
                <x-kpi-tile label="Tournaments" :value="$this->tournaments->count()" hint="In the workspace" tone="success" testid="team-kpi-tournaments" />
            @else
                <x-kpi-tile label="Registered" :value="$this->scopedTeams->count()" :hint="$this->tournaments->count().' tournaments'" tone="primary" testid="team-kpi-registered" />
                <x-kpi-tile label="Approved" :value="$this->kpis['approved']" hint="Eligible for seeding" tone="success" testid="team-kpi-approved" />
                <x-kpi-tile label="Pending" :value="$this->kpis['pending']" hint="Waiting for organizer review" tone="gold" testid="team-kpi-pending" />
                <x-kpi-tile label="Divisions" :value="count($this->divisions)" hint="U12 through open" tone="slate" testid="team-kpi-divisions" />
            @endif
        </div>

        <div class="hf-filter-bar hf-mb-3 {{ $role === 'fan' ? 'hf-filter-bar--one' : '' }}">
            <div class="hf-filter-bar__search">
                <label class="hf-sr-only" for="teamSearch">Search teams</label>
                <i class="bi bi-search" aria-hidden="true"></i>
                <input class="hf-input" id="teamSearch" type="search" placeholder="Team, coach, or city"
                       wire:model.live.debounce.200ms="search" data-testid="team-search">
            </div>
            @if ($role !== 'fan')
                <div class="hf-field">
                    <label class="hf-sr-only" for="teamStatus">Status</label>
                    <select class="hf-select hf-select--compact" id="teamStatus" wire:model.live="status" data-testid="team-status-filter">
                        <option value="">All statuses</option>
                        <option value="approved">Approved</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            @endif
            <div class="hf-field">
                <label class="hf-sr-only" for="teamDivision">Division</label>
                <select class="hf-select hf-select--compact" id="teamDivision" wire:model.live="division" data-testid="team-division-filter">
                    <option value="">All divisions</option>
                    @foreach ($this->divisions as $divisionName)
                        <option value="{{ $divisionName }}">{{ $divisionName }}</option>
                    @endforeach
                </select>
            </div>
            @if ($search !== '' || $status !== '' || $division !== '')
                <button type="button" class="hf-btn hf-btn--sm" wire:click="clearFilters" wire:loading.class="is-loading" data-testid="team-clear-filters-top">
                    <i class="bi bi-x-lg" aria-hidden="true"></i> Clear
                </button>
            @endif
        </div>

        <div class="hf-between hf-mb-2">
            <p class="hf-results-count hf-mb-0">{{ $this->teams->count() }} {{ \Illuminate\Support\Str::plural('team', $this->teams->count()) }}</p>
            <span class="hf-small hf-muted">Sorted by name</span>
        </div>

        <div id="teamList" data-testid="team-list" data-reveal wire:loading.class="is-updating" wire:loading.attr="aria-busy">
            @if ($this->teams->isEmpty())
                <div class="hf-empty">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <h3>No teams match</h3>
                    <p>Try another status, division, or search term.</p>
                    <button type="button" class="hf-btn" wire:click="clearFilters" wire:loading.class="is-loading" data-testid="team-clear-filters">Clear filters</button>
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
                        <article class="hf-card hf-card--flat hf-card--link" data-testid="team-card-{{ $team->id }}" wire:key="team-{{ $team->id }}">
                            <div class="hf-between">
                                <span class="hf-flex hf-items-center hf-gap-2">
                                    <x-team-shield :team="$team" size="lg" />
                                    <span>
                                        <strong>{{ $team->name }}</strong>
                                        <span class="hf-small hf-muted hf-block">
                                            {{ $team->coachUser?->name ?? $team->coach_name ?? 'Coach' }} · {{ $team->city }}
                                        </span>
                                    </span>
                                </span>
                                <span class="hf-flex hf-items-center hf-gap-2">
                                    <span class="hf-flex hf-wrap hf-gap-1">
                                        @if ($registration)
                                            <x-status-chip :status="$registration->status" />
                                        @endif
                                        @if ($canSeePrivate && $registration?->payment)
                                            <x-payment-chip :payment="$registration->payment" />
                                        @endif
                                    </span>
                                    <span class="hf-card__arrow" aria-hidden="true"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                                </span>
                            </div>
                            <dl class="hf-team-card__meta">
                                <div><dt>Tournament</dt><dd>{{ $registration?->tournament?->name ?? '—' }}</dd></div>
                                <div><dt>Division</dt><dd>{{ $divisionName }}</dd></div>
                                <div><dt>Record</dt><dd>{{ $record['wins'] }}-{{ $record['losses'] }}</dd></div>
                                <div><dt>Roster</dt><dd>{{ $team->players->count() }}</dd></div>
                            </dl>
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
