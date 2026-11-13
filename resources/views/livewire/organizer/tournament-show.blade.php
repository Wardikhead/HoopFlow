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
    @php
        $tournament = $this->tournament;
        $role = $this->role;
        $registrations = $this->registrations;
        $announcements = $this->announcements;
        $coachTeam = $this->coachTeam;
        $money = fn ($value) => '₱' . number_format((int) ($value ?? 0));
    @endphp

    @if (! $tournament)
        @section('title', 'Tournament not found')

        <div class="hf-empty" data-testid="tournament-not-found">
            <i class="bi bi-trophy" aria-hidden="true"></i>
            <h3>Tournament not found</h3>
            <p>The tournament link is invalid or the demo has been reset.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}">Back to tournaments</a>
        </div>
    @else
        @section('title', $tournament->name)

        @php
            $division = $tournament->divisions->first();
            $courtCount = $tournament->courtCount();
            $registered = $registrations->count();
            $approved = $this->approvedCount;
            $pending = $this->pendingCount;
            $played = $this->matchesPlayed;
            $scheduled = $this->matchesScheduled;
            $collected = $this->collected;
            $collectionRate = $this->collectionRate;
            $hasBracket = $this->hasBracket;
            $bracketRounds = $this->bracketRounds;
            $champion = $this->champion;
            $canGenerateBracket = $this->canGenerateBracket;
            $canGenerateSchedule = $this->canGenerateSchedule;
            $paymentLabel = function ($payment): string {
                if (! $payment) {
                    return 'missing';
                }

                return $payment->status === 'verified' ? 'paid' : (string) $payment->status;
            };
            $registrationLabel = fn ($status) => match ($status) {
                'approved' => 'approved',
                'rejected' => 'rejected',
                default => 'pending',
            };
        @endphp

        <section class="hf-hero hf-mb-3" data-testid="tournament-hero-{{ $tournament->id }}">
            <span class="hf-eyebrow" style="color: var(--hf-gold)">{{ $division?->name ?? 'Division' }} · {{ $tournament->statusLabel() }}</span>
            <h1>{{ $tournament->name }}</h1>
            <p>{{ $tournament->description ?? 'Youth basketball tournament managed with HoopFlow.' }}</p>
            <div class="hf-stat-strip">
                <div><span>Dates</span><strong>{{ $tournament->start_date?->format('M d, Y') ?? 'TBD' }} – {{ $tournament->end_date?->format('M d, Y') ?? 'TBD' }}</strong></div>
                <div><span>Venue</span><strong>{{ $tournament->venueName() }}</strong></div>
                <div><span>Courts</span><strong>{{ $courtCount }}</strong></div>
                <div><span>Entry</span><strong>{{ $money($tournament->entryFee()) }}</strong></div>
                <div><span>Format</span><strong>{{ $tournament->formatLabel() }}</strong></div>
                <div><span>Payment</span><strong>{{ $tournament->payment_method ?: 'GCash' }} · {{ $tournament->payment_account ?: 'TBD' }}</strong></div>
            </div>
            <div class="hf-btn-row hf-mt-3">
                <a class="hf-btn hf-btn--primary" href="{{ route('bracket', ['id' => $tournament->id]) }}" data-testid="show-bracket">Bracket</a>
                <a class="hf-btn" href="{{ route('schedule', ['id' => $tournament->id]) }}" data-testid="show-schedule">Schedule</a>
                <a class="hf-btn" href="{{ route('standings', ['id' => $tournament->id]) }}" data-testid="show-standings">Standings</a>
                @if ($role === 'coach')
                    @if ($coachTeam)
                        <a class="hf-btn" href="{{ route('myTeam', ['team' => $coachTeam->id, 'tournament' => $tournament->id]) }}" data-testid="show-my-team">My team status</a>
                    @elseif ($tournament->status === 'registration-open')
                        <a class="hf-btn hf-btn--primary" href="{{ route('teamRegister', ['tournament' => $tournament->id]) }}" data-testid="show-register">Register team</a>
                    @endif
                @endif
                @if ($role === 'organizer')
                    <a class="hf-btn" href="{{ route('announcementCreate', ['tournament' => $tournament->id]) }}" data-testid="show-create-announcement">Announce</a>
                @endif
            </div>
        </section>

        <div class="hf-grid hf-grid--4 hf-mb-3">
            @if ($role === 'fan')
                <x-kpi-tile label="Teams" :value="$approved" :hint="$tournament->slotCount().' slots'" tone="primary" testid="show-kpi-registered" />
                <x-kpi-tile label="Matches played" :value="$played" :hint="$scheduled.' scheduled'" tone="success" testid="show-kpi-played" />
                <x-kpi-tile label="Courts" :value="$courtCount" hint="Venue capacity" tone="gold" testid="show-kpi-courts" />
                <x-kpi-tile label="Entry" :value="$money($tournament->entryFee())" hint="Per team" tone="alert" testid="show-kpi-entry" />
            @else
                <x-kpi-tile label="Registered" :value="$registered" :hint="$tournament->slotCount().' slots'" tone="primary" testid="show-kpi-registered" />
                <x-kpi-tile label="Approved" :value="$approved" :hint="$pending.' pending'" tone="success" testid="show-kpi-approved" />
                <x-kpi-tile label="Matches played" :value="$played" :hint="$scheduled.' scheduled'" tone="gold" testid="show-kpi-played" />
                <x-kpi-tile label="Collected" :value="$money($collected)" :hint="$collectionRate.'% of expected'" tone="alert" testid="show-kpi-money" />
            @endif
        </div>

        <div class="hf-split">
            <div>
                <div class="hf-section-head">
                    <h2 class="hf-section-title">Team list</h2>
                    <a class="hf-section-link" href="{{ route('teams') }}">All teams</a>
                </div>
                <div class="hf-grid" data-testid="tournament-team-list">
                    @forelse ($registrations as $registration)
                        @php
                            $team = $registration->team;
                            $payment = $registration->payment;
                            $showMoney = $role === 'organizer' || ($role === 'coach' && $coachTeam?->id === $team?->id);
                            $isPending = in_array($registration->status, ['submitted', 'pending'], true);
                        @endphp
                        <a class="hf-card hf-card--flat" href="{{ route('teamShow', ['team' => $registration->team_id]) }}" data-testid="tournament-team-{{ $team?->id ?? $registration->id }}" wire:key="tournament-team-{{ $registration->id }}">
                            <div class="hf-between">
                                <span class="hf-flex hf-items-center hf-gap-2">
                                    <x-team-shield :team="$team" />
                                    <span>
                                        <strong>{{ $team?->name ?? 'Team' }}</strong>
                                        <span class="hf-small hf-muted" style="display:block">{{ $team?->coach_name ?? 'Coach TBD' }} · {{ $team?->city ?? 'City TBD' }}</span>
                                    </span>
                                </span>
                                <span class="hf-flex hf-wrap hf-gap-1">
                                    <x-status-chip :status="$registrationLabel($registration->status)" />
                                    @if ($showMoney)
                                        <x-status-chip :status="$paymentLabel($payment)" />
                                    @endif
                                </span>
                            </div>
                            @if ($role === 'organizer' && $isPending)
                                <div class="hf-small hf-muted hf-mt-1">Waiting for review · {{ $team?->players->count() ?? 0 }} players</div>
                            @endif
                        </a>
                    @empty
                        <div class="hf-empty">
                            <i class="bi bi-people" aria-hidden="true"></i>
                            <h3>No teams yet</h3>
                            <p>Coach registrations will appear here.</p>
                        </div>
                    @endforelse
                </div>

                <div class="hf-section-head hf-mt-3">
                    <h2 class="hf-section-title">Announcements</h2>
                    <a class="hf-section-link" href="{{ route('announcements', ['id' => $tournament->id]) }}">All announcements</a>
                </div>
                <div class="hf-grid">
                    @forelse ($announcements as $announcement)
                        <article class="hf-card hf-card--flat {{ $announcement->pinned ? 'hf-announcement--pinned' : '' }}" wire:key="tournament-announcement-{{ $announcement->id }}" data-testid="tournament-announcement-{{ $announcement->id }}">
                            <div class="hf-between">
                                <span class="hf-chip hf-chip--slate">{{ \App\Models\Announcement::categoryLabel($announcement->category) }}</span>
                                @if ($announcement->pinned)
                                    <span class="hf-chip hf-chip--gold"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i>Pinned</span>
                                @endif
                            </div>
                            <h3 class="hf-section-title hf-mt-1">{{ $announcement->title }}</h3>
                            <p class="hf-muted hf-small hf-mb-0">{{ $announcement->body }}</p>
                            <div class="hf-match-head hf-mt-2">
                                <span>{{ $announcement->createdBy?->name ?? 'Tournament desk' }}</span>
                                <span>{{ ($announcement->published_at ?? $announcement->created_at)?->format('M d, Y') ?? '' }}</span>
                            </div>
                            <div class="hf-match-head">
                                <span>Audience: {{ \App\Models\Announcement::audienceLabel($announcement->audience) }}</span>
                                <span>{{ $tournament->name }}</span>
                            </div>
                        </article>
                    @empty
                        <div class="hf-empty">
                            <i class="bi bi-megaphone" aria-hidden="true"></i>
                            <h3>No announcements</h3>
                            <p>Publish a venue or schedule update for this tournament.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <aside>
                <div class="hf-section-head">
                    <h2 class="hf-section-title">Bracket</h2>
                    <a class="hf-section-link" href="{{ route('bracket', ['id' => $tournament->id]) }}">Open</a>
                </div>
                <div class="hf-card hf-mb-3">
                    @if ($hasBracket)
                        <div class="hf-between">
                            <span class="hf-chip hf-chip--gold">{{ $bracketRounds }} rounds</span>
                            <strong>{{ $played }} played</strong>
                        </div>
                        <p class="hf-small hf-muted hf-mt-1">{{ $champion ? 'Champion: '.$champion->name : 'Bracket is live. Winners advance automatically.' }}</p>
                        <a class="hf-btn hf-btn--sm" href="{{ route('bracket', ['id' => $tournament->id]) }}">Open bracket</a>
                    @else
                        <p class="hf-small hf-muted">{{ $approved }} approved teams ready to seed.</p>
                        @if ($canGenerateBracket)
                            <button type="button" class="hf-btn hf-btn--primary" wire:click="requestGenerateBracket" data-testid="tournament-generate-bracket" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="requestGenerateBracket,confirm">Generate bracket</span>
                                <span wire:loading wire:target="requestGenerateBracket,confirm">Seeding...</span>
                            </button>
                        @else
                            <button type="button" class="hf-btn" disabled data-testid="tournament-generate-bracket-disabled">Generate bracket</button>
                            <p class="hf-small hf-muted hf-mt-1">{{ $role === 'organizer' ? 'At least two approved teams are required.' : 'Organizer access required.' }}</p>
                        @endif
                    @endif
                </div>

                <div class="hf-section-head">
                    <h2 class="hf-section-title">Court schedule</h2>
                    <a class="hf-section-link" href="{{ route('schedule', ['id' => $tournament->id]) }}">Open</a>
                </div>
                <div class="hf-card hf-mb-3">
                    @if (! $hasBracket)
                        <p class="hf-small hf-muted hf-mb-0">Generate the bracket before scheduling courts.</p>
                    @elseif ($canGenerateSchedule)
                        <p class="hf-small hf-muted">{{ $courtCount }} courts · {{ $tournament->slotMinutes() }}-minute slots · {{ $tournament->restMinutes() }}-minute rest.</p>
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="requestGenerateSchedule" data-testid="tournament-generate-schedule" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="requestGenerateSchedule">Preview schedule</span>
                            <span wire:loading wire:target="requestGenerateSchedule">Planning...</span>
                        </button>
                    @else
                        <p class="hf-small hf-muted hf-mb-0">{{ $played > 0 ? 'Results are recorded; the schedule is locked.' : 'Schedule already generated.' }}</p>
                        <a class="hf-btn hf-btn--sm hf-mt-1" href="{{ route('schedule', ['id' => $tournament->id]) }}">Open schedule</a>
                    @endif
                </div>

                <div class="hf-card">
                    <span class="hf-kpi-label">Format</span>
                    <h3 class="hf-section-title hf-mt-1">{{ $tournament->formatLabel() }}</h3>
                    <p class="hf-small hf-muted hf-mb-0">
                        {{ $tournament->slotCount() }}-team bracket.
                        @if ($tournament->format() === 'round-robin')
                            Every team plays every team; standings decide the champion.
                        @elseif ($tournament->format() === 'double-elimination')
                            Two losses eliminate a team.
                            {{ $tournament->isModifiedDoubleElimination()
                                ? 'A best-of-'.$tournament->grandFinalBestOf().' grand final decides the title; no reset game.'
                                : 'If the losers-bracket team wins the grand final, a reset game decides the title.' }}
                        @else
                            Winners advance automatically; empty slots become byes.
                        @endif
                    </p>
                </div>
            </aside>
        </div>
    @endif

    @if ($showSchedulePreview)
        <dialog
            class="hf-dialog hf-dialog--wide"
            x-data
            x-init="if (! $el.open) $el.showModal()"
            x-on:cancel.prevent="$wire.closeSchedulePreview()"
            x-on:click="if ($event.target === $el) $wire.closeSchedulePreview()"
            wire:key="tournament-schedule-preview"
            data-testid="schedule-preview-dialog"
        >
            <div class="hf-dialog__head">
                <h2 class="hf-dialog__title">Schedule preview</h2>
                <button type="button" class="hf-icon-btn" wire:click="closeSchedulePreview" aria-label="Close dialog" data-testid="dialog-close">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="hf-dialog__body">
                <p class="hf-small hf-muted">
                    {{ count($this->previewRows) }} matches across {{ $previewPlan['courtCount'] ?? 0 }} courts. No court double-bookings; every team has at least {{ $previewPlan['restMinutes'] ?? 0 }} minutes of rest.
                </p>
                <div class="hf-table-wrap">
                    <table class="hf-table">
                        <thead>
                            <tr>
                                <th scope="col">Round</th>
                                <th scope="col">Match</th>
                                <th scope="col">Court</th>
                                <th scope="col">Slot</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->previewRows as $row)
                                <tr wire:key="schedule-preview-row-{{ $loop->index }}">
                                    <td>{{ $row['round'] }}</td>
                                    <td>{{ $row['match'] }}</td>
                                    <td>{{ $row['court'] }}</td>
                                    <td>{{ $row['slot'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="hf-dialog__foot">
                <button type="button" class="hf-btn" wire:click="closeSchedulePreview" data-testid="schedule-preview-cancel">Cancel</button>
                <button type="button" class="hf-btn hf-btn--primary" wire:click="publishSchedule" data-testid="publish-schedule" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="publishSchedule">Publish schedule</span>
                    <span wire:loading wire:target="publishSchedule">Publishing...</span>
                </button>
            </div>
        </dialog>
    @endif

    @if ($showConfirm)
        <dialog
            class="hf-dialog"
            x-data
            x-init="if (! $el.open) $el.showModal()"
            x-on:cancel.prevent="$wire.cancelConfirm()"
            x-on:click="if ($event.target === $el) $wire.cancelConfirm()"
            wire:key="tournament-confirm-dialog"
            data-testid="tournament-confirm-dialog"
        >
            <div class="hf-dialog__head">
                <h2 class="hf-dialog__title">{{ $confirmTitle }}</h2>
                <button type="button" class="hf-icon-btn" wire:click="cancelConfirm" aria-label="Close dialog" data-testid="dialog-close">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="hf-dialog__body">
                <p class="hf-mb-0">{{ $confirmMessage }}</p>
            </div>
            <div class="hf-dialog__foot">
                <button type="button" class="hf-btn" wire:click="cancelConfirm" data-testid="confirm-cancel">Cancel</button>
                <button type="button" class="hf-btn hf-btn--{{ $confirmTone }}" wire:click="confirm" data-testid="confirm-ok" wire:loading.attr="disabled">{{ $confirmLabel }}</button>
            </div>
        </dialog>
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
