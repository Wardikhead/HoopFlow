<div>
    @php
        $tournament = $this->tournament;
        $bracket = $this->bracket;
        $kpis = $this->kpis;
        $approved = $this->approvedTeams;
        $isOrganizer = auth()->user()?->hasRole('organizer') ?? false;
        $format = $this->format;
        $rules = $tournament?->rules_json;
        $tournamentSlots = is_array($rules) && isset($rules['slots']) && is_numeric($rules['slots'])
            ? (int) $rules['slots']
            : (int) ($tournament?->divisions?->first()?->max_teams ?? 0);
        $courtCount = $tournament !== null
            ? $tournament->matchSlots->pluck('court_id')->filter()->unique()->count()
            : 0;
        $sections = $bracket['sections'];
        $mobileRounds = collect($sections)->flatMap(fn ($section) => $section['rounds'])->values();
        $totalRounds = collect($sections)->sum(fn ($section) => count($section['rounds']));
        $totalGames = collect($sections)->sum(
            fn ($section) => collect($section['rounds'])->sum(
                fn ($round) => $round['matches']->count()
            )
        );
        $playedGames = collect($sections)->sum(
            fn ($section) => collect($section['rounds'])->sum(
                fn ($round) => $round['matches']->filter(
                    fn ($match) => in_array($match->status, ['final', 'forfeit'], true)
                )->count()
            )
        );
        $progressPercent = $totalGames > 0 ? (int) round(($playedGames / $totalGames) * 100) : 0;
        $mobileRoundCount = $mobileRounds->count();
        $lastSection = collect($sections)->last();
        $featuredMobileIndex = $lastSection !== null && $lastSection['key'] !== 'group'
            ? $mobileRoundCount - 1
            : null;
        $hasTree = collect($sections)->contains(fn ($section) => $section['key'] !== 'group');
    @endphp

    @section('meta-description', 'Follow the live tournament bracket — every round, series score, court assignment, and final result in one HoopFlow view.')

    @if (! $tournament)
        <div class="hf-card">
            <p class="hf-muted hf-mb-0">Tournament not found.</p>
        </div>
    @else
        @section('breadcrumbs')
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Tournaments', 'href' => route('tournaments')],
                ['label' => $tournament->name, 'href' => route('tournamentShow', ['tournament' => $tournament->id])],
                ['label' => 'Bracket'],
            ]" />
        @endsection

        <x-matchday-strip
            eyebrow="{{ $tournament->divisions?->first()?->name ?? $tournament->name }} · {{ $tournament->statusLabel() }}"
            :title="$bracket['hasBracket'] ? 'Bracket' : 'Build the bracket'"
            :description="$bracket['hasBracket']
                ? $tournament->name.' · '.$tournament->formatLabel().' · '.$totalRounds.' rounds'
                : $tournament->name.' · '.$approved->count().' approved teams ready to seed into a '.$tournamentSlots.'-slot '.$tournament->formatLabel().' bracket.'"
            :status="$tournament->statusLabel()"
            :meta="[
                ['icon' => 'geo-alt', 'label' => $tournament->venueName()],
                ['icon' => 'diagram-3', 'label' => $tournament->formatLabel()],
                ['icon' => 'people', 'label' => $approved->count().' approved teams'],
            ]"
        >
            <a class="hf-btn" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}" data-testid="bracket-tournament"><i class="bi bi-arrow-left" aria-hidden="true"></i> Tournament</a>
            <a class="hf-btn" href="{{ route('schedule', ['id' => $tournament->id]) }}" data-testid="bracket-schedule"><i class="bi bi-calendar3" aria-hidden="true"></i> Schedule</a>
            <a class="hf-btn" href="{{ route('standings', ['id' => $tournament->id]) }}" data-testid="bracket-standings"><i class="bi bi-bar-chart" aria-hidden="true"></i> Standings</a>
            @if ($isOrganizer && $bracket['hasBracket'] && $kpis['matchesPlayed'] === 0)
                <div class="hf-btn-group">
                    <button type="button" class="hf-btn hf-btn--sm" wire:click="requestRegenerate" data-testid="bracket-regenerate">Regenerate bracket</button>
                    <button type="button" class="hf-btn hf-btn--sm" wire:click="openEditSlots" data-testid="bracket-edit-slots">Edit slots</button>
                </div>
            @endif
            @if ($isOrganizer && $format === 'round-robin' && $this->canGeneratePlayoffs)
                <button type="button" class="hf-btn hf-btn--primary" wire:click="requestGeneratePlayoffs" data-testid="bracket-generate-playoffs" wire:loading.class="is-loading" wire:loading.attr="aria-busy">
                    Generate playoffs
                </button>
            @endif
        </x-matchday-strip>

        @if (! $bracket['hasBracket'])
            <div class="hf-grid hf-grid--3 hf-mb-3">
                <x-kpi-tile label="Approved teams" :value="$approved->count()" :hint="$tournamentSlots.' bracket slots'" tone="primary" testid="bracket-kpi-approved" />
                <x-kpi-tile label="First-round games" :value="(int) ($tournamentSlots / 2)" hint="Byes fill empty slots" tone="gold" testid="bracket-kpi-games" />
                <x-kpi-tile label="Courts" :value="$courtCount" :hint="$tournament->name" tone="slate" testid="bracket-kpi-courts" />
            </div>

            <div class="hf-card" data-testid="bracket-empty">
                <h2 class="hf-section-title">Seed preview</h2>
                <p class="hf-small hf-muted">Teams are seeded by their registration seed, then the bracket pairs 1–16, 8–9, 5–12, 4–13, and so on. Empty slots become byes.</p>
                <div class="hf-grid hf-grid--2 hf-mt-2">
                    @forelse ($approved as $registration)
                        <div class="hf-flex hf-items-center hf-gap-2 hf-card hf-card--flat" data-testid="bracket-seed-{{ $registration->id }}">
                            <x-team-shield :team="$registration->team" />
                            <span>
                                <strong>{{ $registration->team?->name ?? 'Team' }}</strong>
                                <span class="hf-small hf-muted hf-block">{{ $registration->team?->city ?? 'City TBD' }} · seed {{ $registration->seed ?? 'TBD' }}</span>
                            </span>
                        </div>
                    @empty
                        <div class="hf-empty">
                            <i class="bi bi-people" aria-hidden="true"></i>
                            <h3>No approved teams</h3>
                            <p>Approve at least two teams before generating the bracket.</p>
                        </div>
                    @endforelse
                </div>
                <div class="hf-btn-row hf-mt-3">
                    @if ($isOrganizer && $approved->count() >= 2)
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="requestGenerate" data-testid="bracket-generate" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="requestGenerate,confirm">Generate bracket</span>
                            <span wire:loading wire:target="requestGenerate,confirm">Seeding...</span>
                        </button>
                    @else
                        <button type="button" class="hf-btn" disabled data-testid="bracket-generate-disabled">Generate bracket</button>
                        <p class="hf-small hf-muted hf-mb-0">{{ $isOrganizer ? 'At least two approved teams are required.' : 'Organizer access required.' }}</p>
                    @endif
                </div>
            </div>
        @else
            @if ($bracket['champion'])
                <section class="hf-hero hf-champion hf-mb-3" data-testid="bracket-champion">
                    <span class="hf-eyebrow hf-eyebrow--on-dark">Champion</span>
                    <div class="hf-flex hf-items-center hf-gap-3 hf-mt-1">
                        <x-team-shield :team="$bracket['champion']" size="lg" />
                        <div>
                            <h2 class="hf-hero__name">{{ $bracket['champion']->name }}</h2>
                            <p class="hf-mb-0">{{ $bracket['champion']->coach_name ?? 'Coach TBD' }} · {{ $bracket['champion']->city ?? 'City TBD' }}</p>
                            <p class="hf-mb-0">Road to the title · {{ $totalRounds }} rounds · {{ $tournament->name }}</p>
                        </div>
                    </div>
                </section>
            @endif

            <div class="hf-grid hf-grid--4 hf-mb-3">
                <x-kpi-tile label="Rounds" :value="$totalRounds" :hint="$tournamentSlots.'-team bracket'" tone="primary" testid="bracket-kpi-rounds" />
                <x-kpi-tile label="Played" :value="$kpis['matchesPlayed']" :hint="$kpis['matchesScheduled'].' scheduled'" tone="success" testid="bracket-kpi-played" />
                <x-kpi-tile label="Champion" :value="$bracket['champion']?->shortLabel() ?? 'TBD'" :hint="$bracket['champion']?->name ?? 'Final not played'" tone="gold" testid="bracket-kpi-champion" />
                <x-kpi-tile label="Corrections" :value="$kpis['corrections']" hint="Audited score changes" tone="alert" testid="bracket-kpi-corrections" />
            </div>

            <div class="hf-flex hf-wrap hf-gap-2 hf-mb-2 hf-small hf-muted">
                <span class="hf-chip hf-chip--slate"><i class="bi bi-clock" aria-hidden="true"></i> Scheduled</span>
                <span class="hf-chip hf-chip--success"><i class="bi bi-flag-fill" aria-hidden="true"></i> Final</span>
                <span class="hf-chip hf-chip--danger"><i class="bi bi-shield-x" aria-hidden="true"></i> Forfeit</span>
                <span>{{ $isOrganizer ? 'Select a match to enter or correct a score.' : 'Public bracket view. Scores are read-only for this role.' }}</span>
            </div>

            <div class="hf-arena" data-bracket-arena>
                <div class="hf-arena__head">
                    <div class="hf-arena__intro">
                        <span class="hf-eyebrow hf-eyebrow--on-dark">{{ $hasTree ? 'Road to the title' : 'Matchday board' }}</span>
                        <p class="hf-arena__summary">
                            <strong>{{ $playedGames }}</strong> of {{ $totalGames }} games final
                            <span aria-hidden="true">·</span> {{ $approved->count() }} teams
                            <span aria-hidden="true">·</span> {{ $totalRounds }} {{ $hasTree ? 'rounds' : 'matchdays' }}
                        </p>
                    </div>
                    @if ($hasTree)
                        <div class="hf-arena__tools">
                            <div class="hf-arena__zoom" role="group" aria-label="Bracket zoom">
                                <button type="button" class="hf-arena__tool" data-bracket-zoom="out" aria-label="Zoom out"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
                                <button type="button" class="hf-arena__tool" data-bracket-zoom="reset">Fit</button>
                                <button type="button" class="hf-arena__tool" data-bracket-zoom="in" aria-label="Zoom in"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                            </div>
                            <button type="button" class="hf-arena__tool hf-arena__tool--gold" data-bracket-jump="final">
                                <i class="bi bi-trophy" aria-hidden="true"></i> Jump to final
                            </button>
                        </div>
                    @endif
                </div>

                <div
                    class="hf-arena__progress"
                    role="progressbar"
                    aria-label="Bracket progress"
                    aria-valuemin="0"
                    aria-valuemax="{{ max(1, $totalGames) }}"
                    aria-valuenow="{{ $playedGames }}"
                >
                    <span style="width: {{ $progressPercent }}%"></span>
                </div>

                @foreach ($sections as $section)
                    @php
                        $sectionGames = collect($section['rounds'])->sum(fn ($round) => $round['matches']->count());
                        $sectionRounds = count($section['rounds']);
                        $isGoldSection = $section['key'] === 'grand_final';
                    @endphp
                    <section class="hf-bracket-section" data-testid="bracket-section-{{ $section['key'] }}">
                        <header class="hf-bracket-section__head">
                            <h2 class="hf-bracket-section__title{{ $isGoldSection ? ' hf-bracket-section__title--gold' : '' }}">{{ $section['title'] }}</h2>
                            <span class="hf-bracket-section__meta">
                                {{ $sectionRounds }} {{ \Illuminate\Support\Str::plural('round', $sectionRounds) }}
                                <span aria-hidden="true">·</span>
                                {{ $sectionGames }} {{ \Illuminate\Support\Str::plural('game', $sectionGames) }}
                            </span>
                        </header>

                        @if ($section['key'] === 'grand_final' && $tournament->isModifiedDoubleElimination())
                            <p class="hf-small hf-bracket-section__note" data-testid="bracket-grand-final-note">
                                Best of {{ $tournament->grandFinalBestOf() }} — the series winner takes the title. No reset game is played.
                            </p>
                        @endif

                        <div class="hf-bracket-viewport" data-bracket-viewport>
                            <div
                                class="hf-bracket {{ $section['key'] === 'group' ? 'hf-bracket--matchdays' : 'hf-bracket--tree' }}"
                                data-testid="bracket-tree"
                                @if ($section['key'] !== 'group') data-bracket-canvas @endif
                                wire:loading.class="is-updating"
                                wire:loading.attr="aria-busy"
                            >
                                <div class="hf-updating-pill" wire:loading wire:target="requestGenerate,requestGeneratePlayoffs,requestRegenerate,saveSlots,addScore,undoScore,requestFinalizeScore,requestCorrection,confirm">
                                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Updating…
                                </div>

                                @if ($section['key'] === 'group')
                                    <div class="hf-bracket-matchdays">
                                        @foreach ($section['rounds'] as $round)
                                            @php
                                                $roundGames = $round['matches']->count();
                                                $roundDone = $round['matches']->filter(fn ($match) => in_array($match->status, ['final', 'forfeit'], true))->count();
                                                $roundTitleId = 'bracket-round-'.$section['key'].'-'.$loop->index;
                                            @endphp
                                            <section class="hf-matchday-block" aria-labelledby="{{ $roundTitleId }}">
                                                <div class="hf-matchday-block__head">
                                                    <h3 class="hf-matchday-block__title" id="{{ $roundTitleId }}">{{ $round['label'] }}</h3>
                                                    <span class="hf-matchday-block__meta">{{ $roundGames }} {{ \Illuminate\Support\Str::plural('game', $roundGames) }} · {{ $roundDone }} final</span>
                                                </div>
                                                <div class="hf-matchday-block__grid">
                                                    @foreach ($round['matches'] as $match)
                                                        <x-bracket-match
                                                            :match="$match"
                                                            :round-label="$round['label']"
                                                            :rescheduled="in_array((int) $match->id, $this->rescheduledMatchIds, true)"
                                                        />
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="hf-bracket-tree" data-bracket-tree>
                                        <svg class="hf-bracket-lines" data-bracket-lines aria-hidden="true" wire:ignore></svg>

                                        @foreach ($section['rounds'] as $round)
                                            @php
                                                $roundGames = $round['matches']->count();
                                                $roundDone = $round['matches']->filter(fn ($match) => in_array($match->status, ['final', 'forfeit'], true))->count();
                                                $roundPercent = $roundGames > 0 ? (int) round(($roundDone / $roundGames) * 100) : 0;
                                                $isFeaturedRound = $loop->last && in_array($section['key'], ['main', 'playoffs', 'grand_final'], true);
                                            @endphp
                                            <div class="hf-bracket-round" data-bracket-round="{{ $loop->index }}" data-round-label="{{ $round['label'] }}">
                                                <div class="hf-bracket-round-title">
                                                    <span class="hf-bracket-round-title__label">{{ $round['label'] }}</span>
                                                    <span class="hf-bracket-round-title__meta">{{ $roundGames }} {{ \Illuminate\Support\Str::plural('game', $roundGames) }}</span>
                                                    <span class="hf-bracket-round-title__meter" aria-hidden="true"><span style="width: {{ $roundPercent }}%"></span></span>
                                                </div>
                                                <div class="hf-bracket-round__body">
                                                    @foreach ($round['matches'] as $match)
                                                        <x-bracket-match
                                                            :match="$match"
                                                            :round-label="$round['label']"
                                                            :featured="$isFeaturedRound"
                                                            :rescheduled="in_array((int) $match->id, $this->rescheduledMatchIds, true)"
                                                        />
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <span class="hf-bracket-viewport__fade" aria-hidden="true"></span>
                        </div>
                    </section>
                @endforeach

                <div class="hf-arena__foot">
                    @if ($hasTree)
                        <span class="hf-arena__hint"><i class="bi bi-bezier2" aria-hidden="true"></i> Hover a matchup to trace its road to the title.</span>
                    @else
                        <span class="hf-arena__hint"><i class="bi bi-calendar3" aria-hidden="true"></i> Every matchday, every game, in one board.</span>
                    @endif
                    <span>Live results update the board automatically.</span>
                </div>
            </div>

            <div
                class="hf-bracket-mobile"
                data-testid="bracket-mobile"
                x-data="{
                    mobileRound: 0,
                    totalRounds: {{ $mobileRoundCount }},
                    touchStartX: null,
                    touchStartY: null,
                    goToRound(index, focusTab = false) {
                        this.mobileRound = Math.max(0, Math.min(this.totalRounds - 1, index));

                        if (! focusTab) return;

                        this.$nextTick(() => {
                            if (! this.$refs.tabs) return;
                            this.$refs.tabs.querySelectorAll('[role=tab]')[this.mobileRound]?.focus();
                        });
                    },
                    onTouchStart(event) {
                        const touch = event.changedTouches[0];
                        this.touchStartX = touch.clientX;
                        this.touchStartY = touch.clientY;
                    },
                    onTouchEnd(event) {
                        if (this.touchStartX === null) return;

                        const touch = event.changedTouches[0];
                        const dx = touch.clientX - this.touchStartX;
                        const dy = touch.clientY - this.touchStartY;
                        this.touchStartX = null;
                        this.touchStartY = null;

                        if (Math.abs(dx) < 50 || Math.abs(dx) <= Math.abs(dy)) return;

                        this.goToRound(this.mobileRound + (dx < 0 ? 1 : -1));
                    }
                }"
                x-cloak
                x-init="mobileRound = Math.min(mobileRound, {{ max(0, $mobileRoundCount - 1) }})"
            >
                <div class="hf-round-stepper">
                    <div
                        class="hf-round-pager"
                        role="tablist"
                        aria-label="Bracket rounds"
                        x-ref="tabs"
                        x-on:keydown.arrow-right.prevent="goToRound(mobileRound + 1, true)"
                        x-on:keydown.arrow-left.prevent="goToRound(mobileRound - 1, true)"
                        x-on:keydown.home.prevent="goToRound(0, true)"
                        x-on:keydown.end.prevent="goToRound(totalRounds - 1, true)"
                    >
                        @foreach ($mobileRounds as $index => $round)
                            <button
                                type="button"
                                role="tab"
                                id="bracket-round-tab-{{ $index }}"
                                aria-controls="bracket-round-panel-{{ $index }}"
                                aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                                tabindex="{{ $index === 0 ? '0' : '-1' }}"
                                x-bind:aria-selected="mobileRound === {{ $index }} ? 'true' : 'false'"
                                x-bind:tabindex="mobileRound === {{ $index }} ? 0 : -1"
                                x-bind:class="{ 'is-active': mobileRound === {{ $index }} }"
                                x-on:click="goToRound({{ $index }}, true)"
                                data-testid="bracket-round-tab-{{ $index }}"
                            >{{ $round['label'] }}</button>
                        @endforeach
                    </div>
                    <div
                        class="hf-round-progress"
                        role="progressbar"
                        aria-label="Round progress"
                        aria-valuemin="1"
                        aria-valuemax="{{ max(1, $mobileRoundCount) }}"
                        aria-valuenow="1"
                        x-bind:aria-valuenow="mobileRound + 1"
                    >
                        <span x-bind:style="'width: ' + (((mobileRound + 1) / {{ max(1, $mobileRoundCount) }}) * 100) + '%'"></span>
                    </div>
                </div>

                <div class="hf-round-panels" x-on:touchstart.passive="onTouchStart($event)" x-on:touchend.passive="onTouchEnd($event)">
                    @foreach ($mobileRounds as $index => $round)
                        <div
                            id="bracket-round-panel-{{ $index }}"
                            role="tabpanel"
                            aria-labelledby="bracket-round-tab-{{ $index }}"
                            x-show="mobileRound === {{ $index }}"
                            x-cloak
                        >
                            <div class="hf-round-panel__head">
                                <h3 class="hf-round-panel__title">{{ $round['label'] }}</h3>
                                <span class="hf-small hf-muted">{{ $round['matches']->count() }} {{ \Illuminate\Support\Str::plural('game', $round['matches']->count()) }}</span>
                            </div>
                            @foreach ($round['matches'] as $match)
                                <x-bracket-match
                                    :match="$match"
                                    :round-label="$round['label']"
                                    :mobile="true"
                                    :featured="$index === $featuredMobileIndex"
                                    :rescheduled="in_array((int) $match->id, $this->rescheduledMatchIds, true)"
                                />
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="hf-between hf-mt-2">
                    <button type="button" class="hf-btn hf-btn--sm" x-on:click="goToRound(mobileRound - 1)" x-bind:disabled="mobileRound === 0" @if ($mobileRoundCount <= 1) disabled @endif data-testid="bracket-prev-round">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
                    </button>
                    <span class="hf-small hf-muted" x-text="(mobileRound + 1) + ' of {{ $mobileRoundCount }}'"></span>
                    <button type="button" class="hf-btn hf-btn--sm" x-on:click="goToRound(mobileRound + 1)" x-bind:disabled="mobileRound === {{ max(0, $mobileRoundCount - 1) }}" @if ($mobileRoundCount <= 1) disabled @endif data-testid="bracket-next-round">
                        Next <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="hf-card hf-card--flat hf-mt-3">
                <p class="hf-small hf-muted hf-mb-0">Bracket progress is computed from match results. A correction that changes a winner rewrites the next-round slot, team record, standings, and audit trail in one action.</p>
            </div>
        @endif
    @endif

    @if ($showEditSlots)
        <dialog
            class="hf-dialog hf-dialog--wide"
            x-data
            x-init="if (! $el.open) $el.showModal()"
            x-on:cancel.prevent="$wire.closeEditSlots()"
            x-on:click="if ($event.target === $el) $wire.closeEditSlots()"
            wire:key="bracket-edit-slots-dialog"
            data-testid="bracket-edit-slots-dialog"
            aria-labelledby="bracketEditSlotsDialogTitle">
            <div class="hf-dialog__head">
                <h2 class="hf-dialog__title" id="bracketEditSlotsDialogTitle">Edit bracket slots</h2>
                <button type="button" class="hf-icon-btn" wire:click="closeEditSlots" aria-label="Close dialog" data-testid="dialog-close">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="hf-dialog__body">
                <p class="hf-small hf-muted">Reassign approved teams to seed positions. Save to rebuild the bracket.</p>
                @error('slots')
                    <p class="hf-field-error hf-mb-2">{{ $message }}</p>
                @enderror
                <div class="hf-table-wrap">
                    <table class="hf-table">
                        <caption class="hf-sr-only">Bracket seeds</caption>
                        <thead>
                            <tr>
                                <th scope="col">Seed</th>
                                <th scope="col">Team</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($slotAssignments as $index => $assignment)
                                <tr wire:key="bracket-slot-{{ $assignment['seed'] }}">
                                    <td>Seed {{ $assignment['seed'] }}</td>
                                    <td>
                                        <select class="hf-select" wire:model="slotAssignments.{{ $index }}.team_id" aria-label="Team for seed {{ $assignment['seed'] }}">
                                            <option value="">— bye —</option>
                                            @foreach ($approved as $registration)
                                                <option value="{{ $registration->team_id }}">{{ $registration->team?->name ?? 'Team' }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="hf-dialog__foot">
                <button type="button" class="hf-btn" wire:click="closeEditSlots" data-testid="edit-slots-cancel">Cancel</button>
                <button type="button" class="hf-btn hf-btn--primary" wire:click="saveSlots" data-testid="edit-slots-save">Save slots</button>
            </div>
        </dialog>
    @endif

    @if ($showMatchDetail)
        @php
            $detailMatch = $this->selectedMatch;
            $detailHome = $detailMatch?->homeTeam;
            $detailAway = $detailMatch?->awayTeam;
            $detailSlot = $detailMatch?->matchSlots->first();
            $detailStart = $detailSlot?->start_at;
            $detailActiveGame = $this->selectedActiveGame;
            $detailSeriesAnchor = $this->selectedSeriesAnchor;
            $detailCorrection = $this->selectedCorrection;
            $detailBye = $detailMatch !== null && $detailMatch->status === 'final'
                && (($detailMatch->home_team_id !== null) !== ($detailMatch->away_team_id !== null))
                && $detailMatch->home_score === null
                && $detailMatch->away_score === null;
        @endphp
        @if ($detailMatch)
            <dialog
                class="hf-dialog hf-dialog--wide"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.closeMatchDetail()"
                x-on:click="if ($event.target === $el) $wire.closeMatchDetail()"
                wire:key="bracket-match-dialog-{{ $detailMatch->id }}"
                aria-labelledby="hfDialogTitle1"
                data-testid="bracket-match-detail-dialog"
            >
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title" id="hfDialogTitle1">{{ $this->selectedRoundLabel }} · Slot {{ $detailMatch->match_number }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeMatchDetail" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body">
                    @if ($detailBye)
                        <p class="hf-small hf-muted">This is a bye. The seeded team advances automatically.</p>
                    @endif
                    <div class="hf-card hf-card--flat" data-testid="bracket-match-detail">
                        <div class="hf-between hf-match-team {{ $detailMatch->winner_team_id !== null && (int) $detailMatch->winner_team_id === (int) $detailMatch->home_team_id ? 'is-winner' : '' }}">
                            <span class="hf-flex hf-items-center hf-gap-2">
                                <x-team-shield :team="$detailHome" />
                                <strong>{{ $detailHome?->name ?? 'TBD' }}</strong>
                            </span>
                            <span class="hf-score">{{ $detailMatch->home_score !== null ? $detailMatch->home_score : '–' }}</span>
                        </div>
                        <div class="hf-between hf-match-team {{ $detailMatch->winner_team_id !== null && (int) $detailMatch->winner_team_id === (int) $detailMatch->away_team_id ? 'is-winner' : '' }}">
                            <span class="hf-flex hf-items-center hf-gap-2">
                                <x-team-shield :team="$detailAway" />
                                <strong>{{ $detailAway?->name ?? 'TBD' }}</strong>
                            </span>
                            <span class="hf-score">{{ $detailMatch->away_score !== null ? $detailMatch->away_score : '–' }}</span>
                        </div>
                        <div class="hf-stat-strip hf-mt-2">
                            <div><span>Status</span><strong>{{ $detailMatch->statusLabel() }}</strong></div>
                            <div><span>Court</span><strong>{{ $detailSlot?->court?->name ?? 'TBD' }}</strong></div>
                            <div><span>Date</span><strong>{{ $detailStart?->format('M j, Y') ?? 'TBD' }}</strong></div>
                            <div><span>Tip-off</span><strong>{{ $detailStart?->format('g:i A') ?? 'TBD' }}</strong></div>
                        </div>
                        @if (in_array((int) $detailMatch->id, $this->rescheduledMatchIds, true))
                            <div class="hf-flex hf-wrap hf-items-center hf-gap-2 hf-mt-2" data-testid="bracket-detail-rescheduled-{{ $detailMatch->id }}">
                                <span class="hf-chip hf-chip--warning"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Rescheduled</span>
                                <span class="hf-small hf-muted">This match has been moved from its original slot.</span>
                            </div>
                        @endif
                        @if ($detailSeriesAnchor)
                            <div class="hf-flex hf-wrap hf-items-center hf-gap-2 hf-mt-2" data-testid="bracket-series-summary">
                                <span class="hf-chip hf-chip--slate">Best of {{ $detailSeriesAnchor->best_of }}</span>
                                <strong>{{ $this->selectedSeriesSummary }}</strong>
                                @if ($detailActiveGame && (int) $detailActiveGame->id !== (int) $detailMatch->id)
                                    <a class="hf-btn hf-btn--sm hf-btn--primary" href="{{ route('gameManage', ['match' => $detailActiveGame->id]) }}" data-testid="bracket-active-game">
                                        <i class="bi bi-arrow-right-circle" aria-hidden="true"></i> Game {{ $detailActiveGame->game_number }} is next
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                    @if ($this->selectedSeriesGames->isNotEmpty())
                        <div class="hf-mt-2" data-testid="bracket-series-games">
                            <h3 class="hf-section-title">Series games</h3>
                            <div class="hf-table-wrap">
                                <table class="hf-table hf-table--stack" data-testid="bracket-series-table">
                                    <caption class="hf-sr-only">Series games</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Game</th>
                                            <th scope="col">Score</th>
                                            <th scope="col">Schedule</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->selectedSeriesGames as $game)
                                            @php
                                                $seriesActive = $this->isActiveSeriesGame($game);
                                                $gameStatus = $this->seriesGameStatus($game);
                                            @endphp
                                            <tr
                                                wire:key="series-game-{{ $game->id }}"
                                                class="{{ $seriesActive ? 'is-active' : '' }}"
                                                @if ($seriesActive) data-testid="bracket-series-active-{{ $game->id }}" @endif
                                            >
                                                <td data-label="Game">
                                                    <span>
                                                        <strong>Game {{ $game->game_number }}</strong>
                                                        <span class="hf-small hf-muted">of {{ $game->best_of }}</span>
                                                    </span>
                                                </td>
                                                <td data-label="Score">{{ $game->home_score ?? '–' }}–{{ $game->away_score ?? '–' }}</td>
                                                <td class="hf-small" data-label="Schedule">{{ $this->seriesGameSchedule($game) }}</td>
                                                <td data-label="Status">
                                                    <span>
                                                        <span class="hf-chip hf-chip--{{ $this->seriesGameStatusTone($game) }}">{{ $this->seriesGameStatusLabel($game) }}</span>
                                                    </span>
                                                </td>
                                                <td data-label="Action">
                                                    <div class="hf-btn-row">
                                                        @if ($isOrganizer)
                                                            @if ($seriesActive && $gameStatus === 'scheduled')
                                                                <a
                                                                    class="hf-btn hf-btn--sm hf-btn--primary"
                                                                    href="{{ route('gameManage', ['match' => $game->id]) }}"
                                                                    data-testid="series-open-{{ $game->id }}"
                                                                    aria-label="Open scoring for Game {{ $game->game_number }}"
                                                                >Open scoring</a>
                                                                <button
                                                                    type="button"
                                                                    class="hf-btn hf-btn--sm"
                                                                    wire:click="openScoreConsole({{ $game->id }})"
                                                                    data-testid="series-score-{{ $game->id }}"
                                                                    aria-label="Enter final score for Game {{ $game->game_number }}"
                                                                >Enter final score</button>
                                                            @elseif ($gameStatus === 'scheduled')
                                                                <button
                                                                    type="button"
                                                                    class="hf-btn hf-btn--sm"
                                                                    wire:click="openScoreConsole({{ $game->id }})"
                                                                    data-testid="series-score-{{ $game->id }}"
                                                                    aria-label="Enter final score for Game {{ $game->game_number }}"
                                                                >Enter final score</button>
                                                            @elseif ($gameStatus === 'in_progress')
                                                                <a
                                                                    class="hf-btn hf-btn--sm hf-btn--primary"
                                                                    href="{{ route('gameManage', ['match' => $game->id]) }}"
                                                                    data-testid="series-resume-{{ $game->id }}"
                                                                    aria-label="Resume scoring for Game {{ $game->game_number }}"
                                                                >Resume scoring</a>
                                                            @elseif (in_array($gameStatus, ['final', 'forfeit'], true))
                                                                <button
                                                                    type="button"
                                                                    class="hf-btn hf-btn--sm hf-btn--danger"
                                                                    wire:click="openCorrection({{ $game->id }})"
                                                                    data-testid="series-correct-{{ $game->id }}"
                                                                    aria-label="Correct score for Game {{ $game->game_number }}"
                                                                >Correct score</button>
                                                            @endif
                                                        @endif

                                                        <a
                                                            class="hf-btn hf-btn--sm"
                                                            href="{{ route('gameStats', ['match' => $game->id]) }}"
                                                            data-testid="series-stats-{{ $game->id }}"
                                                            aria-label="View box score for Game {{ $game->game_number }}"
                                                        >View box score</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                    @if ($detailCorrection)
                        <div class="hf-card hf-card--flat hf-mt-2">
                            <span class="hf-chip hf-chip--danger">Corrected</span>
                            <p class="hf-small hf-muted hf-mt-1 hf-mb-0">{{ $detailCorrection['reason'] }} · previous {{ $detailCorrection['previous1'] ?? '–' }}–{{ $detailCorrection['previous2'] ?? '–' }}</p>
                        </div>
                    @endif
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="closeMatchDetail" data-testid="bracket-match-close">Close</button>
                    <a class="hf-btn" href="{{ route('gameStats', ['match' => $detailMatch->id]) }}" data-testid="bracket-match-stats">View box score</a>
                    @if ($isOrganizer)
                        @if ($this->selectedSeriesGames->isNotEmpty())
                            <button type="button" class="hf-btn" wire:click="openGameChooser({{ $detailMatch->id }})" data-testid="bracket-in-game">
                                <i class="bi bi-clipboard-pulse" aria-hidden="true"></i> In-game score &amp; player stats
                            </button>
                        @else
                            <a class="hf-btn" href="{{ route('gameManage', ['match' => $detailMatch->id]) }}" data-testid="bracket-in-game">
                                <i class="bi bi-clipboard-pulse" aria-hidden="true"></i> In-game score &amp; player stats
                            </a>
                        @endif
                    @endif
                    @if ($isOrganizer && $this->selectedCanScore && $detailActiveGame)
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="openScoreConsole({{ $detailActiveGame->id }})" data-testid="bracket-enter-score">Enter final score</button>
                    @endif
                    @if ($isOrganizer && $this->selectedCanCorrect)
                        <button type="button" class="hf-btn hf-btn--danger" wire:click="openCorrection({{ $detailMatch->id }})" data-testid="bracket-correct-score">Correct score</button>
                    @endif
                </div>
            </dialog>
        @endif
    @endif

    @if ($showGameChooser)
        <dialog
            class="hf-dialog hf-dialog--wide"
            x-data
            x-init="if (! $el.open) $el.showModal()"
            x-on:cancel.prevent="$wire.closeGameChooser()"
            x-on:click="if ($event.target === $el) $wire.closeGameChooser()"
            wire:key="bracket-game-chooser"
            data-testid="bracket-game-chooser"
            aria-labelledby="bracketGameChooserTitle">
            <div class="hf-dialog__head">
                <h2 class="hf-dialog__title" id="bracketGameChooserTitle">Choose game · {{ $this->chooserSummary }}</h2>
                <button type="button" class="hf-icon-btn" wire:click="closeGameChooser" aria-label="Close dialog" data-testid="dialog-close">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="hf-dialog__body">
                <div class="hf-stack hf-gap-2">
                    @foreach ($this->chooserGames as $game)
                        @php
                            $activeGame = $this->chooserAnchor?->activeGame();
                            $isActive = $activeGame instanceof \App\Models\MatchModel && (int) $activeGame->id === (int) $game->id;
                            $gameStatus = $this->seriesGameStatus($game);
                        @endphp
                        <div class="hf-card hf-card--flat" data-testid="chooser-game-{{ $game->id }}">
                            <div class="hf-between hf-wrap hf-gap-2">
                                <div>
                                    <strong>{{ $game->seriesGameLabel() }}</strong>
                                    <p class="hf-small hf-muted hf-mb-0">
                                        {{ $game->home_score ?? '–' }}–{{ $game->away_score ?? '–' }}
                                        · {{ $this->seriesGameSchedule($game) }}
                                        · {{ $this->seriesGameStatusLabel($game) }}
                                    </p>
                                </div>
                                <div class="hf-btn-row">
                                    @if ($isActive && $gameStatus === 'scheduled')
                                        <a class="hf-btn hf-btn--sm hf-btn--primary" href="{{ route('gameManage', ['match' => $game->id]) }}">Open scoring</a>
                                    @elseif ($gameStatus === 'in_progress')
                                        <a class="hf-btn hf-btn--sm hf-btn--primary" href="{{ route('gameManage', ['match' => $game->id]) }}">Resume scoring</a>
                                    @elseif ($gameStatus === 'conditional')
                                        <button type="button" class="hf-btn hf-btn--sm" disabled>Available after Game {{ $game->game_number - 1 }} is final</button>
                                    @elseif ($gameStatus === 'cancelled')
                                        <button type="button" class="hf-btn hf-btn--sm" disabled>Series ended. This game is not needed.</button>
                                    @endif
                                    <a class="hf-btn hf-btn--sm" href="{{ route('gameStats', ['match' => $game->id]) }}">View box score</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="hf-dialog__foot">
                <button type="button" class="hf-btn" wire:click="closeGameChooser">Close</button>
            </div>
        </dialog>
    @endif

    @if ($showScoreConsole)
        @php
            $scoreMatch = $this->selectedMatch;
            $scoreHome = $scoreMatch?->homeTeam;
            $scoreAway = $scoreMatch?->awayTeam;
            $display1 = $forfeitSide === 2 ? 20 : ($forfeitSide === 1 ? 0 : (int) ($score1 ?? 0));
            $display2 = $forfeitSide === 1 ? 20 : ($forfeitSide === 2 ? 0 : (int) ($score2 ?? 0));
        @endphp
        @if ($scoreMatch)
            <dialog
                class="hf-dialog hf-dialog--wide"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.closeScoreConsole()"
                x-on:click="if ($event.target === $el) $wire.closeScoreConsole()"
                wire:key="bracket-score-dialog-{{ $scoreMatch->id }}"
                aria-labelledby="hfDialogTitle2"
                data-testid="bracket-score-dialog"
                data-score-console
                tabindex="-1"
            >
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title" id="hfDialogTitle2">{{ $scoreHome?->shortLabel() ?? 'TBD' }} vs {{ $scoreAway?->shortLabel() ?? 'TBD' }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeScoreConsole" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-small hf-muted">{{ $this->selectedRoundLabel }} · Slot {{ $scoreMatch->match_number }}. Use the score buttons or a forfeit, then finalize. The winner advances immediately.</p>
                    <div class="hf-grid hf-grid--2">
                        <div class="hf-card hf-card--flat" data-team-panel="1" data-score-team="1">
                            <span class="hf-flex hf-items-center hf-gap-2">
                                <x-team-shield :team="$scoreHome" />
                                <strong>{{ $scoreHome?->name ?? 'TBD' }}</strong>
                            </span>
                            <div class="hf-score hf-score--xl" data-score-display="1">{{ $display1 }}</div>
                            <label class="hf-label" for="scoreInput1">Final score</label>
                            <input id="scoreInput1" type="number" min="0" max="199" inputmode="numeric" class="hf-input hf-mb-2" wire:model.blur="score1" value="{{ $display1 }}" data-score-input="1" data-testid="score-input-1" @if ($errors->has('score1') || $errors->has('score2')) aria-invalid="true" aria-describedby="bracketScoreError" @endif @disabled($forfeitSide !== 0)>
                            <div class="hf-btn-row">
                                @foreach ([1, 2, 3] as $points)
                                    <button type="button" class="hf-btn" wire:click="addScore(1, {{ $points }})" data-add-score data-team="1" data-points="{{ $points }}" data-testid="score-add-1-{{ $points }}" @disabled($forfeitSide !== 0) wire:loading.attr="disabled">+{{ $points }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="hf-card hf-card--flat" data-team-panel="2" data-score-team="2">
                            <span class="hf-flex hf-items-center hf-gap-2">
                                <x-team-shield :team="$scoreAway" />
                                <strong>{{ $scoreAway?->name ?? 'TBD' }}</strong>
                            </span>
                            <div class="hf-score hf-score--xl" data-score-display="2">{{ $display2 }}</div>
                            <label class="hf-label" for="scoreInput2">Final score</label>
                            <input id="scoreInput2" type="number" min="0" max="199" inputmode="numeric" class="hf-input hf-mb-2" wire:model.blur="score2" value="{{ $display2 }}" data-score-input="2" data-testid="score-input-2" @if ($errors->has('score1') || $errors->has('score2')) aria-invalid="true" aria-describedby="bracketScoreError" @endif @disabled($forfeitSide !== 0)>
                            <div class="hf-btn-row">
                                @foreach ([1, 2, 3] as $points)
                                    <button type="button" class="hf-btn" wire:click="addScore(2, {{ $points }})" data-add-score data-team="2" data-points="{{ $points }}" data-testid="score-add-2-{{ $points }}" @disabled($forfeitSide !== 0) wire:loading.attr="disabled">+{{ $points }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="hf-card hf-card--flat hf-mt-2">
                        <label class="hf-label" for="scoreForfeit">Forfeit</label>
                        <select class="hf-select" id="scoreForfeit" wire:model.live="forfeitSide" data-testid="score-forfeit">
                            <option value="0">No forfeit</option>
                            <option value="1">{{ $scoreHome?->name ?? 'Home' }} forfeits · {{ $scoreAway?->shortLabel() ?? 'Away' }} wins 20–0</option>
                            <option value="2">{{ $scoreAway?->name ?? 'Away' }} forfeits · {{ $scoreHome?->shortLabel() ?? 'Home' }} wins 20–0</option>
                        </select>
                        @if ($forfeitSide !== 0)
                            <p class="hf-small hf-muted hf-mt-1 hf-mb-0">FIBA forfeit score is 20–0. Score buttons are disabled while a forfeit is selected.</p>
                        @endif
                        @if ($errors->has('score1') || $errors->has('score2'))
                            <p class="hf-field-error hf-mt-1" id="bracketScoreError">{{ $errors->first('score1') ?: $errors->first('score2') }}</p>
                        @endif
                    </div>
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="undoScore" data-testid="score-undo" @disabled($forfeitSide !== 0 || $scoreHistory === [])>Undo</button>
                    <button type="button" class="hf-btn" wire:click="closeScoreConsole" data-testid="score-cancel">Cancel</button>
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="requestFinalizeScore" data-testid="score-finalize" wire:loading.attr="disabled">Finalize score</button>
                </div>
            </dialog>
        @endif
    @endif

    @if ($showCorrection)
        @php
            $correctionMatch = $this->selectedMatch;
            $correctionHome = $correctionMatch?->homeTeam;
            $correctionAway = $correctionMatch?->awayTeam;
            $correction = $this->selectedCorrection;
        @endphp
        @if ($correctionMatch)
            <dialog
                class="hf-dialog hf-dialog--wide"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.closeCorrection()"
                x-on:click="if ($event.target === $el) $wire.closeCorrection()"
                wire:key="bracket-correction-dialog-{{ $correctionMatch->id }}"
                aria-labelledby="hfDialogTitle3"
                data-testid="bracket-correction-dialog"
            >
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title" id="hfDialogTitle3">Correct score · {{ $correctionHome?->shortLabel() ?? 'Home' }} vs {{ $correctionAway?->shortLabel() ?? 'Away' }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeCorrection" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-small hf-muted">Current result: {{ $correctionMatch->home_score ?? '–' }}–{{ $correctionMatch->away_score ?? '–' }}@if ($correction) (corrected from {{ $correction['previous1'] ?? '–' }}–{{ $correction['previous2'] ?? '–' }})@endif. A correction requires a reason and rewrites the downstream bracket if the winner changes.</p>
                    <div class="hf-form-grid">
                        <div class="hf-field">
                            <label class="hf-label" for="correctionScore1">{{ $correctionHome?->name ?? 'Home' }}</label>
                            <input class="hf-input" id="correctionScore1" type="number" min="0" wire:model.blur="score1" data-testid="correction-score-1" @if ($errors->any()) aria-invalid="true" aria-describedby="bracketCorrectionError" @endif>
                        </div>
                        <div class="hf-field">
                            <label class="hf-label" for="correctionScore2">{{ $correctionAway?->name ?? 'Away' }}</label>
                            <input class="hf-input" id="correctionScore2" type="number" min="0" wire:model.blur="score2" data-testid="correction-score-2" @if ($errors->any()) aria-invalid="true" aria-describedby="bracketCorrectionError" @endif>
                        </div>
                        <div class="hf-field hf-field--full">
                            <label class="hf-label" for="correctionReason">Reason</label>
                            <textarea class="hf-textarea" id="correctionReason" rows="3" placeholder="Shot-clock correction after referee review." wire:model.blur="reason" data-testid="correction-reason" @if ($errors->any()) aria-invalid="true" aria-describedby="bracketCorrectionError" @endif></textarea>
                        </div>
                    </div>
                    @if ($errors->any())
                        <p class="hf-field-error hf-mt-1" id="bracketCorrectionError">{{ $errors->first() }}</p>
                    @endif
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="closeCorrection" data-testid="correction-cancel">Cancel</button>
                    <button type="button" class="hf-btn hf-btn--danger" wire:click="requestCorrection" data-testid="correction-save" wire:loading.attr="disabled">Save correction</button>
                </div>
            </dialog>
        @endif
    @endif

    @if ($showConfirm)
        <dialog
            class="hf-dialog"
            x-data
            x-init="if (! $el.open) $el.showModal()"
            x-on:cancel.prevent="$wire.cancelConfirm()"
            x-on:click="if ($event.target === $el) $wire.cancelConfirm()"
            wire:key="bracket-confirm-dialog"
            data-testid="bracket-confirm-dialog"
            aria-labelledby="bracketConfirmDialogTitle">
            <div class="hf-dialog__head">
                <h2 class="hf-dialog__title" id="bracketConfirmDialogTitle">{{ $confirmTitle }}</h2>
                <button type="button" class="hf-icon-btn" wire:click="cancelConfirm" aria-label="Close dialog" data-testid="dialog-close">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="hf-dialog__body">
                <p class="hf-mb-0">{{ $confirmMessage }}</p>
            </div>
            <div class="hf-dialog__foot">
                <button type="button" class="hf-btn" wire:click="cancelConfirm" data-testid="confirm-cancel">Cancel</button>
                <button type="button" class="hf-btn hf-btn--{{ $confirmTone }}" wire:click="confirm" data-testid="confirm-ok" wire:loading.attr="disabled">{{ $confirmLabel }}</button>
            </div>
        </dialog>
    @endif

</div>
