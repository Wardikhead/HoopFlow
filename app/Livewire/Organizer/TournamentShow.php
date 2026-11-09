<?php

namespace App\Livewire\Organizer;

use App\Models\Announcement;
use App\Models\Court;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\BracketService;
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
 * @property-read string $role
 * @property-read Team|null $coachTeam
 * @property-read Collection<int, Registration> $registrations
 * @property-read int $approvedCount
 * @property-read int $pendingCount
 * @property-read int $matchesPlayed
 * @property-read int $matchesScheduled
 * @property-read float $collected
 * @property-read float $expected
 * @property-read int $collectionRate
 * @property-read bool $hasBracket
 * @property-read int $bracketRounds
 * @property-read Team|null $champion
 * @property-read bool $canGenerateBracket
 * @property-read bool $canGenerateSchedule
 * @property-read Collection<int, Announcement> $announcements
 * @property-read array<int, array{round: string, match: string, court: string, slot: string}> $previewRows
 */
#[Layout('layouts.public')]
#[Title('Tournament')]
class TournamentShow extends Component
{
    public int $tournamentId = 0;

    public bool $showConfirm = false;

    public string $confirmTitle = 'Confirm action';

    public string $confirmMessage = '';

    public string $confirmLabel = 'Confirm';

    public string $confirmTone = 'primary';

    public string $confirmAction = '';

    public bool $showSchedulePreview = false;

    /** @var array<string, mixed> */
    public array $previewPlan = [];

    public function mount(int|string|Tournament|null $tournament = null): void
    {
        $id = $tournament instanceof Tournament
            ? (int) $tournament->id
            : (is_numeric($tournament) ? (int) $tournament : null);

        if ($id === null) {
            $query = request()->query('tournament') ?? request()->query('id');

            if (is_numeric($query)) {
                $id = (int) $query;
            }
        }

        $this->tournamentId = $id ?? 0;
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        if ($this->tournamentId === 0) {
            return null;
        }

        return Tournament::query()
            ->with([
                'divisions',
                'registrations.team.players',
                'registrations.payment',
                'matches.matchSlots',
                'matches.winnerTeam',
                'announcements.createdBy',
            ])
            ->find($this->tournamentId);
    }

    #[Computed]
    public function role(): string
    {
        $user = auth()->user();

        if ($user?->hasRole('organizer')) {
            return 'organizer';
        }

        if ($user?->hasRole('coach')) {
            return 'coach';
        }

        return 'fan';
    }

    #[Computed]
    public function coachTeam(): ?Team
    {
        $user = auth()->user();
        $tournament = $this->tournament;

        if ($this->role !== 'coach' || $user === null || ! $tournament instanceof Tournament) {
            return null;
        }

        return Team::query()
            ->with('registrations')
            ->where('coach_user_id', $user->id)
            ->whereHas('registrations', fn ($query) => $query->where('tournament_id', $tournament->id))
            ->first();
    }

