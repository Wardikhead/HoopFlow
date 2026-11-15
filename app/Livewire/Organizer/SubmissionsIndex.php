<?php

namespace App\Livewire\Organizer;

use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EligibilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Registration> $registrations
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read array{pending: int, approved: int, rejected: int, open: int} $kpis
 */
#[Layout('layouts.public')]
#[Title('Submissions')]
class SubmissionsIndex extends Component
{
    public string $status = 'submitted';

    public string $search = '';

    public string $tournament = 'all';

    public ?int $reviewingId = null;

    public ?int $approvingId = null;

    public bool $approveAnyway = false;

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public function mount(): void
    {
        $status = request()->query('status', 'submitted');
        $status = is_string($status) ? $status : 'submitted';
        $status = $status === 'pending' ? 'submitted' : $status;

        $this->status = in_array($status, ['submitted', 'approved', 'rejected', 'all'], true) ? $status : 'submitted';

        $search = request()->query('q');
        $this->search = is_string($search) ? $search : '';

        $tournament = request()->query('tournament');
        $this->tournament = is_string($tournament) && $tournament !== '' ? $tournament : 'all';
    }

    public function openReview(int $registrationId): void
    {
        $this->reviewingId = $registrationId;
        $this->approvingId = null;
        $this->rejectingId = null;
    }

    public function closeReview(): void
    {
        $this->reviewingId = null;
    }

    public function approve(int $registrationId): void
    {
        if ($this->seedsLockedForRegistration($registrationId)) {
            $this->dispatch(
                'hf-toast',
                title: 'Seeds are locked',
                message: 'Approvals close when the bracket is generated. Reject the team with a reason instead.',
                tone: 'warning',
            );

            return;
        }

        $this->approvingId = $registrationId;
        $this->approveAnyway = false;
        $this->reviewingId = null;
        $this->rejectingId = null;
        $this->resetValidation();
    }

    public function confirmApprove(): void
    {
        if ($this->approvingId === null) {
            return;
        }

        if ($this->seedsLockedForRegistration($this->approvingId)) {
            $this->addError('approveAnyway', 'Seeds are locked. Reject the team with a reason instead.');

            return;
        }

        if ($this->ageIssues($this->approvingId) !== []) {
            $this->addError('approveAnyway', 'Fix the age eligibility issues before approving this team.');

            return;
        }

        if ($this->approvalIssues($this->approvingId) !== [] && ! $this->approveAnyway) {
            $this->addError('approveAnyway', 'Confirm the override before approving an incomplete team.');

            return;
        }

        $this->doApprove($this->approvingId);
        $this->approvingId = null;
        $this->approveAnyway = false;
        $this->resetValidation();
    }

    public function cancelApprove(): void
    {
        $this->approvingId = null;
        $this->approveAnyway = false;
        $this->resetValidation();
    }

    public function reject(int $registrationId): void
    {
        $this->rejectingId = $registrationId;
        $this->rejectReason = '';
        $this->reviewingId = null;
        $this->approvingId = null;
        $this->resetValidation();
    }

    public function confirmReject(): void
    {
        if ($this->rejectingId === null) {
            return;
        }

        $this->validate([
            'rejectReason' => ['required', 'string', 'min:5'],
        ], [
            'rejectReason.required' => 'Tell the coach what needs to change.',
            'rejectReason.min' => 'Enter at least 5 characters.',
        ]);

        $this->doReject($this->rejectingId, trim($this->rejectReason));
        $this->rejectingId = null;
        $this->rejectReason = '';
        $this->resetValidation();
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectReason = '';
        $this->resetValidation();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = 'submitted';
        $this->tournament = 'all';
    }

