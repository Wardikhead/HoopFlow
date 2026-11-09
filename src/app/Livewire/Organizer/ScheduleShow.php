<?php

namespace App\Livewire\Organizer;

use App\Models\Court;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Tournament;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Tournament|null $tournament
 * @property-read Collection<int, Court> $courts
 * @property-read Collection<int, MatchModel> $matches
 * @property-read Collection<int, MatchSlot> $matchSlots
 * @property-read Collection<int, MatchSlot> $scheduledSlots
 * @property-read Collection<int, MatchSlot> $visibleSlots
 * @property-read Collection<int, MatchSlot> $daySlots
 * @property-read array<int, string> $matchDays
 * @property-read array<int, array{key: string, day: int, count: int, outside: bool}> $monthCells
 * @property-read array<int, array{key: string, weekday: string, day: int, count: int}> $weekDays
 * @property-read array{games: int, courts: int, first: string} $daySummary
 * @property-read array<int, array{id: int, label: string, courtId: int, scheduledAt: string}> $scheduledMatchOptions
 * @property-read array<int, array{round: string, match: string, court: string, slot: string}> $previewRows
 * @property-read string $venueName
 * @property-read string $selectedDayLabel
 * @property-read string $monthLabel
 * @property-read int $scheduledCount
 * @property-read int $totalMatches
 * @property-read int $matchDayCount
 * @property-read int $resultsCount
 * @property-read int $unscheduledCount
 * @property-read bool $hasBracket
 * @property-read bool $hasScheduledGames
 * @property-read bool $isOrganizer
 * @property-read bool $canGenerate
 * @property-read bool $locked
 * @property-read int $slotMinutes
 * @property-read int $restMinutes
 */
#[Layout('layouts.public')]
#[Title('Schedule')]
class ScheduleShow extends Component
{
    public int $tournamentId = 0;

    public string $currentMonth = '';

    public ?string $selectedDay = null;

    /** @var array<int, int> */
    public array $selectedCourtIds = [];

    public bool $showPreview = false;

    /** @var array<string, mixed> */
    public array $previewPlan = [];

    public bool $showReschedule = false;

    public ?int $rescheduleMatchId = null;

    public ?int $rescheduleCourtId = null;

    public string $rescheduleScheduledAt = '';

    public bool $showConfirm = false;

    public string $confirmTitle = 'Confirm action';

    public string $confirmMessage = '';

    public string $confirmLabel = 'Confirm';

    public string $confirmTone = 'primary';

    public string $confirmAction = '';

    public function mount(int|string|null $id = null): void
    {
        if ($id !== null && is_numeric($id)) {
            $this->tournamentId = (int) $id;
        }

        if ($this->tournamentId === 0) {
            $query = request()->query('tournament') ?? request()->query('id');

            if (is_numeric($query)) {
                $this->tournamentId = (int) $query;
            }
        }

        if ($this->tournamentId === 0) {
            $first = Tournament::query()->orderBy('id')->first();
            $this->tournamentId = $first->id ?? 0;
        }

        $tournament = Tournament::find($this->tournamentId);

        if (! $tournament instanceof Tournament) {
            return;
        }

        $service = app(SchedulingService::class);

        $this->selectedCourtIds = $service->courts($tournament)
            ->pluck('id')
            ->map(fn ($courtId): int => (int) $courtId)
            ->all();

        $requestedDate = request()->query('date');
        $firstSlot = MatchSlot::query()
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('start_at')
            ->orderBy('start_at')
            ->value('start_at');

        $firstMatchDay = $firstSlot !== null
            ? Carbon::parse($firstSlot)->format('Y-m-d')
            : null;

        $selected = is_string($requestedDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) === 1
            ? $requestedDate
            : ($firstMatchDay ?? $service->startDate($tournament));

        $this->selectedDay = $selected;
        $this->currentMonth = substr($selected, 0, 7);
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        return Tournament::find($this->tournamentId);
    }

    /**
     * @return Collection<int, Court>
     */
    #[Computed]
    public function courts(): Collection
    {
        return $this->tournament instanceof Tournament
            ? app(SchedulingService::class)->courts($this->tournament)
            : new Collection;
    }

    #[Computed]
    public function venueName(): string
    {
        $court = $this->courts->first();

        if ($court?->venue !== null) {
            return $court->venue->name;
        }

        return $this->tournament instanceof Tournament
            ? $this->tournament->name
            : 'Tournament';
    }

    /**
     * @return Collection<int, MatchModel>
     */
    #[Computed]
    public function matches(): Collection
    {
        if (! $this->tournament instanceof Tournament) {
            return new Collection;
        }

        return MatchModel::query()
            ->where('tournament_id', $this->tournament->id)
            ->with(['homeTeam', 'awayTeam', 'matchSlots.court'])
            ->get();
    }

