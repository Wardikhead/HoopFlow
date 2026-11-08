<?php

namespace App\Livewire\Organizer;

use App\Models\Registration;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Submissions')]
class SubmissionsIndex extends Component
{
    public string $status = 'all';

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public function openReject(int $registrationId): void
    {
        $this->rejectingId = $registrationId;
        $this->rejectReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectReason = '';
    }

    public function confirmReject(): void
    {
        if ($this->rejectingId === null) {
            return;
        }

        $this->reject($this->rejectingId, $this->rejectReason);
        $this->rejectingId = null;
        $this->rejectReason = '';
    }

    /**
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function registrations(): Collection
    {
        $query = Registration::query()->with(['team', 'tournament', 'decider']);

        if ($this->status !== '' && $this->status !== 'all') {
            $query->where('status', $this->status);
        }

        return $query->orderByDesc('submitted_at')->get();
    }

    public function approve(int $registrationId): void
    {
        $registration = Registration::query()->find($registrationId);

        if ($registration === null) {
            return;
        }

        $registration->update([
            'status' => 'approved',
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);
    }

    public function reject(int $registrationId, string $reason): void
    {
        $registration = Registration::query()->find($registrationId);

        if ($registration === null) {
            return;
        }

        $registration->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);
    }

    public function render(): View
    {
        return view('livewire.organizer.submissions-index');
    }
}
