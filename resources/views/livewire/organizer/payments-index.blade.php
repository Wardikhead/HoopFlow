<div
    x-data="{
        toasts: [],
        pushToast(detail) {
            const toast = {
                id: Date.now() + Math.random(),
                title: (detail && detail.title) || '',
                message: (detail && detail.message) || '',
                tone: (detail && detail.tone) || 'primary'
            };
            this.toasts.push(toast);
            setTimeout(() => this.dismissToast(toast.id), 4200);
        },
        dismissToast(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
        toastIcon(tone) {
            return tone === 'danger' ? 'x-circle-fill' : tone === 'warning' ? 'exclamation-triangle-fill' : tone === 'success' ? 'check-circle-fill' : 'info-circle-fill';
        }
    }"
    x-on:hf-toast.window="pushToast($event.detail)"
>
    @section('title', 'Payments')

    <header class="hf-page-head hf-mb-3">
        <div>
            <span class="hf-eyebrow">Organizer desk · Finance</span>
            <h1 class="hf-page-title">Payments</h1>
            <p class="hf-page-desc">Verify entry fees, track the collection rate, and keep every reference in the audit trail.</p>
        </div>
        <div class="hf-page-actions">
            <a class="hf-btn" href="{{ route('submissions') }}" data-testid="payments-open-submissions"><i class="bi bi-clipboard-check"></i> Submissions</a>
        </div>
    </header>

    <div id="paymentKpis" class="hf-grid hf-grid--4 hf-mb-3">
        @php
            $paymentKpis = [
                ['label' => 'Collected', 'value' => '₱'.number_format((float) $this->kpis['collected']), 'hint' => $this->kpis['paidCount'].' teams paid', 'tone' => 'success', 'testid' => 'payment-kpi-collected'],
                ['label' => 'Outstanding', 'value' => '₱'.number_format((float) $this->kpis['outstanding']), 'hint' => $this->kpis['pendingCount'].' pending · '.$this->kpis['partialCount'].' partial', 'tone' => 'alert', 'testid' => 'payment-kpi-outstanding'],
                ['label' => 'Expected', 'value' => '₱'.number_format((float) $this->kpis['expected']), 'hint' => 'Entry fees for billable teams', 'tone' => 'slate', 'testid' => 'payment-kpi-expected'],
                ['label' => 'Collection rate', 'value' => $this->kpis['rate'].'%', 'hint' => 'Collected against expected', 'tone' => 'primary', 'testid' => 'payment-kpi-rate'],
            ];
        @endphp
        @foreach ($paymentKpis as $kpi)
            @include('components.kpi-tile', $kpi)
        @endforeach
    </div>

    <div id="paymentDestination" data-testid="payment-destination" class="hf-card hf-card--flat hf-mb-3">
        @if ($this->destinationTournament)
            <div class="hf-between">
                <span class="hf-kpi-label">Send payments to</span>
                <strong>{{ $this->destinationTournament->payment_method ?? 'GCash' }} · {{ $this->destinationTournament->payment_account ?? 'TBD' }}</strong>
            </div>
        @else
            <span class="hf-small hf-muted">Select a tournament to see the payment destination.</span>
        @endif
    </div>

    <div class="hf-card hf-mb-3">
        <div class="hf-form-grid">
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="paymentSearch">Search</label>
                <input class="hf-input" id="paymentSearch" type="search" placeholder="Team, reference, or coach"
                    wire:model.live.debounce.150ms="search" data-testid="payment-search">
            </div>
            <div class="hf-field">
                <label class="hf-label" for="paymentStatus">Status</label>
                <select class="hf-select" id="paymentStatus" wire:model.live="status" data-testid="payment-status-filter">
                    <option value="pending">Pending</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                    <option value="unpaid">Pending or partial</option>
                    <option value="all">All payments</option>
                </select>
            </div>
            <div class="hf-field">
                <label class="hf-label" for="paymentTournament">Tournament</label>
                <select class="hf-select" id="paymentTournament" wire:model.live="tournament" data-testid="payment-tournament-filter">
                    <option value="all">All tournaments</option>
                    @foreach ($this->tournaments as $tournament)
                        <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div id="paymentList" data-testid="payment-list">
        @if ($this->payments->isEmpty())
            <div class="hf-empty" data-testid="payment-empty">
                <i class="bi bi-cash-stack" aria-hidden="true"></i>
                <h3>No payments match</h3>
                <p>Try another status, tournament, or search term.</p>
                <div class="hf-btn-row hf-mt-2">
                    <button type="button" class="hf-btn" wire:click="clearFilters" data-testid="payment-clear-filters">Clear filters</button>
                </div>
            </div>
        @else
            <div class="hf-grid">
                @foreach ($this->payments as $payment)
                    @php
                        $registration = $payment->registration;
                        $team = $registration?->team;
                        $tournament = $registration?->tournament;
                        $pstatus = $payment->status ?? 'pending';
                        $pstatusChip = $pstatus === 'verified' ? 'paid' : $pstatus;
                    @endphp
                    <article class="hf-card hf-card--flat" data-testid="payment-row-{{ $payment->id }}" wire:key="payment-{{ $payment->id }}">
                        <div class="hf-between">
                            <span class="hf-flex hf-items-center hf-gap-2">
                                <x-team-shield :team="$team" size="lg" />
                                <span>
                                    <strong>{{ $team?->name ?? 'Unknown team' }}</strong>
                                    <span class="hf-small hf-muted" style="display:block">{{ $tournament?->name ?? '' }} · {{ $team?->coach_name ?? 'Coach' }}</span>
                                </span>
                            </span>
                            @include('components.status-chip', ['status' => $pstatusChip])
                        </div>
                        <div class="hf-flex hf-wrap hf-gap-3 hf-mt-2 hf-small">
                            <span><span class="hf-kpi-label">Method</span><strong style="display:block">{{ $payment->method ?: '—' }}</strong></span>
                            <span><span class="hf-kpi-label">Reference</span><strong style="display:block">{{ $payment->reference_no ?: '—' }}</strong></span>
                            <span><span class="hf-kpi-label">Amount</span><strong style="display:block">{{ '₱'.number_format((float) $payment->amount) }}</strong></span>
                            <span><span class="hf-kpi-label">Verified</span><strong style="display:block">{{ $payment->paid_at ? $payment->paid_at->format('M d, Y') : '—' }}</strong></span>
                        </div>
                        <div class="hf-btn-row hf-mt-2">
                            <button type="button" class="hf-btn hf-btn--sm {{ $pstatus !== 'verified' ? 'hf-btn--primary' : '' }}" wire:click="openVerify({{ $payment->id }})" data-testid="payment-verify-{{ $payment->id }}">
                                {{ $pstatus === 'verified' ? 'Review record' : 'Verify payment' }}
                            </button>
                            @if ($team)
                                <a class="hf-btn hf-btn--sm" href="{{ route('teamShow', ['team' => $team->id]) }}" data-testid="payment-team-{{ $payment->id }}">Team detail</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    @if ($verifyingId !== null)
        @php $payment = \App\Models\Payment::query()->with(['registration.team', 'registration.tournament'])->find($verifyingId); @endphp
        @if ($payment)
            @php
                $verifyTournament = $payment->registration->tournament;
                $verifyFee = (float) ($verifyTournament?->rules_json['fee'] ?? $payment->registration->division?->entry_fee ?? 0);
            @endphp
            <dialog class="hf-dialog hf-dialog--wide" x-data x-init="if (! $el.open) $el.showModal()" x-on:cancel.prevent="$wire.closeVerify()" x-on:click="if ($event.target === $el) $wire.closeVerify()" wire:key="payment-verify-dialog" data-testid="payment-verify-dialog">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">Verify payment · {{ $payment->registration->team->name }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeVerify()" aria-label="Close dialog" data-testid="dialog-close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-small hf-muted">Entry fee is {{ '₱'.number_format($verifyFee) }}. A saved verification updates collected totals and writes an audit entry.</p>
                    <div class="hf-form-grid">
                        <div class="hf-field">
                            <label class="hf-label" for="verifyMethod">Method</label>
                            <select class="hf-select" id="verifyMethod" wire:model="verifyMethod" data-testid="payment-verify-method">
                                @foreach (\App\Livewire\Organizer\PaymentsIndex::METHODS as $method)
                                    <option value="{{ $method }}">{{ $method }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="hf-field">
                            <label class="hf-label" for="verifyStatus">Status</label>
                            <select class="hf-select" id="verifyStatus" wire:model="verifyStatus" data-testid="payment-verify-status">
                                <option value="paid">Paid in full</option>
                                <option value="partial">Partial payment</option>
                            </select>
                        </div>
                        <div class="hf-field">
                            <label class="hf-label" for="verifyRef">Reference</label>
                            <input class="hf-input" id="verifyRef" type="text" wire:model="verifyRef" placeholder="GCASH-9283746" data-testid="payment-verify-ref">
                            @error('verifyRef')
                                <p class="hf-field-error hf-mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="hf-field">
                            <label class="hf-label" for="verifyAmount">Amount (PHP)</label>
                            <input class="hf-input" id="verifyAmount" type="number" min="1" step="100" wire:model="verifyAmount" data-testid="payment-verify-amount">
                            @error('verifyAmount')
                                <p class="hf-field-error hf-mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="closeVerify()" data-testid="payment-verify-cancel">Cancel</button>
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="saveVerify" data-testid="payment-verify-save">Save verification</button>
                </div>
            </dialog>
        @endif
    @endif

    <div class="hf-toast-region" aria-live="polite" aria-atomic="false">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="hf-toast" x-bind:class="'hf-toast--' + toast.tone" x-bind:role="toast.tone === 'danger' ? 'alert' : 'status'">
                <i class="bi" x-bind:class="'bi-' + toastIcon(toast.tone)" aria-hidden="true"></i>
                <div>
                    <strong x-show="toast.title !== ''" x-text="toast.title"></strong>
                    <p x-text="toast.message"></p>
                </div>
                <button type="button" aria-label="Dismiss notification" data-testid="toast-dismiss" x-on:click="dismissToast(toast.id)">&times;</button>
            </div>
        </template>
    </div>
</div>
