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
    @section('title', 'Submissions')

    <header class="hf-page-head hf-mb-3">
        <div>
            <span class="hf-eyebrow">Organizer desk · Eligibility</span>
            <h1 class="hf-page-title">Submissions</h1>
            <p class="hf-page-desc">Review rosters, consent, documents, and payment references before a team enters the bracket.</p>
        </div>
        <div class="hf-page-actions">
            <a class="hf-btn" href="{{ route('payments') }}" data-testid="submissions-open-payments"><i class="bi bi-credit-card"></i> Payments</a>
        </div>
    </header>

    <div id="submissionKpis" class="hf-grid hf-grid--4 hf-mb-3">
        @php
            $submissionKpis = [
                ['label' => 'Pending review', 'value' => $this->kpis['pending'], 'hint' => 'Awaiting organizer decision', 'tone' => 'gold', 'testid' => 'submission-kpi-pending'],
                ['label' => 'Approved', 'value' => $this->kpis['approved'], 'hint' => 'Eligible for seeding', 'tone' => 'success', 'testid' => 'submission-kpi-approved'],
                ['label' => 'Rejected', 'value' => $this->kpis['rejected'], 'hint' => 'Archived from the bracket', 'tone' => 'danger', 'testid' => 'submission-kpi-rejected'],
                ['label' => 'Open registration', 'value' => $this->kpis['open'], 'hint' => 'Tournaments accepting teams', 'tone' => 'primary', 'testid' => 'submission-kpi-open'],
            ];
        @endphp
        @foreach ($submissionKpis as $kpi)
            @include('components.kpi-tile', $kpi)
        @endforeach
    </div>

    <div class="hf-card hf-mb-3">
        <div class="hf-form-grid">
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="submissionSearch">Search</label>
                <input class="hf-input" id="submissionSearch" type="search" placeholder="Team, coach, or city"
                    wire:model.live.debounce.150ms="search" data-testid="submission-search">
            </div>
            <div class="hf-field">
                <label class="hf-label" for="submissionStatus">Status</label>
                <select class="hf-select" id="submissionStatus" wire:model="status" data-testid="submission-status-filter">
                    <option value="submitted">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="all">All statuses</option>
                </select>
            </div>
            <div class="hf-field">
                <label class="hf-label" for="submissionTournament">Tournament</label>
                <select class="hf-select" id="submissionTournament" wire:model="tournament" data-testid="submission-tournament-filter">
                    <option value="all">All tournaments</option>
                    @foreach ($this->tournaments as $tournament)
                        <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div id="submissionList" data-testid="submission-list">
        @if ($this->registrations->isEmpty())
            <div class="hf-empty" data-testid="submission-empty">
                <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                <h3>No submissions match</h3>
                <p>Try another status, tournament, or search term.</p>
                <div class="hf-btn-row hf-mt-2">
                    <button type="button" class="hf-btn" wire:click="clearFilters" data-testid="submission-clear-filters">Clear filters</button>
                </div>
            </div>
        @else
            <div class="hf-grid">
                @foreach ($this->registrations as $registration)
                    @php
                        $team = $registration->team;
                        $tournament = $registration->tournament;
                        $division = $registration->division;
                        $payment = $registration->payment;
                        $status = $registration->status ?? 'submitted';
                        $players = $team?->players ?? collect();
                        $eligibility = app(\App\Services\EligibilityService::class)->status($team ?? new \App\Models\Team());
                        $documentsComplete = $eligibility['documentsComplete'] ?? false;
                        $consentComplete = $eligibility['consentComplete'] ?? false;
                    @endphp
                    <article class="hf-card hf-card--flat" data-testid="submission-row-{{ $registration->id }}" wire:key="submission-{{ $registration->id }}">
                        <div class="hf-between">
                            <span class="hf-flex hf-items-center hf-gap-2">
                                <span class="hf-shield hf-shield--lg" style="background: {{ $team?->displayColor() ?? '#64748b' }}; color: #ffffff">{{ strtoupper(substr($team?->name ?? '?', 0, 1)) }}</span>
                                <span>
                                    <strong>{{ $team?->name ?? 'Unknown team' }}</strong>
                                    <span class="hf-small hf-muted" style="display:block">
                                        {{ $team?->coach_name ?? 'Coach' }} · {{ $team?->city ?? '' }} · {{ $tournament?->name ?? 'Tournament' }}
                                    </span>
                                </span>
                            </span>
                            <span class="hf-flex hf-wrap hf-gap-1">
                                @include('components.status-chip', ['status' => $status])
                                @include('components.status-chip', ['status' => $payment?->status ?? 'pending'])
                            </span>
                        </div>
                        <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2 hf-small">
                            <span class="hf-chip hf-chip--slate">{{ $players->count() }} players</span>
                            <span class="hf-chip hf-chip--{{ $documentsComplete ? 'success' : 'danger' }}">{{ $documentsComplete ? 'Documents' : 'Missing documents' }}</span>
                            <span class="hf-chip hf-chip--{{ $consentComplete ? 'success' : 'danger' }}">{{ $consentComplete ? 'Consent' : 'Missing consent' }}</span>
                            <span class="hf-chip hf-chip--slate">{{ $division?->name ?? ($tournament?->divisions?->first()?->name ?? '') }}</span>
                            @if ($registration->submitted_at)
                                <span class="hf-chip hf-chip--slate">Submitted {{ $registration->submitted_at->format('M d, Y') }}</span>
                            @endif
                        </div>
                        @if ($registration->rejection_reason)
                            <p class="hf-small hf-muted hf-mt-1 hf-mb-0">Reason: {{ $registration->rejection_reason }}</p>
                        @endif
                        <div class="hf-btn-row hf-mt-2">
                            <button type="button" class="hf-btn hf-btn--sm" wire:click="openReview({{ $registration->id }})" data-testid="submission-open-{{ $registration->id }}">Review detail</button>
                            @if ($status === 'submitted')
                                <button type="button" class="hf-btn hf-btn--sm hf-btn--primary" wire:click="approve({{ $registration->id }})" data-testid="submission-approve-{{ $registration->id }}">Approve</button>
                                <button type="button" class="hf-btn hf-btn--sm hf-btn--danger" wire:click="reject({{ $registration->id }})" data-testid="submission-reject-{{ $registration->id }}">Reject</button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    @if ($reviewingId !== null)
        @php $review = $this->findRegistration($reviewingId); @endphp
        @if ($review)
            @php
                $reviewTeam = $review->team;
                $reviewPlayers = $reviewTeam?->players ?? collect();
                $reviewEligibility = app(\App\Services\EligibilityService::class)->status($reviewTeam ?? new \App\Models\Team());
                $reviewPayment = $review->payment;
            @endphp
            <dialog class="hf-dialog hf-dialog--wide" x-data x-init="if (! $el.open) $el.showModal()" x-on:cancel.prevent="$wire.closeReview()" x-on:click="if ($event.target === $el) $wire.closeReview()" wire:key="submission-review-dialog" data-testid="submission-review-dialog">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">{{ $review->team?->name ?? 'Team' }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeReview()" aria-label="Close dialog" data-testid="dialog-close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <div class="hf-dialog__body">
                    <div class="hf-flex hf-items-center hf-gap-2 hf-mb-2">
                        <span class="hf-shield hf-shield--lg" style="background: {{ $reviewTeam?->displayColor() ?? '#64748b' }}; color: #ffffff">{{ strtoupper(substr($reviewTeam?->name ?? '?', 0, 1)) }}</span>
                        <div>
                            <strong>{{ $reviewTeam?->coach_name ?? 'Coach' }} · {{ $reviewTeam?->city ?? '' }}</strong>
                            <span class="hf-small hf-muted" style="display:block">{{ $review->tournament?->name ?? '' }} · {{ $review->division?->name ?? '' }}</span>
                        </div>
                        <span class="hf-ml-auto hf-flex hf-gap-1">
                            @include('components.status-chip', ['status' => $review->status ?? 'submitted'])
                            @include('components.status-chip', ['status' => $reviewPayment?->status ?? 'pending'])
                        </span>
                    </div>
                    <div class="hf-grid hf-grid--2 hf-mb-3">
                        <div class="hf-card hf-card--flat">
                            <span class="hf-kpi-label">Eligibility</span>
                            <div class="hf-flex hf-wrap hf-gap-1 hf-mt-1">
                                @include('components.status-chip', ['status' => $reviewEligibility['documentsComplete'] ? 'verified' : 'missing', 'label' => $reviewEligibility['documentsComplete'] ? 'Documents' : 'Missing documents'])
                                @include('components.status-chip', ['status' => $reviewEligibility['consentComplete'] ? 'verified' : 'missing', 'label' => $reviewEligibility['consentComplete'] ? 'Consent' : 'Missing consent'])
                            </div>
                            <p class="hf-small hf-muted hf-mt-1 hf-mb-0">{{ $reviewPlayers->where('eligibility_status', 'verified')->count() }} of {{ $reviewPlayers->count() }} players verified.</p>
                        </div>
                        <div class="hf-card hf-card--flat">
                            <span class="hf-kpi-label">Payment reference</span>
                            <p class="hf-mb-0"><strong>{{ $reviewPayment?->method ?: 'No method' }}</strong> · {{ $reviewPayment?->reference_no ?: 'No reference' }}</p>
                            <p class="hf-small hf-muted hf-mb-0">Amount {{ number_format((float) ($reviewPayment?->amount ?? 0), 2) }} · {{ ucfirst($reviewPayment?->status ?? 'pending') }}</p>
                        </div>
                    </div>
                    <h3 class="hf-section-title">Roster ({{ $reviewPlayers->count() }})</h3>
                    <div class="hf-table-wrap">
                        <table class="hf-table">
                            <thead>
                                <tr><th scope="col">#</th><th scope="col">Player</th><th scope="col">Position</th><th scope="col">Age</th><th scope="col">Consent</th><th scope="col">Documents</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($reviewPlayers as $player)
                                    @php
                                        $pdoc = $player->eligibilityDocuments->where('type', 'birth_certificate')->first();
                                        $pdocStatus = $pdoc?->status ?? 'missing';
                                        $pdocLabel = $pdocStatus === 'approved' ? 'On file' : 'Missing';
                                        $pdocTone = $pdocStatus === 'approved' ? 'success' : 'danger';
                                    @endphp
                                    <tr>
                                        <td>{{ $player->jersey_number }}</td>
                                        <td>{{ $player->first_name }} {{ $player->last_name }}</td>
                                        <td>{{ $player->position }}</td>
                                        <td>{{ $player->birthdate?->age ?? '—' }}</td>
                                        <td><span class="hf-chip hf-chip--{{ $player->consent_at ? 'success' : 'danger' }}">{{ $player->consent_at ? 'On file' : 'Missing' }}</span></td>
                                        <td><span class="hf-chip hf-chip--{{ $pdocTone }}">{{ $pdocLabel }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="hf-small hf-muted hf-mt-2 hf-mb-0">Submitted {{ $review->submitted_at?->format('M d, Y') }}. Approval verifies the team's eligibility snapshot; the roster locks for the coach.</p>
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="closeReview()" data-testid="submission-detail-close">Close</button>
                    @if ($review->status === 'submitted')
                        <button type="button" class="hf-btn hf-btn--danger" wire:click="reject({{ $review->id }})" data-testid="submission-detail-reject">Reject</button>
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="approve({{ $review->id }})" data-testid="submission-detail-approve">Approve team</button>
                    @endif
                </div>
            </dialog>
        @endif
    @endif

    @if ($approvingId !== null)
        @php $approveReg = $this->findRegistration($approvingId); @endphp
        @if ($approveReg)
            <dialog class="hf-dialog" x-data x-init="if (! $el.open) $el.showModal()" x-on:cancel.prevent="$wire.cancelApprove()" x-on:click="if ($event.target === $el) $wire.cancelApprove()" wire:key="submission-approve-confirm-dialog" data-testid="submission-approve-confirm-dialog">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">Approve {{ $approveReg->team?->name ?? 'team' }}?</h2>
                    <button type="button" class="hf-icon-btn" wire:click="cancelApprove()" aria-label="Close dialog" data-testid="dialog-close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-small hf-muted">The team becomes eligible for seeding and its roster locks for the coach.</p>
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="cancelApprove()" data-testid="submission-approve-cancel">Cancel</button>
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="confirmApprove" data-testid="submission-approve-confirm">Approve team</button>
                </div>
            </dialog>
        @endif
    @endif

    @if ($rejectingId !== null)
        @php $rejectReg = $this->findRegistration($rejectingId); @endphp
        @if ($rejectReg)
            <dialog class="hf-dialog" x-data x-init="if (! $el.open) $el.showModal()" x-on:cancel.prevent="$wire.cancelReject()" x-on:click="if ($event.target === $el) $wire.cancelReject()" wire:key="submission-reject-dialog" data-testid="submission-reject-dialog">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">Reject {{ $rejectReg->team?->name ?? 'team' }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="cancelReject()" aria-label="Close dialog" data-testid="dialog-close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-small hf-muted">Tell the coach what needs to change. The reason is shown on the coach's status page and written to the audit log.</p>
                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="submissionRejectReason">Reason</label>
                        <textarea id="submissionRejectReason" class="hf-textarea" rows="4" wire:model="rejectReason" placeholder="Missing PSA birth certificate for two players." data-testid="submission-reject-reason"></textarea>
                        @error('rejectReason')
                            <p class="hf-field-error hf-mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="cancelReject()" data-testid="submission-reject-cancel">Cancel</button>
                    <button type="button" class="hf-btn hf-btn--danger" wire:click="confirmReject" data-testid="submission-reject-confirm">Reject team</button>
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
