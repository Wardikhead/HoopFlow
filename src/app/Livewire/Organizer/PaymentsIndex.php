<?php

namespace App\Livewire\Organizer;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\Tournament;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read Tournament|null $destinationTournament
 * @property-read array{collected: float, outstanding: float, expected: float, rate: int, paidCount: int, pendingCount: int, partialCount: int} $kpis
 */
#[Layout('layouts.public')]
#[Title('Payments')]
class PaymentsIndex extends Component
{
    public const METHODS = ['GCash', 'Maya', 'Bank transfer', 'Cash'];

    public string $status = 'pending';

    public string $search = '';

    public string $tournament = 'all';

    public ?int $verifyingId = null;

    public string $verifyMethod = 'GCash';

    public string $verifyStatus = 'paid';

    public string $verifyRef = '';

    public ?int $verifyAmount = null;

    public function mount(): void
    {
        $status = request()->query('status', 'pending');
        $this->status = is_string($status) && in_array($status, ['pending', 'partial', 'paid', 'unpaid', 'all'], true)
            ? $status
            : 'pending';

        $search = request()->query('q');
        $this->search = is_string($search) ? $search : '';

        $tournament = request()->query('tournament');
        $this->tournament = is_string($tournament) && $tournament !== '' ? $tournament : 'all';
    }

    public function openVerify(int $paymentId): void
    {
        $payment = Payment::query()->with(['registration.team', 'registration.tournament'])->find($paymentId);

        if (! $payment instanceof Payment) {
            return;
        }

        $this->verifyingId = (int) $payment->id;
        $this->verifyMethod = $payment->method ?? 'GCash';
        $this->verifyStatus = $payment->status === 'partial' ? 'partial' : 'paid';
        $this->verifyRef = $payment->reference_no ?? '';
        $this->verifyAmount = (int) $payment->amount;
    }

    public function closeVerify(): void
    {
        $this->verifyingId = null;
        $this->verifyMethod = 'GCash';
        $this->verifyStatus = 'paid';
        $this->verifyRef = '';
        $this->verifyAmount = null;
        $this->resetValidation();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = 'pending';
        $this->tournament = 'all';
    }

    public function saveVerify(): void
    {
        if ($this->verifyingId === null) {
            return;
        }

        $this->validate([
            'verifyRef' => ['required', 'string', 'min:1'],
            'verifyAmount' => ['required', 'integer', 'min:1'],
        ], [
            'verifyRef.required' => 'Enter the payment reference.',
            'verifyAmount.required' => 'Amount must be greater than zero.',
            'verifyAmount.integer' => 'Amount must be a whole number.',
            'verifyAmount.min' => 'Amount must be greater than zero.',
        ]);

        $payment = Payment::query()->with(['registration.team'])->find($this->verifyingId);

        if (! $payment instanceof Payment) {
            return;
        }

        $before = [
            'status' => $payment->status,
            'ref' => $payment->reference_no,
            'amount' => (float) $payment->amount,
        ];

        $payment->update([
            'method' => $this->verifyMethod,
            'reference_no' => $this->verifyRef,
            'amount' => $this->verifyAmount,
            'status' => $this->verifyStatus === 'paid' ? 'verified' : 'partial',
            'paid_at' => $this->verifyStatus === 'paid' ? now() : $payment->paid_at,
            'recorded_by' => auth()->id(),
        ]);

        $registration = $payment->registration;

        app(AuditLogger::class)->log(
            'payment.verify',
            'team',
            $registration?->team_id,
            $before,
            [
                'status' => $payment->status,
                'ref' => $this->verifyRef,
                'amount' => (int) $this->verifyAmount,
            ],
            $registration?->team?->organization_id,
        );

        $this->closeVerify();
        $this->dispatch('hf-toast', title: 'Payment verified', message: 'Payment recorded and written to the audit trail.', tone: 'success');
    }

    /**
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function payments(): Collection
    {
        $query = Payment::query()
            ->with(['registration.team', 'registration.tournament', 'registration.division', 'registration.payment', 'recordedBy'])
            ->whereHas('registration', function ($q): void {
                $q->where('status', '!=', 'rejected');
            });

        if ($this->status !== '' && $this->status !== 'all') {
            match ($this->status) {
                'unpaid' => $query->where('status', '!=', 'verified'),
                'paid' => $query->where('status', 'verified'),
                default => $query->where('status', $this->status),
            };
        }

        if ($this->tournament !== '' && $this->tournament !== 'all') {
            $query->whereHas('registration', function ($q): void {
                $q->where('tournament_id', $this->tournament);
            });
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->whereHas('registration.team', function ($teamQuery) use ($term): void {
                    $teamQuery->where('name', 'LIKE', $term)
                        ->orWhere('city', 'LIKE', $term)
                        ->orWhere('coach_name', 'LIKE', $term);
                })->orWhere('reference_no', 'LIKE', $term)
                    ->orWhere('method', 'LIKE', $term);
            });
        }

        $rank = ['pending' => 0, 'partial' => 1, 'verified' => 2];

        return $query->get()
            ->sortBy(fn (Payment $payment): array => [
                $rank[$payment->status] ?? 3,
                mb_strtolower($payment->registration->team->name ?? ''),
            ])
            ->values();
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::query()->orderBy('name')->get();
    }

    #[Computed]
    public function destinationTournament(): ?Tournament
    {
        if ($this->tournament === '' || $this->tournament === 'all') {
            return null;
        }

        return Tournament::find($this->tournament);
    }

    /**
     * @return array{collected: float, outstanding: float, expected: float, rate: int, paidCount: int, pendingCount: int, partialCount: int}
     */
    #[Computed]
    public function kpis(): array
    {
        $query = Registration::query()
            ->with(['division', 'payment'])
            ->where('status', '!=', 'rejected');

        if ($this->tournament !== '' && $this->tournament !== 'all') {
            $query->where('tournament_id', $this->tournament);
        }

        $billable = $query->get();

        $expected = (float) $billable->sum(fn (Registration $registration): float => (float) ($registration->division->entry_fee ?? 0));

        $paidTeams = $billable->filter(fn (Registration $registration): bool => $registration->payment?->status === 'verified');
        $collected = (float) $paidTeams->sum(fn (Registration $registration): float => (float) ($registration->payment->amount ?? 0));

        $pendingCount = $billable->filter(fn (Registration $registration): bool => $registration->payment?->status === 'pending')->count();
        $partialCount = $billable->filter(fn (Registration $registration): bool => $registration->payment?->status === 'partial')->count();

        $outstanding = max(0.0, $expected - $collected);
        $rate = $expected > 0 ? (int) round(($collected / $expected) * 100) : 0;

        return [
            'collected' => $collected,
            'outstanding' => $outstanding,
            'expected' => $expected,
            'rate' => $rate,
            'paidCount' => $paidTeams->count(),
            'pendingCount' => $pendingCount,
            'partialCount' => $partialCount,
        ];
    }

    public function render(): View
    {
        return view('livewire.organizer.payments-index');
    }
}
