<div>
@section('title', 'Payments')

<header class="hf-page-head hf-mb-3">
    <div>
        <span class="hf-eyebrow">Organizer desk · Finance</span>
        <h1 class="hf-page-title">Payments</h1>
        <p class="hf-page-desc">Verify entry fees, track collection, and keep every reference in the audit trail.</p>
    </div>
    <div class="hf-page-actions">
        <a class="hf-btn" href="{{ url('/submissions') }}" data-testid="payments-open-submissions"><i class="bi bi-clipboard-check"></i> Submissions</a>
    </div>
</header>

<div class="hf-card hf-mb-3">
    <div class="hf-form-grid">
        <div class="hf-field">
            <label class="hf-label" for="paymentStatus">Status</label>
            <select class="hf-select" id="paymentStatus" wire:model.live="status" data-testid="payment-status-filter">
                <option value="all">All payments</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
            </select>
        </div>
    </div>
</div>

<div id="paymentList" data-testid="payment-list">
    @if ($this->payments->isEmpty())
        <div class="hf-empty" data-testid="payment-empty">
            <i class="bi bi-cash-stack" aria-hidden="true"></i>
            <h3>No payments match</h3>
            <p>Try another status filter.</p>
        </div>
    @else
        <div class="hf-grid hf-grid--2">
            @foreach ($this->payments as $payment)
                @php
                    $registration = $payment->registration;
                    $team = $registration?->team;
                    $recorder = $payment->recordedBy;
                    $status = $payment->status ?? 'pending';
                    $statusTone = match ($status) {
                        'verified' => 'success',
                        'refunded' => 'slate',
                        'pending' => 'warning',
                        default => 'slate',
                    };
                @endphp
                <article class="hf-card hf-card--flat" data-testid="payment-row-{{ $payment->id }}" wire:key="payment-{{ $payment->id }}">
                    <div class="hf-between">
                        <span class="hf-flex hf-items-center hf-gap-2">
                            <span class="hf-shield hf-shield--lg">{{ strtoupper(substr($team?->name ?? '?', 0, 1)) }}</span>
                            <span>
                                <strong>{{ $team?->name ?? 'Unknown team' }}</strong>
                                <span class="hf-small hf-muted" style="display:block">
                                    {{ $team?->coachUser?->first_name ?? 'Coach' }} {{ $team?->coachUser?->last_name ?? '' }} · {{ $registration?->tournament?->name ?? '' }}
                                </span>
                            </span>
                        </span>
                        <span class="hf-flex hf-wrap hf-gap-1">
                            <span class="hf-chip hf-chip--{{ $statusTone }}">{{ ucfirst($status) }}</span>
                        </span>
                    </div>
                    <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small">
                        <span><span class="hf-kpi-label">Method</span><strong style="display:block">{{ $payment->method ?: '—' }}</strong></span>
                        <span><span class="hf-kpi-label">Reference</span><strong style="display:block">{{ $payment->reference_no ?: '—' }}</strong></span>
                        <span><span class="hf-kpi-label">Amount</span><strong style="display:block">{{ number_format((float) $payment->amount, 2) }}</strong></span>
                        <span><span class="hf-kpi-label">Verified</span><strong style="display:block">{{ $payment->paid_at ? $payment->paid_at->format('M d, Y') : '—' }}</strong></span>
                    </div>
                    @if ($recorder)
                        <p class="hf-small hf-muted hf-mt-1 hf-mb-0">Recorded by {{ $recorder->name ?? $recorder->email ?? 'Organizer' }}</p>
                    @endif
                    <div class="hf-btn-row hf-mt-2">
                        @if ($status !== 'verified' && $status !== 'refunded')
                            <button type="button" class="hf-btn hf-btn--sm hf-btn--primary" wire:click="verify({{ $payment->id }})" data-testid="payment-verify-{{ $payment->id }}">Verify payment</button>
                        @endif
                        @if ($status === 'verified')
                            <button type="button" class="hf-btn hf-btn--sm hf-btn--danger" wire:click="refund({{ $payment->id }})" data-testid="payment-refund-{{ $payment->id }}">Refund</button>
                        @endif
                        @if ($registration?->team)
                            <a class="hf-btn hf-btn--sm" href="{{ url('/teams/' . $registration->team->id) }}" data-testid="payment-team-{{ $payment->id }}">Team detail</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
</div>
