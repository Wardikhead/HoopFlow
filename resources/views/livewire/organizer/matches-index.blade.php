<div>
    @php
        $tournament = $this->currentTournament;
        $matches = $this->matches;
        $groups = $matches->groupBy(function ($match) {
            return $match->matchSlots->first()?->start_at?->format('Y-m-d') ?? 'unscheduled';
        });
    @endphp

    @section('title', 'Matchups')

    @if (! $tournament)
        <div class="hf-empty">
            <i class="bi bi-list-ul" aria-hidden="true"></i>
            <h1>Tournament not found</h1>
            <p>The matchup link is invalid or the demo has been reset.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}">Back to tournaments</a>
        </div>
    @else
        @section('meta-description', $tournament->name.' matchups · every scheduled game, court, and result in one place.')

        <x-matchday-strip
            eyebrow="{{ $tournament->divisions->first()?->name ?? $tournament->name }} · {{ $tournament->name }}"
            title="Matchups"
            :status="$tournament->statusLabel()"
            description="Every bracket game grouped by matchday, with court, status, and a link to the official box score."
            :meta="[
                ['icon' => 'calendar3', 'label' => $groups->count().' matchdays'],
                ['icon' => 'list-ul', 'label' => $matches->count().' games'],
            ]"
        >
            <a class="hf-btn" href="{{ route('schedule', ['id' => $tournament->id]) }}" data-testid="matches-schedule"><i class="bi bi-calendar3" aria-hidden="true"></i> Schedule</a>
            <a class="hf-btn" href="{{ route('bracket', ['id' => $tournament->id]) }}" data-testid="matches-bracket"><i class="bi bi-diagram-3" aria-hidden="true"></i> Bracket</a>
            <button type="button" class="hf-btn" data-print-page><i class="bi bi-printer" aria-hidden="true"></i> Print</button>
        </x-matchday-strip>

        <div class="hf-card hf-mb-3">
            <div class="hf-form-grid">
                <div class="hf-field hf-field--full">
                    <label class="hf-label" for="matchSearch">Search</label>
                    <input class="hf-input" id="matchSearch" type="search" placeholder="Team or round" wire:model.live.debounce.150ms="search" data-testid="match-search">
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="matchDate">Day</label>
                    <input class="hf-input" id="matchDate" type="date" wire:model.live="day" data-testid="match-date-filter">
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="matchCourt">Court</label>
                    <select class="hf-select" id="matchCourt" wire:model.live="court" data-testid="match-court-filter">
                        <option value="all">All courts</option>
                        @foreach ($this->courts as $court)
                            <option value="{{ $court->id }}">{{ $court->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="matchStatus">Status</label>
                    <select class="hf-select" id="matchStatus" wire:model.live="status" data-testid="match-status-filter">
                        <option value="all">All statuses</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="final">Final</option>
                        <option value="forfeit">Forfeit</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="matchList" data-testid="match-list" wire:loading.class="is-updating" wire:loading.attr="aria-busy">
            <div class="hf-updating-pill" wire:loading wire:target="search,day,court,status">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Updating…
            </div>
            @if ($matches->isEmpty())
                <div class="hf-empty">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <h3>No matchups match</h3>
                    <p>Clear the day, court, or status filter and try again.</p>
                    <button type="button" class="hf-btn" wire:click="clearFilters" data-testid="match-clear-filters">Clear filters</button>
                </div>
            @else
                @foreach ($groups as $day => $dayMatches)
                    <section class="hf-mb-3" data-testid="match-day-{{ $day }}">
                        <div class="hf-section-head">
                            <h2 class="hf-section-title">{{ $day === 'unscheduled' ? 'Unscheduled' : \Carbon\Carbon::parse($day)->format('l, F j, Y') }}</h2>
                            <span class="hf-chip hf-chip--slate">{{ $dayMatches->count() }} games</span>
                        </div>
                        <div class="hf-grid hf-grid--2">
                            @foreach ($dayMatches as $match)
                                <x-match-card
                                    :match="$match"
                                    :href="route('gameStats', ['match' => $match->id])"
                                />
                            @endforeach
                        </div>
                    </section>
                @endforeach
            @endif
        </div>
    @endif
</div>
