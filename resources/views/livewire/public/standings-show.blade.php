<div>
    @section('title', 'Standings')

    @php $tournament = $this->tournament; @endphp

    @if (! $tournament)
        <div class="hf-empty" data-testid="standings-not-found">
            <i class="bi bi-bar-chart" aria-hidden="true"></i>
            <h1>Tournament not found</h1>
            <p>The standings link is invalid or the demo has been reset.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}">Back to tournaments</a>
        </div>
    @else
        @php
            $rows = $this->rows;
            $allRows = $this->allRows;
            $kpis = $this->kpis;
            $cut = $this->cut;
            $divisionName = $tournament->divisions->first()?->name ?? 'Division';
            $showQualificationLine = trim($search) === '' && $filter === 'all';
            $lastQualified = $showQualificationLine
                ? $rows->filter(fn (array $row) => $row['qualified'])->count()
                : 0;
            $filters = [
                ['value' => 'all', 'label' => 'All teams'],
                ['value' => 'qualified', 'label' => 'Playoff cut'],
                ['value' => 'wins', 'label' => 'With a win'],
                ['value' => 'winless', 'label' => 'Winless'],
            ];
        @endphp

        @section('meta-description', $tournament->name.' · '.$divisionName.' standings, wins, losses, point differential, and the playoff cut.')

        <x-matchday-strip
            eyebrow="{{ $divisionName }} · {{ $tournament->name }}"
            title="Standings"
            :status="$cut ? 'Top '.$cut.' qualify' : null"
            description="Wins, losses, point differential, and the playoff cut — computed from every recorded result."
            :meta="[
                ['icon' => 'trophy', 'label' => $kpis['leader_short'].' leads'],
                ['icon' => 'bar-chart', 'label' => $kpis['results'].' results'],
                ['icon' => 'people', 'label' => $kpis['qualified'].' above the cut'],
            ]"
            class="hf-mb-3"
        >
            <label class="hf-sr-only" for="standingsTournament">Tournament</label>
            <select class="hf-select" data-hf-select id="standingsTournament" wire:change="changeTournament($event.target.value)" data-testid="standings-tournament">
                @foreach ($this->tournaments as $tournamentOption)
                    <option value="{{ $tournamentOption->id }}" @selected($tournamentOption->id === $tournament->id)>{{ $tournamentOption->name }}</option>
                @endforeach
            </select>
            <a class="hf-btn" href="{{ route('bracket', ['id' => $tournament->id]) }}" data-testid="standings-bracket"><i class="bi bi-diagram-3" aria-hidden="true"></i> Bracket</a>
            <a class="hf-btn" href="{{ route('matches', ['id' => $tournament->id]) }}" data-testid="standings-matches"><i class="bi bi-list-ul" aria-hidden="true"></i> Matchups</a>
        </x-matchday-strip>

        <div class="hf-grid hf-grid--4 hf-mb-3" id="standingsKpis" data-testid="standings-kpis">
            @include('components.kpi-tile', [
                'label' => 'Leader',
                'value' => $kpis['leader_short'],
                'hint' => $kpis['leader_hint'],
                'tone' => 'gold',
                'testid' => 'standings-kpi-leader',
            ])
            @include('components.kpi-tile', [
                'label' => 'Playoff cut',
                'value' => 'Top '.$kpis['cut'],
                'hint' => $kpis['qualified'].' teams above the line',
                'tone' => 'primary',
                'testid' => 'standings-kpi-cut',
            ])
            @include('components.kpi-tile', [
                'label' => 'Best diff',
                'value' => ($kpis['best_diff'] > 0 ? '+' : '').$kpis['best_diff'],
                'hint' => $kpis['best_diff_short'],
                'tone' => 'success',
                'testid' => 'standings-kpi-diff',
            ])
            @include('components.kpi-tile', [
                'label' => 'Results',
                'value' => $kpis['results'],
                'hint' => 'Final and forfeit games',
                'tone' => 'slate',
                'testid' => 'standings-kpi-results',
            ])
        </div>

        <div class="hf-card hf-mb-3">
            <div class="hf-field">
                <label class="hf-label" for="standingsSearch">Search teams</label>
                <input class="hf-input" id="standingsSearch" type="search"
                       placeholder="Team, short name, or city"
                       wire:model.live.debounce.140ms="search"
                       data-testid="standings-search">
            </div>
            <div class="hf-filter-row hf-mt-2" data-standings-filters data-testid="standings-filters">
                @foreach ($filters as $option)
                    <button type="button"
                            class="hf-chip {{ $filter === $option['value'] ? 'hf-chip--primary' : 'hf-chip--slate' }}"
                            wire:click="$set('filter', '{{ $option['value'] }}')"
                            data-standings-filter="{{ $option['value'] }}"
                            aria-pressed="{{ $filter === $option['value'] ? 'true' : 'false' }}"
                            data-testid="standings-filter-{{ $option['value'] }}">
                        {{ $option['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div id="standingsBody" data-testid="standings-body" wire:loading.class="is-updating" wire:loading.attr="aria-busy">
            <div class="hf-updating-pill" wire:loading wire:target="search,filter">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Updating…
            </div>
            @if ($allRows->isEmpty())
                <div class="hf-empty" data-testid="standings-empty">
                    <i class="bi bi-bar-chart" aria-hidden="true"></i>
                    <h2>No standings yet</h2>
                    <p>Standings appear as soon as approved teams have recorded results.</p>
                </div>
            @elseif ($rows->isEmpty())
                <div class="hf-empty" data-testid="standings-no-match">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <h2>No teams match</h2>
                    <p>Clear the search or choose another standings filter.</p>
                </div>
            @else
                <div class="hf-table-wrap">
                    <table class="hf-table hf-table--standings">
                        <caption class="hf-sr-only">Standings</caption>
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Team</th>
                                <th scope="col" class="is-num">W</th>
                                <th scope="col" class="is-num">L</th>
                                <th scope="col" class="is-num">PF</th>
                                <th scope="col" class="is-num">PA</th>
                                <th scope="col" class="is-num">+/-</th>
                                <th scope="col" class="is-num">Seed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $index => $row)
                                <tr class="{{ $row['qualified'] ? 'is-qualified' : '' }}" data-testid="standings-row-{{ $row['team_id'] }}">
                                    <td class="is-num">{{ $row['rank'] }}</td>
                                    <td>
                                        <a class="hf-flex hf-items-center hf-gap-1" href="{{ route('teamShow', ['team' => $row['team_id'], 'tournament' => $tournament->id]) }}">
                                            <x-team-shield :team="$row['team']" />
                                            <strong>{{ $row['team_name'] ?? 'Team' }}</strong>
                                        </a>
                                    </td>
                                    <td class="is-num">{{ $row['wins'] }}</td>
                                    <td class="is-num">{{ $row['losses'] }}</td>
                                    <td class="is-num">{{ $row['points_for'] }}</td>
                                    <td class="is-num">{{ $row['points_against'] }}</td>
                                    <td class="is-num">{{ $row['point_diff'] > 0 ? '+' : '' }}{{ $row['point_diff'] }}</td>
                                    <td class="is-num">{{ $row['seed'] ?? '–' }}</td>
                                </tr>
                                @if ($lastQualified === $index + 1)
                                    <tr class="hf-qual-line"><td colspan="8"></td></tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="hf-standings-cards">
                    @foreach ($rows as $row)
                        <a class="hf-card hf-card--flat" href="{{ route('teamShow', ['team' => $row['team_id'], 'tournament' => $tournament->id]) }}" data-testid="standings-card-{{ $row['team_id'] }}">
                            <div class="hf-between">
                                <span class="hf-flex hf-items-center hf-gap-1">
                                    <span class="hf-kpi-label">#{{ $row['rank'] }}</span>
                                    <x-team-shield :team="$row['team']" />
                                    <strong>{{ $row['team_name'] ?? 'Team' }}</strong>
                                </span>
                                @if ($row['qualified'])
                                    <span class="hf-chip hf-chip--gold">Playoff cut</span>
                                @endif
                            </div>
                            <div class="hf-flex hf-gap-3 hf-mt-1 hf-small">
                                <span><strong>{{ $row['wins'] }}</strong> W</span>
                                <span><strong>{{ $row['losses'] }}</strong> L</span>
                                <span><strong>{{ $row['point_diff'] > 0 ? '+' : '' }}{{ $row['point_diff'] }}</strong> DIFF</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="hf-card hf-card--flat hf-mt-3">
            <p class="hf-small hf-muted hf-mb-0">
                <strong>Tiebreakers:</strong> wins, then point differential, then points for, then registration seed.
                The top {{ $cut }} teams sit above the gold qualification line.
            </p>
        </div>
    @endif
</div>
