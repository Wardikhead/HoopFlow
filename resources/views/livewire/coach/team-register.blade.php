<div>
    @section('title', 'Register team')

    @php
        $tournament = $this->tournament;
        $existingTeam = $this->existingTeam;
        $existingRegistration = $existingTeam?->registrations->firstWhere('tournament_id', $tournament?->id);
        $division = $tournament?->divisions->first();
        $money = fn ($value) => '₱' . number_format((int) ($value ?? 0));
    @endphp

    @if (! $tournament)
        <div class="hf-empty" data-testid="register-not-found">
            <i class="bi bi-trophy" aria-hidden="true"></i>
            <h3>Tournament not found</h3>
            <p>Choose an open tournament from the directory.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}">Browse tournaments</a>
        </div>
    @elseif ($tournament->status !== 'registration-open')
        <div class="hf-empty" data-testid="register-closed">
            <i class="bi bi-lock" aria-hidden="true"></i>
            <h3>Registration is closed</h3>
            <p>{{ $tournament->name }} is not accepting new teams.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('tournaments') }}">Find an open tournament</a>
        </div>
    @elseif ($existingTeam)
        <div class="hf-empty" data-testid="register-existing">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>
            <h3>Team already registered</h3>
            <p>{{ $existingTeam->name }} is already in {{ $tournament->name }} with status “{{ $existingRegistration?->status === 'submitted' ? 'pending' : ($existingRegistration?->status ?? 'pending') }}”.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('myTeam', array_filter(['team' => $existingTeam->id, 'tournament' => $tournament->id])) }}">Open my team</a>
        </div>
    @else
        <header class="hf-page-head hf-mb-3">
            <div>
                <span class="hf-eyebrow">{{ $division?->name ?? 'Division' }} · Registration open</span>
                <h1 class="hf-page-title">Register a team</h1>
                <p class="hf-page-desc">
                    {{ $tournament->name }} · {{ $tournament->venueName() }} · {{ $tournament->start_date?->format('M d, Y') ?? 'TBD' }} to {{ $tournament->end_date?->format('M d, Y') ?? 'TBD' }} · {{ $money($tournament->entryFee()) }} entry.
                </p>
            </div>
            <div class="hf-page-actions">
                <a class="hf-btn" href="{{ route('tournamentShow', ['tournament' => $tournament->id]) }}" data-testid="register-cancel"><i class="bi bi-arrow-left"></i> Tournament</a>
            </div>
        </header>

        <p class="hf-small hf-muted hf-mb-2">Step {{ $step + 1 }} of 4 · {{ ['Team', 'Roster', 'Eligibility & payment', 'Review'][$step] }}</p>

        <ol class="hf-stepper" data-testid="register-stepper">
            @foreach (['Team', 'Roster', 'Eligibility & payment', 'Review'] as $index => $label)
                <li class="hf-step {{ $step === $index ? 'is-active' : '' }} {{ $step > $index ? 'is-done' : '' }}"
                    data-step-indicator="{{ $index }}"
                    data-testid="register-step-{{ $index + 1 }}"
                    @if ($step === $index) aria-current="step" @endif>
                    <span>{{ $index + 1 }}</span>{{ $label }}
                </li>
            @endforeach
        </ol>

        <div class="hf-wizard">
            <form wire:submit="save" class="hf-card hf-wizard__form" novalidate data-testid="register-form">
                <x-form-errors />
            <section data-step-panel="0" @if ($step !== 0) hidden @endif>
                <div class="hf-between hf-mb-2">
                    <p class="hf-small hf-muted hf-mb-0">Coach account: {{ auth()->user()?->name }}</p>
                    <button type="button" class="hf-btn hf-btn--sm" wire:click="fillDemo" data-testid="register-fill-demo">
                        <i class="bi bi-magic"></i> Fill sample data
                    </button>
                </div>
                <div class="hf-form-grid">
                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerTeamName">Team name</label>
                        <input class="hf-input" id="registerTeamName" type="text" wire:model="name" placeholder="San Antonio Vipers U17"
                               @error('name') aria-invalid="true" aria-describedby="name-error" @enderror data-testid="register-team-name">
                        @error('name') <p class="hf-field-error" id="name-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="hf-field">
                        <label class="hf-label" for="registerShortName">Short name (2–3 letters)</label>
                        <input class="hf-input" id="registerShortName" type="text" wire:model="shortName" maxlength="3" placeholder="VPR"
                               @error('shortName') aria-invalid="true" aria-describedby="shortName-error" @enderror data-testid="register-short-name">
                        @error('shortName') <p class="hf-field-error" id="shortName-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="hf-field">
                        <label class="hf-label" for="registerCity">City</label>
                        <input class="hf-input" id="registerCity" type="text" wire:model="city" placeholder="Pasig City"
                               @error('city') aria-invalid="true" aria-describedby="city-error" @enderror data-testid="register-city">
                        @error('city') <p class="hf-field-error" id="city-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="hf-field hf-field--full">
                        <label class="hf-label" for="registerCoach">Coach</label>
                        <input class="hf-input" id="registerCoach" type="text" wire:model="coach"
                               @error('coach') aria-invalid="true" aria-describedby="coach-error" @enderror data-testid="register-coach">
                        @error('coach') <p class="hf-field-error" id="coach-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="hf-field hf-field--full">
                        <span class="hf-label">Team color</span>
                        <div class="hf-flex hf-wrap hf-gap-2">
                            @foreach (\App\Livewire\Coach\TeamRegister::COLORS as $index => $color)
                                <label class="hf-check">
                                    <input type="radio" wire:model="avatarColor" value="{{ $color['value'] }}" data-testid="register-color-{{ $index }}">
                                    <span class="hf-shield" style="--team-color: {{ $color['value'] }}" aria-hidden="true"></span>
                                    {{ $color['label'] }}
                                </label>
                            @endforeach
                        </div>
                        @error('avatarColor') <p class="hf-field-error" id="avatarColor-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section data-step-panel="1" @if ($step !== 1) hidden @endif>
                <div class="hf-between hf-mb-2">
                    <div>
                        <p class="hf-small hf-muted hf-mb-0">A roster needs 8 to 12 players. Jersey numbers must be unique. Birthdate must fall within {{ $this->division?->name ?? 'the division' }} limits ({{ $this->divisionAgeMin }}–{{ $this->divisionAgeMax }}) at tournament start.</p>
                        <p class="hf-small hf-muted hf-mb-0">Guardian consent and documents are reviewed by the organizer after submission.</p>
                    </div>
                    <button type="button" class="hf-btn hf-btn--sm" wire:click="addPlayer" data-testid="register-add-player">
                        <i class="bi bi-plus-lg"></i> Add player
                    </button>
                </div>
                @error('players') <p class="hf-field-error" id="players-error">{{ $message }}</p> @enderror
                <div data-testid="register-roster">
                    @foreach ($players as $index => $player)
                        <div class="hf-card hf-card--flat hf-mb-2" wire:key="player-row-{{ $index }}" data-player-row="{{ $index }}" data-testid="register-player-{{ $index }}">
                            <div class="hf-between hf-mb-1">
                                <strong class="hf-small">Player {{ $index + 1 }}</strong>
                                <button type="button" class="hf-btn hf-btn--sm" wire:click="removePlayer({{ $index }})"
                                        @if (count($players) <= 8) disabled @endif data-testid="register-remove-player-{{ $index }}">
                                    Remove
                                </button>
                            </div>
                            <div class="hf-form-grid">
                                <div class="hf-field hf-field--full">
                                    <label class="hf-label" for="playerName{{ $index }}">Player name</label>
                                    <input class="hf-input" id="playerName{{ $index }}" type="text" wire:model="players.{{ $index }}.name" placeholder="Full name"
                                           @error('players.'.$index.'.name') aria-invalid="true" aria-describedby="players-{{$index}}-name-error" @enderror data-testid="register-player-name-{{ $index }}">
                                    @error('players.'.$index.'.name') <p class="hf-field-error" id="players-{{$index}}-name-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="hf-field">
                                    <label class="hf-label" for="playerNumber{{ $index }}">Jersey number</label>
                                    <input class="hf-input" id="playerNumber{{ $index }}" type="number" min="1" max="99" wire:model="players.{{ $index }}.number"
                                           @error('players.'.$index.'.number') aria-invalid="true" aria-describedby="players-{{$index}}-number-error" @enderror data-testid="register-player-number-{{ $index }}">
                                    @error('players.'.$index.'.number') <p class="hf-field-error" id="players-{{$index}}-number-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="hf-field">
                                    <label class="hf-label" for="playerPosition{{ $index }}">Position</label>
                                    <select class="hf-select" id="playerPosition{{ $index }}" wire:model="players.{{ $index }}.position" data-testid="register-player-position-{{ $index }}">
                                        @foreach (\App\Livewire\Coach\TeamRegister::POSITIONS as $position)
                                            <option value="{{ $position }}">{{ $position }}</option>
                                        @endforeach
                                    </select>
                                    @error('players.'.$index.'.position') <p class="hf-field-error" id="players-{{$index}}-position-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="hf-field">
                                    <label class="hf-label" for="playerBirthdate{{ $index }}">Birthdate</label>
                                    <input class="hf-input" id="playerBirthdate{{ $index }}" type="date" max="{{ now()->format('Y-m-d') }}" wire:model="players.{{ $index }}.birthdate"
                                           @error('players.'.$index.'.birthdate') aria-invalid="true" aria-describedby="players-{{$index}}-birthdate-error" @enderror data-testid="register-player-birthdate-{{ $index }}">
                                    @error('players.'.$index.'.birthdate') <p class="hf-field-error" id="players-{{$index}}-birthdate-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section data-step-panel="2" @if ($step !== 2) hidden @endif>
                <div class="hf-grid hf-grid--2">
                    <div class="hf-card hf-card--flat">
                        <span class="hf-eyebrow">Eligibility checklist</span>
                        <ul class="hf-small hf-muted hf-mt-1">
                            <li>8–12 players on the roster</li>
                            <li>Unique jersey numbers from 1 to 99</li>
                            <li>Birthdates inside the division age range</li>
                            <li>Organizer reviews guardian consent and PSA birth certificates after submission</li>
                        </ul>
                    </div>
                    <div class="hf-card hf-card--flat">
                        <span class="hf-eyebrow">Entry fee</span>
                        <strong class="hf-kpi-value hf-kpi-value--lg">{{ $money($tournament->entryFee()) }}</strong>
                        <p class="hf-small hf-muted hf-mb-0">Submit a reference now; the organizer verifies the payment before seeding.</p>
                        <div class="hf-mt-2 hf-card hf-card--flat">
                            <span class="hf-kpi-label">Send payment to</span>
                            <strong class="hf-block">{{ $tournament->payment_method ?: 'GCash' }} · {{ $tournament->payment_account ?: 'TBD' }}</strong>
                        </div>
                    </div>
                </div>
                <div class="hf-form-grid hf-mt-3">
                    <div class="hf-field">
                        <label class="hf-label" for="registerMethod">Payment method</label>
                        <select class="hf-select" id="registerMethod" wire:model="paymentMethod"
                                @error('paymentMethod') aria-invalid="true" aria-describedby="paymentMethod-error" @enderror data-testid="register-payment-method">
                            <option value="GCash">GCash</option>
                            <option value="Maya">Maya</option>
                            <option value="Bank transfer">Bank transfer</option>
                            <option value="Cash">Cash</option>
                        </select>
                        @error('paymentMethod') <p class="hf-field-error" id="paymentMethod-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="hf-field">
                        <label class="hf-label" for="registerRef">Reference</label>
                        <input class="hf-input" id="registerRef" type="text" wire:model="paymentRef" placeholder="GCASH-9283746"
                               @error('paymentRef') aria-invalid="true" aria-describedby="paymentRef-error" @enderror data-testid="register-payment-ref">
                        @error('paymentRef') <p class="hf-field-error" id="paymentRef-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section data-step-panel="3" @if ($step !== 3) hidden @endif>
                @php
                    $filledPlayers = collect($players)->filter(fn ($player) => trim((string) ($player['name'] ?? '')) !== '');
                @endphp
                <div class="hf-grid hf-grid--3 hf-mb-3">
                    <div class="hf-card hf-card--flat">
                        <span class="hf-kpi-label">Team</span>
                        <strong class="hf-section-title hf-mt-1">{{ $name !== '' ? $name : 'Untitled' }}</strong>
                        <p class="hf-small hf-muted hf-mb-0">{{ $city }} · {{ $coach }}</p>
                    </div>
                    <div class="hf-card hf-card--flat">
                        <span class="hf-kpi-label">Roster</span>
                        <strong class="hf-kpi-value hf-kpi-value--lg">{{ $filledPlayers->count() }}</strong>
                        <p class="hf-small hf-muted hf-mb-0">players · consent and documents pending organizer review</p>
                    </div>
                    <div class="hf-card hf-card--flat">
                        <span class="hf-kpi-label">Payment</span>
                        <strong class="hf-section-title hf-mt-1">{{ $paymentMethod }}</strong>
                        <p class="hf-small hf-muted hf-mb-0">{{ $paymentRef !== '' ? $paymentRef : 'No reference' }} · {{ $money($tournament->entryFee()) }}</p>
                    </div>
                </div>
                <div class="hf-card hf-card--flat">
                    <div class="hf-table-wrap">
                        <table class="hf-table">
                            <caption class="hf-sr-only">Players added to this team</caption>
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Player</th>
                                    <th scope="col">Position</th>
                                    <th scope="col">Age at start</th>
                                    <th scope="col">Eligibility</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filledPlayers as $player)
                                    <tr>
                                        <td>{{ $player['number'] }}</td>
                                        <td><strong>{{ $player['name'] }}</strong></td>
                                        <td>{{ $player['position'] }}</td>
                                        <td>{{ $this->ageAtTournamentStart($player['birthdate'] ?? null) }}</td>
                                        <td><x-status-chip status="pending" label="Pending organizer review" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="hf-small hf-muted hf-mt-2 hf-mb-0">Submitting creates a pending team. The organizer records guardian consent and verifies documents in Submissions.</p>
                </div>
            </section>

            <div class="hf-sticky-actions hf-mt-3">
                <button type="button" class="hf-btn" wire:click="previousStep" data-testid="register-back" @if ($step === 0) hidden @endif wire:loading.attr="disabled">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
                </button>
                <button type="button" class="hf-btn hf-btn--primary" wire:click="nextStep" data-testid="register-next" @if ($step === 3) hidden @endif wire:loading.attr="disabled" wire:loading.class="is-loading">
                    Continue <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </button>
                <button type="submit" class="hf-btn hf-btn--primary" data-testid="register-submit" @if ($step !== 3) hidden @endif wire:loading.attr="disabled" wire:loading.class="is-loading">
                    <i class="bi bi-send" aria-hidden="true"></i> Submit registration
                </button>
            </div>
            </form>

            <aside class="hf-wizard__summary" aria-label="Registration summary">
                <span class="hf-eyebrow">Registration</span>
                <h2 class="hf-section-title hf-mt-1">{{ $name !== '' ? $name : 'Your team' }}</h2>
                <dl class="hf-summary-list">
                    <div><dt>Tournament</dt><dd>{{ $tournament->name }}</dd></div>
                    <div><dt>Division</dt><dd>{{ $division?->name ?? 'Division' }}</dd></div>
                    <div><dt>Venue</dt><dd>{{ $tournament->venueName() }}</dd></div>
                    <div><dt>Entry</dt><dd>{{ $money($tournament->entryFee()) }}</dd></div>
                    <div><dt>Roster</dt><dd>{{ count($players) }} players</dd></div>
                </dl>
                <p class="hf-small hf-muted hf-mt-2 hf-mb-0">You can edit the roster until the organizer verifies it.</p>
            </aside>
        </div>
    @endif

</div>
