<div>
    @section('title', 'Register team')

    <div class="hf-page-header hf-mb-3">
        <span class="hf-eyebrow">Coach desk · Registration</span>
        <h1 class="hf-page-title">Register a team</h1>
        <p class="hf-muted">Create your team, roster, and payment reference.</p>
    </div>

    <form wire:submit="save" class="hf-card" data-testid="register-form">
        <div class="hf-form-grid">
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="trTournament">Tournament</label>
                <select class="hf-select" id="trTournament" wire:model="tournament_id" data-testid="register-tournament">
                    <option value="">Select tournament</option>
                    @foreach ($this->tournaments as $tournamentOption)
                        <option value="{{ $tournamentOption->id }}">{{ $tournamentOption->name }}</option>
                    @endforeach
                </select>
                @error('tournament_id') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="trName">Team name</label>
                <input class="hf-input" id="trName" type="text" wire:model="name" data-testid="register-team-name">
                @error('name') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="trShort">Short name</label>
                <input class="hf-input" id="trShort" type="text" wire:model="shortName" maxlength="3" data-testid="register-short-name">
                @error('shortName') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="trCity">City</label>
                <input class="hf-input" id="trCity" type="text" wire:model="city" data-testid="register-city">
                @error('city') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="trCoach">Coach</label>
                <input class="hf-input" id="trCoach" type="text" wire:model="coach" data-testid="register-coach">
                @error('coach') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="trColor">Team color</label>
                <input class="hf-input" id="trColor" type="text" wire:model="avatarColor" data-testid="register-color">
                @error('avatarColor') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="hf-mt-3">
            <div class="hf-between hf-mb-2">
                <h2 class="hf-section-title">Roster</h2>
                <button type="button" class="hf-btn hf-btn--sm" wire:click="addPlayer" data-testid="register-add-player">
                    <i class="bi bi-plus-lg"></i> Add player
                </button>
            </div>
            @error('players') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            @foreach ($players as $index => $player)
                <div class="hf-card hf-card--flat hf-mb-2" wire:key="player-{{ $index }}" data-testid="register-player-{{ $index }}">
                    <div class="hf-form-grid">
                        <div class="hf-field hf-field--full">
                            <label class="hf-label">Player name</label>
                            <input class="hf-input" type="text" wire:model="players.{{ $index }}.name" data-testid="register-player-name-{{ $index }}">
                            @error('players.'.$index.'.name') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="hf-field">
                            <label class="hf-label">Jersey number</label>
                            <input class="hf-input" type="number" wire:model="players.{{ $index }}.number" data-testid="register-player-number-{{ $index }}">
                            @error('players.'.$index.'.number') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="hf-field">
                            <label class="hf-label">Position</label>
                            <select class="hf-select" wire:model="players.{{ $index }}.position" data-testid="register-player-position-{{ $index }}">
                                <option value="Point Guard (PG)">Point Guard (PG)</option>
                                <option value="Shooting Guard (SG)">Shooting Guard (SG)</option>
                                <option value="Small Forward (SF)">Small Forward (SF)</option>
                                <option value="Power Forward (PF)">Power Forward (PF)</option>
                                <option value="Center (C)">Center (C)</option>
                            </select>
                            @error('players.'.$index.'.position') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="hf-field">
                            <label class="hf-label">Age</label>
                            <input class="hf-input" type="number" wire:model="players.{{ $index }}.age" data-testid="register-player-age-{{ $index }}">
                            @error('players.'.$index.'.age') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="hf-field">
                            <label class="hf-label">Documents</label>
                            <select class="hf-select" wire:model="players.{{ $index }}.documentStatus" data-testid="register-player-document-{{ $index }}">
                                <option value="pending">Pending review</option>
                                <option value="verified">Verified</option>
                            </select>
                            @error('players.'.$index.'.documentStatus') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="hf-field hf-field--full">
                            <label class="hf-check">
                                <input type="checkbox" wire:model="players.{{ $index }}.guardianConsent" data-testid="register-player-consent-{{ $index }}">
                                Guardian consent on file
                            </label>
                            @error('players.'.$index.'.guardianConsent') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="hf-btn-row hf-mt-2">
                        <button type="button" class="hf-btn hf-btn--sm" wire:click="removePlayer({{ $index }})" data-testid="register-remove-player-{{ $index }}">Remove</button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="hf-mt-3">
            <h2 class="hf-section-title">Payment</h2>
            <div class="hf-form-grid">
                <div class="hf-field">
                    <label class="hf-label" for="trMethod">Payment method</label>
                    <select class="hf-select" id="trMethod" wire:model="paymentMethod" data-testid="register-payment-method">
                        <option value="GCash">GCash</option>
                        <option value="Maya">Maya</option>
                        <option value="Bank transfer">Bank transfer</option>
                        <option value="Cash">Cash</option>
                    </select>
                    @error('paymentMethod') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
                </div>
                <div class="hf-field">
                    <label class="hf-label" for="trRef">Reference</label>
                    <input class="hf-input" id="trRef" type="text" wire:model="paymentRef" data-testid="register-payment-ref">
                    @error('paymentRef') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="hf-btn-row hf-mt-4">
            <button type="submit" class="hf-btn hf-btn--primary" data-testid="register-submit">
                <i class="bi bi-send"></i> Submit registration
            </button>
        </div>
    </form>
</div>
