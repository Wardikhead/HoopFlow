<div
    x-data="{
        mobileRound: 0,
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
>
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
                        {{ $tournament->name }} · {{ $format }} · {{ $totalRounds }} rounds
                    @else
                        {{ $tournament->name }} · {{ $approved->count() }} approved teams ready to seed into a {{ $tournamentSlots }}-slot {{ $format }} bracket.
                    @endif
                </p>
            </div>
            <div class="hf-page-actions">
                <a class="hf-btn" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}" data-testid="bracket-tournament"><i class="bi bi-arrow-left"></i> Tournament</a>
                <a class="hf-btn" href="{{ route('schedule', ['id' => $tournament->id]) }}" data-testid="bracket-schedule"><i class="bi bi-calendar3"></i> Schedule</a>
                <a class="hf-btn" href="{{ route('standings', ['id' => $tournament->id]) }}" data-testid="bracket-standings"><i class="bi bi-bar-chart"></i> Standings</a>
                @if ($isOrganizer && $bracket['hasBracket'] && $kpis['matchesPlayed'] === 0)
                    <button type="button" class="hf-btn" wire:click="requestRegenerate" data-testid="bracket-regenerate">Regenerate bracket</button>
                    <button type="button" class="hf-btn" wire:click="openEditSlots" data-testid="bracket-edit-slots">Edit slots</button>
                @endif
                @if ($isOrganizer && $format === 'round-robin' && $this->canGeneratePlayoffs)
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="requestGeneratePlayoffs" data-testid="bracket-generate-playoffs">
                        Generate playoffs
                    </button>
                @endif
            </div>
        </header>

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
                                <span class="hf-small hf-muted" style="display:block">{{ $registration->team?->city ?? 'City TBD' }} · seed {{ $registration->seed ?? 'TBD' }}</span>
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
                <section class="hf-bracket-champion hf-mb-3" data-testid="bracket-champion">
                    <span class="hf-eyebrow" style="color: var(--hf-gold)">Champion</span>
                    <div class="hf-flex hf-items-center hf-gap-3 hf-mt-1">
                        <x-team-shield :team="$bracket['champion']" size="lg" />
                        <div>
                            <h1>{{ $bracket['champion']->name }}</h1>
                            <p class="hf-mb-0">{{ $bracket['champion']->coach_name ?? 'Coach TBD' }} · {{ $bracket['champion']->city ?? 'City TBD' }}</p>
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
                <span class="hf-chip hf-chip--slate"><i class="bi bi-clock"></i> Scheduled</span>
                <span class="hf-chip hf-chip--success"><i class="bi bi-flag-fill"></i> Final</span>
                <span class="hf-chip hf-chip--danger"><i class="bi bi-shield-x"></i> Forfeit</span>
                <span>{{ $isOrganizer ? 'Select a match to enter or correct a score.' : 'Public bracket view. Scores are read-only for this role.' }}</span>
            </div>

            @foreach ($sections as $section)
                <section class="hf-mb-3" data-testid="bracket-section-{{ $section['key'] }}">
                    <h2 class="hf-section-title">{{ $section['title'] }}</h2>
                    <div class="hf-bracket" data-testid="bracket-tree">
                        @foreach ($section['rounds'] as $round)
                            <div class="hf-bracket-round">
                                <div class="hf-bracket-round-title">{{ $round['label'] }} <span class="hf-small hf-muted">{{ $round['matches']->count() }}</span></div>
                                @foreach ($round['matches'] as $match)
                                    <x-bracket-match :match="$match" :round-label="$round['label']" />
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            @php $roundCount = $mobileRounds->count(); @endphp
            <div class="hf-bracket-mobile" data-testid="bracket-mobile" x-cloak x-init="mobileRound = Math.min(mobileRound, {{ max(0, $roundCount - 1) }})">
                <div class="hf-round-pager">
                    @foreach ($mobileRounds as $index => $round)
                        <button
                            type="button"
                            x-bind:class="{ 'is-active': mobileRound === {{ $index }} }"
                            x-on:click="mobileRound = {{ $index }}"
                            data-testid="bracket-round-tab-{{ $index }}"
                        >{{ $round['label'] }}</button>
                    @endforeach
                </div>
                <div class="hf-round-progress"><span x-bind:style="'width: ' + (((mobileRound + 1) / {{ $roundCount }}) * 100) + '%'"></span></div>
                @foreach ($mobileRounds as $index => $round)
                    <div x-show="mobileRound === {{ $index }}" x-cloak>
                        @foreach ($round['matches'] as $match)
                            <x-bracket-match :match="$match" :round-label="$round['label']" />
                        @endforeach
                    </div>
                @endforeach
                <div class="hf-between hf-mt-2">
                    <button type="button" class="hf-btn hf-btn--sm" x-on:click="mobileRound = Math.max(0, mobileRound - 1)" x-bind:disabled="mobileRound === 0" data-testid="bracket-prev-round">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <span class="hf-small hf-muted" x-text="(mobileRound + 1) + ' of {{ $roundCount }}'"></span>
                    <button type="button" class="hf-btn hf-btn--sm" x-on:click="mobileRound = Math.min({{ $roundCount - 1 }}, mobileRound + 1)" x-bind:disabled="mobileRound === {{ $roundCount - 1 }}" data-testid="bracket-next-round">
                        Next <i class="bi bi-arrow-right"></i>
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
        >
            <div class="hf-dialog__head">
                <h2 class="hf-dialog__title">Edit bracket slots</h2>
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
                data-testid="bracket-match-detail-dialog"
            >
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">{{ $this->selectedRoundLabel }} · Slot {{ $detailMatch->match_number }}</h2>
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
                        @if ($detailSeriesAnchor)
                            <div class="hf-flex hf-wrap hf-items-center hf-gap-2 hf-mt-2" data-testid="bracket-series-summary">
                                <span class="hf-chip hf-chip--slate">Best of {{ $detailSeriesAnchor->best_of }}</span>
                                <strong>{{ $this->selectedSeriesSummary }}</strong>
                                @if ($detailActiveGame && (int) $detailActiveGame->id !== (int) $detailMatch->id)
                                    <a class="hf-btn hf-btn--sm hf-btn--primary" href="{{ route('gameManage', ['match' => $detailActiveGame->id]) }}" data-testid="bracket-active-game">
                                        <i class="bi bi-arrow-right-circle"></i> Game {{ $detailActiveGame->game_number }} is next
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                    @if ($this->selectedSeriesGames->isNotEmpty())
                        <div class="hf-mt-2" data-testid="bracket-series-games">
                            <h3 class="hf-section-title">Series games</h3>
                            <div class="hf-table-wrap">
                                <table class="hf-table">
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
                                            @endphp
                                            <tr
                                                wire:key="series-game-{{ $game->id }}"
                                                class="{{ $seriesActive ? 'is-active' : '' }}"
                                                @if ($seriesActive) data-testid="bracket-series-active-{{ $game->id }}" @endif
                                            >
                                                <td>
                                                    <strong>Game {{ $game->game_number }}</strong>
                                                    <span class="hf-small hf-muted">of {{ $game->best_of }}</span>
                                                </td>
                                                <td>{{ $game->home_score ?? '–' }}–{{ $game->away_score ?? '–' }}</td>
                                                <td class="hf-small">{{ $this->seriesGameSchedule($game) }}</td>
                                                <td>
                                                    <span class="hf-chip hf-chip--{{ $this->seriesGameStatusTone($game) }}">{{ $this->seriesGameStatusLabel($game) }}</span>
                                                </td>
                                                <td>
                                                    @if ($seriesActive && $game->status === 'scheduled')
                                                        <a class="hf-btn hf-btn--sm hf-btn--primary" href="{{ route('gameManage', ['match' => $game->id]) }}" data-testid="series-open-{{ $game->id }}">Open</a>
                                                    @elseif ($game->status === 'scheduled')
                                                        <button type="button" class="hf-btn hf-btn--sm" wire:click="openScoreConsole({{ $game->id }})" data-testid="series-score-{{ $game->id }}">Score</button>
                                                    @elseif (in_array($game->status, ['final', 'forfeit'], true))
                                                        <button type="button" class="hf-btn hf-btn--sm" wire:click="openCorrection({{ $game->id }})" data-testid="series-correct-{{ $game->id }}">Correct</button>
                                                    @elseif ($game->status === 'in_progress')
                                                        <a class="hf-btn hf-btn--sm" href="{{ route('gameStats', ['match' => $game->id]) }}" data-testid="series-stats-{{ $game->id }}">Box score</a>
                                                    @endif
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
                    <a class="hf-btn" href="{{ route('gameStats', ['match' => $detailMatch->id]) }}" data-testid="bracket-match-stats">Game stats</a>
                    @if ($isOrganizer)
                        <a class="hf-btn" href="{{ route('gameManage', ['match' => $detailActiveGame?->id ?? $detailMatch->id]) }}" data-testid="bracket-in-game"><i class="bi bi-clipboard-pulse"></i> In-game score &amp; player stats</a>
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
                data-testid="bracket-score-dialog"
            >
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">{{ $scoreHome?->shortLabel() ?? 'TBD' }} vs {{ $scoreAway?->shortLabel() ?? 'TBD' }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeScoreConsole" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-small hf-muted">{{ $this->selectedRoundLabel }} · Slot {{ $scoreMatch->match_number }}. Use the score buttons or a forfeit, then finalize. The winner advances immediately.</p>
                    <div class="hf-grid hf-grid--2">
                        <div class="hf-card hf-card--flat" data-team-panel="1">
                            <span class="hf-flex hf-items-center hf-gap-2">
                                <x-team-shield :team="$scoreHome" />
                                <strong>{{ $scoreHome?->name ?? 'TBD' }}</strong>
                            </span>
                            <div class="hf-score" data-score-display="1" style="font-size: clamp(48px, 9vw, 78px); line-height: 1">{{ $display1 }}</div>
                            <label class="hf-label" for="scoreInput1">Final score</label>
                            <input id="scoreInput1" type="number" min="0" max="199" inputmode="numeric" class="hf-input hf-mb-2" wire:model.blur="score1" value="{{ $display1 }}" data-score-input="1" data-testid="score-input-1" @disabled($forfeitSide !== 0)>
                            <div class="hf-btn-row">
                                @foreach ([1, 2, 3] as $points)
                                    <button type="button" class="hf-btn" wire:click="addScore(1, {{ $points }})" data-add-score data-team="1" data-points="{{ $points }}" data-testid="score-add-1-{{ $points }}" @disabled($forfeitSide !== 0) wire:loading.attr="disabled">+{{ $points }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="hf-card hf-card--flat" data-team-panel="2">
                            <span class="hf-flex hf-items-center hf-gap-2">
                                <x-team-shield :team="$scoreAway" />
                                <strong>{{ $scoreAway?->name ?? 'TBD' }}</strong>
                            </span>
                            <div class="hf-score" data-score-display="2" style="font-size: clamp(48px, 9vw, 78px); line-height: 1">{{ $display2 }}</div>
                            <label class="hf-label" for="scoreInput2">Final score</label>
                            <input id="scoreInput2" type="number" min="0" max="199" inputmode="numeric" class="hf-input hf-mb-2" wire:model.blur="score2" value="{{ $display2 }}" data-score-input="2" data-testid="score-input-2" @disabled($forfeitSide !== 0)>
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
                            <p class="hf-field-error hf-mt-1">{{ $errors->first('score1') ?: $errors->first('score2') }}</p>
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
                data-testid="bracket-correction-dialog"
            >
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">Correct score · {{ $correctionHome?->shortLabel() ?? 'Home' }} vs {{ $correctionAway?->shortLabel() ?? 'Away' }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeCorrection" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-small hf-muted">Current result: {{ $correctionMatch->home_score ?? '–' }}–{{ $correctionMatch->away_score ?? '–' }}@if ($correction) (corrected from {{ $correction['previous1'] ?? '–' }}–{{ $correction['previous2'] ?? '–' }})@endif. A correction requires a reason and rewrites the downstream bracket if the winner changes.</p>
                    <div class="hf-form-grid">
                        <div class="hf-field">
                            <label class="hf-label" for="correctionScore1">{{ $correctionHome?->name ?? 'Home' }}</label>
                            <input class="hf-input" id="correctionScore1" type="number" min="0" wire:model.blur="score1" data-testid="correction-score-1">
                        </div>
                        <div class="hf-field">
                            <label class="hf-label" for="correctionScore2">{{ $correctionAway?->name ?? 'Away' }}</label>
                            <input class="hf-input" id="correctionScore2" type="number" min="0" wire:model.blur="score2" data-testid="correction-score-2">
                        </div>
                        <div class="hf-field hf-field--full">
                            <label class="hf-label" for="correctionReason">Reason</label>
                            <textarea class="hf-textarea" id="correctionReason" rows="3" placeholder="Shot-clock correction after referee review." wire:model.blur="reason" data-testid="correction-reason"></textarea>
                        </div>
                    </div>
                    @if ($errors->any())
                        <p class="hf-field-error hf-mt-1">{{ $errors->first() }}</p>
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
        >
            <div class="hf-dialog__head">
                <h2 class="hf-dialog__title">{{ $confirmTitle }}</h2>
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
