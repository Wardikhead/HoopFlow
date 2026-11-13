<div>
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
                ['label' => 'Pending review', 'value' => $this->kpis['pending'], 'hint' => 'Across all tournaments', 'tone' => 'gold', 'testid' => 'submission-kpi-pending'],
                ['label' => 'Approved', 'value' => $this->kpis['approved'], 'hint' => 'Eligible for seeding', 'tone' => 'success', 'testid' => 'submission-kpi-approved'],
                ['label' => 'Rejected', 'value' => $this->kpis['rejected'], 'hint' => 'Archived from the bracket', 'tone' => 'alert', 'testid' => 'submission-kpi-rejected'],
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
                <select class="hf-select" id="submissionStatus" wire:model.live="status" data-testid="submission-status-filter">
                    <option value="submitted">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="all">All statuses</option>
                </select>
            </div>
            <div class="hf-field">
                <label class="hf-label" for="submissionTournament">Tournament</label>
                <select class="hf-select" id="submissionTournament" wire:model.live="tournament" data-testid="submission-tournament-filter">
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
                        $paymentChipStatus = $payment?->status === 'verified' ? 'paid' : ($payment?->status ?? 'pending');
                    @endphp
                    <article class="hf-card hf-card--flat" data-testid="submission-row-{{ $registration->id }}" wire:key="submission-{{ $registration->id }}">
                        <div class="hf-between">
                            <span class="hf-flex hf-items-center hf-gap-2">
                                <x-team-shield :team="$team" size="lg" />
                                <span>
                                    <strong>{{ $team?->name ?? 'Unknown team' }}</strong>
                                    <span class="hf-small hf-muted" style="display:block">
                                        {{ $team?->coach_name ?? 'Coach' }} · {{ $team?->city ?? '' }} · {{ $tournament?->name ?? 'Tournament' }}
                                    </span>
                                </span>
                            </span>
                            <span class="hf-flex hf-wrap hf-gap-1">
                                @include('components.status-chip', ['status' => $status])
                                @include('components.status-chip', ['status' => $paymentChipStatus])
                            </span>
                        </div>
                        <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2 hf-small">
                            <span class="hf-chip hf-chip--slate">{{ $players->count() }} players</span>
                            @include('components.status-chip', ['status' => $documentsComplete ? 'verified' : 'missing', 'label' => 'Documents'])
                            @include('components.status-chip', ['status' => $consentComplete ? 'verified' : 'missing', 'label' => 'Consent'])
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
                                @if ($tournament?->seedsLocked())
                                    <span class="hf-chip hf-chip--gold" data-testid="submission-seeds-locked-{{ $registration->id }}"><i class="bi bi-lock-fill" aria-hidden="true"></i>Seeds locked</span>
                                @else
                                    <button type="button" class="hf-btn hf-btn--sm hf-btn--primary" wire:click="approve({{ $registration->id }})" data-testid="submission-approve-{{ $registration->id }}">Approve</button>
                                @endif
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
                $reviewEligibilityService = app(\App\Services\EligibilityService::class);
                $reviewEligibility = $reviewEligibilityService->status($reviewTeam ?? new \App\Models\Team());
                $reviewPayment = $review->payment;
                $reviewPaymentChipStatus = $reviewPayment?->status === 'verified' ? 'paid' : ($reviewPayment?->status ?? 'pending');
                $consentOptions = [
                    ['value' => 'pending', 'label' => 'Pending', 'description' => 'Guardian consent still to be collected', 'tone' => 'warning', 'icon' => 'hourglass-split'],
                    ['value' => 'verified', 'label' => 'Verified', 'description' => 'Guardian consent recorded', 'tone' => 'success', 'icon' => 'check-circle-fill'],
                    ['value' => 'missing', 'label' => 'Missing', 'description' => 'No guardian consent on file', 'tone' => 'danger', 'icon' => 'x-circle-fill'],
                ];
                $documentOptions = [
                    ['value' => 'pending', 'label' => 'Pending', 'description' => 'Birth certificate awaiting review', 'tone' => 'warning', 'icon' => 'hourglass-split'],
                    ['value' => 'approved', 'label' => 'Verified', 'description' => 'Birth certificate approved', 'tone' => 'success', 'icon' => 'check-circle-fill'],
                    ['value' => 'missing', 'label' => 'Missing', 'description' => 'No birth certificate on file', 'tone' => 'danger', 'icon' => 'file-earmark-x'],
                ];
            @endphp
            <dialog class="hf-dialog hf-dialog--wide" x-data x-init="if (! $el.open) $el.showModal()" x-on:cancel.prevent="$wire.closeReview()" x-on:click="if ($event.target === $el) $wire.closeReview()" wire:key="submission-review-dialog" data-testid="submission-review-dialog">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">{{ $review->team?->name ?? 'Team' }}</h2>
                    <button type="button" class="hf-icon-btn" wire:click="closeReview()" aria-label="Close dialog" data-testid="dialog-close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <div class="hf-dialog__body">
                    <div class="hf-flex hf-items-center hf-gap-2 hf-mb-2">
                        <x-team-shield :team="$reviewTeam" size="lg" />
                        <div>
                            <strong>{{ $reviewTeam?->coach_name ?? 'Coach' }} · {{ $reviewTeam?->city ?? '' }}</strong>
                            <span class="hf-small hf-muted" style="display:block">{{ $review->tournament?->name ?? '' }} · {{ $review->division?->name ?? '' }}</span>
                        </div>
                        <span class="hf-ml-auto hf-flex hf-gap-1">
                            @include('components.status-chip', ['status' => $review->status ?? 'submitted'])
                            @include('components.status-chip', ['status' => $reviewPaymentChipStatus])
                        </span>
                    </div>
                    <div class="hf-grid hf-grid--2 hf-mb-3">
                        <div class="hf-card hf-card--flat">
                            <span class="hf-kpi-label">Eligibility</span>
                            <div class="hf-flex hf-wrap hf-gap-1 hf-mt-1">
                                @include('components.status-chip', ['status' => $reviewEligibility['documentsComplete'] ? 'verified' : 'missing', 'label' => 'Documents'])
                                @include('components.status-chip', ['status' => $reviewEligibility['consentComplete'] ? 'verified' : 'missing', 'label' => 'Consent'])
                            </div>
                            @if ($reviewEligibility['verifiedAt'])
                                <p class="hf-small hf-muted hf-mt-1 hf-mb-0">Verified {{ \Illuminate\Support\Carbon::parse($reviewEligibility['verifiedAt'])->format('M d, Y') }}</p>
                            @else
                                <p class="hf-small hf-muted hf-mt-1 hf-mb-0">Verification is recorded when you approve the team.</p>
                            @endif
                        </div>
                        <div class="hf-card hf-card--flat">
                            <span class="hf-kpi-label">Payment reference</span>
                            <p class="hf-mb-0"><strong>{{ $reviewPayment?->method ?: 'No method' }}</strong> · {{ $reviewPayment?->reference_no ?: 'No reference' }}</p>
                            <p class="hf-small hf-muted hf-mb-0">Amount {{ '₱'.number_format((float) ($reviewPayment?->amount ?? 0)) }} · {{ ucfirst($reviewPaymentChipStatus) }}</p>
                        </div>
                    </div>
                    <div class="hf-between hf-mt-2">
                        <h3 class="hf-section-title hf-mb-0">Roster ({{ $reviewPlayers->count() }})</h3>
                        <div class="hf-status-legend" aria-hidden="true">
                            <span class="hf-status-legend__item hf-status-legend__item--success"><i class="bi bi-check-circle-fill"></i>Verified</span>
                            <span class="hf-status-legend__item hf-status-legend__item--warning"><i class="bi bi-hourglass-split"></i>Pending</span>
                            <span class="hf-status-legend__item hf-status-legend__item--danger"><i class="bi bi-x-circle-fill"></i>Missing</span>
                        </div>
                    </div>
                    <div class="hf-table-wrap hf-mt-1">
                        <table class="hf-table">
                            <thead>
                                <tr><th scope="col">#</th><th scope="col">Player</th><th scope="col">Position</th><th scope="col">Age at start</th><th scope="col">Consent</th><th scope="col">Documents</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($reviewPlayers as $player)
                                    @php
                                        $document = $player->eligibilityDocuments
                                            ->firstWhere('type', \App\Services\EligibilityService::DOCUMENT_TYPE);
                                        $documentStatus = $document?->status ?? 'pending';
                                        $editable = in_array($review->status, ['submitted', 'approved'], true);
                                        $ageAtStart = $reviewEligibilityService->ageAtTournamentStart($player->birthdate, $review->tournament);
                                        $ageIssue = $reviewEligibilityService->ageIssue($player->birthdate, $review->division, $review->tournament);
                                    @endphp
                                    <tr wire:key="review-player-{{ $player->id }}" @class(['is-ineligible' => $ageIssue !== null])>
                                        <td>{{ $player->jersey_number }}</td>
                                        <td>{{ $player->first_name }} {{ $player->last_name }}</td>
                                        <td>{{ $player->position }}</td>
                                        <td>
                                            <span class="hf-player-age">
                                                <strong>{{ $ageAtStart ?? '—' }}</strong>
                                                @if ($ageIssue)
                                                    <span class="hf-chip hf-chip--danger" title="{{ $ageIssue }}"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>Out of range</span>
                                                @endif
                                            </span>
                                        </td>
                                        <td>
                                            @if ($editable)
                                                <x-status-select
                                                    :value="$player->consent_status"
                                                    :options="$consentOptions"
                                                    action="updateConsent"
                                                    :player-id="$player->id"
                                                    testid="submission-consent-{{ $player->id }}"
                                                    label="Guardian consent"
                                                />
                                            @else
                                                @include('components.status-chip', ['status' => $player->consent_status])
                                            @endif
                                        </td>
                                        <td>
                                            @if ($editable)
                                                <x-status-select
                                                    :value="$documentStatus"
                                                    :options="$documentOptions"
                                                    action="updateDocument"
                                                    :player-id="$player->id"
                                                    testid="submission-document-{{ $player->id }}"
                                                    label="Birth certificate"
                                                />
                                            @else
                                                @include('components.status-chip', ['status' => $documentStatus === 'approved' ? 'verified' : $documentStatus])
                                            @endif
                                        </td>
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
                        @if ($review->tournament?->seedsLocked())
                            <span class="hf-chip hf-chip--gold" data-testid="submission-detail-seeds-locked"><i class="bi bi-lock-fill" aria-hidden="true"></i>Seeds locked</span>
                        @else
                            <button type="button" class="hf-btn hf-btn--primary" wire:click="approve({{ $review->id }})" data-testid="submission-detail-approve">Approve team</button>
                        @endif
                    @endif
                </div>
            </dialog>
        @endif
    @endif

    @if ($approvingId !== null)
        @php $approveReg = $this->findRegistration($approvingId); @endphp
        @if ($approveReg)
            @php
                $approveIssues = collect($this->approvalIssues($approveReg->id));
                $ageBlockers = $approveIssues->filter(fn (array $issue): bool => $issue['ageIssue'] !== null);
                $overrideIssues = $approveIssues->reject(fn (array $issue): bool => $issue['ageIssue'] !== null);
            @endphp
            <dialog class="hf-dialog" x-data x-init="if (! $el.open) $el.showModal()" x-on:cancel.prevent="$wire.cancelApprove()" x-on:click="if ($event.target === $el) $wire.cancelApprove()" wire:key="submission-approve-confirm-dialog" data-testid="submission-approve-confirm-dialog">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">Approve {{ $approveReg->team?->name ?? 'team' }}?</h2>
                    <button type="button" class="hf-icon-btn" wire:click="cancelApprove()" aria-label="Close dialog" data-testid="dialog-close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-small hf-muted">The team becomes eligible for seeding and its roster locks for the coach.</p>
                    @if ($ageBlockers->isNotEmpty())
                        <div class="hf-callout hf-callout--danger hf-mt-2" data-testid="submission-age-blockers">
                            <strong class="hf-small"><i class="bi bi-shield-x" aria-hidden="true"></i> Age eligibility blocks approval</strong>
                            <ul class="hf-small hf-mt-1 hf-mb-0">
                                @foreach ($ageBlockers as $issue)
                                    <li><strong>{{ $issue['name'] }}</strong> — {{ $issue['ageIssue'] }}</li>
                                @endforeach
                            </ul>
                            <p class="hf-small hf-mb-0 hf-mt-1">Correct the birthdate or move the player to an eligible division. Age issues cannot be overridden.</p>
                        </div>
                    @endif
                    @if ($overrideIssues->isNotEmpty())
                        <div class="hf-callout hf-callout--warning hf-mt-2">
                            <strong class="hf-small">Incomplete eligibility</strong>
                            <ul class="hf-small hf-mt-1">
                                @foreach ($overrideIssues as $issue)
                                    <li>{{ $issue['name'] }} — consent {{ $issue['consent'] }}, documents {{ $issue['document'] }}</li>
                                @endforeach
                            </ul>
                            <label class="hf-check">
                                <input type="checkbox" wire:model="approveAnyway" data-testid="submission-approve-anyway">
                                Approve anyway
                            </label>
                        </div>
                    @endif
                    @error('approveAnyway') <p class="hf-field-error hf-mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="cancelApprove()" data-testid="submission-approve-cancel">Cancel</button>
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="confirmApprove" data-testid="submission-approve-confirm" @disabled($ageBlockers->isNotEmpty() || ($overrideIssues->isNotEmpty() && ! $approveAnyway))>Approve team</button>
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

</div>
