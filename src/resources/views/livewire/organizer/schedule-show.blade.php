<div>
@php
    $tournament = $this->tournament;
    $matchSlots = $tournament?->matchSlots()->with(['match.homeTeam', 'match.awayTeam', 'court'])->get() ?? collect();
    $slotsByDay = $matchSlots->groupBy(fn ($slot) => optional($slot->start_at)->format('Y-m-d'));
    $selectedDay = $this->selectedDay;
    if (! $selectedDay) {
        $selectedDay = $slotsByDay->keys()->first() ?? optional($tournament?->start_date)->format('Y-m-d') ?? now()->format('Y-m-d');
    }
    $currentMonth = $this->currentMonth ?: substr($selectedDay, 0, 7);
    $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $currentMonth) ?: now();
    $firstOfMonth = $monthDate->copy()->startOfMonth();
    $startCell = $firstOfMonth->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
    $cells = [];
    for ($i = 0; $i < 42; $i++) {
        $cells[] = $startCell->copy()->addDays($i)->format('Y-m-d');
    }
    $daySlots = ($slotsByDay[$selectedDay] ?? collect())->sortBy('start_at')->values();
    $user = auth()->user();
    $isOrganizer = $user && $user->hasRole('organizer');
    $scheduledCount = $matchSlots->count();
    $matchDayCount = $slotsByDay->count();
    $courtCount = $matchSlots->pluck('court_id')->filter()->unique()->count();
    $resultsCount = $tournament?->matches()->whereIn('status', ['final', 'forfeit'])->count() ?? 0;
    $unscheduledCount = $tournament ? $tournament->matches()->whereDoesntHave('matchSlots')->count() : 0;
@endphp

<div class="hf-page-header hf-mb-3">
    <span class="hf-eyebrow">{{ $tournament?->division ?? '' }} · {{ $tournament?->venue ?? '' }}</span>
    <h1 class="hf-page-title">Court schedule</h1>
    <p class="hf-muted">
        @if ($tournament)
            {{ optional($tournament->start_date)->format('M d, Y') }} to {{ optional($tournament->end_date)->format('M d, Y') }} · {{ $courtCount }} courts · {{ $tournament->slotMinutes ?? 30 }}-minute slots
        @else
            No tournament selected.
        @endif
    </p>
    <div class="hf-flex hf-gap-2 hf-mt-2">
        @if ($tournament)
            <a class="hf-btn" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}" data-testid="schedule-tournament"><i class="bi bi-arrow-left"></i> Tournament</a>
            <a class="hf-btn" href="{{ route('bracket') }}" data-testid="schedule-bracket"><i class="bi bi-diagram-3"></i> Bracket</a>
        @endif
        @if ($isOrganizer && $matchSlots->isNotEmpty())
            <button type="button" class="hf-btn" wire:click="generateSchedule" data-testid="schedule-regenerate">Regenerate schedule</button>
        @endif
    </div>
</div>

<div class="hf-grid hf-grid--4 hf-mb-3">
    <div class="hf-card hf-card--flat" data-testid="schedule-kpi-scheduled">
        <span class="hf-kpi-label">Scheduled</span>
        <strong class="hf-kpi-value">{{ $scheduledCount }}</strong>
        <span class="hf-small hf-muted">of {{ $tournament?->matches()->count() ?? 0 }} bracket games</span>
    </div>
    <div class="hf-card hf-card--flat" data-testid="schedule-kpi-days">
        <span class="hf-kpi-label">Matchdays</span>
        <strong class="hf-kpi-value">{{ $matchDayCount }}</strong>
        <span class="hf-small hf-muted">Days with at least one game</span>
    </div>
    <div class="hf-card hf-card--flat" data-testid="schedule-kpi-courts">
        <span class="hf-kpi-label">Courts</span>
        <strong class="hf-kpi-value">{{ $courtCount }}</strong>
        <span class="hf-small hf-muted">{{ $tournament?->venue ?? '' }}</span>
    </div>
    <div class="hf-card hf-card--flat" data-testid="schedule-kpi-results">
        <span class="hf-kpi-label">Results</span>
        <strong class="hf-kpi-value">{{ $resultsCount }}</strong>
        <span class="hf-small hf-muted">Final and forfeit</span>
    </div>
</div>

