<div>
    @section('title', 'My team')

    @php
        $team = $this->team;
        $registration = $this->registration;
        $tournament = $this->tournament;
        $payment = $this->payment;
        $locked = $this->rosterLocked;
        $players = $team?->players ?? collect();
        $playerCount = $players->count();
        $consentCount = $players->filter(fn ($player) => $player->consent_status === 'verified')->count();
        $docsCount = $players->filter(fn ($player) => $player->eligibilityDocuments
            ->contains(fn ($document) => $document->type === \App\Services\EligibilityService::DOCUMENT_TYPE
                && $document->status === 'approved'))->count();
        $status = $registration?->status ?? 'submitted';
        $paymentStatus = $this->paymentStatusText();
        $reviewPending = in_array($status, ['submitted', 'pending'], true);
        $fee = $this->entryFee;
        $ageService = app(\App\Services\EligibilityService::class);
        $division = $this->division;
        $ageRange = $ageService->ageRange($division);
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
                <h1 class="hf-page-title">My team</h1>
                <h2 class="hf-hero__name">{{ $team->name }}</h2>
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
            <div class="hf-card hf-card--flat hf-card--alert hf-mb-3">
                <span class="hf-chip hf-chip--danger">Rejected</span>
                <h3 class="hf-section-title hf-mt-1">Organizer feedback</h3>
                <p class="hf-small hf-muted hf-mb-0">{{ $registration?->rejection_reason ?: 'Contact the tournament committee for details.' }}</p>
            </div>
        @endif

        @php
            $nextStep = match (true) {
                $playerCount < 8 => [
                    'Add '.max(0, 8 - $playerCount).' more '.($playerCount === 7 ? 'player' : 'players').' to reach the eight-player minimum.',
                    'Add player',
                    'openPlayerDialog',
                ],
                ! $payment => ['Submit your entry fee reference so the organizer can verify it.', 'Add payment reference', 'openPaymentDialog'],
                $status === 'rejected' => ['Update the roster using the organizer feedback below, then resubmit.', null, null],
                $status === 'submitted' => ['Your registration is in the organizer queue. No action needed right now.', null, null],
                $status === 'approved' => ['Your team is approved. Watch the bracket for the first game.', null, null],
                default => ['Check the tournament timeline for the next registration step.', null, null],
            };
        @endphp

        <x-callout
            :tone="$status === 'rejected' ? 'danger' : ($status === 'approved' ? 'success' : 'info')"
            :title="$nextStep[0]"
            class="hf-next-step"
        >
            @if ($nextStep[1])
                <button type="button" class="hf-btn hf-btn--sm" wire:click="{{ $nextStep[2] }}">{{ $nextStep[1] }}</button>
            @endif
        </x-callout>

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
                :hint="$consentCount.' consents · '.$docsCount.' documents'"
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
                            wire:loading.class="is-loading"
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
                            <table class="hf-table hf-table--roster hf-table--stack">
                                <caption class="hf-sr-only">Team roster</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Player</th>
                                        <th scope="col">Position</th>
                                        <th scope="col">Age at start</th>
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
                                            <td data-label="#">{{ $player->jersey_number }}</td>
                                            <td data-label="Player"><strong>{{ $playerName }}</strong></td>
                                            <td data-label="Position">{{ $player->position }}</td>
                                            <td data-label="Age at start">{{ $ageService->ageAtTournamentStart($player->birthdate, $tournament) ?? '—' }}</td>
                                            <td data-label="Consent"><x-status-chip :status="$player->consent_status" /></td>
                                            <td data-label="Documents"><x-status-chip :status="app(\App\Services\EligibilityService::class)->documentStatusForPlayer($player)" /></td>
                                            @unless ($locked)
                                                <td data-label="Actions">
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
                        {{ $locked ? 'The organizer verified this roster. Contact the committee to make a change.' : 'The organizer records consent and verifies documents in Submissions. Keep player details accurate.' }}
                    </p>
                </div>

                <div class="hf-card hf-payment-summary hf-mb-3" data-testid="my-team-payment">
                    <div class="hf-between">
                        <span class="hf-kpi-label">Payment</span>
                        @if ($payment)
                            <x-status-chip :status="$this->paymentChipStatus()" />
                        @else
                            <span class="hf-chip hf-chip--slate">No payment</span>
                        @endif
                    </div>
                    <dl>
                        <div><dt>Method</dt><dd>{{ $payment?->method ?: 'No method' }}</dd></div>
                        <div><dt>Reference</dt><dd>{{ $payment?->reference_no ?: 'No reference' }}</dd></div>
                        <div><dt>Amount</dt><dd>₱{{ number_format($fee, 0) }}</dd></div>
                        <div><dt>Status</dt><dd>{{ $paymentStatus }}</dd></div>
                    </dl>
                    <p class="hf-small hf-muted hf-mt-2 hf-mb-0">
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
                aria-labelledby="myPlayerDialogTitle">
                <form wire:submit="savePlayer" novalidate wire:loading.attr="aria-busy">
                    <x-form-errors />
                    <div class="hf-dialog__head">
                        <h2 class="hf-dialog__title" id="myPlayerDialogTitle">{{ $editingPlayerId !== null ? 'Edit '.$playerName : 'Add player' }}</h2>
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
                                    @error('playerName') aria-invalid="true" aria-describedby="playerName-error" @enderror
                                    data-testid="my-player-name"
                                >
                                @error('playerName') <p class="hf-field-error" id="playerName-error">{{ $message }}</p> @enderror
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
                                    @error('playerNumber') aria-invalid="true" aria-describedby="playerNumber-error" @enderror
                                    data-testid="my-player-number"
                                >
                                @error('playerNumber') <p class="hf-field-error" id="playerNumber-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="hf-field">
                                <label class="hf-label" for="myPlayerPosition">Position</label>
                                <select class="hf-select" id="myPlayerPosition" wire:model="playerPosition" data-testid="my-player-position">
                                    @foreach (\App\Livewire\Coach\MyTeam::POSITIONS as $position)
                                        <option value="{{ $position }}">{{ $position }}</option>
                                    @endforeach
                                </select>
                                @error('playerPosition') <p class="hf-field-error" id="playerPosition-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="hf-field">
                                <label class="hf-label" for="myPlayerBirthdate">Birthdate</label>
                                <input
                                    class="hf-input"
                                    id="myPlayerBirthdate"
                                    type="date"
                                    max="{{ now()->format('Y-m-d') }}"
                                    wire:model="playerBirthdate"
                                    @error('playerBirthdate') aria-invalid="true" aria-describedby="playerBirthdate-error" @enderror
                                    data-testid="my-player-birthdate"
                                >
                                @error('playerBirthdate') <p class="hf-field-error" id="playerBirthdate-error">{{ $message }}</p> @enderror
                                <p class="hf-help" data-testid="my-player-age-range">
                                    @if ($division)
                                        Eligible age {{ $ageRange['min'] }}–{{ $ageRange['max'] }} at tournament start
                                        @if ($tournament?->start_date)
                                            ({{ $tournament->start_date->format('M j, Y') }})
                                        @endif
                                        .
                                    @else
                                        No division is assigned yet. Contact the organizer before adding players.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="hf-dialog__foot hf-dialog__foot--sticky">
                        <button type="button" class="hf-btn" wire:click="closePlayerDialog" data-testid="my-player-cancel">Cancel</button>
                        <button type="submit" class="hf-btn hf-btn--primary" data-testid="my-player-save" wire:loading.attr="disabled" wire:loading.class="is-loading">
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
                aria-labelledby="myTeamRemoveDialogTitle">
                <div class="hf-dialog__head">
                    <h2 class="hf-dialog__title" id="myTeamRemoveDialogTitle">Remove {{ $removingPlayerName }}?</h2>
                    <button type="button" class="hf-icon-btn" wire:click="cancelRemovePlayer" aria-label="Close dialog" data-testid="dialog-close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="hf-dialog__body">
                    <p class="hf-mb-0">The player is removed from the roster immediately. You can add them again before verification.</p>
                </div>
                <div class="hf-dialog__foot hf-dialog__foot--sticky">
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
                aria-labelledby="myPaymentDialogTitle">
                <form wire:submit="savePayment" novalidate wire:loading.attr="aria-busy">
                    <x-form-errors />
                    <div class="hf-dialog__head">
                        <h2 class="hf-dialog__title" id="myPaymentDialogTitle">Update payment reference</h2>
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
                                @error('paymentMethod') <p class="hf-field-error" id="paymentMethod-error">{{ $message }}</p> @enderror
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
                                    @error('paymentReference') aria-invalid="true" aria-describedby="paymentReference-error" @enderror
                                    data-testid="my-payment-ref"
                                >
                                @error('paymentReference') <p class="hf-field-error" id="paymentReference-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="hf-dialog__foot hf-dialog__foot--sticky">
                        <button type="button" class="hf-btn" wire:click="closePaymentDialog" data-testid="my-payment-cancel">Cancel</button>
                        <button type="submit" class="hf-btn hf-btn--primary" data-testid="my-payment-save" wire:loading.attr="disabled" wire:loading.class="is-loading">Submit reference</button>
                    </div>
                </form>
            </dialog>
        @endif
    @endif

</div>