    public function updateConsent(int $playerId, string $status): void
    {
        if (! in_array($status, ['pending', 'verified', 'missing'], true)) {
            return;
        }

        $registration = $this->reviewingRegistration();

        if (! $registration instanceof Registration
            || ! in_array($registration->status, ['submitted', 'pending', 'approved'], true)) {
            return;
        }

        $player = $registration->team?->players->firstWhere('id', $playerId);

        if (! $player instanceof Player) {
            return;
        }

        $actor = auth()->user();
        $actor = $actor instanceof User ? $actor : null;
        $before = ['consent_status' => $player->consent_status];

        app(EligibilityService::class)->recordConsent($player, $status, $actor);
        app(AuditLogger::class)->log(
            'player.consent.update',
            'player',
            $player->id,
            $before,
            ['consent_status' => $status],
            $registration->team->organization_id,
            $actor?->id,
        );

        unset($this->registrations);
        $this->dispatch('hf-toast', title: 'Consent updated', message: 'The player consent status is now '.$status.'.', tone: 'success');
    }

    public function updateDocument(int $playerId, string $status): void
    {
        if (! in_array($status, ['pending', 'approved', 'missing'], true)) {
            return;
        }

        $registration = $this->reviewingRegistration();

        if (! $registration instanceof Registration
            || ! in_array($registration->status, ['submitted', 'pending', 'approved'], true)) {
            return;
        }

        $player = $registration->team?->players->firstWhere('id', $playerId);

        if (! $player instanceof Player) {
            return;
        }

        $actor = auth()->user();
        $actor = $actor instanceof User ? $actor : null;
        $document = $player->eligibilityDocuments
            ->firstWhere('type', EligibilityService::DOCUMENT_TYPE);
        $before = ['status' => $document->status ?? 'pending'];

        app(EligibilityService::class)->reviewDocument($player, $status, $actor);
        app(AuditLogger::class)->log(
            'player.document.review',
            'player',
            $player->id,
            $before,
            ['status' => $status],
            $registration->team->organization_id,
            $actor?->id,
        );

        unset($this->registrations);
        $this->dispatch('hf-toast', title: 'Document updated', message: 'The document status is now '.$status.'.', tone: 'success');
    }

    /**
     * @return array<int, array{name: string, consent: string, document: string, age: int|null, ageIssue: string|null}>
     */
    public function approvalIssues(int $registrationId): array
    {
        $registration = Registration::query()
            ->with([
                'team.players' => fn ($query) => $query->orderBy('jersey_number')->orderBy('id'),
                'team.players.eligibilityDocuments',
                'division',
                'tournament',
            ])
            ->find($registrationId);

        $team = $registration?->team;

        if (! $team instanceof Team) {
            return [];
        }

        $eligibility = app(EligibilityService::class);

        return $team->players
            ->map(function (Player $player) use ($eligibility, $registration): array {
                $document = $player->eligibilityDocuments
                    ->firstWhere('type', EligibilityService::DOCUMENT_TYPE);

                return [
                    'name' => trim($player->first_name.' '.$player->last_name),
                    'consent' => $player->consent_status,
                    'document' => $document->status ?? 'pending',
                    'age' => $eligibility->ageAtTournamentStart($player->birthdate, $registration->tournament),
                    'ageIssue' => $eligibility->ageIssue(
                        $player->birthdate,
                        $registration->division,
                        $registration->tournament,
                    ),
                ];
            })
            ->filter(fn (array $issue): bool => $issue['consent'] !== 'verified'
                || $issue['document'] !== 'approved'
                || $issue['ageIssue'] !== null)
            ->values()
            ->all();
    }

    /**
     * Age problems cannot be overridden: an ineligible player invalidates the whole roster.
     *
     * @return array<int, array{name: string, consent: string, document: string, age: int|null, ageIssue: string|null}>
     */
    public function ageIssues(int $registrationId): array
    {
        return array_values(array_filter(
            $this->approvalIssues($registrationId),
            fn (array $issue): bool => $issue['ageIssue'] !== null,
        ));
    }

    protected function reviewingRegistration(): ?Registration
    {
        if ($this->reviewingId === null) {
            return null;
        }

        return Registration::query()
            ->with([
                'team.players' => fn ($query) => $query->orderBy('jersey_number')->orderBy('id'),
                'team.players.eligibilityDocuments',
            ])
            ->find($this->reviewingId);
    }

