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
    @section('title', 'My team')

    @php
        $team = $this->team;
        $registration = $this->registration;
        $tournament = $this->tournament;
        $payment = $this->payment;
        $locked = $this->rosterLocked;
        $players = $team?->players ?? collect();
        $playerCount = $players->count();
        $consentCount = $players->filter(fn ($player) => $player->consent_at !== null)->count();
        $docsCount = $players->filter(fn ($player) => $player->eligibility_status === 'verified')->count();
        $status = $registration?->status ?? 'submitted';
        $paymentStatus = $this->paymentStatusText();
        $reviewPending = in_array($status, ['submitted', 'pending'], true);
        $fee = $this->entryFee;
        $paymentDetail = $paymentStatus.' · '.($payment?->method ?: 'No method').($payment?->reference_no ? ' · '.$payment->reference_no : '');
        $timeline = [
            [
                'title' => 'Registration submitted',
                'detail' => $registration?->submitted_at?->format('M d, Y') ?? 'TBD',
                'done' => true,
            ],
            [
                'title' => 'Organizer review',
                'detail' => $reviewPending ? 'Waiting for review' : 'Status: '.$status,
                'done' => ! $reviewPending,
            ],
            [
                'title' => 'Payment',
                'detail' => $paymentDetail,
                'done' => $paymentStatus === 'paid',
            ],
            [
                'title' => 'Roster verified',
                'detail' => $registration?->roster_locked_at
                    ? 'Verified '.$registration->roster_locked_at->format('M d, Y')
                    : 'Locks after organizer verification',
                'done' => $locked,
            ],
            [
                'title' => 'Seeding',
                'detail' => $registration?->seed ? 'Seed '.$registration->seed : 'Assigned when the bracket is generated',
                'done' => $registration?->seed !== null,
            ],
        ];
        $next = $this->nextMatch;
        $nextSlot = $next?->matchSlots->sortBy('start_at')->first();
        $nextStart = $nextSlot?->start_at;
    @endphp

    @if (! $team)
        <div class="hf-empty" data-testid="my-team-empty">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
            <h3>No team attached</h3>
            <p>Register a team for an open tournament to start the coach journey.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}" data-testid="my-team-browse">Browse tournaments</a>
        </div>
    @else
        <header class="hf-page-head hf-mb-3">
            <div>
                <span class="hf-eyebrow">{{ $tournament?->name ?? 'Tournament' }} · {{ $registration?->division?->name ?? '' }}</span>
                <h1 class="hf-page-title">{{ $team->name }}</h1>
                <p class="hf-page-desc">
                    {{ implode(' · ', array_filter([$this->coachName(), $team->city, $this->venueName])) }}
                </p>
                <div class="hf-page-meta">
                    <span><i class="bi bi-shield-check"></i><x-status-chip :status="$status" /></span>
                    <span>
                        <i class="bi bi-cash-coin"></i>
                        @if ($payment)
                            <x-status-chip :status="$this->paymentChipStatus()" />
                        @else
                            <span class="hf-chip hf-chip--slate">No payment</span>
                        @endif
                    </span>
                    <span><i class="bi bi-people"></i>{{ $playerCount }} players</span>
                </div>
            </div>
            <div class="hf-page-actions">
                <a class="hf-btn" href="{{ route('teamShow', ['team' => $team->id]) }}" data-testid="my-team-public">Public profile</a>
                <a class="hf-btn" href="{{ route('tournaments') }}" data-testid="my-team-register-another"><i class="bi bi-plus-lg"></i> Register another team</a>
            </div>
        </header>

        @if ($this->teams->count() > 1)
            <div class="hf-card hf-card--flat hf-mb-3">
                <label class="hf-label" for="myTeamSelect">Switch team</label>
                <select
                    class="hf-select"
                    id="myTeamSelect"
                    data-testid="my-team-select"
                    x-on:change="
                        const target = new URL(@js(route('myTeam', array_filter(['tournament' => $this->tournamentId]))));
                        target.searchParams.set('team', $event.target.value);
                        window.location.href = target.toString();
                    "
                >
                    @foreach ($this->teams as $option)
                        @php $optionStatus = $this->registrationStatuses[$option->id] ?? 'submitted'; @endphp
                        <option value="{{ $option->id }}" @selected($option->id === $team->id)>{{ $option->name }} · {{ $optionStatus }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($status === 'rejected')
            <div class="hf-card hf-card--flat hf-mb-3" style="border-color: var(--hf-alert)">
                <span class="hf-chip hf-chip--danger">Rejected</span>
                <h3 class="hf-section-title hf-mt-1">Organizer feedback</h3>
                <p class="hf-small hf-muted hf-mb-0">{{ $registration?->rejection_reason ?: 'Contact the tournament committee for details.' }}</p>
            </div>
        @endif

        <div class="hf-grid hf-grid--4 hf-mb-3">
            <x-kpi-tile
                label="Record"
                :value="$this->record['wins'].'-'.$this->record['losses']"
                hint="Wins and losses"
                tone="primary"
                testid="my-team-record"
            />
            <x-kpi-tile
                label="Roster"
                :value="$playerCount"
                :hint="$consentCount.' consents · '.$docsCount.' verified'"
                tone="success"
                testid="my-team-roster-kpi"
            />
            <x-kpi-tile
                label="Seed"
                :value="$registration?->seed ?? 'TBD'"
                :hint="$registration?->seed ? 'Locked for the bracket' : 'Assigned after approval'"
                tone="gold"
                testid="my-team-seed"
            />
            <x-kpi-tile
                label="Entry fee"
                :value="'₱'.number_format($fee, 0)"
                :hint="$paymentStatus === 'paid' ? 'Paid' : 'Payment outstanding'"
                tone="alert"
                testid="my-team-fee"
            />
        </div>

        <div class="hf-split">
            <div>
                <div class="hf-section-head">
                    <h2 class="hf-section-title">Roster ({{ $playerCount }})</h2>
                    @if ($locked)
                        <span class="hf-chip hf-chip--slate"><i class="bi bi-lock"></i> Locked after verification</span>
                    @else
                        <button
                            type="button"
                            class="hf-btn hf-btn--sm hf-btn--primary"
                            wire:click="openPlayerDialog"
                            data-testid="my-team-add-player"
                            @disabled($playerCount >= 12)
                        >
                            <i class="bi bi-plus-lg"></i> Add player
                        </button>
                    @endif
                </div>

                <div class="hf-card hf-mb-3" data-testid="my-team-roster">
                    @if ($players->isEmpty())
                        <div class="hf-empty">
                            <i class="bi bi-person-plus" aria-hidden="true"></i>
                            <h3>No players yet</h3>
                            <p>Add eight to twelve players to complete the roster.</p>
                        </div>
                    @else
                        <div class="hf-table-wrap">
                            <table class="hf-table">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Player</th>
                                        <th scope="col">Position</th>
                                        <th scope="col">Age</th>
                                        <th scope="col">Consent</th>
                                        <th scope="col">Documents</th>
                                        @unless ($locked)
                                            <th scope="col">Actions</th>
                                        @endunless
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($players as $player)
                                        @php $playerName = trim($player->first_name.' '.$player->last_name); @endphp
                                        <tr wire:key="my-team-player-{{ $player->id }}" data-testid="my-team-player-{{ $player->id }}">
                                            <td>{{ $player->jersey_number }}</td>
                                            <td><strong>{{ $playerName }}</strong></td>
                                            <td>{{ $player->position }}</td>
                                            <td>{{ $player->birthdate?->age ?? '—' }}</td>
                                            <td>
                                                @if ($player->consent_at)
                                                    <span class="hf-chip hf-chip--success">On file</span>
                                                @else
                                                    <span class="hf-chip hf-chip--danger">Missing</span>
                                                @endif
                                            </td>
                                            <td><x-status-chip :status="$player->eligibility_status" /></td>
                                            @unless ($locked)
                                                <td>
                                                    <div class="hf-flex hf-gap-1">
                                                        <button
                                                            type="button"
                                                            class="hf-icon-btn"
                                                            wire:click="openPlayerDialog({{ $player->id }})"
                                                            aria-label="Edit {{ $playerName }}"
                                                            data-testid="my-team-edit-{{ $player->id }}"
                                                        >
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="hf-icon-btn"
                                                            wire:click="confirmRemovePlayer({{ $player->id }})"
                                                            aria-label="Remove {{ $playerName }}"
                                                            data-testid="my-team-remove-{{ $player->id }}"
                                                        >
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            @endunless
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="hf-section-head">
                    <h2 class="hf-section-title">Registration timeline</h2>
                </div>
                <div class="hf-card hf-mb-3">
                    <ol class="hf-timeline" data-testid="my-team-timeline">
                        @foreach ($timeline as $item)
                            <li class="hf-timeline-item">
                                <div class="hf-timeline-meta">
                                    <span>{{ $item['done'] ? 'Complete' : 'Pending' }}</span>
                                    @if ($item['done'])
                                        <span class="hf-chip hf-chip--success">Done</span>
                                    @else
                                        <span class="hf-chip hf-chip--slate">Waiting</span>
                                    @endif
                                </div>
                                <h3>{{ $item['title'] }}</h3>
                                <p>{{ $item['detail'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            <aside>
                <div class="hf-card hf-mb-3">
                    <span class="hf-kpi-label">Eligibility</span>
                    <h3 class="hf-section-title hf-mt-1">{{ $locked ? 'Roster locked' : 'Roster editable' }}</h3>
                    <div class="hf-flex hf-wrap hf-gap-1 hf-mt-1">
                        @if ($consentCount === $playerCount)
                            <x-status-chip status="verified" label="Consent complete" />
                        @else
                            <x-status-chip status="missing" :label="($playerCount - $consentCount).' consent missing'" />
                        @endif
                        @if ($docsCount === $playerCount)
                            <x-status-chip status="verified" label="Documents complete" />
                        @else
                            <x-status-chip status="missing" :label="($playerCount - $docsCount).' documents pending'" />
                        @endif
                    </div>
                    <p class="hf-small hf-muted hf-mt-2 hf-mb-0">
                        {{ $locked ? 'The organizer verified this roster. Contact the committee to make a change.' : 'Add missing consent and documents before the organizer review.' }}
                    </p>
                </div>

                <div class="hf-card hf-mb-3" data-testid="my-team-payment">
                    <div class="hf-between">
                        <span class="hf-kpi-label">Payment</span>
                        @if ($payment)
                            <x-status-chip :status="$this->paymentChipStatus()" />
                        @else
                            <span class="hf-chip hf-chip--slate">No payment</span>
                        @endif
                    </div>
                    <h3 class="hf-section-title hf-mt-1">{{ $payment?->method ?: 'No method' }}</h3>
                    <p class="hf-small hf-muted hf-mb-1">{{ $payment?->reference_no ?: 'No reference' }} · ₱{{ number_format($fee, 0) }}</p>
                    <p class="hf-small hf-muted hf-mb-0">
                        <span class="hf-kpi-label">Send to:</span> {{ $tournament?->payment_method ?: 'GCash' }} · {{ $tournament?->payment_account ?: 'TBD' }}
                    </p>
                    @if ($payment?->paid_at)
                        <p class="hf-small hf-muted hf-mb-0">Verified {{ $payment->paid_at->format('M d, Y') }}</p>
                    @endif
                    @if ($paymentStatus !== 'paid')
                        <button type="button" class="hf-btn hf-btn--sm hf-btn--primary hf-mt-2" wire:click="openPaymentDialog" data-testid="my-team-update-payment">
                            Update payment reference
                        </button>
                    @endif
                </div>

                <div class="hf-card hf-mb-3">
                    <span class="hf-kpi-label">Next game</span>
                    @if ($next)
                        <h3 class="hf-section-title hf-mt-1">{{ $next->roundLabel() }}</h3>
                        <p class="hf-small hf-muted hf-mb-1">{{ $nextStart?->format('M d, Y') ?? 'TBD' }} · {{ $nextStart?->format('g:i A') ?? 'TBD' }}</p>
                        <p class="hf-small hf-muted hf-mb-1">{{ $nextSlot?->court?->name ?? 'Court TBD' }}</p>
                        <a class="hf-btn hf-btn--sm" href="{{ route('gameStats', ['match' => $next->id]) }}" data-testid="my-team-next-game">Game details</a>
                    @else
                        <p class="hf-small hf-muted hf-mb-0">No game scheduled yet.</p>
                    @endif
                </div>

                <div class="hf-card">
                    <span class="hf-kpi-label">Tournament</span>
                    <h3 class="hf-section-title hf-mt-1">{{ $tournament?->name ?? '—' }}</h3>
                    <p class="hf-small hf-muted hf-mb-1">{{ $registration?->division?->name ?? '' }} · {{ $this->venueName }}</p>
                    @if ($tournament)
                        <a class="hf-btn hf-btn--sm" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}" data-testid="my-team-tournament">Tournament desk</a>
                    @endif
                </div>
            </aside>
        </div>

        @if ($showPlayerDialog)
            <dialog
                class="hf-dialog"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.closePlayerDialog()"
                x-on:click="if ($event.target === $el) $wire.closePlayerDialog()"
                wire:key="my-player-dialog-{{ $editingPlayerId ?? 'new' }}"
                data-testid="my-player-dialog"
            >
                <form wire:submit="savePlayer" novalidate>
                    <div class="hf-dialog__head">
                        <h2 class="hf-dialog__title">{{ $editingPlayerId !== null ? 'Edit '.$playerName : 'Add player' }}</h2>
                        <button type="button" class="hf-icon-btn" wire:click="closePlayerDialog" aria-label="Close dialog" data-testid="dialog-close">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="hf-dialog__body">
                        <div class="hf-form-grid">
                            <div class="hf-field hf-field--full">
                                <label class="hf-label" for="myPlayerName">Player name</label>
                                <input
                                    class="hf-input"
                                    id="myPlayerName"
                                    type="text"
                                    wire:model="playerName"
                                    autocomplete="off"
                                    @error('playerName') aria-invalid="true" @enderror
                                    data-testid="my-player-name"
                                >
                                @error('playerName') <p class="hf-field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="hf-field">
                                <label class="hf-label" for="myPlayerNumber">Jersey number</label>
                                <input
                                    class="hf-input"
                                    id="myPlayerNumber"
                                    type="number"
                                    min="1"
                                    max="99"
                                    wire:model="playerNumber"
                                    @error('playerNumber') aria-invalid="true" @enderror
                                    data-testid="my-player-number"
                                >
                                @error('playerNumber') <p class="hf-field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="hf-field">
                                <label class="hf-label" for="myPlayerPosition">Position</label>
                                <select class="hf-select" id="myPlayerPosition" wire:model="playerPosition" data-testid="my-player-position">
                                    @foreach (\App\Livewire\Coach\MyTeam::POSITIONS as $position)
                                        <option value="{{ $position }}">{{ $position }}</option>
                                    @endforeach
                                </select>
                                @error('playerPosition') <p class="hf-field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="hf-field">
                                <label class="hf-label" for="myPlayerAge">Age</label>
                                <input
                                    class="hf-input"
                                    id="myPlayerAge"
                                    type="number"
                                    min="8"
                                    max="99"
                                    wire:model="playerAge"
                                    @error('playerAge') aria-invalid="true" @enderror
                                    data-testid="my-player-age"
                                >
                                @error('playerAge') <p class="hf-field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="hf-field">
                                <label class="hf-label" for="myPlayerDocument">Documents</label>
                                <select class="hf-select" id="myPlayerDocument" wire:model="playerDocumentStatus" data-testid="my-player-document">
                                    <option value="verified">Verified</option>
                                    <option value="pending">Pending review</option>
                                    <option value="missing">Missing</option>
                                </select>
                                @error('playerDocumentStatus') <p class="hf-field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="hf-field hf-field--full">
                                <label class="hf-check">
                                    <input type="checkbox" wire:model="playerConsent" data-testid="my-player-consent">
                                    Guardian consent on file
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="hf-dialog__foot">
                        <button type="button" class="hf-btn" wire:click="closePlayerDialog" data-testid="my-player-cancel">Cancel</button>
                        <button type="submit" class="hf-btn hf-btn--primary" data-testid="my-player-save">
                            {{ $editingPlayerId !== null ? 'Save player' : 'Add player' }}
                        </button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($showRemoveDialog)
            <dialog
                class="hf-dialog"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.cancelRemovePlayer()"
                x-on:click="if ($event.target === $el) $wire.cancelRemovePlayer()"
                wire:key="my-team-remove-dialog"
                data-testid="my-team-remove-dialog"
            >
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title">Remove {{ $removingPlayerName }}?</h2>
                    <button type="button" class="hf-icon-btn" wire:click="cancelRemovePlayer" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-mb-0">The player is removed from the roster immediately. You can add them again before verification.</p>
                </div>
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="cancelRemovePlayer" data-testid="confirm-cancel">Cancel</button>
                    <button type="button" class="hf-btn hf-btn--danger" wire:click="removePlayer" data-testid="confirm-ok">Remove player</button>
                </div>
            </dialog>
        @endif

        @if ($showPaymentDialog)
            <dialog
                class="hf-dialog"
                x-data
                x-init="if (! $el.open) $el.showModal()"
                x-on:cancel.prevent="$wire.closePaymentDialog()"
                x-on:click="if ($event.target === $el) $wire.closePaymentDialog()"
                wire:key="my-payment-dialog"
                data-testid="my-payment-dialog"
            >
                <form wire:submit="savePayment" novalidate>
                    <div class="hf-dialog__head">
                        <h2 class="hf-dialog__title">Update payment reference</h2>
                        <button type="button" class="hf-icon-btn" wire:click="closePaymentDialog" aria-label="Close dialog" data-testid="dialog-close">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="hf-dialog__body">
                        <p class="hf-small hf-muted">Entry fee is ₱{{ number_format($fee, 0) }}. The organizer verifies the reference before seeding.</p>
                        <div class="hf-form-grid">
                            <div class="hf-field">
                                <label class="hf-label" for="myPaymentMethod">Method</label>
                                <select class="hf-select" id="myPaymentMethod" wire:model="paymentMethod" data-testid="my-payment-method">
                                    @foreach (\App\Livewire\Coach\MyTeam::PAYMENT_METHODS as $method)
                                        <option value="{{ $method }}">{{ $method }}</option>
                                    @endforeach
                                </select>
                                @error('paymentMethod') <p class="hf-field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="hf-field">
                                <label class="hf-label" for="myPaymentRef">Reference</label>
                                <input
                                    class="hf-input"
                                    id="myPaymentRef"
                                    type="text"
                                    wire:model="paymentReference"
                                    placeholder="GCASH-9283746"
                                    autocomplete="off"
                                    @error('paymentReference') aria-invalid="true" @enderror
                                    data-testid="my-payment-ref"
                                >
                                @error('paymentReference') <p class="hf-field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="hf-dialog__foot">
                        <button type="button" class="hf-btn" wire:click="closePaymentDialog" data-testid="my-payment-cancel">Cancel</button>
                        <button type="submit" class="hf-btn hf-btn--primary" data-testid="my-payment-save">Submit reference</button>
                    </div>
                </form>
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
