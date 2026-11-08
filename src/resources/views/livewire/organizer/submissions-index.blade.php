<div>
@section('title', 'Submissions')

<header class="hf-page-head hf-mb-3">
    <div>
        <span class="hf-eyebrow">Organizer desk · Eligibility</span>
        <h1 class="hf-page-title">Submissions</h1>
        <p class="hf-page-desc">Review rosters, consent, documents, and payment references before a team enters the bracket.</p>
    </div>
    <div class="hf-page-actions">
        <a class="hf-btn" href="{{ url('/payments') }}" data-testid="submissions-open-payments"><i class="bi bi-credit-card"></i> Payments</a>
    </div>
</header>

<div class="hf-card hf-mb-3">
    <div class="hf-form-grid">
        <div class="hf-field">
            <label class="hf-label" for="submissionStatus">Status</label>
            <select class="hf-select" id="submissionStatus" wire:model.live="status" data-testid="submission-status-filter">
                <option value="all">All statuses</option>
                <option value="submitted">Submitted</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>
</div>

<div id="submissionList" data-testid="submission-list">
    @if ($this->registrations->isEmpty())
        <div class="hf-empty" data-testid="submission-empty">
            <i class="bi bi-clipboard-check" aria-hidden="true"></i>
            <h3>No submissions match</h3>
            <p>Try another status filter.</p>
        </div>
    @else
        <div class="hf-grid hf-grid--2">
            @foreach ($this->registrations as $registration)
                @php
                    $team = $registration->team;
                    $tournament = $registration->tournament;
                    $decider = $registration->decider;
                    $status = $registration->status ?? 'submitted';
                    $statusTone = match ($status) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'submitted' => 'warning',
                        default => 'slate',
                    };
                @endphp
                <article class="hf-card hf-card--flat" data-testid="submission-row-{{ $registration->id }}" wire:key="submission-{{ $registration->id }}">
                    <div class="hf-between">
                        <span class="hf-flex hf-items-center hf-gap-2">
                            <span class="hf-shield hf-shield--lg">{{ strtoupper(substr($team?->name ?? '?', 0, 1)) }}</span>
                            <span>
                                <strong>{{ $team?->name ?? 'Unknown team' }}</strong>
                                <span class="hf-small hf-muted" style="display:block">
                                    {{ $team?->coachUser?->first_name ?? 'Coach' }} {{ $team?->coachUser?->last_name ?? '' }} · {{ $tournament?->name ?? 'Tournament' }}
                                </span>
                            </span>
                        </span>
                        <span class="hf-flex hf-wrap hf-gap-1">
                            <span class="hf-chip hf-chip--{{ $statusTone }}">{{ ucfirst($status) }}</span>
                        </span>
                    </div>
                    <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2 hf-small">
                        <span class="hf-chip hf-chip--slate">{{ $team?->players?->count() ?? 0 }} players</span>
                        <span class="hf-chip hf-chip--slate">{{ $registration->division?->name ?? ($tournament?->divisions?->first()?->name ?? '') }}</span>
                        @if ($registration->submitted_at)
                            <span class="hf-chip hf-chip--slate">Submitted {{ $registration->submitted_at->format('M d, Y') }}</span>
                        @endif
                    </div>
                    @if ($registration->rejection_reason)
                        <p class="hf-small hf-muted hf-mt-1 hf-mb-0">Reason: {{ $registration->rejection_reason }}</p>
                    @endif
                    @if ($decider)
                        <p class="hf-small hf-muted hf-mt-1 hf-mb-0">Decided by {{ $decider->name ?? $decider->email ?? 'Organizer' }} @if ($registration->decided_at) on {{ $registration->decided_at->format('M d, Y') }} @endif</p>
                    @endif
                    <div class="hf-btn-row hf-mt-2">
                        @if ($status === 'submitted')
                            <button type="button" class="hf-btn hf-btn--sm hf-btn--primary" wire:click="approve({{ $registration->id }})" data-testid="submission-approve-{{ $registration->id }}">Approve</button>
                            <button type="button" class="hf-btn hf-btn--sm hf-btn--danger" wire:click="openReject({{ $registration->id }})" data-testid="submission-reject-{{ $registration->id }}">Reject</button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>

@if ($rejectingId !== null)
    <div class="hf-card hf-mt-3" data-testid="submission-reject-dialog">
        <h2 class="hf-section-title">Reject submission</h2>
        <div class="hf-field hf-field--full">
            <label class="hf-label" for="submissionRejectReason">Reason</label>
            <textarea id="submissionRejectReason" class="hf-textarea" rows="4" wire:model="rejectReason" data-testid="submission-reject-reason"></textarea>
        </div>
        <div class="hf-btn-row hf-mt-2">
            <button type="button" class="hf-btn" wire:click="cancelReject" data-testid="submission-reject-cancel">Cancel</button>
            <button type="button" class="hf-btn hf-btn--danger" wire:click="confirmReject" data-testid="submission-reject-confirm">Reject</button>
        </div>
    </div>
@endif
</div>
