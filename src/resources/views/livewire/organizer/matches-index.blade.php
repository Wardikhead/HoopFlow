<div>
@php
    $matches = $this->matches;
    $groups = $matches->groupBy(function ($match) {
        $slot = $match->matchSlots->first();

        return $slot?->start_at?->format('Y-m-d') ?? 'unscheduled';
    });
    $roundLabel = function ($roundNumber) {
        return match ((int) $roundNumber) {
            2 => 'Final',
            4 => 'Semifinals',
            8 => 'Quarterfinals',
            16 => 'Round of 16',
            32 => 'Round of 32',
            default => 'Round ' . $roundNumber,
        };
    };
@endphp

@section('title', 'Matches')

<header class="hf-page-head hf-mb-3">
    <div>
        <span class="hf-eyebrow">Matchups</span>
        <h1 class="hf-page-title">Matches</h1>
        <p class="hf-page-desc">Every bracket game, filterable by tournament and round, with a link to the official box score.</p>
    </div>
</header>

<div class="hf-card hf-mb-3">
    <div class="hf-form-grid">
        <div class="hf-field">
            <label class="hf-label" for="matchTournament">Tournament</label>
            <select class="hf-select" id="matchTournament" wire:model.live="tournament" data-testid="match-tournament-filter">
                <option value="">All tournaments</option>
                @foreach ($this->tournaments as $tournamentOption)
                    <option value="{{ $tournamentOption->id }}">{{ $tournamentOption->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="hf-field">
            <label class="hf-label" for="matchRound">Round</label>
            <select class="hf-select" id="matchRound" wire:model.live="round" data-testid="match-round-filter">
                <option value="">All rounds</option>
                @foreach ($this->rounds as $roundNumber)
                    <option value="{{ $roundNumber }}">{{ $roundLabel($roundNumber) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="hf-btn-row hf-mt-2">
        <button type="button" class="hf-btn hf-btn--sm" wire:click="clearFilters" data-testid="match-clear-filters">Clear filters</button>
    </div>
</div>

<div id="matchList" data-testid="match-list">
    @if ($matches->isEmpty())
        <div class="hf-empty">
            <i class="bi bi-search" aria-hidden="true"></i>
            <h3>No matchups match</h3>
            <p>Clear the tournament or round filter and try again.</p>
        </div>
    @else
        @foreach ($groups as $day => $dayMatches)
            <section class="hf-mb-3" data-testid="match-day-{{ $day }}">
                <div class="hf-section-head">
                    <h2 class="hf-section-title">{{ $day === 'unscheduled' ? 'Unscheduled' : \Carbon\Carbon::parse($day)->format('M d, Y') }}</h2>
                    <span class="hf-chip hf-chip--slate">{{ $dayMatches->count() }} games</span>
                </div>
                <div class="hf-grid hf-grid--2">
                    @foreach ($dayMatches as $match)
                        @php
                            $home = $match->homeTeam;
                            $away = $match->awayTeam;
                            $winnerHome = $match->winner_team_id !== null && $match->winner_team_id === $match->home_team_id;
                            $winnerAway = $match->winner_team_id !== null && $match->winner_team_id === $match->away_team_id;
                            $court = $match->matchSlots->first()?->court;
                            $when = $match->matchSlots->first()?->start_at?->format('g:i A') ?? 'Time TBD';
                            $currentRoundLabel = $roundLabel($match->round_number);
                        @endphp
                        <a class="hf-match-card hf-match-card--{{ $match->status }}"
                           data-match-id="{{ $match->id }}"
                           data-testid="match-card-{{ $match->id }}"
                           href="{{ route('gameStats', ['match' => $match->id]) }}">
                            <div class="hf-match-head">
                                <span>{{ $currentRoundLabel }}{{ $court ? ' · ' . $court->name : '' }}</span>
                                <span class="hf-chip hf-chip--slate">{{ ucfirst($match->status) }}</span>
                            </div>
                            <div class="hf-match-team {{ $winnerHome ? 'is-winner' : ($match->status !== 'scheduled' ? 'is-loser' : '') }}">
                                <span class="hf-shield hf-shield--sm">{{ strtoupper(substr($home?->name ?? 'TBD', 0, 1)) }}</span>
                                <span>{{ $home?->name ?? 'TBD' }}</span>
                                <span class="hf-score {{ $match->home_score === null ? 'hf-score--muted' : '' }}">{{ $match->home_score ?? '–' }}</span>
                            </div>
                            <div class="hf-match-team {{ $winnerAway ? 'is-winner' : ($match->status !== 'scheduled' ? 'is-loser' : '') }}">
                                <span class="hf-shield hf-shield--sm">{{ strtoupper(substr($away?->name ?? 'TBD', 0, 1)) }}</span>
                                <span>{{ $away?->name ?? 'TBD' }}</span>
                                <span class="hf-score {{ $match->away_score === null ? 'hf-score--muted' : '' }}">{{ $match->away_score ?? '–' }}</span>
                            </div>
                            <div class="hf-match-head">
                                <span>{{ $when }}</span>
                                <span>{{ $currentRoundLabel }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
</div>