    /**
     * @return Collection<int, MatchSlot>
     */
    #[Computed]
    public function matchSlots(): Collection
    {
        if (! $this->tournament instanceof Tournament) {
            return new Collection;
        }

        return MatchSlot::query()
            ->where('tournament_id', $this->tournament->id)
            ->with(['match.homeTeam', 'match.awayTeam', 'court'])
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, MatchSlot>
     */
    #[Computed]
    public function scheduledSlots(): Collection
    {
        return $this->matchSlots->values();
    }

    /**
     * @return Collection<int, MatchSlot>
     */
    #[Computed]
    public function visibleSlots(): Collection
    {
        return $this->scheduledSlots
            ->filter(fn (MatchSlot $slot): bool => in_array((int) $slot->court_id, $this->selectedCourtIds, true))
            ->values();
    }

    /**
     * @return Collection<int, MatchSlot>
     */
    #[Computed]
    public function daySlots(): Collection
    {
        $day = $this->selectedDay;

        if ($day === null) {
            return new Collection;
        }

        return $this->visibleSlots
            ->filter(fn (MatchSlot $slot): bool => $slot->start_at?->format('Y-m-d') === $day)
            ->sortBy('start_at')
            ->values();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function matchDays(): array
    {
        return $this->scheduledSlots
            ->map(fn (MatchSlot $slot): string => $slot->start_at->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    #[Computed]
    public function selectedDayLabel(): string
    {
        $day = $this->selectedDay;

        return $day !== null
            ? Carbon::parse($day)->format('l, F j, Y')
            : 'No day selected';
    }

    #[Computed]
    public function monthLabel(): string
    {
        $month = Carbon::createFromFormat('Y-m', $this->currentMonth) ?: now();

        return $month->format('F Y');
    }

    /**
     * @return array<int, array{key: string, day: int, count: int, outside: bool}>
     */
    #[Computed]
    public function monthCells(): array
    {
        $monthDate = Carbon::createFromFormat('Y-m', $this->currentMonth) ?: now();
        $monthDate = $monthDate->copy()->startOfMonth();
        $start = $monthDate->copy()->startOfWeek(Carbon::SUNDAY);
        $monthKey = $monthDate->format('Y-m');
        $selected = $this->selectedDay ?? '';
        $cells = [];

        for ($index = 0; $index < 42; $index++) {
            $date = $start->copy()->addDays($index);
            $key = $date->format('Y-m-d');

            $cells[] = [
                'key' => $key,
                'day' => (int) $date->format('j'),
                'count' => $this->visibleSlots
                    ->filter(fn (MatchSlot $slot): bool => $slot->start_at?->format('Y-m-d') === $key)
                    ->count(),
                'outside' => $date->format('Y-m') !== $monthKey,
            ];
        }

        return $cells;
    }

    /**
     * @return array<int, array{key: string, weekday: string, day: int, count: int}>
     */
    #[Computed]
    public function weekDays(): array
    {
        $selected = Carbon::parse($this->selectedDay ?? now());
        $start = $selected->copy()->subDays(3);
        $days = [];

        for ($index = 0; $index < 7; $index++) {
            $date = $start->copy()->addDays($index);
            $key = $date->format('Y-m-d');

            $days[] = [
                'key' => $key,
                'weekday' => $date->format('D'),
                'day' => (int) $date->format('j'),
                'count' => $this->visibleSlots
                    ->filter(fn (MatchSlot $slot): bool => $slot->start_at?->format('Y-m-d') === $key)
                    ->count(),
            ];
        }

        return $days;
    }

    /**
     * @return array{games: int, courts: int, first: string}
     */
    #[Computed]
    public function daySummary(): array
    {
        $slots = $this->daySlots;

        return [
            'games' => $slots->count(),
            'courts' => $slots->pluck('court_id')->filter()->unique()->count(),
            'first' => $slots->first()?->start_at?->format('g:i A') ?? 'TBD',
        ];
    }

    #[Computed]
    public function scheduledCount(): int
    {
        return $this->scheduledSlots->count();
    }

    #[Computed]
    public function totalMatches(): int
    {
        return $this->matches->count();
    }

    #[Computed]
    public function matchDayCount(): int
    {
        return count($this->matchDays);
    }

    #[Computed]
    public function resultsCount(): int
    {
        return $this->matches
            ->filter(fn (MatchModel $match): bool => in_array($match->status, ['final', 'forfeit'], true))
            ->count();
    }

    #[Computed]
    public function unscheduledCount(): int
    {
        return $this->matches
            ->filter(fn (MatchModel $match): bool => $match->matchSlots->isEmpty() && ! $match->isByeGame())
            ->count();
    }

    #[Computed]
    public function hasBracket(): bool
    {
        return $this->matches->isNotEmpty();
    }

    #[Computed]
    public function hasScheduledGames(): bool
    {
        return $this->scheduledSlots->isNotEmpty();
    }

    #[Computed]
    public function isOrganizer(): bool
    {
        return auth()->user()?->hasRole('organizer') ?? false;
    }

    #[Computed]
    public function canGenerate(): bool
    {
        return $this->isOrganizer
            && $this->hasBracket
            && $this->resultsCount === 0
            && ! $this->hasScheduledGames;
    }

    #[Computed]
    public function locked(): bool
    {
        return $this->hasBracket && $this->resultsCount > 0;
    }

    #[Computed]
    public function slotMinutes(): int
    {
        return $this->tournament instanceof Tournament
            ? app(SchedulingService::class)->slotMinutes($this->tournament)
            : 30;
    }

    #[Computed]
    public function restMinutes(): int
    {
        return $this->tournament instanceof Tournament
            ? app(SchedulingService::class)->restMinutes($this->tournament)
            : 15;
    }

    /**
     * @return array<int, array{id: int, label: string, courtId: int, scheduledAt: string}>
     */
    #[Computed]
    public function scheduledMatchOptions(): array
    {
        return $this->scheduledSlots
            ->sortBy('start_at')
            ->map(function (MatchSlot $slot): array {
                $match = $slot->match;
                $home = $match?->homeTeam?->name;
                $away = $match?->awayTeam?->name;

                return [
                    'id' => (int) $slot->match_id,
                    'label' => ($match?->roundLabel() ?? 'Match').' · '.($home ?? 'TBD').' vs '.($away ?? 'TBD'),
                    'courtId' => (int) $slot->court_id,
                    'scheduledAt' => $slot->start_at?->format('Y-m-d\TH:i') ?? '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{round: string, match: string, court: string, slot: string}>
     */
    #[Computed]
    public function previewRows(): array
    {
        $assignments = $this->previewPlan['assignments'] ?? [];

        if ($assignments === []) {
            return [];
        }

        $matches = MatchModel::query()
            ->with(['homeTeam', 'awayTeam'])
            ->whereIn('id', array_column($assignments, 'matchId'))
            ->get()
            ->keyBy('id');

        $courts = $this->courts->keyBy('id');

        return array_map(function (array $assignment) use ($matches, $courts): array {
            $match = $matches->get($assignment['matchId']);
            $court = $courts->get($assignment['courtId']);
            $courtName = $court?->name;
            $start = Carbon::parse($assignment['scheduledAt']);

            return [
                'round' => $match?->roundLabel() ?? 'Match',
                'match' => ($match?->homeTeam?->shortLabel() ?? 'TBD').' vs '.($match?->awayTeam?->shortLabel() ?? 'TBD'),
                'court' => $courtName ?? 'TBD',
                'slot' => $start->format('M d, Y').' · '.$start->format('g:i A'),
            ];
        }, $assignments);
    }

    public function navigateMonth(int $delta): void
    {
        $date = Carbon::createFromFormat('Y-m', $this->currentMonth) ?? now()->startOfMonth();
        $this->currentMonth = $date->copy()->addMonthsNoOverflow($delta)->format('Y-m');
        $this->selectedDay = $this->currentMonth.'-01';
    }

    public function selectDay(string $day): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            return;
        }

        $this->selectedDay = $day;
        $this->currentMonth = substr($day, 0, 7);
    }

    public function shiftWeek(int $delta): void
    {
        $date = Carbon::parse($this->selectedDay ?? now())->addDays($delta * 7);
        $this->selectedDay = $date->format('Y-m-d');
        $this->currentMonth = $date->format('Y-m');
    }

    public function firstMatchday(): void
    {
        $day = $this->matchDays[0]
            ?? ($this->tournament instanceof Tournament
                ? app(SchedulingService::class)->startDate($this->tournament)
                : now()->format('Y-m-d'));

        $this->selectedDay = $day;
        $this->currentMonth = substr($day, 0, 7);
    }

    public function toggleCourt(int|string $courtId): void
    {
        $id = (int) $courtId;

        if (in_array($id, $this->selectedCourtIds, true)) {
            $this->selectedCourtIds = array_values(array_filter(
                $this->selectedCourtIds,
                fn (int $selected): bool => $selected !== $id,
            ));

            return;
        }

        $this->selectedCourtIds[] = $id;
    }

    public function allCourts(): void
    {
        $this->selectedCourtIds = $this->courts
            ->pluck('id')
            ->map(fn ($courtId): int => (int) $courtId)
            ->all();
    }

    public function requestRegenerate(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateSchedule', $tournament);

        if ($this->resultsCount > 0) {
            $this->toast('Cannot regenerate a schedule after results are recorded.', 'Schedule locked', 'warning');

            return;
        }

        $this->openConfirm(
            'Regenerate schedule?',
            'This wipes the current schedule and rebuilds. Recorded results will be lost.',
            'Regenerate',
            'danger',
            'regenerate',
        );
    }

    public function openPreview(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateSchedule', $tournament);

        try {
            $this->previewPlan = app(SchedulingService::class)->plan($tournament);
            $this->showPreview = true;
            $this->showConfirm = false;
            $this->showReschedule = false;
            $this->resetValidation();
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'Could not plan schedule', 'danger');
        }
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
        $this->previewPlan = [];
    }

    public function publishSchedule(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateSchedule', $tournament);

        try {
            $count = app(SchedulingService::class)->generate($tournament);
            $courtCount = count($this->previewPlan['courts'] ?? []);
            $first = $this->previewPlan['assignments'][0]['scheduledAt'] ?? null;

            $this->showPreview = false;
            $this->previewPlan = [];

            if (is_string($first) && $first !== '') {
                $this->selectedDay = substr($first, 0, 10);
                $this->currentMonth = substr($first, 0, 7);
            }

            $this->toast(
                $count.' games scheduled across '.$courtCount.' courts.',
                'Schedule published',
            );
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'Could not publish schedule', 'danger');
        }
    }

    public function openReschedule(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateSchedule', $tournament);

        $first = $this->scheduledSlots->sortBy('start_at')->first();

        if (! $first instanceof MatchSlot) {
            $this->toast('No scheduled games to reschedule.', 'Nothing to reschedule', 'warning');

            return;
        }

        $this->rescheduleMatchId = (int) $first->match_id;
        $this->syncRescheduleFields();
        $this->showReschedule = true;
        $this->showPreview = false;
        $this->showConfirm = false;
        $this->resetValidation();
    }

    public function closeReschedule(): void
    {
        $this->showReschedule = false;
        $this->resetValidation();
    }

    public function saveReschedule(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament || $this->rescheduleMatchId === null) {
            return;
        }

        Gate::authorize('generateSchedule', $tournament);

        $match = MatchModel::query()
            ->where('tournament_id', $tournament->id)
            ->find($this->rescheduleMatchId);

        if (! $match instanceof MatchModel) {
            $this->addError('rescheduleScheduledAt', 'Choose a match from this tournament.');

            return;
        }

        try {
            app(SchedulingService::class)->reschedule(
                $match,
                $this->rescheduleCourtId,
                $this->rescheduleScheduledAt,
            );

            $this->showReschedule = false;
            $this->resetValidation();
            $this->toast('Match rescheduled.', 'Schedule updated');
        } catch (InvalidArgumentException $exception) {
            $this->addError('rescheduleScheduledAt', $exception->getMessage());
            $this->toast($exception->getMessage(), 'Could not reschedule', 'danger');
        }
    }

    public function manualManage(): void
    {
        $this->toast(
            'Manual manage mode: edit courts/times per match (coming soon).',
            'Manual manage',
            'info',
        );
    }

    public function confirm(): void
    {
        $action = $this->confirmAction;
        $this->resetConfirm();

        if ($action !== 'regenerate') {
            return;
        }

        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateSchedule', $tournament);

        try {
            $count = app(SchedulingService::class)->generate($tournament);
            $this->toast($count.' games scheduled.', 'Schedule regenerated');
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'Could not regenerate', 'danger');
        }
    }

    public function cancelConfirm(): void
    {
        $this->resetConfirm();
    }

    protected function openConfirm(
        string $title,
        string $message,
        string $label,
        string $tone,
        string $action,
    ): void {
        $this->confirmTitle = $title;
        $this->confirmMessage = $message;
        $this->confirmLabel = $label;
        $this->confirmTone = $tone;
        $this->confirmAction = $action;
        $this->showConfirm = true;
    }

    protected function resetConfirm(): void
    {
        $this->showConfirm = false;
        $this->confirmTitle = 'Confirm action';
        $this->confirmMessage = '';
        $this->confirmLabel = 'Confirm';
        $this->confirmTone = 'primary';
        $this->confirmAction = '';
    }

    protected function syncRescheduleFields(): void
    {
        if ($this->rescheduleMatchId === null) {
            return;
        }

        $slot = MatchSlot::query()
            ->where('tournament_id', $this->tournamentId)
            ->where('match_id', $this->rescheduleMatchId)
            ->first();

        $this->rescheduleCourtId = $slot?->court_id !== null ? (int) $slot->court_id : null;
        $this->rescheduleScheduledAt = $slot?->start_at?->format('Y-m-d\TH:i') ?? '';
    }

    protected function toast(string $message, string $title, string $tone = 'success'): void
    {
        $this->dispatch('hf-toast', title: $title, message: $message, tone: $tone);
    }

    public function render(): View
    {
        return view('livewire.organizer.schedule-show');
    }
}