    /**
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function registrations(): Collection
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return new Collection;
        }

        $registrations = $tournament->registrations;

        if ($this->role === 'fan') {
            $registrations = $registrations->where('status', 'approved');
        }

        return $registrations
            ->sortBy(fn (Registration $registration): int => $registration->seed ?? 999)
            ->values();
    }

    #[Computed]
    public function approvedCount(): int
    {
        return $this->registrations->where('status', 'approved')->count();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->registrations->whereIn('status', ['submitted', 'pending'])->count();
    }

    #[Computed]
    public function matchesPlayed(): int
    {
        return $this->tournament?->matches
            ->whereIn('status', ['final', 'forfeit'])
            ->reject(fn (MatchModel $match): bool => $match->isByeGame())
            ->count() ?? 0;
    }

    #[Computed]
    public function matchesScheduled(): int
    {
        return $this->tournament?->matches->where('status', 'scheduled')->count() ?? 0;
    }

    #[Computed]
    public function collected(): float
    {
        return (float) $this->registrations
            ->where('status', '!=', 'rejected')
            ->sum(function (Registration $registration): float {
                $payment = $registration->payment;

                return $payment?->status === 'verified' ? (float) $payment->amount : 0.0;
            });
    }

    #[Computed]
    public function expected(): float
    {
        $billable = $this->registrations->where('status', '!=', 'rejected')->count();

        return $billable * ($this->tournament?->entryFee() ?? 0.0);
    }

    #[Computed]
    public function collectionRate(): int
    {
        return $this->expected > 0
            ? (int) round(($this->collected / $this->expected) * 100)
            : 0;
    }

    #[Computed]
    public function hasBracket(): bool
    {
        return $this->tournament?->matches->isNotEmpty() ?? false;
    }

    #[Computed]
    public function bracketRounds(): int
    {
        return $this->tournament?->matches
            ->pluck('round_number')
            ->filter()
            ->unique()
            ->count() ?? 0;
    }

    #[Computed]
    public function champion(): ?Team
    {
        if (! $this->hasBracket || $this->tournament?->format() === 'round-robin') {
            return null;
        }

        $final = $this->tournament?->matches->firstWhere('round_number', 2);

        if (! $final instanceof MatchModel || ! in_array($final->status, ['final', 'forfeit'], true)) {
            return null;
        }

        return $final->winnerTeam instanceof Team ? $final->winnerTeam : null;
    }

    #[Computed]
    public function canGenerateBracket(): bool
    {
        return $this->role === 'organizer'
            && ! $this->hasBracket
            && $this->approvedCount >= 2;
    }

    #[Computed]
    public function canGenerateSchedule(): bool
    {
        $tournament = $this->tournament;

        if ($this->role !== 'organizer' || ! $tournament instanceof Tournament || ! $this->hasBracket) {
            return false;
        }

        if ($this->matchesPlayed > 0) {
            return false;
        }

        return ! $tournament->matches->contains(
            fn (MatchModel $match): bool => $match->matchSlots->isNotEmpty(),
        );
    }

    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function announcements(): Collection
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return new Collection;
        }

        return $tournament->announcements
            ->filter(fn (Announcement $announcement): bool => $this->role !== 'fan' || $announcement->audience === 'all')
            ->sortByDesc(function (Announcement $announcement): int {
                $publishedAt = $announcement->getAttribute('published_at');
                $timestamp = $publishedAt instanceof \DateTimeInterface ? $publishedAt->getTimestamp() : 0;

                return ($announcement->pinned ? 1_000_000_000 : 0) + $timestamp;
            })
            ->values();

    }

    /**
     * @return array<int, array{round: string, match: string, court: string, slot: string}>
     */
    #[Computed]
    public function previewRows(): array
    {
        $assignments = $this->previewPlan['assignments'] ?? [];

        if (! is_array($assignments) || $assignments === []) {
            return [];
        }

        $matches = MatchModel::query()
            ->with(['homeTeam', 'awayTeam'])
            ->whereIn('id', array_column($assignments, 'matchId'))
            ->get()
            ->keyBy('id');

        $courts = $this->tournament instanceof Tournament
            ? app(SchedulingService::class)->courts($this->tournament)->keyBy('id')
            : collect();

        return array_map(function (array $assignment) use ($matches, $courts): array {
            $match = $matches->get($assignment['matchId']);
            $court = $courts->get($assignment['courtId']);
            $start = Carbon::parse($assignment['scheduledAt']);

            return [
                'round' => $match?->roundLabel() ?? 'Match',
                'match' => ($match?->homeTeam?->shortLabel() ?? 'TBD').' vs '.($match?->awayTeam?->shortLabel() ?? 'TBD'),
                'court' => $court instanceof Court ? $court->name : 'TBD',
                'slot' => $start->format('M d, Y').' · '.$start->format('g:i A'),
            ];
        }, $assignments);
    }

    public function requestGenerateBracket(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateBracket', $tournament);

        if ($this->hasBracket) {
            $this->toast('This tournament already has a bracket.', 'Bracket exists', 'warning');

            return;
        }

        if ($this->approvedCount < 2) {
            $this->toast('At least two approved teams are required.', 'Could not generate bracket', 'warning');

            return;
        }

        $this->openConfirm(
            'Generate bracket?',
            'The approved teams will be seeded into the bracket and the seeds will lock.',
            'Generate bracket',
            'primary',
            'generate-bracket',
        );
    }

    public function requestGenerateSchedule(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateSchedule', $tournament);

        if (! $this->canGenerateSchedule) {
            $this->toast('Generate the bracket before scheduling courts.', 'Schedule unavailable', 'warning');

            return;
        }

        try {
            $plan = app(SchedulingService::class)->plan($tournament);

            $this->previewPlan = [
                'assignments' => $plan['assignments'],
                'courtCount' => $plan['courts']->count(),
                'restMinutes' => $plan['restMinutes'],
            ];
            $this->showSchedulePreview = true;
            $this->showConfirm = false;
            $this->resetValidation();
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'Could not plan schedule', 'danger');
        }
    }

    public function closeSchedulePreview(): void
    {
        $this->showSchedulePreview = false;
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

            $this->showSchedulePreview = false;
            $this->previewPlan = [];
            $this->toast($count.' games scheduled across '.$tournament->courtCount().' courts.', 'Schedule published');
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'Could not publish schedule', 'danger');
        }
    }

    public function confirm(): void
    {
        $action = $this->confirmAction;
        $this->resetConfirm();

        if ($action !== 'generate-bracket') {
            return;
        }

        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateBracket', $tournament);

        try {
            $result = app(BracketService::class)->generate($tournament, $tournament->format());

            $this->toast(
                $result['matches'].' matches created with '.$result['byes'].' byes.',
                'Bracket generated',
            );
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'Could not generate bracket', 'danger');
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

    protected function toast(string $message, string $title, string $tone = 'success'): void
    {
        $this->dispatch('hf-toast', title: $title, message: $message, tone: $tone);
    }

    public function render(): View
    {
        return view('livewire.organizer.tournament-show');
    }
}
