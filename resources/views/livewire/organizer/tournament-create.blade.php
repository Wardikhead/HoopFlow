{{-- Blade view for Tournament create. --}}
<div>
    @section('title', 'Create tournament')
    @section('meta-description', 'Create a tournament - name, venue, dates, divisions, and game rules in one HoopFlow setup flow.')

    @section('breadcrumbs')
        <x-breadcrumbs :items="[
            ['label' => 'Home', 'href' => route('dashboard')],
            ['label' => 'Tournaments', 'href' => route('tournaments')],
            ['label' => 'Create tournament'],
        ]" />
    @endsection

    @php
        $errorTargets = [
            'name' => 'createName',
            'division_name' => 'createDivision',
            'venue_name' => 'createVenue',
            'start_date' => 'createStart',
            'end_date' => 'createEnd',
            'description' => 'createDescription',
            'format' => 'createFormatElimination',
            'bracketSlots' => 'createSlots',
            'roundRobinLegs' => 'createLegs',
            'playoffTeams' => 'createPlayoffTeams',
            'bestOfQuarterfinal' => 'createBestOfQuarterfinal',
            'bestOfSemifinal' => 'createBestOfSemifinal',
            'bestOfFinal' => 'createBestOfFinal',
            'fee' => 'createFee',
            'paymentMethod' => 'createPaymentMethod',
            'paymentAccount' => 'createPaymentAccount',
            'courts' => 'createCourt0',
            'slotMinutes' => 'createSlotMinutes',
            'restMinutes' => 'createRestMinutes',
        ];
        foreach ($courts as $index => $court) {
            $errorTargets["courts.$index"] = "createCourt$index";
        }
    @endphp

    <header class="hf-page-head hf-mb-3">
        <div>
            <span class="hf-eyebrow">Organizer desk · Setup</span>
            <h1 class="hf-page-title">Create a tournament</h1>
            <p class="hf-page-desc">Name the competition, choose the court plan, then publish a registration page in three steps.</p>
        </div>
        <div class="hf-page-actions">
            <a class="hf-btn" href="{{ route('tournaments') }}" data-testid="create-cancel"><i class="bi bi-arrow-left" aria-hidden="true"></i> Tournaments</a>
        </div>
    </header>

    <progress class="hf-meter hf-mb-2" value="{{ $step + 1 }}" max="3">{{ $step + 1 }} of 3</progress>

    <ol class="hf-stepper" data-testid="create-stepper">
        @foreach (['Basics', 'Format & courts', 'Review'] as $index => $label)
            <li class="hf-step {{ $step === $index ? 'is-active' : '' }} {{ $step > $index ? 'is-done' : '' }}"
                data-step-indicator="{{ $index }}"
                data-testid="create-step-{{ $index + 1 }}"
                @if ($step === $index) aria-current="step" @endif>
                <span>{{ $index + 1 }}</span>{{ $label }}
            </li>
        @endforeach
    </ol>

    <div class="hf-wizard">
        <form wire:submit="save" class="hf-card hf-wizard__form" novalidate data-testid="create-form">
            <x-form-errors :targets="$errorTargets" />
            <x-required-note class="hf-mb-2" />

        <section data-step-panel="0" @if ($step !== 0) hidden @endif>
            <div class="hf-form-grid">
                <div class="hf-field hf-field--full">
                    <label class="hf-label" for="createName">Tournament name</label>
                    <input class="hf-input" id="createName" type="text" wire:model="name" placeholder="HoopFlow Summer Cup" required
                           @error('name') aria-invalid="true" aria-describedby="name-error" @enderror data-testid="create-name">
                    @error('name') <p class="hf-field-error" id="name-error">{{ $message }}</p> @enderror
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="createDivision">Division</label>
                    <select class="hf-select" id="createDivision" wire:model="division_name" required data-testid="create-division"
                            @error('division_name') aria-invalid="true" aria-describedby="division_name-error" @enderror>
                        @foreach (['Under 12 (U12)', 'Under 14 (U14)', 'Under 15 (U15)', 'Under 17 (U17)', 'Open'] as $divisionOption)
                            <option value="{{ $divisionOption }}">{{ $divisionOption }}</option>
                        @endforeach
                    </select>
                    @error('division_name') <p class="hf-field-error" id="division_name-error">{{ $message }}</p> @enderror
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="createVenue">Venue</label>
                    <input class="hf-input" id="createVenue" type="text" wire:model="venue_name" placeholder="Ynares Sports Arena" required
                           @error('venue_name') aria-invalid="true" aria-describedby="venue_name-error" @enderror data-testid="create-venue">
                    @error('venue_name') <p class="hf-field-error" id="venue_name-error">{{ $message }}</p> @enderror
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="createStart">Start date</label>
                    <input class="hf-input" id="createStart" type="date" wire:model="start_date" required
                           @error('start_date') aria-invalid="true" aria-describedby="start_date-error" @enderror data-testid="create-start-date">
                    @error('start_date') <p class="hf-field-error" id="start_date-error">{{ $message }}</p> @enderror
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="createEnd">End date</label>
                    <input class="hf-input" id="createEnd" type="date" wire:model="end_date" required
                           @error('end_date') aria-invalid="true" aria-describedby="end_date-error" @enderror data-testid="create-end-date">
                    @error('end_date') <p class="hf-field-error" id="end_date-error">{{ $message }}</p> @enderror
                </div>
                <div class="hf-field hf-field--full">
                    <label class="hf-label" for="createDescription">Description</label>
                    <textarea class="hf-textarea" id="createDescription" rows="3" wire:model="description"
                              @error('description') aria-invalid="true" aria-describedby="description-error" @enderror data-testid="create-description"></textarea>
                    @error('description') <p class="hf-field-error" id="description-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section data-step-panel="1" @if ($step !== 1) hidden @endif>
            <div class="hf-form-grid">
                <div class="hf-field hf-field--full">
                    <span class="hf-label">Format</span>
                    <div class="hf-grid hf-grid--3">
                        <label class="hf-card hf-card--flat hf-flex hf-items-center hf-gap-2">
                            <input type="radio" id="createFormatElimination" wire:model.live="format" value="single-elimination" required data-testid="create-format-elimination">
                            <span>
                                <strong>Single elimination</strong>
                                <span class="hf-small hf-muted hf-block">One loss ends the run.</span>
                            </span>
                        </label>
                        <label class="hf-card hf-card--flat hf-flex hf-items-center hf-gap-2">
                            <input type="radio" id="createFormatDouble" wire:model.live="format" value="double-elimination" data-testid="create-format-double-elimination">
                            <span>
                                <strong>Double elimination</strong>
                                <span class="hf-small hf-muted hf-block">Two losses to be eliminated.</span>
                            </span>
                        </label>
                        <label class="hf-card hf-card--flat hf-flex hf-items-center hf-gap-2">
                            <input type="radio" id="createFormatRoundRobin" wire:model.live="format" value="round-robin" data-testid="create-format-round-robin">
                            <span>
                                <strong>Round robin</strong>
                                <span class="hf-small hf-muted hf-block">Everyone plays everyone.</span>
                            </span>
                        </label>
                    </div>
                    @error('format') <p class="hf-field-error" id="format-error">{{ $message }}</p> @enderror
                </div>

                <div class="hf-field">
                    <label class="hf-label" for="createSlots">Bracket slots</label>
                    <select class="hf-select" id="createSlots" wire:model="bracketSlots" required
                            @error('bracketSlots') aria-invalid="true" aria-describedby="bracketSlots-error" @enderror data-testid="create-slots">
                        @if ($format === 'round-robin')
                            @foreach (range(2, 16) as $slotOption)
                                <option value="{{ $slotOption }}">{{ $slotOption }} teams</option>
                            @endforeach
                        @else
                            @foreach ([2, 4, 8, 16, 32] as $slotOption)
                                <option value="{{ $slotOption }}">{{ $slotOption }} teams</option>
                            @endforeach
                        @endif
                    </select>
                    <p class="hf-small hf-muted hf-mb-0" data-testid="create-slots-hint">Single/double elimination: 2, 4, 8, 16, or 32. Round robin: 2 to 16.</p>
                    @error('bracketSlots') <p class="hf-field-error" id="bracketSlots-error">{{ $message }}</p> @enderror
                </div>

                @if ($format === 'round-robin')
                    <div class="hf-field">
                        <label class="hf-label" for="createLegs">Meetings per pair</label>
                        <input class="hf-input" id="createLegs" type="number" min="1" max="10" wire:model="roundRobinLegs" required
                               @error('roundRobinLegs') aria-invalid="true" aria-describedby="roundRobinLegs-error" @enderror data-testid="create-round-robin-legs">
                        <p class="hf-small hf-muted hf-mb-0">Each pair meets 1–10 times. Even legs swap home and away.</p>
                        @error('roundRobinLegs') <p class="hf-field-error" id="roundRobinLegs-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="hf-field">
                        <label class="hf-label" for="createPlayoffTeams">Playoff teams</label>
                        <select class="hf-select" id="createPlayoffTeams" wire:model="playoffTeams" required
                                @error('playoffTeams') aria-invalid="true" aria-describedby="playoffTeams-error" @enderror data-testid="create-playoff-teams">
                            <option value="0">No playoffs</option>
                            @foreach ([2, 4, 8, 16] as $playoffOption)
                                <option value="{{ $playoffOption }}">Top {{ $playoffOption }}</option>
                            @endforeach
                        </select>
                        @error('playoffTeams') <p class="hf-field-error" id="playoffTeams-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="hf-field">
                    <label class="hf-label" for="createBestOfQuarterfinal">Quarterfinal series</label>
                    <select class="hf-select" id="createBestOfQuarterfinal" wire:model="bestOfQuarterfinal" required
                            @error('bestOfQuarterfinal') aria-invalid="true" aria-describedby="bestOfQuarterfinal-error" @enderror data-testid="create-best-of-quarterfinal">
                        @foreach ([1, 3, 5, 7, 9] as $bestOfOption)
                            <option value="{{ $bestOfOption }}">Best of {{ $bestOfOption }}</option>
                        @endforeach
                    </select>
                    @error('bestOfQuarterfinal') <p class="hf-field-error" id="bestOfQuarterfinal-error">{{ $message }}</p> @enderror
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="createBestOfSemifinal">Semifinal series</label>
                    <select class="hf-select" id="createBestOfSemifinal" wire:model="bestOfSemifinal" required
                            @error('bestOfSemifinal') aria-invalid="true" aria-describedby="bestOfSemifinal-error" @enderror data-testid="create-best-of-semifinal">
                        @foreach ([1, 3, 5, 7, 9] as $bestOfOption)
                            <option value="{{ $bestOfOption }}">Best of {{ $bestOfOption }}</option>
                        @endforeach
                    </select>
                    @error('bestOfSemifinal') <p class="hf-field-error" id="bestOfSemifinal-error">{{ $message }}</p> @enderror
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="createBestOfFinal">Final series</label>
                    <select class="hf-select" id="createBestOfFinal" wire:model.live="bestOfFinal" required
                            @error('bestOfFinal') aria-invalid="true" aria-describedby="bestOfFinal-error" @enderror data-testid="create-best-of-final">
                        @foreach ([1, 3, 5, 7, 9] as $bestOfOption)
                            <option value="{{ $bestOfOption }}">Best of {{ $bestOfOption }}</option>
                        @endforeach
                    </select>
                    @if ($format === 'double-elimination')
                        <p class="hf-small hf-muted hf-mb-0" data-testid="create-best-of-final-reset-note">
                            @if ((int) $bestOfFinal > 1)
                                Modified double elimination: the best-of-{{ $bestOfFinal }} final decides the title. No reset game is played.
                            @else
                                True double elimination: if the losers-bracket team wins the grand final, a reset game decides the title.
                            @endif
                        </p>
                    @endif
                    @error('bestOfFinal') <p class="hf-field-error" id="bestOfFinal-error">{{ $message }}</p> @enderror
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="createFee">Entry fee (PHP)</label>
                    <input class="hf-input" id="createFee" type="number" min="0" step="100" wire:model="fee" required
                           @error('fee') aria-invalid="true" aria-describedby="fee-error" @enderror data-testid="create-fee">
                    @error('fee') <p class="hf-field-error" id="fee-error">{{ $message }}</p> @enderror
                </div>

                <div class="hf-field">
                    <label class="hf-label" for="createPaymentMethod">Payment method</label>
                    <select class="hf-select" id="createPaymentMethod" wire:model="paymentMethod" required
                            @error('paymentMethod') aria-invalid="true" aria-describedby="paymentMethod-error" @enderror data-testid="create-payment-method">
                        <option value="GCash">GCash</option>
                        <option value="Maya">Maya</option>
                        <option value="Bank transfer">Bank transfer</option>
                        <option value="Cash">Cash</option>
                    </select>
                    @error('paymentMethod') <p class="hf-field-error" id="paymentMethod-error">{{ $message }}</p> @enderror
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="createPaymentAccount">Payment account</label>
                    <input class="hf-input" id="createPaymentAccount" type="text" wire:model="paymentAccount" placeholder="09489666136" required
                           @error('paymentAccount') aria-invalid="true" aria-describedby="paymentAccount-error" @enderror data-testid="create-payment-account">
                    @error('paymentAccount') <p class="hf-field-error" id="paymentAccount-error">{{ $message }}</p> @enderror
                </div>

                <div class="hf-field hf-field--full">
                    <span class="hf-label">Courts</span>
                    <div>
                        @foreach ($courts as $index => $court)
                            <div class="hf-flex hf-items-center hf-gap-2 hf-mt-1" wire:key="court-row-{{ $index }}" data-court-row>
                                <input class="hf-input" id="createCourt{{ $index }}" type="text" wire:model="courts.{{ $index }}" value="{{ $court }}" placeholder="Court name"
                                       aria-label="Court {{ $index + 1 }} name" @if ($index === 0) required @endif
                                       data-testid="create-court-{{ $index }}">
                                <button type="button" class="hf-icon-btn" wire:click="removeCourt({{ $index }})"
                                        aria-label="Remove court {{ $index + 1 }}" data-testid="create-remove-court-{{ $index }}">
                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                </button>
                            </div>
                            @error('courts.'.$index) <p class="hf-field-error" id="courts-{{$index}}-error">{{ $message }}</p> @enderror
                        @endforeach
                    </div>
                    <button type="button" class="hf-btn hf-btn--sm hf-mt-2" wire:click="addCourt" data-testid="create-add-court">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i> Add court
                    </button>
                    @error('courts') <p class="hf-field-error" id="courts-error">{{ $message }}</p> @enderror
                </div>

                <div class="hf-field">
                    <label class="hf-label" for="createSlotMinutes">Slot length (minutes)</label>
                    <input class="hf-input" id="createSlotMinutes" type="number" min="10" step="5" wire:model="slotMinutes" required
                           @error('slotMinutes') aria-invalid="true" aria-describedby="slotMinutes-error" @enderror data-testid="create-slot-minutes">
                    @error('slotMinutes') <p class="hf-field-error" id="slotMinutes-error">{{ $message }}</p> @enderror
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="createRestMinutes">Rest between games (minutes)</label>
                    <input class="hf-input" id="createRestMinutes" type="number" min="0" step="5" wire:model="restMinutes" required
                           @error('restMinutes') aria-invalid="true" aria-describedby="restMinutes-error" @enderror data-testid="create-rest-minutes">
                    @error('restMinutes') <p class="hf-field-error" id="restMinutes-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section data-step-panel="2" @if ($step !== 2) hidden @endif>
            @php
                $filledCourts = collect($courts)->map(fn ($court) => trim((string) $court))->filter()->values();
                $firstRound = (int) ($bracketSlots / 2);
                $formatLabel = match ($format) {
                    'double-elimination' => (int) $bestOfFinal > 1
                        ? 'Modified double elimination'
                        : 'Double elimination',
                    'round-robin' => 'Round robin',
                    default => 'Single elimination',
                };
                $startLabel = $start_date !== '' ? \Carbon\Carbon::parse($start_date)->format('M d, Y') : 'TBD';
                $endLabel = $end_date !== '' ? \Carbon\Carbon::parse($end_date)->format('M d, Y') : 'TBD';
            @endphp

            <div class="hf-grid hf-grid--3 hf-mb-3">
                <div class="hf-card hf-card--flat">
                    <span class="hf-kpi-label">Competition</span>
                    <strong class="hf-section-title hf-mt-1">{{ $name !== '' ? $name : 'Untitled' }}</strong>
                    <p class="hf-small hf-muted hf-mb-0">{{ $division_name }} · {{ $venue_name }}</p>
                </div>
                <div class="hf-card hf-card--flat">
                    <span class="hf-kpi-label">Window</span>
                    <strong class="hf-section-title hf-mt-1">{{ $startLabel }}</strong>
                    <p class="hf-small hf-muted hf-mb-0">to {{ $endLabel }}</p>
                </div>
                <div class="hf-card hf-card--flat">
                    <span class="hf-kpi-label">Entry</span>
                    <strong class="hf-kpi-value hf-kpi-value--sm">₱{{ number_format((int) $fee) }}</strong>
                    <p class="hf-small hf-muted hf-mb-0">per team</p>
                </div>
            </div>

            <div class="hf-card hf-card--flat">
                <div class="hf-between">
                    <span>
                        <strong>{{ $bracketSlots }}-team {{ $formatLabel }}</strong>
                        <span class="hf-small hf-muted hf-block">
                            {{ $format === 'round-robin' ? 'Full round robin: every team plays every team.' : $firstRound.' first-round games, then quarterfinals, semifinals, and the final.' }}
                        </span>
                    </span>
                    <span class="hf-chip hf-chip--gold">{{ $filledCourts->count() }} courts</span>
                </div>
                <div class="hf-flex hf-wrap hf-gap-2 hf-mt-2 hf-small hf-muted">
                    <span><i class="bi bi-clock" aria-hidden="true"></i> {{ $slotMinutes }}-minute slots</span>
                    <span><i class="bi bi-hourglass-split" aria-hidden="true"></i> {{ $restMinutes }}-minute rest</span>
                    <span><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $filledCourts->isNotEmpty() ? $filledCourts->implode(' · ') : 'No courts yet' }}</span>
                </div>
            </div>

            <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
                Publishing opens registration immediately. You can review teams, collect payments, then generate the bracket and court schedule from the tournament desk.
            </p>
        </section>

            <div class="hf-sticky-actions hf-mt-3">
                <button type="button" class="hf-btn" wire:click="previousStep" data-testid="create-back" @if ($step === 0) hidden @endif wire:loading.attr="disabled">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
                </button>
                <button type="button" class="hf-btn hf-btn--primary" wire:click="nextStep" data-testid="create-next" @if ($step === 2) hidden @endif wire:loading.attr="disabled" wire:loading.class="is-loading">
                    Continue <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </button>
                <button type="submit" class="hf-btn hf-btn--primary" data-testid="create-publish" @if ($step !== 2) hidden @endif wire:loading.attr="disabled" wire:loading.class="is-loading">
                    <span wire:loading.remove wire:target="save">Publish tournament</span>
                    <span wire:loading wire:target="save">Publishing…</span>
                </button>
            </div>
        </form>

        <aside class="hf-wizard__summary" aria-label="Tournament summary">
            <span class="hf-eyebrow">Preview</span>
            <h2 class="hf-section-title hf-mt-1">{{ $name !== '' ? $name : 'Untitled tournament' }}</h2>
            <dl class="hf-summary-list">
                <div><dt>Division</dt><dd>{{ $division_name }}</dd></div>
                <div><dt>Venue</dt><dd>{{ $venue_name !== '' ? $venue_name : 'Venue TBD' }}</dd></div>
                <div><dt>Dates</dt><dd>{{ $start_date }} – {{ $end_date }}</dd></div>
                <div><dt>Format</dt><dd>{{ match ($format) { 'double-elimination' => 'Double elimination', 'round-robin' => 'Round robin', default => 'Single elimination' } }}</dd></div>
                <div><dt>Slots</dt><dd>{{ $bracketSlots }} teams</dd></div>
                <div><dt>Entry</dt><dd>₱{{ number_format((float) $fee) }}</dd></div>
                <div><dt>Courts</dt><dd>{{ count($courts) }}</dd></div>
            </dl>
            <p class="hf-small hf-muted hf-mt-2 hf-mb-0">The summary updates as you move between steps.</p>
        </aside>
    </div>

</div>
