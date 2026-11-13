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
 * @property-read array<int, array{matchId: int, courtId: int, scheduledAt: string, round: string, game: string|null, match: string, court: string, slot: string, error: string|null, warning: string|null}> $previewRows
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
 * @property-read bool $canRebuild
 * @property-read bool $canScheduleRemaining
 * @property-read bool $locked
 * @property-read int $slotMinutes
 * @property-read int $restMinutes
 * @property-read array{startDate: string, endDate: string, groupEndDate: string|null, dates: list<string>, dayCount: int, rounds: list<array{label: string, bestOf: int, days: int}>}|null $previewPlayoffWindow
 * @property-read list<string> $legacyPlayoffOverlap
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

    public string $previewMode = 'generate';

    public string $previewStartDate = '';

    public string $previewEndDate = '';

    /** @var list<int> */
    public array $previewWeekdays = [1, 2, 3, 4, 5, 6, 7];

    public int $previewSlotMinutes = 30;

    public int $previewRestMinutes = 15;

    public bool $previewDirty = false;

    /** @var array<int, string> */
    public array $previewErrors = [];

    /** @var array<int, string> */
    public array $previewWarnings = [];

    /** @var array<string, mixed> */
    public array $lastBuiltPlan = [];

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
            ->where('status', '!=', 'cancelled')
            ->with(['homeTeam', 'awayTeam', 'matchSlots.court', 'bracket:id,format,phase'])
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
            ->whereHas('match', fn ($query) => $query->where('status', '!=', 'cancelled'))
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
            ->filter(fn (MatchModel $match): bool => in_array($match->status, ['final', 'forfeit'], true) && ! $match->isByeGame())
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
    public function canRebuild(): bool
    {
        return $this->isOrganizer
            && $this->hasBracket
            && $this->resultsCount === 0
            && $this->hasScheduledGames;
    }

    #[Computed]
    public function canScheduleRemaining(): bool
    {
        return $this->isOrganizer
            && $this->hasBracket
            && $this->resultsCount > 0
            && $this->unscheduledCount > 0;
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
     * @return array{
     *     startDate: string,
     *     endDate: string,
     *     groupEndDate: string|null,
     *     dates: list<string>,
     *     dayCount: int,
     *     rounds: list<array{label: string, bestOf: int, days: int}>
     * }|null
     */
    #[Computed]
    public function previewPlayoffWindow(): ?array
    {
        if (! $this->tournament instanceof Tournament) {
            return null;
        }

        $options = ['weekdays' => $this->previewWeekdays];

        if ($this->previewEndDate !== '') {
            $options['endDate'] = $this->previewEndDate;
        }

        return app(SchedulingService::class)->playoffWindow($this->tournament, $options);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function legacyPlayoffOverlap(): array
    {
        if ($this->previewMode !== 'remaining' || ! $this->tournament instanceof Tournament) {
            return [];
        }

        $hasUnscheduledPlayoff = $this->matches
            ->filter(fn (MatchModel $match): bool => $match->matchSlots->isEmpty()
                && ! $match->isByeGame()
                && $match->bracket?->phase === 'playoffs')
            ->isNotEmpty();

        if (! $hasUnscheduledPlayoff) {
            return [];
        }

        $window = $this->previewPlayoffWindow;

        if ($window === null) {
            return [];
        }

        return app(SchedulingService::class)->playoffWindowOverlap($this->tournament, $window);
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
     * @return array<int, array{matchId: int, courtId: int, scheduledAt: string, round: string, game: string|null, match: string, court: string, slot: string, error: string|null, warning: string|null}>
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
            $start = Carbon::parse($assignment['scheduledAt']);
            $matchId = (int) $assignment['matchId'];

            return [
                'matchId' => $matchId,
                'courtId' => (int) $assignment['courtId'],
                'scheduledAt' => $start->format('Y-m-d\TH:i'),
                'round' => $match?->roundLabel() ?? 'Match',
                'game' => $match?->best_of > 1 ? $match->seriesGameLabel() : null,
                'match' => ($match?->homeTeam?->shortLabel() ?? 'TBD').' vs '.($match?->awayTeam?->shortLabel() ?? 'TBD'),
                'court' => $court->name ?? 'TBD',
                'slot' => $start->format('M d, Y').' · '.$start->format('g:i A'),
                'error' => $this->previewErrors[$matchId] ?? null,
                'warning' => $this->previewWarnings[$matchId] ?? null,
            ];
        }, $assignments);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['previewStartDate', 'previewEndDate', 'previewWeekdays', 'previewSlotMinutes', 'previewRestMinutes'], true)) {
            $this->previewDirty = true;
        }
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

    public function openPreview(): void
    {
        $this->startPreview('generate');
    }

    public function openRebuild(): void
    {
        $this->startPreview('rebuild');
    }

    public function openRemaining(): void
    {
        $this->startPreview('remaining');
    }

    protected function startPreview(string $mode): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateSchedule', $tournament);

        if ($this->resultsCount > 0 && in_array($mode, ['generate', 'rebuild'], true)) {
            $this->toast('Results already exist. Use Reschedule for individual games.', 'Schedule locked', 'warning');

            return;
        }

        if ($mode === 'remaining' && $this->unscheduledCount === 0) {
            $this->toast('Every game already has a court and time.', 'Nothing to schedule', 'warning');

            return;
        }

        if ($mode === 'rebuild' && ! $this->hasScheduledGames) {
            $this->toast('There is no published schedule to rebuild.', 'Nothing to rebuild', 'warning');

            return;
        }

        $service = app(SchedulingService::class);
        $firstSlot = $this->scheduledSlots->sortBy('start_at')->first();

        $this->previewMode = $mode;
        $this->previewStartDate = match (true) {
            $mode === 'rebuild' && $firstSlot?->start_at !== null => $firstSlot->start_at->format('Y-m-d'),
            $mode === 'remaining' => $service->remainingStartDate($tournament)->format('Y-m-d'),
            default => $service->startDate($tournament),
        };
        $this->previewEndDate = $tournament->end_date?->format('Y-m-d') ?? '';
        $this->previewWeekdays = in_array($mode, ['rebuild', 'remaining'], true)
            ? $this->scheduledSlots
                ->filter(fn (MatchSlot $slot): bool => $slot->start_at !== null)
                ->map(fn (MatchSlot $slot): int => (int) $slot->start_at->isoWeekday())
                ->unique()
                ->sort()
                ->values()
                ->all()
            : range(1, 7);
        $this->previewSlotMinutes = $service->slotMinutes($tournament);
        $this->previewRestMinutes = $service->restMinutes($tournament);

        if ($this->previewWeekdays === []) {
            $this->previewWeekdays = range(1, 7);
        }

        $this->showPreview = true;
        $this->showConfirm = false;
        $this->showReschedule = false;
        $this->resetValidation();
        $this->buildPreview();
    }

    public function buildPreview(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateSchedule', $tournament);

        try {
            $service = app(SchedulingService::class);
            $options = [
                'startDate' => $this->previewStartDate,
                'endDate' => $this->previewEndDate,
                'weekdays' => $this->previewWeekdays,
                'slotMinutes' => $this->previewSlotMinutes,
                'restMinutes' => $this->previewRestMinutes,
            ];

            $plan = $this->previewMode === 'remaining'
                ? $service->planRemaining($tournament, $options)
                : $service->plan($tournament, $options);

            $validation = $service->validatePlan($tournament, $plan, $this->previewMode === 'remaining');

            $this->previewPlan = $plan;
            $this->lastBuiltPlan = $plan;
            $this->previewErrors = $validation['errors'];
            $this->previewWarnings = $validation['warnings'];
            $this->previewDirty = false;
            $this->showPreview = true;
            $this->resetValidation();
        } catch (InvalidArgumentException $exception) {
            $this->previewErrors = [0 => $exception->getMessage()];
            $this->previewWarnings = [];
            $this->previewDirty = true;
            $this->toast($exception->getMessage(), 'Could not build preview', 'danger');
        }
    }

    public function togglePreviewWeekday(int $day): void
    {
        if ($day < 1 || $day > 7) {
            return;
        }

        $this->previewWeekdays = in_array($day, $this->previewWeekdays, true)
            ? array_values(array_filter($this->previewWeekdays, fn (int $selected): bool => $selected !== $day))
            : [...$this->previewWeekdays, $day];

        sort($this->previewWeekdays);
        $this->previewDirty = true;
    }

    public function updatePreviewAssignment(int|string $matchId, int|string|null $courtId, ?string $scheduledAt): void
    {
        $found = false;

        foreach ($this->previewPlan['assignments'] ?? [] as $index => $assignment) {
            if ((int) $assignment['matchId'] !== (int) $matchId) {
                continue;
            }

            if ($courtId !== null && is_numeric($courtId)) {
                $this->previewPlan['assignments'][$index]['courtId'] = (int) $courtId;
            }

            if (is_string($scheduledAt) && trim($scheduledAt) !== '') {
                $this->previewPlan['assignments'][$index]['scheduledAt'] = Carbon::parse($scheduledAt)->format('Y-m-d H:i:s');
            }

            $found = true;
            break;
        }

        if (! $found || ! $this->tournament instanceof Tournament) {
            return;
        }

        $validation = app(SchedulingService::class)->validatePlan(
            $this->tournament,
            $this->previewPlan,
            $this->previewMode === 'remaining',
        );
        $this->previewErrors = $validation['errors'];
        $this->previewWarnings = $validation['warnings'];
    }

    public function resetPreview(): void
    {
        if ($this->lastBuiltPlan === []) {
            return;
        }

        $this->previewPlan = $this->lastBuiltPlan;

        if ($this->tournament instanceof Tournament) {
            $validation = app(SchedulingService::class)->validatePlan(
                $this->tournament,
                $this->previewPlan,
                $this->previewMode === 'remaining',
            );
            $this->previewErrors = $validation['errors'];
            $this->previewWarnings = $validation['warnings'];
        }

        $this->previewDirty = false;
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

        if (($this->previewPlan['assignments'] ?? []) === []) {
            $this->toast('Build a preview before publishing.', 'No preview', 'warning');

            return;
        }

        if ($this->previewDirty) {
            $this->toast('Settings changed — rebuild the preview before publishing.', 'Preview is stale', 'warning');

            return;
        }

        $validation = app(SchedulingService::class)->validatePlan(
            $tournament,
            $this->previewPlan,
            $this->previewMode === 'remaining',
        );
        $this->previewErrors = $validation['errors'];
        $this->previewWarnings = $validation['warnings'];

        if (! $validation['valid']) {
            $this->toast((string) reset($validation['errors']), 'Fix the schedule first', 'danger');

            return;
        }

        if ($this->previewMode === 'rebuild') {
            $this->openConfirm(
                'Replace the current schedule?',
                'This will delete the published schedule and replace the current schedule with the preview. Per-game moves are kept until you publish.',
                'Replace schedule',
                'danger',
                'rebuild',
            );

            return;
        }

        $this->performPublish();
    }

    protected function performPublish(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateSchedule', $tournament);

        $mode = $this->previewMode;

        try {
            $count = $mode === 'remaining'
                ? app(SchedulingService::class)->publishRemaining($tournament, $this->previewPlan)
                : app(SchedulingService::class)->publish(
                    $tournament,
                    $this->previewPlan,
                    $mode === 'rebuild' ? 'schedule.rebuild' : 'schedule.publish',
                );

            $summary = $this->previewPlan['summary'] ?? [];
            $first = is_string($summary['firstDate'] ?? null) ? $summary['firstDate'] : null;

            $this->showPreview = false;
            $this->previewPlan = [];
            $this->lastBuiltPlan = [];
            $this->previewErrors = [];
            $this->previewWarnings = [];
            $this->previewDirty = false;

            if ($first !== null && $first !== '') {
                $this->selectedDay = $first;
                $this->currentMonth = substr($first, 0, 7);
            }

            $title = match ($mode) {
                'remaining' => 'Remaining games scheduled',
                'rebuild' => 'Schedule rebuilt',
                default => 'Schedule published',
            };

            $this->toast(
                $count.' games · '.($summary['matchDays'] ?? 0).' match days · ends '.($summary['lastDate'] ?? 'TBD').'.',
                $title,
            );
        } catch (InvalidArgumentException $exception) {
            $this->previewErrors = [0 => $exception->getMessage()];
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

    public function confirm(): void
    {
        $action = $this->confirmAction;
        $this->resetConfirm();

        if ($action === 'rebuild') {
            $this->performPublish();
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
