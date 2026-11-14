<div>
    @if (! $this->match)
        @if ($requestedMatchMissing)
            <div class="hf-empty" data-testid="ingame-missing-match">
                <i class="bi bi-search" aria-hidden="true"></i>
                <h3>Match not found</h3>
                <p>That game is not in this organization, or the link is out of date.</p>
                <a class="hf-btn hf-btn--primary" href="{{ route('gameManage') }}" data-testid="manage-select-match">Browse matchups</a>
            </div>
        @else
            <header class="hf-page-head hf-mb-3">
                <div>
                    <span class="hf-eyebrow">In-game management</span>
                    <h1 class="hf-page-title">Select a match to manage</h1>
                    <p class="hf-page-desc">Filter matchups by tournament, status, and team, then choose one to enter quarter scores and player stats.</p>
                </div>
            </header>

            <section class="hf-card hf-mb-3" data-testid="ingame-picker-filters">
                <div class="hf-grid hf-grid--3">
                    <div class="hf-field">
                        <label class="hf-label" for="ingameTournamentFilter">Tournament</label>
                        <select class="hf-select" id="ingameTournamentFilter" wire:model.live="tournamentFilter" data-testid="ingame-tournament-filter">
                            <option value="all">All tournaments</option>
                            @foreach ($this->tournaments as $tournament)
                                <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="hf-field">
                        <label class="hf-label" for="ingameStatusFilter">Status</label>
                        <select class="hf-select" id="ingameStatusFilter" wire:model.live="statusFilter" data-testid="ingame-status-filter">
                            <option value="all">All statuses</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="final">Final</option>
                        </select>
                    </div>
                    <div class="hf-field">
                        <label class="hf-label" for="ingameTeamSearch">Team search</label>
                        <input class="hf-input" id="ingameTeamSearch" type="search" placeholder="Search teams" wire:model.live.debounce.300ms="teamSearch" data-testid="ingame-team-search">
                    </div>
                </div>
            </section>

            <div class="hf-grid hf-grid--2" data-testid="ingame-match-list">
                @forelse ($this->matches as $match)
                    <x-match-card :match="$match" :href="route('gameManage', ['match' => $match->id])" :testid="'ingame-match-card-'.$match->id" />
                @empty
                    <div class="hf-empty" data-testid="ingame-no-matches">
                        <i class="bi bi-calendar-x" aria-hidden="true"></i>
                        <h3>No matches match</h3>
                        <p>Clear the filters or choose another tournament.</p>
                        <button type="button" class="hf-btn" wire:click="clearFilters" data-testid="ingame-clear-filters">Clear filters</button>
                    </div>
                @endforelse
            </div>
        @endif
    @else
        @php
            $match = $this->match;
            $home = $this->homeTeam;
            $away = $this->awayTeam;
            $locked = $this->isLocked;
            $labels = $this->quarterLabels;
            $teamsReady = $home !== null && $away !== null;
        @endphp

        @section('breadcrumbs')
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Tournaments', 'href' => route('tournaments')],
                ['label' => $match->tournament?->name ?? 'Tournament', 'href' => route('tournamentShow', ['tournament' => $match->tournament_id])],
                ['label' => 'Game scoring'],
            ]" />
        @endsection

        <header class="hf-page-head hf-mb-3">
            <div>
                <span class="hf-eyebrow">{{ $home?->name ?? 'TBD' }} vs {{ $away?->name ?? 'TBD' }}</span>
                <h1 class="hf-page-title">In-game score &amp; player stats</h1>
                <p class="hf-page-desc">
                    {{ $match->roundLabel() }} · Slot {{ $match->match_number }}
                    @if ($match->best_of > 1)
                        · {{ $match->seriesGameLabel() }} · {{ $this->seriesSummary }}
                    @else
                        · {{ $match->statusLabel() }}
                    @endif
                </p>
            </div>
            <div class="hf-page-actions">
                <a class="hf-btn" href="{{ route('bracket', ['id' => $match->tournament_id, 'match' => $match->id]) }}" data-testid="ingame-bracket"><i class="bi bi-diagram-3" aria-hidden="true"></i> Bracket</a>
                <a class="hf-btn" href="{{ route('matches', ['id' => $match->tournament_id]) }}" data-testid="ingame-matches"><i class="bi bi-list-ul" aria-hidden="true"></i> Matchups</a>
                <a class="hf-btn" href="{{ route('gameManage') }}" data-testid="ingame-change-match"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Change match</a>
                <button type="button" class="hf-btn" data-scorekeeper-toggle aria-pressed="false"><i class="bi bi-arrows-fullscreen" aria-hidden="true"></i> Scorekeeper mode</button>
            </div>
        </header>

        @if ($this->seriesAnchor)
            <section class="hf-card hf-card--flat hf-mb-3" data-testid="ingame-series-banner">
                <div class="hf-between hf-wrap">
                    <div>
                        <span class="hf-eyebrow">Best of {{ $this->seriesAnchor->best_of }} · {{ $this->seriesGameLabel }}</span>
                        <h2 class="hf-section-title hf-mb-0">{{ $this->seriesSummary }}</h2>
                        <p class="hf-small hf-muted hf-mb-0"><i class="bi bi-calendar-event"></i> {{ $this->seriesScheduleLabel }}</p>
                    </div>
                    <span class="hf-chip hf-chip--{{ $match->status === 'forfeit' ? 'danger' : ($match->status === 'in_progress' ? 'gold' : ($match->status === 'final' ? 'success' : 'primary')) }}">{{ $match->statusLabel() }}</span>
                </div>
                <div class="hf-btn-row hf-mt-2" data-testid="ingame-series-games">
                    @foreach ($this->seriesGames as $seriesGame)
                        @php
                            $seriesSlot = $seriesGame->matchSlots->first();
                            $isCurrent = (int) $seriesGame->id === (int) $match->id;
                            $tone = $this->seriesStatusTone($seriesGame);
                            $seriesLabel = 'Game '.$seriesGame->game_number.' · '.$this->seriesStatusLabel($seriesGame);
                            $seriesSchedule = $seriesSlot?->start_at !== null
                                ? ($seriesSlot->court?->name ?? 'Court TBD').' '.$seriesSlot->start_at->format('M j, g:i A')
                                : null;
                            $unavailableReason = $this->seriesGameUnavailableReason($seriesGame);
                            $gameStatus = $this->seriesGameStatus($seriesGame);
                        @endphp
                        @if ($isCurrent)
                            <span class="hf-chip hf-chip--{{ $tone }}" data-testid="ingame-series-game-{{ $seriesGame->id }}" aria-current="true">
                                {{ $seriesLabel }}
                                @if ($seriesSchedule)
                                    <span class="hf-small">{{ $seriesSchedule }}</span>
                                @endif
                            </span>
                        @elseif (in_array($gameStatus, ['scheduled', 'in_progress'], true))
                            <a class="hf-chip hf-chip--{{ $tone }}" href="{{ route('gameManage', ['match' => $seriesGame->id]) }}" data-testid="ingame-series-game-{{ $seriesGame->id }}">
                                {{ $seriesLabel }}
                                @if ($seriesSchedule)
                                    <span class="hf-small">{{ $seriesSchedule }}</span>
                                @endif
                            </a>
                        @elseif (in_array($gameStatus, ['final', 'forfeit'], true))
                            <a class="hf-chip hf-chip--{{ $tone }}" href="{{ route('gameStats', ['match' => $seriesGame->id]) }}" data-testid="ingame-series-game-{{ $seriesGame->id }}">
                                {{ $seriesLabel }}
                                @if ($seriesSchedule)
                                    <span class="hf-small">{{ $seriesSchedule }}</span>
                                @endif
                            </a>
                        @else
                            <span class="hf-chip hf-chip--{{ $tone }}" data-testid="ingame-series-game-{{ $seriesGame->id }}" aria-disabled="true">
                                {{ $seriesLabel }}
                                @if ($unavailableReason)
                                    <span class="hf-small">{{ $unavailableReason }}</span>
                                @endif
                                @if ($seriesSchedule)
                                    <span class="hf-small">{{ $seriesSchedule }}</span>
                                @endif
                            </span>
                        @endif
                    @endforeach
                </div>
                @if ($this->nextSeriesSummary !== '')
                    <p class="hf-small hf-mt-2 hf-mb-0" data-testid="ingame-series-next"><i class="bi bi-arrow-right-circle"></i> {{ $this->nextSeriesSummary }}</p>
                @endif
            </section>
        @endif

        @if ($correctionMode)
            <div class="hf-card hf-card--flat hf-mb-3" data-testid="ingame-correction-banner">
                <div class="hf-between hf-wrap">
                    <span class="hf-chip hf-chip--gold"><i class="bi bi-arrow-repeat"></i> Correction mode</span>
                </div>
                <p class="hf-small hf-mt-1 hf-mb-0">You are editing a recorded result. A reason is required, and changes are written to the audit trail.</p>
            </div>
        @endif

        @if (! $teamsReady)
            <div class="hf-empty" data-testid="ingame-teams-pending">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <h3>Teams still pending</h3>
                <p>Both teams must be decided before quarter scores and player stats can be entered.</p>
                <a class="hf-btn" href="{{ route('gameManage') }}">Change match</a>
            </div>
        @elseif ($locked)
            <section class="hf-card" data-testid="ingame-locked">
                <div class="hf-between hf-wrap">
                    <div>
                        <span class="hf-eyebrow">{{ $this->lockedTitle }}</span>
                        <h2 class="hf-section-title hf-mb-0">{{ $match->home_score ?? '–' }}–{{ $match->away_score ?? '–' }}</h2>
                        <p class="hf-small hf-muted hf-mb-0">
                            @if ($this->seriesAnchor)
                                {{ $this->seriesSummary }}. In-game edits for this game are closed.
                            @else
                                Recorded as {{ $match->statusLabel() }}. In-game edits are closed.
                            @endif
                        </p>
                    </div>
                    <span class="hf-chip hf-chip--{{ $match->status === 'forfeit' ? 'danger' : ($match->status === 'in_progress' ? 'gold' : 'success') }}">{{ $match->statusLabel() }}</span>
                </div>
                @if ($this->seriesAnchor)
                    <div class="hf-stat-strip hf-mt-2">
                        <div><span>Series</span><strong>{{ $this->seriesSummary }}</strong></div>
                        <div><span>Game</span><strong>{{ $match->seriesGameLabel() }}</strong></div>
                        <div><span>Schedule</span><strong>{{ $this->seriesScheduleLabel() }}</strong></div>
                    </div>
                @endif
                @if ($this->nextSeriesGame)
                    <div class="hf-card hf-card--flat hf-mt-2" data-testid="ingame-next-game">
                        <div class="hf-between hf-wrap">
                            <div>
                                <span class="hf-eyebrow">Next up</span>
                                <strong>Game {{ $this->nextSeriesGame->game_number }}</strong>
                                <p class="hf-small hf-muted hf-mb-0">{{ $this->nextSeriesSummary }}</p>
                            </div>
                            <a class="hf-btn hf-btn--primary" href="{{ route('gameManage', ['match' => $this->nextSeriesGame->id]) }}" data-testid="ingame-next-game-link">
                                <i class="bi bi-arrow-right-circle"></i> Go to Game {{ $this->nextSeriesGame->game_number }}
                            </a>
                        </div>
                    </div>
                @endif
                <div class="hf-btn-row hf-mt-2">
                    <a class="hf-btn hf-btn--primary" href="{{ route('gameStats', ['match' => $match->id]) }}" data-testid="ingame-view-stats">View box score</a>
                    @if (in_array($match->status, ['final', 'forfeit'], true))
                        @if ($this->hasEditableBoxScore)
                            <a class="hf-btn hf-btn--danger" href="{{ route('gameManage', ['match' => $match->id, 'correct' => 1]) }}" data-testid="ingame-correct">Correct score</a>
                        @else
                            <a class="hf-btn hf-btn--danger" href="{{ route('gameStats', ['match' => $match->id, 'correct' => 1]) }}" data-testid="ingame-correct">Correct score</a>
                        @endif
                    @endif
                    <a class="hf-btn" href="{{ route('gameManage') }}">Change match</a>
                    @if ($this->seriesAnchor)
                        <a class="hf-btn" href="{{ route('bracket', ['id' => $match->tournament_id, 'match' => $this->seriesAnchor->id]) }}" data-testid="ingame-series-bracket">View series in bracket</a>
                    @endif
                </div>
            </section>
        @else
            <section class="hf-card hf-card--flat hf-mb-3" data-testid="ingame-scoreboard">
                <div class="hf-between hf-wrap">
                    <div class="hf-flex hf-items-center hf-gap-2">
                        <x-team-shield :team="$home" />
                        <strong>{{ $home?->shortLabel() ?? 'TBD' }}</strong>
                    </div>
                    <div class="hf-score hf-score--lg" data-testid="ingame-live-score">
                        {{ array_sum($this->quarters['team1']) }} – {{ array_sum($this->quarters['team2']) }}
                    </div>
                    <div class="hf-flex hf-items-center hf-gap-2">
                        <strong>{{ $away?->shortLabel() ?? 'TBD' }}</strong>
                        <x-team-shield :team="$away" />
                    </div>
                </div>
                <p class="hf-small hf-muted hf-mb-0 hf-mt-1">
                    Live total from quarter scores · {{ $match->seriesGameLabel() }} · {{ $this->seriesScheduleLabel }}
                </p>
            </section>

            <section class="hf-card hf-mb-3" data-testid="ingame-quarter-tabs">
                <div class="hf-between hf-wrap hf-mb-2">
                    <div>
                        <span class="hf-eyebrow">Game clock</span>
                        <h2 class="hf-section-title hf-mb-0">Quarter</h2>
                    </div>
                    <div class="hf-btn-row">
                        @foreach ($labels as $index => $label)
                            <button type="button" class="hf-btn hf-btn--sm {{ $activeQuarter === $index ? 'hf-btn--primary' : '' }}" wire:click="setActiveQuarter({{ $index }})" aria-pressed="{{ $activeQuarter === $index ? 'true' : 'false' }}" data-testid="ingame-quarter-tab-{{ $index }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="hf-btn-row">
                    <button type="button" class="hf-btn" wire:click="addOvertime" data-testid="ingame-add-overtime"><i class="bi bi-plus-lg"></i> Add overtime</button>
                    <button type="button" class="hf-btn" wire:click="removeOvertime" data-testid="ingame-remove-overtime"><i class="bi bi-dash-lg"></i> Remove last OT</button>
                </div>
            </section>

            <div class="hf-grid hf-grid--2 hf-mb-3">
                @foreach ([['side' => 'team1', 'team' => $home], ['side' => 'team2', 'team' => $away]] as $panel)
                    <section class="hf-card" data-testid="ingame-team-panel-{{ $panel['side'] }}">
                        <div class="hf-flex hf-items-center hf-gap-2 hf-mb-2">
                            <x-team-shield :team="$panel['team']" />
                            <strong>{{ $panel['team']?->name ?? 'TBD' }}</strong>
                        </div>
                        <div class="hf-grid hf-grid--3">
                            @foreach ($this->quarters[$panel['side']] as $index => $value)
                                <div class="hf-field">
                                    <label class="hf-label" for="ingameQ{{ $panel['side'] }}_{{ $index }}">{{ $labels[$index] ?? 'Q'.($index + 1) }}</label>
                                    <input class="hf-input" id="ingameQ{{ $panel['side'] }}_{{ $index }}" type="number" min="0" max="199" wire:model.live="quarters.{{ $panel['side'] }}.{{ $index }}" data-testid="ingame-q-{{ $panel['side'] }}-{{ $index }}">
                                </div>
                            @endforeach
                        </div>
                        <p class="hf-small hf-mt-1 hf-mb-1">Running total: <strong>{{ array_sum($this->quarters[$panel['side']]) }}</strong></p>
                        <label class="hf-label" for="ingameFinal{{ $panel['side'] }}">Final score</label>
                        <input class="hf-input" id="ingameFinal{{ $panel['side'] }}" type="number" min="0" max="199" wire:model.live="finals.{{ $panel['side'] }}" data-testid="ingame-final-{{ $panel['side'] }}">
                        @php
                            $playerPoints = $this->statPointsForSide($panel['side']);
                            $finalPoints = (int) $this->finals[$panel['side']];
                            $pointsGap = $finalPoints - $playerPoints;
                        @endphp
                        @if ($correctionMode)
                            <p class="hf-small hf-mt-1 hf-mb-0" data-testid="ingame-mismatch-{{ $panel['side'] }}">
                                @if ($playerPoints === $finalPoints)
                                    Player points match the final score
                                @elseif ($playerPoints < $finalPoints)
                                    Player points {{ $playerPoints }} · Final {{ $finalPoints }} · {{ $finalPoints - $playerPoints }} unassigned
                                @else
                                    Player points {{ $playerPoints }} · Final {{ $finalPoints }} · {{ $playerPoints - $finalPoints }} above the final score
                                @endif
                            </p>
                        @elseif ($pointsGap !== 0)
                            <p class="hf-small hf-mt-1 hf-mb-0" data-testid="ingame-points-check-{{ $panel['side'] }}">
                                <span class="hf-chip hf-chip--{{ $pointsGap > 0 ? 'warning' : 'danger' }}">
                                    @if ($pointsGap > 0)
                                        {{ $pointsGap }} points unassigned
                                    @else
                                        {{ abs($pointsGap) }} points above the final score
                                    @endif
                                </span>
                            </p>
                        @else
                            <p class="hf-small hf-mt-1 hf-mb-0" data-testid="ingame-points-check-{{ $panel['side'] }}">
                                <span class="hf-chip hf-chip--success">Player points match the final score</span>
                            </p>
                        @endif
                    </section>
                @endforeach
            </div>

            <section class="hf-card hf-card--flat hf-mb-3" data-testid="ingame-side-filters">
                <div class="hf-between hf-wrap">
                    <div class="hf-filter-row">
                        @foreach ([['key' => 'all', 'label' => 'All'], ['key' => 'team1', 'label' => 'Team 1'], ['key' => 'team2', 'label' => 'Team 2']] as $filter)
                            <button type="button" class="hf-chip hf-chip--{{ $side === $filter['key'] ? 'primary' : 'slate' }}" wire:click="setSide('{{ $filter['key'] }}')" aria-pressed="{{ $side === $filter['key'] ? 'true' : 'false' }}" data-testid="ingame-side-{{ $filter['key'] }}">{{ $filter['label'] }}</button>
                        @endforeach
                    </div>
                    <select class="hf-select" wire:model.live="side" data-testid="ingame-team-filter" aria-label="Team filter">
                        <option value="all">Both teams</option>
                        <option value="team1">{{ $home?->name ?? 'Team 1' }}</option>
                        <option value="team2">{{ $away?->name ?? 'Team 2' }}</option>
                    </select>
                </div>
            </section>

            @foreach ([['side' => 'team1', 'team' => $home, 'search' => 'search1'], ['side' => 'team2', 'team' => $away, 'search' => 'search2']] as $block)
                @continue($side !== 'all' && $side !== $block['side'])
                @php $rows = $this->playersFor($block['side']); @endphp
                <section class="hf-card hf-mb-3" data-testid="ingame-stats-{{ $block['side'] }}">
                    <div class="hf-between hf-wrap hf-mb-2">
                        <div class="hf-flex hf-items-center hf-gap-2">
                            <x-team-shield :team="$block['team']" />
                            <h2 class="hf-section-title hf-mb-0">{{ $block['team']?->name ?? 'TBD' }}</h2>
                        </div>
                        <input class="hf-input" type="search" placeholder="Search player" aria-label="Search {{ $block['team']?->name ?? 'team' }} players" wire:model.live.debounce.300ms="{{ $block['search'] }}" data-testid="ingame-player-search-{{ $block['side'] }}">
                    </div>
                    <div class="hf-table-wrap">
                        <table class="hf-table">
                            <caption class="hf-sr-only">Player box score</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Player</th>
                                    <th scope="col" class="is-num">FG M</th>
                                    <th scope="col" class="is-num">FG A</th>
                                    <th scope="col" class="is-num">3PT M</th>
                                    <th scope="col" class="is-num">3PT A</th>
                                    <th scope="col" class="is-num">FT M</th>
                                    <th scope="col" class="is-num">FT A</th>
                                    <th scope="col" class="is-num">REB</th>
                                    <th scope="col" class="is-num">AST</th>
                                    <th scope="col" class="is-num">STL</th>
                                    <th scope="col" class="is-num">BLK</th>
                                    <th scope="col" class="is-num">TO</th>
                                    <th scope="col" class="is-num">PF</th>
                                    <th scope="col" class="is-num">PTS</th>
                                    <th scope="col" class="is-num">Totals</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php
                                        $quarterStats = $this->stats[$block['side']][$row['id']]['quarters'][$activeQuarter] ?? [];
                                    @endphp
                                    <tr wire:key="ingame-stat-row-{{ $block['side'] }}-{{ $row['id'] }}-{{ $activeQuarter }}" data-testid="ingame-stat-row-{{ $block['side'] }}-{{ $loop->index }}" data-player-name="{{ mb_strtolower($row['name']) }}">
                                        <td>
                                            <strong>#{{ $row['number'] ?? '–' }} {{ $row['name'] }}</strong>
                                            <span class="hf-small hf-muted hf-block">{{ $row['position'] ?: 'Player' }}</span>
                                        </td>
                                        @foreach (array_keys(\App\Services\GameStatsService::FIELDS) as $field)
                                            <td class="is-num">
                                                <input class="hf-input" type="number" min="0" max="199" aria-label="{{ $row['name'] }} {{ $field }} {{ $labels[$activeQuarter] ?? '' }}" wire:model.live="stats.{{ $block['side'] }}.{{ $row['id'] }}.quarters.{{ $activeQuarter }}.{{ $field }}" data-testid="ingame-stat-{{ $block['side'] }}-{{ $row['id'] }}-{{ $field }}">
                                            </td>
                                        @endforeach
                                        <td class="is-num"><strong>{{ $this->statPoints($quarterStats) }}</strong></td>
                                        <td class="is-num">{{ $row['totals']['pts'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="15">
                                            <div class="hf-empty">
                                                <h3>No players match</h3>
                                                <p>Clear the player search to see the full rotation.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="hf-small hf-muted hf-mt-1 hf-mb-0">Totals are the sum of all quarters. Editing a stat recomputes the active quarter and final score.</p>
                </section>
            @endforeach

            <section class="hf-card hf-card--flat" data-testid="ingame-actions">
                @if ($errorMessage !== '')
                    <p class="hf-field-error hf-mb-2" data-testid="ingame-error">{{ $errorMessage }}</p>
                @endif
                @if ($correctionMode)
                    <div class="hf-field hf-mb-2">
                        <label class="hf-label" for="ingameCorrectionReason">Correction reason</label>
                        <textarea class="hf-textarea" id="ingameCorrectionReason" rows="3" placeholder="Why is this result being corrected?" wire:model.blur="correctionReason" data-testid="ingame-correction-reason" @error('correctionReason') aria-invalid="true" aria-describedby="ingameCorrectionReasonError" @enderror></textarea>
                        @error('correctionReason')
                            <p class="hf-field-error" id="ingameCorrectionReasonError">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
                @if (! $correctionMode && $this->seriesAnchor)
                    <p class="hf-small hf-muted hf-mt-0 hf-mb-2" data-testid="ingame-series-hint">
                        <i class="bi bi-info-circle"></i>
                        Publishing {{ $match->seriesGameLabel() }} locks it and updates the series. The next game opens when you are ready.
                    </p>
                @endif
                <div class="hf-sticky-actions">
                    @if ($correctionMode)
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="requestSaveCorrection" wire:loading.attr="disabled" data-testid="ingame-save-correction"><i class="bi bi-check2-circle" aria-hidden="true"></i> Save correction</button>
                        <button type="button" class="hf-btn" wire:click="cancelCorrection" data-testid="ingame-cancel-correction">Cancel correction</button>
                    @else
                        <button type="button" class="hf-btn" wire:click="saveDraft" wire:loading.attr="disabled" data-testid="ingame-save"><i class="bi bi-save" aria-hidden="true"></i> Save</button>
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="requestPublish" wire:loading.attr="disabled" data-testid="ingame-publish">
                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                            {{ $this->seriesAnchor ? 'Publish '.$match->seriesGameLabel() : 'Publish result' }}
                        </button>
                        <a class="hf-btn" href="{{ route('bracket', ['id' => $match->tournament_id]) }}" data-testid="ingame-cancel">Cancel</a>
                    @endif
                </div>
            </section>
        @endif
    @endif

    @if ($showConfirm)
        <dialog
            class="hf-dialog"
            x-data
            x-init="if (! $el.open) $el.showModal()"
            x-on:cancel.prevent="$wire.closeConfirm()"
            x-on:click="if ($event.target === $el) $wire.closeConfirm()"
            wire:key="ingame-confirm-dialog"
            data-testid="ingame-confirm-dialog"
            aria-labelledby="ingameConfirmDialogTitle">
            <div class="hf-dialog__head">
                <h2 class="hf-dialog__title" id="ingameConfirmDialogTitle">{{ $confirmTitle }}</h2>
                <button type="button" class="hf-icon-btn" wire:click="closeConfirm" aria-label="Close dialog" data-testid="dialog-close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
            <div class="hf-dialog__body">
                <p class="hf-small hf-muted">{{ $confirmMessage }}</p>
            </div>
            <div class="hf-dialog__foot">
                <button type="button" class="hf-btn" wire:click="closeConfirm" data-testid="ingame-confirm-cancel">Cancel</button>
                <button type="button" class="hf-btn hf-btn--{{ $confirmTone }}" wire:click="{{ $confirmAction }}" data-testid="ingame-confirm">{{ $confirmLabel }}</button>
            </div>
        </dialog>
    @endif
</div>
