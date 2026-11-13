<div>
    @php
        $match = $this->match;
        $isOrganizer = $this->isOrganizer;
        $canScore = $this->canScore;
        $canCorrect = $this->canCorrect;
        $correction = $this->correction;
        $quarters = $this->quarters;
        $hasStats = $this->hasStats;
        $home = $match?->homeTeam;
        $away = $match?->awayTeam;
        $slot = $match?->matchSlots->first();
        $court = $slot?->court;
        $tipoff = $slot?->start_at !== null
            ? $slot->start_at->format('M j, Y').' at '.$slot->start_at->format('g:i A')
            : 'Tip-off TBD';
        $homeWinner = $match !== null && $match->winner_team_id !== null && (int) $match->winner_team_id === (int) $match->home_team_id;
        $awayWinner = $match !== null && $match->winner_team_id !== null && (int) $match->winner_team_id === (int) $match->away_team_id;
    @endphp

    @if ($match === null)
        <div class="hf-empty">
            <i class="bi bi-graph-up" aria-hidden="true"></i>
            <h3>Game not found</h3>
            <p>Open a game from the bracket, schedule, or matchup list.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('matches') }}">Browse matchups</a>
        </div>
    @else
        @section('breadcrumbs')
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Tournaments', 'href' => route('tournaments')],
                ['label' => $match->tournament?->name ?? 'Tournament', 'href' => route('tournamentShow', ['tournament' => $match->tournament_id])],
                ['label' => 'Game stats'],
            ]" />
        @endsection

        <header class="hf-page-head hf-mb-3">
            <div>
                <span class="hf-eyebrow">{{ $match->tournament?->divisions?->first()?->name ?? 'Tournament' }} · {{ $match->tournament?->name ?? 'HoopFlow' }}</span>
                <h1 class="hf-page-title">Game stats</h1>
                <p class="hf-page-desc">
                    {{ $match->roundLabel() }} · {{ $court?->name ?? 'Court TBD' }} · {{ $tipoff }}.
                    @if ($this->seriesAnchor)
                        · {{ $match->seriesGameLabel() }} · {{ $this->seriesSummary }}
                    @endif
                </p>
            </div>
            <div class="hf-page-actions">
                <a class="hf-btn" href="{!! route('bracket', ['id' => $match->tournament_id, 'match' => $match->id]) !!}" data-testid="stats-bracket"><i class="bi bi-diagram-3"></i> Bracket</a>
                <a class="hf-btn" href="{{ route('schedule', ['id' => $match->tournament_id]) }}" data-testid="stats-schedule"><i class="bi bi-calendar3"></i> Schedule</a>
                <a class="hf-btn" href="{{ route('matches', ['id' => $match->tournament_id]) }}" data-testid="stats-matches"><i class="bi bi-list-ul"></i> Matchups</a>
                @if ($canScore)
                    <a class="hf-btn" href="{{ route('gameManage', ['match' => $match->id]) }}" data-testid="stats-in-game"><i class="bi bi-clipboard-pulse"></i> In-game scoring</a>
                @endif
            </div>
        </header>

        @if ($this->seriesAnchor)
            <section class="hf-card hf-card--flat hf-mb-3" data-testid="stats-series">
                <div class="hf-btn-row" data-testid="stats-series-games">
                    @foreach ($this->seriesGames as $seriesGame)
                        @php
                            $isCurrent = (int) $seriesGame->id === (int) $match->id;
                            $summary = ($seriesGame->home_score ?? '–').'–'.($seriesGame->away_score ?? '–');
                        @endphp
                        @if ($isCurrent)
                            <span class="hf-chip hf-chip--primary" aria-current="true" data-testid="stats-series-game-{{ $seriesGame->id }}">
                                Game {{ $seriesGame->game_number }} · {{ $summary }} · {{ $this->seriesGameStatusLabel($seriesGame) }}
                            </span>
                        @else
                            <a class="hf-chip hf-chip--slate" href="{{ route('gameStats', ['match' => $seriesGame->id]) }}" data-testid="stats-series-game-{{ $seriesGame->id }}">
                                Game {{ $seriesGame->game_number }} · {{ $summary }} · {{ $this->seriesGameStatusLabel($seriesGame) }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        <section class="hf-hero" data-testid="scoreboard-{{ $match->id }}">
            <span class="hf-eyebrow" style="color: var(--hf-gold)">{{ $match->roundLabel() }} · {{ $match->status }}</span>
            <div class="hf-mt-2">
                <div class="hf-flex hf-items-center hf-between hf-gap-2 {{ $homeWinner ? 'is-winner' : '' }}">
                    <span class="hf-flex hf-items-center hf-gap-2">
                        <x-team-shield :team="$home" size="lg" />
                        <strong class="hf-team-name hf-mb-0" style="font-size: clamp(20px, 3vw, 34px)">{{ $home?->name ?? 'TBD' }}</strong>
                    </span>
                    <span class="hf-score" style="font-size: clamp(34px, 6vw, 64px)">{{ $match->home_score ?? '–' }}</span>
                </div>
                <div class="hf-stat-strip">
                    <div><span>Court</span><strong>{{ $court?->name ?? 'TBD' }}</strong></div>
                    <div><span>Tip-off</span><strong>{{ $slot?->start_at?->format('g:i A') ?? 'TBD' }}</strong></div>
                    <div><span>Date</span><strong>{{ $slot?->start_at?->format('M j, Y') ?? 'TBD' }}</strong></div>
                </div>
                <div class="hf-flex hf-items-center hf-between hf-gap-2 {{ $awayWinner ? 'is-winner' : '' }}">
                    <span class="hf-flex hf-items-center hf-gap-2">
                        <x-team-shield :team="$away" size="lg" />
                        <strong class="hf-team-name hf-mb-0" style="font-size: clamp(20px, 3vw, 34px)">{{ $away?->name ?? 'TBD' }}</strong>
                    </span>
                    <span class="hf-score" style="font-size: clamp(34px, 6vw, 64px)">{{ $match->away_score ?? '–' }}</span>
                </div>
            </div>
        </section>

        @if ($this->scoreMismatch !== [])
            <div class="hf-card hf-card--flat hf-mb-3" data-testid="stats-score-mismatch">
                <div class="hf-between hf-wrap">
                    <span class="hf-chip hf-chip--gold"><i class="bi bi-exclamation-triangle"></i> Box score mismatch</span>
                </div>
                @foreach ($this->scoreMismatch as $mismatch)
                    <p class="hf-small hf-mt-1 hf-mb-0">
                        Box score doesn’t match the final score: <strong>{{ $mismatch['team'] }}</strong> player points {{ $mismatch['points'] }} vs final {{ $mismatch['score'] }}.
                        @if (! $mismatch['editable'])
                            Per-quarter stats were not recorded, so only the score can be corrected.
                        @endif
                    </p>
                @endforeach
            </div>
        @endif

        @if ($correction)
            <div class="hf-card hf-card--flat hf-mb-3" data-testid="stats-correction">
                <div class="hf-between hf-wrap">
                    <span class="hf-chip hf-chip--gold"><i class="bi bi-arrow-repeat"></i> Corrected score</span>
                    <span class="hf-small hf-muted">{{ $correction['corrected_at']?->format('M j, Y') }} · {{ $correction['corrected_at']?->format('g:i A') }}</span>
                </div>
                <p class="hf-small hf-mt-1 hf-mb-0">
                    <strong>{{ $correction['previous1'] ?? '–' }}–{{ $correction['previous2'] ?? '–' }}</strong>
                    →
                    <strong>{{ $match->home_score ?? '–' }}–{{ $match->away_score ?? '–' }}</strong>
                    · {{ $correction['reason'] }}
                </p>
            </div>
        @endif

        <div class="hf-card hf-card--flat hf-mb-3" data-testid="stats-controls">
            <div class="hf-between hf-wrap">
                <div>
                    <span class="hf-kpi-label">Official result</span>
                    <p class="hf-small hf-muted hf-mb-0">
                        @if ($match->status === 'scheduled')
                            @if ($home && $away)
                                Both teams are set. Enter the final score to advance the winner and generate the box score.
                            @else
                                Waiting for both semifinal results before this game can be played.
                            @endif
                        @else
                            Recorded as {{ $match->status }}. Corrections require a reason and are written to the audit trail.
                        @endif
                    </p>
                </div>
                <div class="hf-btn-row">
                    @if ($canScore)
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="openScoreConsole" data-testid="stats-enter-score"><i class="bi bi-pencil-square"></i> Enter final score</button>
                    @endif
                    @if ($canCorrect)
                        <button type="button" class="hf-btn hf-btn--danger" wire:click="openCorrection" data-testid="stats-correct-score"><i class="bi bi-arrow-repeat"></i> Correct score</button>
                    @endif
                    @if (! $canScore && ! $canCorrect)
                        <span class="hf-chip hf-chip--slate"><i class="bi bi-eye"></i> Read-only {{ $this->roleLabel }} view</span>
                    @endif
                </div>
            </div>
        </div>

        @if ($quarters)
            <section class="hf-card hf-mb-3" data-testid="stats-quarters">
                <div class="hf-between hf-wrap hf-mb-2">
                    <div>
                        <span class="hf-eyebrow">Quarter breakdown</span>
                        <h2 class="hf-section-title hf-mb-0" data-testid="quarter-breakdown">By quarter</h2>
                    </div>
                </div>
                <div class="hf-table-wrap" data-testid="stats-quarter-breakdown">
                    <table class="hf-table">
                        <thead>
                            <tr>
                                <th scope="col">Team</th>
                                @foreach (array_keys($quarters['team1']) as $index)
                                    <th scope="col" class="is-num">{{ $index < 4 ? 'Q'.($index + 1) : 'OT'.($index - 3) }}</th>
                                @endforeach
                                <th scope="col" class="is-num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr data-testid="stats-quarters-team1">
                                <td><strong>{{ $home?->name ?? 'Team 1' }}</strong></td>
                                @foreach ($quarters['team1'] as $points)
                                    <td class="is-num">{{ $points }}</td>
                                @endforeach
                                <td class="is-num"><strong>{{ array_sum($quarters['team1']) }}</strong></td>
                            </tr>
                            <tr data-testid="stats-quarters-team2">
                                <td><strong>{{ $away?->name ?? 'Team 2' }}</strong></td>
                                @foreach ($quarters['team2'] as $points)
                                    <td class="is-num">{{ $points }}</td>
                                @endforeach
                                <td class="is-num"><strong>{{ array_sum($quarters['team2']) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($this->boxScoreMessage !== '')
            <div class="hf-card hf-card--flat hf-mb-3" data-testid="stats-state-banner">
                <div class="hf-between hf-wrap">
                    <span class="hf-chip hf-chip--{{ $match->status === 'cancelled' ? 'slate' : ($match->status === 'conditional' ? 'gold' : 'primary') }}">
                        {{ $this->boxScoreLabel }}
                    </span>
                </div>
                <p class="hf-small hf-mt-1 hf-mb-0">{{ $this->boxScoreMessage }}</p>
            </div>
        @endif

        <section class="hf-card hf-mb-3" data-testid="stats-box-score">
            <div class="hf-between hf-wrap hf-mb-2">
                <div>
                    <span class="hf-eyebrow">{{ $this->boxScoreLabel }}</span>
                    <div class="hf-flex hf-items-center hf-gap-2">
                        <h2 class="hf-section-title hf-mb-0">Player stats</h2>
                        <span class="hf-chip hf-chip--slate" data-testid="stats-period-label">{{ $this->periodLabel }}</span>
                    </div>
                </div>
                <div class="hf-filter-row" data-testid="stats-side-filters">
                    <button type="button" class="hf-chip hf-chip--{{ $side === 'all' ? 'primary' : 'slate' }}" wire:click="$set('side', 'all')" aria-pressed="{{ $side === 'all' ? 'true' : 'false' }}" data-testid="stats-side-all">All</button>
                    @if ($home)
                        <button type="button" class="hf-chip hf-chip--{{ $side === 'team1' ? 'primary' : 'slate' }}" wire:click="$set('side', 'team1')" aria-pressed="{{ $side === 'team1' ? 'true' : 'false' }}" data-testid="stats-side-team1">Team 1</button>
                    @endif
                    @if ($away)
                        <button type="button" class="hf-chip hf-chip--{{ $side === 'team2' ? 'primary' : 'slate' }}" wire:click="$set('side', 'team2')" aria-pressed="{{ $side === 'team2' ? 'true' : 'false' }}" data-testid="stats-side-team2">Team 2</button>
                    @endif
                </div>
            </div>

            @if ($home && $away)
                <nav class="hf-period-nav" aria-label="Player stat period" data-testid="stats-period-nav">
                    @foreach ($this->periods as $period)
                        <button
                            type="button"
                            class="hf-period-nav__btn {{ $this->period === $period['key'] ? 'is-active' : '' }}"
                            wire:click="setPeriod('{{ $period['key'] }}')"
                            aria-pressed="{{ $this->period === $period['key'] ? 'true' : 'false' }}"
                            @if ($this->period === $period['key']) aria-current="true" @endif
                            data-testid="stats-period-{{ $period['key'] }}"
                        >{{ $period['label'] }}</button>
                    @endforeach
                </nav>

                @if (! $this->hasQuarterData)
                    <p class="hf-small hf-muted hf-mb-2" data-testid="stats-period-note">Per-quarter player stats were not recorded for this game.</p>
                @endif
            @endif

            <div class="hf-field hf-mb-2">
                <label class="hf-label" for="statsSearch">Search player</label>
                <input class="hf-input" id="statsSearch" type="search" placeholder="Player name" wire:model.live.debounce.150ms="search" data-testid="stats-player-search">
            </div>

            <div class="hf-field hf-mb-2">
                <label class="hf-label" for="statsTeamFilter">Team filter</label>
                <select id="statsTeamFilter" class="hf-select" wire:model.live="side" data-testid="stats-team-filter">
                    <option value="all">All</option>
                    <option value="team1">Team 1</option>
                    <option value="team2">Team 2</option>
                </select>
            </div>

            <div id="statsBody" data-testid="stats-body">
                @if (! $home || ! $away)
                    <div class="hf-empty">
                        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                        <h3>Teams still pending</h3>
                        <p>The box score unlocks when both teams are known.</p>
                    </div>
                @else
                    @foreach ([['team' => $home, 'sideKey' => 'team1'], ['team' => $away, 'sideKey' => 'team2']] as $entry)
                        @continue($side !== 'all' && $side !== $entry['sideKey'])
                        @php
                            $team = $entry['team'];
                            $sideKey = $entry['sideKey'];
                            $rows = $this->rowsFor($team);
                            $totals = $this->totalsFor($rows);
                        @endphp
                        <section class="hf-mb-3" data-testid="stats-team-{{ $team->id }}">
                            @if (count($rows) === 0)
                                <h3 class="hf-section-title">{{ $team->name }}</h3>
                                <div class="hf-empty">
                                    <i class="bi bi-person-x" aria-hidden="true"></i>
                                    @if (trim($search) !== '')
                                        <h3>No players match</h3>
                                        <p>Clear the player search to see the full rotation.</p>
                                    @else
                                        <h3>No player stats</h3>
                                        <p>This team does not have a recorded box score for this game.</p>
                                    @endif
                                </div>
                            @else
                                <div class="hf-between hf-mb-1">
                                    <span class="hf-flex hf-items-center hf-gap-2">
                                        <x-team-shield :team="$team" />
                                        <h3 class="hf-section-title hf-mb-0">{{ $team->name }}</h3>
                                    </span>
                                    <span class="hf-chip hf-chip--slate">{{ count($rows) }} players</span>
                                </div>
                                <div class="hf-table-wrap">
                                    <table class="hf-table">
                                        <thead>
                                            <tr>
                                                <th scope="col" class="is-num">#</th>
                                                <th scope="col">Player</th>
                                                <th scope="col" class="is-num">FG</th>
                                                <th scope="col" class="is-num">3PT</th>
                                                <th scope="col" class="is-num">FT</th>
                                                <th scope="col" class="is-num">
                                                    <button type="button" class="hf-section-link" wire:click="sortBy('rebounds')" data-testid="stats-sort-{{ $sideKey }}-rebounds">
                                                        REB
                                                        @if ($sortKey === 'rebounds')
                                                            <i class="bi bi-caret-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
                                                        @else
                                                            <i class="bi bi-arrow-down-up hf-muted" aria-hidden="true"></i>
                                                        @endif
                                                    </button>
                                                </th>
                                                <th scope="col" class="is-num">
                                                    <button type="button" class="hf-section-link" wire:click="sortBy('assists')" data-testid="stats-sort-{{ $sideKey }}-assists">
                                                        AST
                                                        @if ($sortKey === 'assists')
                                                            <i class="bi bi-caret-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
                                                        @else
                                                            <i class="bi bi-arrow-down-up hf-muted" aria-hidden="true"></i>
                                                        @endif
                                                    </button>
                                                </th>
                                                <th scope="col" class="is-num">STL</th>
                                                <th scope="col" class="is-num">BLK</th>
                                                <th scope="col" class="is-num">TO</th>
                                                <th scope="col" class="is-num">PF</th>
                                                <th scope="col" class="is-num">
                                                    <button type="button" class="hf-section-link" wire:click="sortBy('points')" data-testid="stats-sort-{{ $sideKey }}-points">
                                                        PTS
                                                        @if ($sortKey === 'points')
                                                            <i class="bi bi-caret-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
                                                        @else
                                                            <i class="bi bi-arrow-down-up hf-muted" aria-hidden="true"></i>
                                                        @endif
                                                    </button>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($rows as $row)
                                                <tr data-testid="stats-row-{{ $sideKey }}-{{ $row['playerId'] }}">
                                                    <td class="is-num">{{ $row['number'] ?? '–' }}</td>
                                                    <td>
                                                        <strong>{{ $row['name'] }}</strong>
                                                        <span class="hf-small hf-muted" style="display: block">{{ $row['position'] ?: 'Player' }}</span>
                                                    </td>
                                                    <td class="is-num">{{ $row['fgm'] }}/{{ $row['fga'] }}</td>
                                                    <td class="is-num">{{ $row['tpm'] }}/{{ $row['tpa'] }}</td>
                                                    <td class="is-num">{{ $row['ftm'] }}/{{ $row['fta'] }}</td>
                                                    <td class="is-num">{{ $row['reb'] }}</td>
                                                    <td class="is-num">{{ $row['ast'] }}</td>
                                                    <td class="is-num">{{ $row['stl'] }}</td>
                                                    <td class="is-num">{{ $row['blk'] }}</td>
                                                    <td class="is-num">{{ $row['to'] }}</td>
                                                    <td class="is-num">{{ $row['pf'] }}</td>
                                                    <td class="is-num">{{ $row['points'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="2"><strong>Team totals</strong></td>
                                                <td class="is-num"><strong>{{ $totals['fgm'] }}/{{ $totals['fga'] }}</strong></td>
                                                <td class="is-num"><strong>{{ $totals['tpm'] }}/{{ $totals['tpa'] }}</strong></td>
                                                <td class="is-num"><strong>{{ $totals['ftm'] }}/{{ $totals['fta'] }}</strong></td>
                                                <td class="is-num"><strong>{{ $totals['reb'] }}</strong></td>
                                                <td class="is-num"><strong>{{ $totals['ast'] }}</strong></td>
                                                <td class="is-num"><strong>{{ $totals['stl'] }}</strong></td>
                                                <td class="is-num"><strong>{{ $totals['blk'] }}</strong></td>
                                                <td class="is-num"><strong>{{ $totals['to'] }}</strong></td>
                                                <td class="is-num"><strong>{{ $totals['pf'] }}</strong></td>
                                                <td class="is-num"><strong>{{ $totals['points'] }}</strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @endif
                        </section>
                    @endforeach
                @endif
            </div>

            @if (! $hasStats && $match->status !== 'scheduled')
                <p class="hf-small hf-muted hf-mt-2 hf-mb-0">
                    {{ ($home && $away) ? 'No player stats were recorded for this game. The team score is still official.' : 'Player stats appear once both teams are decided and a result is recorded.' }}
                </p>
            @endif
        </section>

        @if ($showScoreConsole)
            @php
                $display1 = $forfeitSide === 2 ? 20 : ($forfeitSide === 1 ? 0 : (int) ($score1 ?? 0));
                $display2 = $forfeitSide === 1 ? 20 : ($forfeitSide === 2 ? 0 : (int) ($score2 ?? 0));
            @endphp
            <dialog
                class="hf-dialog hf-dialog--wide"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.closeScoreConsole()"
                x-on:click="if ($event.target === $el) $wire.closeScoreConsole()"
                wire:key="stats-score-dialog-{{ $match->id }}"
                data-testid="stats-score-dialog"
                data-score-console
                tabindex="-1"
            >
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">{{ $home?->shortLabel() ?? 'TBD' }} vs {{ $away?->shortLabel() ?? 'TBD' }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeScoreConsole" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-small hf-muted">{{ $match->roundLabel() }} · Slot {{ $match->match_number }}. Use the score buttons or a forfeit, then finalize. The winner advances immediately.</p>
                    <div class="hf-grid hf-grid--2">
                        <div class="hf-card hf-card--flat" data-team-panel="1" data-score-team="1">
                            <span class="hf-flex hf-items-center hf-gap-2">
                                <x-team-shield :team="$home" />
                                <strong>{{ $home?->name ?? 'TBD' }}</strong>
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
                        <div class="hf-card hf-card--flat" data-team-panel="2" data-score-team="2">
                            <span class="hf-flex hf-items-center hf-gap-2">
                                <x-team-shield :team="$away" />
                                <strong>{{ $away?->name ?? 'TBD' }}</strong>
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
                            <option value="1">{{ $home?->name ?? 'Home' }} forfeits · {{ $away?->shortLabel() ?? 'Away' }} wins 20–0</option>
                            <option value="2">{{ $away?->name ?? 'Away' }} forfeits · {{ $home?->shortLabel() ?? 'Home' }} wins 20–0</option>
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

        @if ($showCorrection)
            <dialog
                class="hf-dialog hf-dialog--wide"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.closeCorrection()"
                x-on:click="if ($event.target === $el) $wire.closeCorrection()"
                wire:key="stats-correction-dialog-{{ $match->id }}"
                data-testid="stats-correction-dialog"
            >
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">Correct score · {{ $home?->shortLabel() ?? 'Home' }} vs {{ $away?->shortLabel() ?? 'Away' }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeCorrection" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-small hf-muted">Current result: {{ $match->home_score ?? '–' }}–{{ $match->away_score ?? '–' }}@if ($correction) (corrected from {{ $correction['previous1'] ?? '–' }}–{{ $correction['previous2'] ?? '–' }})@endif. A correction requires a reason and rewrites the downstream bracket if the winner changes.</p>
                    <div class="hf-form-grid">
                        <div class="hf-field">
                            <label class="hf-label" for="correctionScore1">{{ $home?->name ?? 'Home' }}</label>
                            <input class="hf-input" id="correctionScore1" type="number" min="0" wire:model.blur="score1" data-testid="correction-score-1">
                        </div>
                        <div class="hf-field">
                            <label class="hf-label" for="correctionScore2">{{ $away?->name ?? 'Away' }}</label>
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

        @if ($showConfirm)
            <dialog
                class="hf-dialog"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.cancelConfirm()"
                x-on:click="if ($event.target === $el) $wire.cancelConfirm()"
                wire:key="stats-confirm-dialog"
                data-testid="stats-confirm-dialog"
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

    @endif
</div>
