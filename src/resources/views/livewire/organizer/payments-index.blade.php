<div>
    @section('title', 'Payments')

    @section('breadcrumbs')
        <x-breadcrumbs :items="[
            ['label' => 'Home', 'href' => route('dashboard')],
            ['label' => 'Payments'],
        ]" />
    @endsection

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

    <x-callout tone="info" title="Send payments to" class="hf-mb-3" data-testid="payment-destination">
        @if ($this->destinationTournament)
            <strong>{{ $this->destinationTournament->payment_method ?? 'GCash' }} · {{ $this->destinationTournament->payment_account ?? 'TBD' }}</strong>
            <p class="hf-small hf-muted hf-mb-0">{{ $this->destinationTournament->name }}</p>
        @else
            <p class="hf-small hf-muted hf-mb-0">Select a tournament to see the payment destination.</p>
        @endif
    </x-callout>

    <div class="hf-filter-bar hf-mb-3">
        <div class="hf-filter-bar__search">
            <label class="hf-sr-only" for="paymentSearch">Search payments</label>
            <i class="bi bi-search" aria-hidden="true"></i>
            <input class="hf-input" id="paymentSearch" type="search" placeholder="Team, reference, or coach"
                wire:model.live.debounce.150ms="search" data-testid="payment-search">
        </div>
        <div class="hf-field">
            <label class="hf-sr-only" for="paymentStatus">Status</label>
            <select class="hf-select hf-select--compact" id="paymentStatus" wire:model.live="status" data-testid="payment-status-filter">
                <option value="pending">Pending</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
                <option value="unpaid">Pending or partial</option>
                <option value="all">All payments</option>
            </select>
        </div>
        <div class="hf-field">
            <label class="hf-sr-only" for="paymentTournament">Tournament</label>
            <select class="hf-select hf-select--compact" id="paymentTournament" wire:model.live="tournament" data-testid="payment-tournament-filter">
                <option value="all">All tournaments</option>
                @foreach ($this->tournaments as $tournament)
                    <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($search !== '' || $tournament !== 'all' || $status !== 'pending')
            <button type="button" class="hf-btn hf-btn--sm" wire:click="clearFilters" wire:loading.class="is-loading" data-testid="payment-clear-filters-top">
                <i class="bi bi-x-lg" aria-hidden="true"></i> Clear
            </button>
        @endif
    </div>

    <p class="hf-results-count hf-mb-2">{{ $this->payments->count() }} {{ \Illuminate\Support\Str::plural('payment', $this->payments->count()) }} in this view</p>

    <div id="paymentList" data-testid="payment-list" wire:loading.class="is-updating" wire:loading.attr="aria-busy">
        <div class="hf-updating-pill" wire:loading wire:target="status,tournament,search">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Updating…
        </div>
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
                        $money = fn ($value) => '₱'.number_format((float) ($value ?? 0));
                    @endphp
                    <article class="hf-card hf-card--flat" data-testid="payment-row-{{ $payment->id }}" wire:key="payment-{{ $payment->id }}">
                        <div class="hf-payment-row">
                            <div class="hf-payment-row__identity">
                                <x-team-shield :team="$team" />
                                <div>
                                    <strong>{{ $team?->name ?? 'Unknown team' }}</strong>
                                    <span class="hf-small hf-muted hf-block">{{ $tournament?->name ?? 'Tournament' }} · {{ $team?->coach_name ?? 'Coach' }}</span>
                                </div>
                            </div>
                            <dl class="hf-payment-row__facts">
                                <div><dt>Reference</dt><dd class="hf-mono">{{ $payment->reference_no ?: '—' }}</dd></div>
                                <div><dt>Method</dt><dd>{{ $payment->method ?: '—' }}</dd></div>
                                <div><dt>Amount</dt><dd class="hf-money">{{ $money($payment->amount) }}</dd></div>
                            </dl>
                            <div class="hf-payment-row__status">
                                @include('components.status-chip', ['status' => $pstatusChip])
                                <span class="hf-small hf-muted">{{ $payment->paid_at?->format('M d, Y') ?? 'Not verified' }}</span>
                            </div>
                            <div class="hf-payment-row__actions">
                                <button type="button" class="hf-btn hf-btn--sm {{ $pstatus !== 'verified' ? 'hf-btn--primary' : '' }}" wire:click="openVerify({{ $payment->id }})" wire:loading.class="is-loading" wire:loading.attr="aria-busy" data-testid="payment-verify-{{ $payment->id }}">
                                    {{ $pstatus === 'verified' ? 'Review record' : 'Verify payment' }}
                                </button>
                                @if ($team)
                                    <a class="hf-btn hf-btn--sm" href="{{ route('teamShow', array_filter(['team' => $team->id, 'tournament' => $tournament?->id])) }}" data-testid="payment-team-{{ $payment->id }}">Team detail</a>
                                @endif
                            </div>
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
            <dialog class="hf-dialog hf-dialog--wide" x-data x-init="if (! $el.open) $el.showModal()" x-on:cancel.prevent="$wire.closeVerify()" x-on:click="if ($event.target === $el) $wire.closeVerify()" wire:key="payment-verify-dialog" data-testid="payment-verify-dialog" aria-labelledby="paymentVerifyDialogTitle">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title" id="paymentVerifyDialogTitle">Verify payment · {{ $payment->registration->team->name }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeVerify()" aria-label="Close dialog" data-testid="dialog-close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <div class="hf-dialog__body hf-dialog__body--split">
                    <div class="hf-min-w-0">
                        <x-form-errors />
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
                            </div>
                            <div class="hf-field">
                                <label class="hf-label" for="verifyAmount">Amount (PHP)</label>
                                <input class="hf-input" id="verifyAmount" type="number" min="1" step="100" wire:model="verifyAmount" data-testid="payment-verify-amount">
                            </div>
                        </div>
                    </div>
                    <aside class="hf-min-w-0">
                        <div class="hf-card hf-card--flat">
                            <span class="hf-kpi-label">Entry fee</span>
                            <strong class="hf-kpi-value hf-kpi-value--sm hf-block">{{ '₱'.number_format($verifyFee) }}</strong>
                            <dl class="hf-summary-list">
                                <div><dt>Team</dt><dd>{{ $payment->registration->team->name }}</dd></div>
                                <div><dt>Tournament</dt><dd>{{ $verifyTournament?->name ?? 'Tournament' }}</dd></div>
                                <div><dt>Current reference</dt><dd class="hf-mono">{{ $payment->reference_no ?: '—' }}</dd></div>
                            </dl>
                        </div>
                    </aside>
                </div>
                <div class="hf-dialog__foot hf-dialog__foot--sticky">
                    <button type="button" class="hf-btn" wire:click="closeVerify()" data-testid="payment-verify-cancel">Cancel</button>
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="saveVerify" wire:loading.class="is-loading" wire:loading.attr="disabled" data-testid="payment-verify-save">Save verification</button>
                </div>
            </dialog>
        @endif
    @endif

</div>