    protected function doApprove(int $registrationId): void
    {
        if ($this->seedsLockedForRegistration($registrationId)) {
            return;
        }

        if ($this->ageIssues($registrationId) !== []) {
            return;
        }

        $registration = Registration::query()->find($registrationId);

        if ($registration === null || ! in_array($registration->status, ['submitted', 'pending'], true)) {
            return;
        }

        $registration->update([
            'status' => 'approved',
            'roster_locked_at' => now(),
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        app(AuditLogger::class)->log(
            'team.approve',
            'team',
            $registration->team_id,
            ['status' => 'submitted'],
            ['status' => 'approved'],
            $registration->team->organization_id,
        );

        unset($this->registrations, $this->kpis);
        $this->dispatch('hf-toast', title: 'Team approved', message: 'The team is eligible for seeding and its roster locks for the coach.', tone: 'success');
    }

    protected function doReject(int $registrationId, string $reason): void
    {
        $registration = Registration::query()->find($registrationId);

        if ($registration === null || ! in_array($registration->status, ['submitted', 'pending'], true)) {
            return;
        }

        $registration->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        app(AuditLogger::class)->log(
            'team.reject',
            'team',
            $registration->team_id,
            ['status' => 'submitted'],
            ['status' => 'rejected', 'reason' => $reason],
            $registration->team->organization_id,
        );

        unset($this->registrations, $this->kpis);
        $this->dispatch('hf-toast', title: 'Team rejected', message: 'The reason is shown to the coach and written to the audit log.', tone: 'success');
    }

    /**
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function registrations(): Collection
    {
        $query = Registration::query()
            ->whereHas('tournament')
            ->with(['team', 'tournament', 'division', 'payment', 'team.players.eligibilityDocuments']);

        if ($this->status === 'submitted') {
            $query->whereIn('status', ['submitted', 'pending']);
        } elseif ($this->status !== '' && $this->status !== 'all') {
            $query->where('status', $this->status);
        }

        if ($this->tournament !== '' && $this->tournament !== 'all') {
            $query->where('tournament_id', $this->tournament);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->whereHas('team', function ($teamQuery) use ($term): void {
                    $teamQuery->where('name', 'LIKE', $term)
                        ->orWhere('city', 'LIKE', $term)
                        ->orWhere('coach_name', 'LIKE', $term);
                })->orWhereHas('tournament', function ($tournamentQuery) use ($term): void {
                    $tournamentQuery->where('name', 'LIKE', $term);
                });
            });
        }

        return $query->orderByDesc('submitted_at')->get();
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::query()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{pending: int, approved: int, rejected: int, open: int}
     */
    #[Computed]
    public function kpis(): array
    {
        $active = fn () => Registration::query()
            ->whereHas('tournament', fn ($query) => $query->whereNull('archived_at'));

        return [
            'pending' => $active()->whereIn('status', ['submitted', 'pending'])->count(),
            'approved' => $active()->where('status', 'approved')->count(),
            'rejected' => $active()->where('status', 'rejected')->count(),
            'open' => Tournament::query()->whereNull('archived_at')->where('status', 'registration-open')->count(),
        ];
    }

    public function findRegistration(int|string|null $id): ?Registration
    {
        if ($id === null || ! is_numeric($id)) {
            return null;
        }

        return Registration::query()
            ->with([
                'team.players' => fn ($query) => $query->orderBy('jersey_number')->orderBy('id'),
                'team.players.eligibilityDocuments',
                'tournament',
                'division',
                'payment',
            ])
            ->find((int) $id);
    }

    protected function seedsLockedForRegistration(int $registrationId): bool
    {
        $registration = Registration::query()->with('tournament')->find($registrationId);

        return $registration?->tournament?->seedsLocked() ?? false;
    }

    public function render(): View
    {
        return view('livewire.organizer.submissions-index');
    }
}
