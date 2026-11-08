<?php

namespace App\Livewire\Organizer;

use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Payments')]
class PaymentsIndex extends Component
{
    public string $status = 'all';

    /**
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function payments(): Collection
    {
        $query = Payment::query()->with(['registration.team', 'recordedBy']);

        if ($this->status !== '' && $this->status !== 'all') {
            $query->where('status', $this->status);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function verify(int $paymentId): void
    {
        $payment = Payment::query()->find($paymentId);

        if ($payment === null || $payment->status !== 'pending') {
            return;
        }

        $payment->update([
            'status' => 'verified',
            'paid_at' => now(),
            'recorded_by' => auth()->id(),
        ]);
    }

    public function refund(int $paymentId): void
    {
        $payment = Payment::query()->find($paymentId);

        if ($payment === null || $payment->status !== 'verified') {
            return;
        }

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refunded_amount' => $payment->amount,
        ]);
    }

    public function render(): View
    {
        return view('livewire.organizer.payments-index');
    }
}
