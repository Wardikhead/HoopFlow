<div>
    @php
        $tournament = $this->tournament;
        $bracket = $this->bracket;
        $role = auth()->user()?->hasRole('organizer') ? 'organizer' : (auth()->user()?->hasRole('coach') ? 'coach' : 'fan');
        $canGenerate = $role === 'organizer' && $tournament && $tournament->registrations->where('status', 'approved')->count() >= 2;
        $approved = $tournament ? $tournament->registrations->where('status', 'approved') : collect();
    @endphp

    @if (! $tournament)
        <div class="hf-card">
            <p class="hf-muted hf-mb-0">Tournament not found.</p>
        </div>
    @else
        <header class="hf-page-head hf-mb-3">
            <div>
                <span class="hf-eyebrow">{{ $tournament->divisions?->first()?->name ?? $tournament->name }} · {{ $tournament->status }}</span>
                <h1 class="hf-page-title">{{ $bracket['hasBracket'] ? 'Bracket' : 'Build the bracket' }}</h1>
                <p class="hf-page-desc">
                    @if ($bracket['hasBracket'])
                        {{ $tournament->name }} · {{ count($bracket['rounds']) }} rounds
                    @else
                        {{ $tournament->name }} · {{ $approved->count() }} approved teams ready to seed into a {{ $tournament->divisions?->first()?->max_teams ?? 0 }}-slot single-elimination bracket.
                    @endif
                </p>
            </div>
            <div class="hf-page-actions">
                <a class="hf-btn" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}"><i class="bi bi-arrow-left"></i> Tournament</a>
                <a class="hf-btn" href="{{ route('schedule') }}"><i class="bi bi-calendar3"></i> Schedule</a>
                <a class="hf-btn" href="{{ route('standings') }}"><i class="bi bi-bar-chart"></i> Standings</a>
                @if ($role === 'organizer' && $bracket['hasBracket'] && $tournament->matches->where('status', 'final')->isEmpty())
                    <button type="button" class="hf-btn" wire:click="regenerateBracket" data-testid="bracket-regenerate">Regenerate bracket</button>
                    <button type="button" class="hf-btn" wire:click="editSlots" data-testid="bracket-edit-slots">Edit slots</button>
                @endif
            </div>
        </header>

        @if (! $bracket['hasBracket'])
            <div class="hf-grid hf-grid--3 hf-mb-3">
                <div class="hf-card hf-card--flat">
                    <span class="hf-kpi-label">Approved teams</span>
                    <strong class="hf-kpi-value">{{ $approved->count() }}</strong>
                    <p class="hf-small hf-muted hf-mb-0">{{ $tournament->divisions?->first()?->max_teams ?? 0 }} bracket slots</p>
                </div>
                <div class="hf-card hf-card--flat">
                    <span class="hf-kpi-label">First-round games</span>
                    <strong class="hf-kpi-value">{{ (int) (($tournament->divisions?->first()?->max_teams ?? 0) / 2) }}</strong>
                    <p class="hf-small hf-muted hf-mb-0">Byes fill empty slots</p>
                </div>
                <div class="hf-card hf-card--flat">
                    <span class="hf-kpi-label">Courts</span>
                    <strong class="hf-kpi-value">{{ $tournament->matchSlots->pluck('court_id')->filter()->unique()->count() }}</strong>
                    <p class="hf-small hf-muted hf-mb-0">{{ $tournament->name }}</p>
                </div>
            </div>

            <div class="hf-card" data-testid="bracket-empty">
                <h2 class="hf-section-title">Seed preview</h2>
                <p class="hf-small hf-muted">Teams are seeded by registration seed. Empty slots become byes.</p>
                <div class="hf-grid hf-grid--2 hf-mt-2">
                    @forelse ($approved->sortBy('seed') as $reg)
                        <div class="hf-flex hf-items-center hf-gap-2 hf-card hf-card--flat">
                            <span><strong>{{ $reg->team?->name ?? 'Team' }}</strong><span class="hf-small hf-muted" style="display:block">seed {{ $reg->seed ?? 'TBD' }}</span></span>
                        </div>
                    @empty
                        <div class="hf-card hf-card--flat">
                            <p class="hf-muted hf-small hf-mb-0">No approved teams. Approve at least two teams before generating.</p>
                        </div>
                    @endforelse
                </div>
                <div class="hf-btn-row hf-mt-3">
                    @if ($canGenerate)
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="generateBracket" data-testid="bracket-generate">Generate bracket</button>
                    @else
                        <button type="button" class="hf-btn" disabled data-testid="bracket-generate-disabled">Generate bracket</button>
                        <p class="hf-small hf-muted hf-mb-0">{{ $role === 'organizer' ? 'At least two approved teams are required.' : 'Organizer access required.' }}</p>
                    @endif
                </div>
            </div>
        @else
            @if ($bracket['champion'])
                <section class="hf-bracket-champion hf-mb-3" data-testid="bracket-champion">
                    <span class="hf-eyebrow" style="color: var(--hf-gold)">Champion</span>
                    <div class="hf-flex hf-items-center hf-gap-3 hf-mt-1">
                        <div>
                            <h1>{{ $bracket['champion']->name }}</h1>
                        </div>
                    </div>
                </section>
            @endif

            <div class="hf-grid hf-grid--4 hf-mb-3">
                <div class="hf-card hf-card--flat">
                    <span class="hf-kpi-label">Rounds</span>
                    <strong class="hf-kpi-value">{{ count($bracket['rounds']) }}</strong>
                    <p class="hf-small hf-muted hf-mb-0">{{ $tournament->divisions?->first()?->max_teams ?? 0 }}-team bracket</p>
                </div>
                <div class="hf-card hf-card--flat">
                    <span class="hf-kpi-label">Played</span>
                    <strong class="hf-kpi-value">{{ $tournament->matches->where('status', 'final')->count() }}</strong>
                    <p class="hf-small hf-muted hf-mb-0">{{ $tournament->matches->where('status', 'scheduled')->count() }} scheduled</p>
                </div>
                <div class="hf-card hf-card--flat">
                    <span class="hf-kpi-label">Champion</span>
                    <strong class="hf-kpi-value">{{ $bracket['champion']?->name ?? 'TBD' }}</strong>
                    <p class="hf-small hf-muted hf-mb-0">{{ $bracket['champion'] ? 'Final played' : 'Final not played' }}</p>
                </div>
            </div>

            <div class="hf-flex hf-wrap hf-gap-2 hf-mb-2 hf-small hf-muted">
                <span class="hf-chip hf-chip--slate"><i class="bi bi-clock"></i> Scheduled</span>
                <span class="hf-chip hf-chip--success"><i class="bi bi-flag-fill"></i> Final</span>
                <span class="hf-chip hf-chip--danger"><i class="bi bi-shield-x"></i> Forfeit</span>
                <span>{{ $role === 'organizer' ? 'Select a match to enter or correct a score.' : 'Public bracket view. Scores are read-only for this role.' }}</span>
            </div>

            @foreach ($bracket['rounds'] as $round)
                <div class="hf-card hf-mb-3">
                    <h2 class="hf-section-title">{{ $round['label'] }}</h2>
                    <div class="hf-grid hf-grid--2">
                        @foreach ($round['matches'] as $match)
                            <div class="hf-card hf-card--flat" data-testid="bracket-match-{{ $match->id }}">
                                <div class="hf-between">
                                    <span>{{ $match->homeTeam?->name ?? 'TBD' }}</span>
                                    <strong>{{ $match->home_score ?? 0 }} – {{ $match->away_score ?? 0 }}</strong>
                                    <span>{{ $match->awayTeam?->name ?? 'TBD' }}</span>
                                </div>
                                <p class="hf-small hf-muted hf-mb-0">{{ $match->status }}</p>
                                @if ($role === 'organizer')
                                    <div class="hf-btn-row hf-mt-2">
                                        <button type="button" class="hf-btn hf-btn--sm" wire:click="openScoreConsole({{ $match->id }})">Score</button>
                                        <button type="button" class="hf-btn hf-btn--sm" wire:click="openCorrection({{ $match->id }})">Correct</button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Edit slots modal --}}
        @if ($showEditSlots)
            <div class="hf-card hf-mt-3">
                <h2 class="hf-section-title">Edit first-round slots</h2>
                @foreach ($slots as $i => $assignment)
                    <div class="hf-flex hf-gap-2 hf-mb-2">
                        <span>Match {{ $assignment['matchId'] }}</span>
                        <input type="number" wire:model="slots.{{ $i }}.teamId" class="hf-input" style="width:120px" placeholder="Team ID">
                    </div>
                @endforeach
                <div class="hf-btn-row">
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="saveSlots">Save</button>
                    <button type="button" class="hf-btn" wire:click="$set('showEditSlots', false)">Cancel</button>
                </div>
            </div>
        @endif

        {{-- Score console --}}
        @if ($showScoreConsole)
            <div class="hf-card hf-mt-3">
                <h2 class="hf-section-title">Score console</h2>
                <div class="hf-flex hf-gap-2 hf-mb-2">
                    <input type="number" wire:model="score1" class="hf-input" placeholder="Home score">
                    <input type="number" wire:model="score2" class="hf-input" placeholder="Away score">
                </div>
                <input type="text" wire:model="reason" class="hf-input hf-mb-2" placeholder="Reason (optional for entry)">
                @error('reason') <p class="text-danger small">{{ $message }}</p> @enderror
                <div class="hf-btn-row">
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="submitScore">Submit</button>
                    <button type="button" class="hf-btn" wire:click="$set('showScoreConsole', false)">Cancel</button>
                </div>
            </div>
        @endif

        {{-- Correction dialog --}}
        @if ($showCorrection)
            <div class="hf-card hf-mt-3">
                <h2 class="hf-section-title">Correct score</h2>
                <div class="hf-flex hf-gap-2 hf-mb-2">
                    <input type="number" wire:model="score1" class="hf-input" placeholder="Home score">
                    <input type="number" wire:model="score2" class="hf-input" placeholder="Away score">
                </div>
                <input type="text" wire:model="reason" class="hf-input hf-mb-2" placeholder="Reason (required)">
                @error('reason') <p class="text-danger small">{{ $message }}</p> @enderror
                <div class="hf-btn-row">
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="submitCorrection">Submit correction</button>
                    <button type="button" class="hf-btn" wire:click="$set('showCorrection', false)">Cancel</button>
                </div>
            </div>
        @endif
    @endif
</div>