<div class="hf-split">
    <div>
        <div class="hf-card hf-mb-3">
            <div class="hf-between hf-mb-2">
                <button type="button" class="hf-btn hf-btn--sm" wire:click="navigateMonth(-1)" data-testid="schedule-month-prev">
                    <i class="bi bi-chevron-left"></i> Prev
                </button>
                <strong class="hf-section-title hf-mb-0" data-testid="schedule-month-label">{{ $monthDate->format('F Y') }}</strong>
                <button type="button" class="hf-btn hf-btn--sm" wire:click="navigateMonth(1)" data-testid="schedule-month-next">
                    Next <i class="bi bi-chevron-right"></i>
                </button>
            </div>
            <div class="hf-calendar" data-testid="schedule-calendar">
                @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday)
                    <div class="hf-calendar-head">{{ $weekday }}</div>
                @endforeach
                @foreach ($cells as $cell)
                    @php
                        $count = isset($slotsByDay[$cell]) ? $slotsByDay[$cell]->count() : 0;
                        $outside = substr($cell, 0, 7) !== $currentMonth;
                    @endphp
                    <button type="button"
                            class="hf-calendar-cell {{ $count ? 'has-events' : '' }} {{ $cell === $selectedDay ? 'is-selected' : '' }}"
                            wire:click="selectDay('{{ $cell }}')"
                            data-testid="schedule-day-{{ $cell }}"
                            style="{{ $outside ? 'opacity: .45' : '' }}">
                        <span>{{ (int) substr($cell, 8, 2) }}</span>
                        @if ($count)
                            <span class="hf-calendar-count">{{ $count }} games</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        <div class="hf-card hf-mb-3">
            <div class="hf-between hf-mb-2">
                <span class="hf-label hf-mb-0">Selected day</span>
                <span class="hf-chip hf-chip--slate">{{ $daySlots->count() }} games</span>
            </div>
            <div class="hf-grid hf-grid--2" data-testid="schedule-day-list">
                @forelse ($daySlots as $slot)
                    <div class="hf-card hf-card--flat" data-testid="schedule-match-{{ $slot->match?->id ?? $slot->id }}">
                        <div class="hf-between">
                            <span>{{ $slot->match?->homeTeam?->name ?? 'TBD' }}</span>
                            <strong>vs</strong>
                            <span>{{ $slot->match?->awayTeam?->name ?? 'TBD' }}</span>
                        </div>
                        <p class="hf-small hf-muted hf-mb-0">
                            {{ $slot->court?->name ?? 'Court TBD' }} · {{ optional($slot->start_at)->format('g:i A') }}
                        </p>
                    </div>
                @empty
                    <div class="hf-empty">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        <h3>No games scheduled</h3>
                        <p>Choose another day or publish a schedule.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <aside>
        <div class="hf-card hf-mb-3">
            <span class="hf-kpi-label">Selected day</span>
            <h3 class="hf-section-title hf-mt-1">{{ \Carbon\Carbon::parse($selectedDay)->format('l, M d, Y') }}</h3>
            <p class="hf-small hf-muted hf-mb-2" data-testid="schedule-day-summary">
                {{ $daySlots->count() }} games · {{ $daySlots->pluck('court_id')->filter()->unique()->count() }} courts · first tip-off {{ $daySlots->first()?->start_at?->format('g:i A') ?? 'TBD' }}
            </p>
            @if ($tournament)
                <a class="hf-btn hf-btn--sm" href="#" data-testid="schedule-open-day">Open day list</a>
            @endif
        </div>

        <div class="hf-card hf-mb-3">
            <span class="hf-kpi-label">Schedule generation</span>
            @if (! $tournament || $tournament->matches()->count() === 0)
                <p class="hf-small hf-muted hf-mt-1 hf-mb-0">Generate the bracket before assigning courts.</p>
            @elseif ($matchSlots->isEmpty())
                <p class="hf-small hf-muted hf-mt-1">The planner checks court double-bookings and team rest before publishing.</p>
                @if ($isOrganizer)
                    <button type="button" class="hf-btn hf-btn--primary hf-mt-1" wire:click="generateSchedule" data-testid="schedule-generate">Preview schedule</button>
                @endif
            @else
                <p class="hf-small hf-muted hf-mt-1 hf-mb-0">
                    {{ $matchSlots->isEmpty() ? '' : 'The court schedule is already published.' }}
                </p>
                @if ($isOrganizer && $resultsCount === 0)
                    <button type="button" class="hf-btn hf-mt-1" wire:click="generateSchedule" data-testid="schedule-regenerate">Regenerate schedule</button>
                @elseif ($resultsCount > 0)
                    <p class="hf-small hf-muted hf-mt-1 hf-mb-0">Results exist, so the schedule is locked.</p>
                @endif
            @endif
        </div>

        <div class="hf-card">
            <span class="hf-kpi-label">Unscheduled</span>
            <h3 class="hf-section-title hf-mt-1">{{ $unscheduledCount }} games</h3>
            <p class="hf-small hf-muted hf-mb-0">
                {{ $unscheduledCount ? 'These bracket games still need a court and time.' : 'Every non-bye game has a court and time.' }}
            </p>
        </div>
    </aside>
</div>
</div>
