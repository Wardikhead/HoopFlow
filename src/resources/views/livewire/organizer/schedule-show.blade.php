<div>
    @php
        $tournament = $this->tournament;
    @endphp

    @if (! $tournament)
        <div class="hf-empty" data-testid="schedule-empty">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <h3>Tournament not found</h3>
            <p>The schedule link is invalid or the demo has been reset.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}">Back to tournaments</a>
        </div>
    @else
        @php
            $courts = $this->courts;
            $daySlots = $this->daySlots;
        @endphp

        @section('meta-description', $tournament->name.' court schedule · dates, courts, and tip-off times for every matchup.')

        @section('breadcrumbs')
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Tournaments', 'href' => route('tournaments')],
                ['label' => $tournament->name, 'href' => route('tournamentShow', ['tournament' => $tournament->id])],
                ['label' => 'Schedule'],
            ]" />
        @endsection

        <x-matchday-strip
            eyebrow="{{ $tournament->divisions?->first()?->name ?? 'Tournament' }} · {{ $this->venueName }}"
            title="Court schedule"
            :status="$this->hasScheduledGames ? 'Published' : 'Not published'"
            :description="$tournament->start_date?->format('M d, Y').' to '.$tournament->end_date?->format('M d, Y').' · '.$courts->count().' courts · '.$this->slotMinutes.'-minute slots with '.$this->restMinutes.' minutes of rest.'"
            :meta="[
                ['icon' => 'calendar3', 'label' => $this->matchDayCount.' matchdays'],
                ['icon' => 'clock', 'label' => $this->slotMinutes.'-minute slots'],
                ['icon' => 'geo-alt', 'label' => $this->venueName],
            ]"
        >
            <a class="hf-btn" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}" data-testid="schedule-tournament"><i class="bi bi-arrow-left" aria-hidden="true"></i> Tournament</a>
            <a class="hf-btn" href="{{ route('bracket', ['id' => $tournament->id]) }}" data-testid="schedule-bracket"><i class="bi bi-diagram-3" aria-hidden="true"></i> Bracket</a>
            <a class="hf-btn" href="{{ route('matches', ['id' => $tournament->id]) }}" data-testid="schedule-matches"><i class="bi bi-list-ul" aria-hidden="true"></i> Matchups</a>
            <button type="button" class="hf-btn" data-print-page><i class="bi bi-printer" aria-hidden="true"></i> Print</button>
            @if ($this->isOrganizer && $this->hasScheduledGames && $this->resultsCount === 0)
                <button type="button" class="hf-btn" wire:click="openRebuild" data-testid="schedule-rebuild" wire:loading.attr="disabled" wire:loading.class="is-loading">Rebuild schedule</button>
            @endif
            @if ($this->isOrganizer && $this->hasScheduledGames)
                <button type="button" class="hf-btn" wire:click="openReschedule" data-testid="schedule-reschedule" wire:loading.attr="disabled" wire:loading.class="is-loading">Reschedule match</button>
            @endif
        </x-matchday-strip>

        <div class="hf-grid hf-grid--4 hf-mb-3">
            <x-kpi-tile label="Scheduled" :value="$this->scheduledCount" :hint="'of '.$this->totalMatches.' bracket games'" tone="primary" testid="schedule-kpi-scheduled" />
            <x-kpi-tile label="Matchdays" :value="$this->matchDayCount" hint="Days with at least one game" tone="gold" testid="schedule-kpi-days" />
            <x-kpi-tile label="Courts" :value="$courts->count()" :hint="$this->venueName" tone="slate" testid="schedule-kpi-courts" />
            <x-kpi-tile label="Results" :value="$this->resultsCount" hint="Final and forfeit" tone="success" testid="schedule-kpi-results" />
        </div>

        <div class="hf-split">
            <div>
                <div class="hf-card hf-mb-3">
                    <div class="hf-between hf-mb-2">
                        <button type="button" class="hf-btn hf-btn--sm" wire:click="navigateMonth(-1)" data-testid="schedule-month-prev">
                            <i class="bi bi-chevron-left"></i> Prev
                        </button>
                        <strong class="hf-section-title hf-mb-0" data-testid="schedule-month-label">{{ $this->monthLabel }}</strong>
                        <button type="button" class="hf-btn hf-btn--sm" wire:click="navigateMonth(1)" data-testid="schedule-month-next">
                            Next <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>

                    <div class="hf-calendar" data-testid="schedule-calendar">
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                            <div class="hf-calendar-head">{{ $weekday }}</div>
                        @endforeach
                        @foreach ($this->monthCells as $cell)
                            <button type="button"
                                    class="hf-calendar-cell{{ $cell['count'] ? ' has-events' : '' }}{{ $cell['reserved'] ? ' is-reserved' : '' }}{{ $cell['key'] === $this->selectedDay ? ' is-selected' : '' }}{{ $cell['key'] === now()->toDateString() ? ' is-today' : '' }}"
                                    wire:click="selectDay('{{ $cell['key'] }}')"
                                    data-testid="schedule-day-{{ $cell['key'] }}"
                                    data-calendar-day
                                    tabindex="0"
                                    data-reserved="{{ $cell['reserved'] ? 'true' : 'false' }}"
                                    title="{{ \Carbon\Carbon::parse($cell['key'])->format('l, F j') }}{{ $cell['count'] ? ' · '.$cell['count'].' games' : ' · no games' }}{{ $cell['reserved'] ? ' · reserved for playoffs' : '' }}"
                                    style="{{ $cell['outside'] ? 'opacity: .45' : '' }}">
                                <span>{{ $cell['day'] }}</span>
                                @if ($cell['count'])
                                    <span class="hf-calendar-count">{{ $cell['count'] }} games</span>
                                @endif
                                <span class="hf-sr-only">{{ \Carbon\Carbon::parse($cell['key'])->format('l, F j, Y') }}{{ $cell['reserved'] ? ', reserved for playoffs' : '' }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="hf-calendar-legend" aria-label="Calendar legend">
                        <span><i class="hf-legend-dot hf-legend-dot--today" aria-hidden="true"></i> Today</span>
                        <span><i class="hf-legend-dot hf-legend-dot--event" aria-hidden="true"></i> Games</span>
                        <span><i class="hf-legend-dot hf-legend-dot--reserved" aria-hidden="true"></i> Playoffs reserved</span>
                    </div>

                    <div class="hf-week-strip" data-testid="schedule-week">
                        @foreach ($this->weekDays as $day)
                            <button type="button"
                                    class="{{ $day['reserved'] ? 'is-reserved' : '' }}{{ $day['key'] === $this->selectedDay ? ' is-selected' : '' }}"
                                    wire:click="selectDay('{{ $day['key'] }}')"
                                    data-testid="schedule-week-{{ $day['key'] }}">
                                <small>{{ $day['weekday'] }}</small>
                                <strong>{{ $day['day'] }}</strong>
                                @if ($day['count'])
                                    <span class="hf-calendar-count">{{ $day['count'] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    @php
                        $calendarWindow = $this->calendarPlayoffWindow;
                    @endphp

                    @if ($calendarWindow !== null)
                        <p class="hf-small hf-muted hf-mt-2 hf-mb-0" data-testid="schedule-playoff-window">
                            <i class="bi bi-calendar-check" aria-hidden="true"></i>
                            {{ \Carbon\Carbon::parse($calendarWindow['startDate'])->format('M j, Y') }}–{{ \Carbon\Carbon::parse($calendarWindow['endDate'])->format('M j, Y') }} reserved for playoffs
                        </p>
                    @endif

                    <div class="hf-between hf-mt-2">
                        <button type="button" class="hf-btn hf-btn--sm" wire:click="shiftWeek(-1)" data-testid="schedule-week-prev">
                            <i class="bi bi-arrow-left"></i> Week
                        </button>
                        <button type="button" class="hf-btn hf-btn--sm" wire:click="firstMatchday" data-testid="schedule-first-day">First matchday</button>
                        <button type="button" class="hf-btn hf-btn--sm" wire:click="shiftWeek(1)" data-testid="schedule-week-next">
                            Week <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <div class="hf-card hf-mb-3">
                    <div class="hf-between hf-mb-2">
                        <span class="hf-label hf-mb-0">Court filters</span>
                        <button type="button" class="hf-section-link" wire:click="allCourts" data-testid="schedule-all-courts">All courts</button>
                    </div>
                    <div class="hf-filter-row" data-court-filters>
                        @foreach ($courts as $court)
                            @php
                                $active = in_array((int) $court->id, $this->selectedCourtIds, true);
                            @endphp
                            <button type="button"
                                    class="hf-chip hf-chip--{{ $active ? 'primary' : 'slate' }}"
                                    wire:click="toggleCourt({{ $court->id }})"
                                    aria-pressed="{{ $active ? 'true' : 'false' }}"
                                    data-testid="schedule-court-{{ $court->id }}">
                                <i class="bi bi-geo-alt"></i> {{ $court->name }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div id="scheduleList" data-testid="schedule-list" wire:loading.class="is-updating" wire:loading.attr="aria-busy">
                    <div class="hf-updating-pill" wire:loading wire:target="selectDay,shiftWeek,firstMatchday,allCourts,toggleCourt">
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Updating…
                    </div>
                    <div class="hf-section-head">
                        <h2 class="hf-section-title">{{ $daySlots->isNotEmpty() ? $this->selectedDayLabel : 'No games on this day' }}</h2>
                        <span class="hf-chip hf-chip--slate">{{ $daySlots->count() }} games</span>
                    </div>
                    @if ($daySlots->isNotEmpty())
                        <div class="hf-grid hf-grid--2" data-testid="schedule-day-list">
                            @foreach ($daySlots as $slot)
                                @if ($slot->match)
                                    <x-match-card
                                        :match="$slot->match"
                                        :href="route('gameStats', ['match' => $slot->match_id])"
                                        :testid="'schedule-match-'.$slot->match_id"
                                    />
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="hf-empty">
                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                            <h3>No games scheduled</h3>
                            <p>Choose another day, re-enable a court filter, or publish a schedule from the tournament desk.</p>
                        </div>
                    @endif
                </div>
            </div>

            <aside>
                <div class="hf-card hf-mb-3">
                    <span class="hf-kpi-label">Selected day</span>
                    <h3 class="hf-section-title hf-mt-1">{{ $this->selectedDayLabel }}</h3>
                    <p class="hf-small hf-muted hf-mb-2" data-testid="schedule-day-summary">
                        {{ $this->daySummary['games'] }} games · {{ $this->daySummary['courts'] }} courts · first tip-off {{ $this->daySummary['first'] }}
                    </p>
                    <a class="hf-btn hf-btn--sm" href="{{ route('matches', ['id' => $tournament->id, 'date' => $this->selectedDay]) }}" data-testid="schedule-open-day">Open day list</a>
                </div>

                <div class="hf-card hf-mb-3">
                    <span class="hf-kpi-label">Schedule generation</span>
                    @if (! $this->hasBracket)
                        <p class="hf-small hf-muted hf-mt-1 hf-mb-0">Generate the bracket before assigning courts.</p>
                    @elseif ($this->canGenerate)
                        <p class="hf-small hf-muted hf-mt-1">The planner checks court double-bookings and team rest before publishing.</p>
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="openPreview" data-testid="schedule-generate" wire:loading.attr="disabled">Build schedule preview</button>
                    @elseif ($this->canRebuild)
                        <p class="hf-small hf-muted hf-mt-1">Rebuild the published schedule from new dates, match days, and timing settings.</p>
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="openRebuild" data-testid="schedule-rebuild-preview">Rebuild schedule</button>
                    @elseif ($this->canScheduleRemaining)
                        <p class="hf-small hf-muted hf-mt-1">Group results are in. Place the remaining playoff games after the last published match day.</p>
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="openRemaining" data-testid="schedule-remaining-preview">Schedule remaining games</button>
                    @else
                        <p class="hf-small hf-muted hf-mt-1 hf-mb-0">
                            {{ $this->locked ? 'Results exist, so the schedule is locked.' : 'The court schedule is already published.' }}
                        </p>
                        <a class="hf-btn hf-btn--sm hf-mt-1" href="{{ route('matches', ['id' => $tournament->id]) }}" data-testid="schedule-view-all">View all matchups</a>
                    @endif
                </div>

                <div class="hf-card">
                    <span class="hf-kpi-label">Unscheduled</span>
                    <h3 class="hf-section-title hf-mt-1">{{ $this->unscheduledCount }} games</h3>
                    <p class="hf-small hf-muted hf-mb-0">
                        {{ $this->unscheduledCount ? 'These bracket games still need a court and time.' : 'Every non-bye game has a court and time.' }}
                    </p>
                </div>
            </aside>
        </div>

        @if ($showPreview)
            <dialog
                class="hf-dialog hf-dialog--wide"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.closePreview()"
                x-on:click="if ($event.target === $el) $wire.closePreview()"
                wire:key="schedule-preview-dialog"
                data-testid="schedule-preview-dialog"
                aria-labelledby="schedulePreviewDialogTitle">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title" id="schedulePreviewDialogTitle">{{ $previewMode === 'rebuild' ? 'Rebuild schedule' : ($previewMode === 'remaining' ? 'Schedule remaining games' : 'Schedule preview') }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closePreview" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body">
                    <div class="hf-form-grid">
                        <div class="hf-field">
                            <label class="hf-label" for="previewStart">Start date</label>
                            <input id="previewStart" class="hf-input" type="date" wire:model.live="previewStartDate" data-testid="preview-start">
                        </div>
                        <div class="hf-field">
                            <label class="hf-label" for="previewEnd">End date</label>
                            <input id="previewEnd" class="hf-input" type="date" wire:model.live="previewEndDate" data-testid="preview-end">
                            @if ($previewEndDate === '')
                                <span class="hf-small hf-muted">No end date set.</span>
                            @endif
                        </div>
                        <div class="hf-field">
                            <label class="hf-label" for="previewSlot">Slot minutes</label>
                            <input id="previewSlot" class="hf-input" type="number" min="10" max="240" wire:model.live="previewSlotMinutes" data-testid="preview-slot">
                        </div>
                        <div class="hf-field">
                            <label class="hf-label" for="previewRest">Rest minutes</label>
                            <input id="previewRest" class="hf-input" type="number" min="0" max="120" wire:model.live="previewRestMinutes" data-testid="preview-rest">
                        </div>
                    </div>

                    <div class="hf-field hf-mt-2">
                        <span class="hf-label">Preferred match days</span>
                        <div class="hf-btn-row">
                            @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day => $label)
                                <button
                                    type="button"
                                    class="hf-chip hf-chip--{{ in_array($day, $previewWeekdays, true) ? 'primary' : 'slate' }}"
                                    wire:click="togglePreviewWeekday({{ $day }})"
                                    aria-pressed="{{ in_array($day, $previewWeekdays, true) ? 'true' : 'false' }}"
                                    data-testid="preview-weekday-{{ $day }}"
                                >{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>

                    <p class="hf-small hf-muted hf-mt-2" data-testid="preview-summary">
                        {{ count($previewPlan['assignments'] ?? []) }} games ·
                        {{ $previewPlan['summary']['matchDays'] ?? 0 }} match days ·
                        ends {{ $previewPlan['summary']['lastDate'] ?? 'TBD' }} ·
                        {{ count($previewWarnings) }} warnings ·
                        {{ count($previewErrors) }} conflicts
                    </p>

                    @php
                        $playoffWindow = $this->previewPlayoffWindow;
                    @endphp

                    @if ($playoffWindow !== null)
                        <div class="hf-card hf-card--flat hf-mt-2" data-testid="preview-playoff-window">
                            <div class="hf-between">
                                <strong>Playoffs reserved</strong>
                                <span class="hf-small hf-muted">
                                    {{ \Carbon\Carbon::parse($playoffWindow['startDate'])->format('M j, Y') }}–{{ \Carbon\Carbon::parse($playoffWindow['endDate'])->format('M j, Y') }}
                                    · {{ $playoffWindow['dayCount'] }} {{ \Illuminate\Support\Str::plural('match day', $playoffWindow['dayCount']) }}
                                </span>
                            </div>
                            <p class="hf-small hf-muted hf-mb-0 hf-mt-1">
                                @if ($playoffWindow['groupEndDate'] !== null)
                                    Group stage must finish by {{ \Carbon\Carbon::parse($playoffWindow['groupEndDate'])->format('M j, Y') }}.
                                @else
                                    The playoff window uses every remaining match day.
                                @endif
                                @foreach ($playoffWindow['rounds'] as $round)
                                    {{ $round['label'] }}: {{ $round['days'] }} {{ \Illuminate\Support\Str::plural('day', $round['days']) }}@if (! $loop->last) · @endif
                                @endforeach
                            </p>
                        </div>
                    @endif

                    @php
                        $legacyOverlap = $this->legacyPlayoffOverlap;
                    @endphp

                    @if ($legacyOverlap !== [])
                        <div class="hf-callout hf-callout--warning hf-mt-2" data-testid="preview-playoff-overlap">
                            <strong><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Playoff window occupied</strong>
                            <p class="hf-small hf-mb-0">
                                Published group games still occupy {{ \Carbon\Carbon::parse($legacyOverlap[0])->format('M j') }}–{{ \Carbon\Carbon::parse($legacyOverlap[count($legacyOverlap) - 1])->format('M j, Y') }}, which is reserved for playoffs. Extend the end date or reschedule those group games; published slots are never moved automatically.
                            </p>
                        </div>
                    @endif

                    @if ($previewDirty)
                        <p class="hf-field-error" data-testid="preview-stale">Settings changed — rebuild the preview before publishing.</p>
                    @endif

                    @if ($previewErrors !== [])
                        <div class="hf-card hf-card--flat hf-mb-2" data-testid="preview-errors">
                            @foreach ($previewErrors as $message)
                                <p class="hf-small hf-mb-0">{{ $message }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="hf-table-wrap">
                        <table class="hf-table">
                            <caption class="hf-sr-only">Scheduled matches</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Round</th>
                                    <th scope="col">Match</th>
                                    <th scope="col">Court</th>
                                    <th scope="col">Slot</th>
                                    <th scope="col">Check</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->previewRows as $row)
                                    <tr data-testid="preview-row-{{ $row['matchId'] }}">
                                        <td>
                                            {{ $row['round'] }}
                                            @if ($row['game'])
                                                <span class="hf-small hf-muted">{{ $row['game'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $row['match'] }}</td>
                                        <td>
                                            <select
                                                class="hf-select"
                                                aria-label="Court for {{ $row['match'] }}"
                                                @if ($row['error']) aria-invalid="true" aria-describedby="preview-error-{{ $row['matchId'] }}" @endif
                                                wire:change="updatePreviewAssignment({{ $row['matchId'] }}, $event.target.value, null)"
                                                data-testid="preview-court-{{ $row['matchId'] }}"
                                            >
                                                @foreach ($courts as $courtOption)
                                                    <option value="{{ $courtOption->id }}" @selected((int) $courtOption->id === $row['courtId'])>{{ $courtOption->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input
                                                class="hf-input"
                                                type="datetime-local"
                                                aria-label="Start time for {{ $row['match'] }}"
                                                @if ($row['error']) aria-invalid="true" aria-describedby="preview-error-{{ $row['matchId'] }}" @endif
                                                value="{{ $row['scheduledAt'] }}"
                                                wire:change="updatePreviewAssignment({{ $row['matchId'] }}, null, $event.target.value)"
                                                data-testid="preview-time-{{ $row['matchId'] }}"
                                            >
                                        </td>
                                        <td>
                                            @if ($row['error'])
                                                <span class="hf-field-error" id="preview-error-{{ $row['matchId'] }}">{{ $row['error'] }}</span>
                                            @elseif ($row['warning'])
                                                <span class="hf-chip hf-chip--gold">{{ $row['warning'] }}</span>
                                            @else
                                                <span class="hf-chip hf-chip--success">OK</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="closePreview" data-testid="schedule-preview-cancel">Cancel</button>
                    <button type="button" class="hf-btn" wire:click="resetPreview" data-testid="preview-reset">Reset</button>
                    <button type="button" class="hf-btn" wire:click="buildPreview" data-testid="preview-build" wire:loading.attr="disabled">Build preview</button>
                    <button
                        type="button"
                        class="hf-btn hf-btn--primary"
                        wire:click="publishSchedule"
                        data-testid="schedule-preview-publish"
                        wire:loading.attr="disabled"
                        wire:loading.class="is-loading"
                        @disabled($previewDirty || $previewErrors !== [])
                    >{{ $previewMode === 'remaining' ? 'Schedule remaining games' : 'Publish schedule' }}</button>
                </div>
            </dialog>
        @endif

        @if ($showReschedule)
            <dialog
                class="hf-dialog hf-dialog--wide"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.closeReschedule()"
                x-on:click="if ($event.target === $el) $wire.closeReschedule()"
                wire:key="schedule-reschedule-dialog"
                data-testid="schedule-reschedule-dialog"
                aria-labelledby="scheduleRescheduleDialogTitle">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title" id="scheduleRescheduleDialogTitle">Reschedule match</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeReschedule" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body">
                    <div class="hf-form-grid">
                        <div class="hf-field hf-field--full">
                            <label class="hf-label" for="rescheduleMatch">Match</label>
                            <select
                                id="rescheduleMatch"
                                class="hf-select"
                                wire:model="rescheduleMatchId"
                                data-testid="reschedule-match"
                                x-on:change="
                                    const option = $event.target.selectedOptions[0];
                                    if (! option) return;
                                    if (option.dataset.courtId) {
                                        $refs.rescheduleCourt.value = option.dataset.courtId;
                                        $refs.rescheduleCourt.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                    if (option.dataset.scheduledAt) {
                                        $refs.rescheduleTime.value = option.dataset.scheduledAt;
                                        $refs.rescheduleTime.dispatchEvent(new Event('input', { bubbles: true }));
                                    }
                                "
                            >
                                @foreach ($this->scheduledMatchOptions as $option)
                                    <option
                                        value="{{ $option['id'] }}"
                                        data-court-id="{{ $option['courtId'] }}"
                                        data-scheduled-at="{{ $option['scheduledAt'] }}"
                                    >{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="hf-field">
                            <label class="hf-label" for="rescheduleCourt">Court</label>
                            <select id="rescheduleCourt" class="hf-select" wire:model="rescheduleCourtId" x-ref="rescheduleCourt" data-testid="reschedule-court">
                                @foreach ($courts as $court)
                                    <option value="{{ $court->id }}">{{ $court->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="hf-field">
                            <label class="hf-label" for="rescheduleTime">Date &amp; time</label>
                            <input id="rescheduleTime" class="hf-input" type="datetime-local" wire:model="rescheduleScheduledAt" x-ref="rescheduleTime" data-testid="reschedule-time" @error('rescheduleScheduledAt') aria-invalid="true" aria-describedby="rescheduleTimeError" @enderror>
                        </div>
                    </div>
                    @error('rescheduleScheduledAt')
                        <p class="hf-field-error hf-mt-1" id="rescheduleTimeError">{{ $message }}</p>
                    @enderror
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="closeReschedule" data-testid="reschedule-cancel">Cancel</button>
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="saveReschedule" data-testid="reschedule-save" wire:loading.attr="disabled">Save</button>
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
                wire:key="schedule-confirm-dialog"
                data-testid="schedule-confirm-dialog"
                aria-labelledby="scheduleConfirmDialogTitle">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title" id="scheduleConfirmDialogTitle">{{ $confirmTitle }}</h2>
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
