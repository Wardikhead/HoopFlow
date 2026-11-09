<?php

namespace App\Livewire\Organizer;

use App\Models\Registration;
use App\Models\Tournament;
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

    public ?int $rejectingId = null;

    public string $rejectReason = '';

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
        $this->approvingId = $registrationId;
        $this->reviewingId = null;
        $this->rejectingId = null;
    }

    public function confirmApprove(): void
    {
        if ($this->approvingId === null) {
            return;
        }

        $this->doApprove($this->approvingId);
        $this->approvingId = null;
    }

    public function cancelApprove(): void
    {
        $this->approvingId = null;
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

    protected function doApprove(int $registrationId): void
    {
        $registration = Registration::query()->find($registrationId);

        if ($registration === null || $registration->status !== 'submitted') {
            return;
        }

        $registration->update([
            'status' => 'approved',
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        $this->dispatch('hf-toast', title: 'Team approved', message: 'The team is eligible for seeding and its roster locks for the coach.', tone: 'success');
    }

    protected function doReject(int $registrationId, string $reason): void
    {
        $registration = Registration::query()->find($registrationId);

        if ($registration === null || $registration->status !== 'submitted') {
            return;
        }

        $registration->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        $this->dispatch('hf-toast', title: 'Team rejected', message: 'The reason is shown to the coach and written to the audit log.', tone: 'success');
    }

    /**
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function registrations(): Collection
    {
        $query = Registration::query()
            ->with(['team', 'tournament', 'division', 'payment', 'team.players.eligibilityDocuments']);

        if ($this->status !== '' && $this->status !== 'all') {
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
        return Tournament::query()->orderBy('name')->get();
    }

    /**
     * @return array{pending: int, approved: int, rejected: int, open: int}
     */
    #[Computed]
    public function kpis(): array
    {
        return [
            'pending' => Registration::query()->where('status', 'submitted')->count(),
            'approved' => Registration::query()->where('status', 'approved')->count(),
            'rejected' => Registration::query()->where('status', 'rejected')->count(),
            'open' => Tournament::query()->where('status', 'registration-open')->count(),
        ];
    }

    public function findRegistration(int|string|null $id): ?Registration
    {
        if ($id === null || ! is_numeric($id)) {
            return null;
        }

        return Registration::query()->find((int) $id);
    }

    public function render(): View
    {
        return view('livewire.organizer.submissions-index');
    }
}
