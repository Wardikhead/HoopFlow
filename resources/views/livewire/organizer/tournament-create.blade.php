<div>
    @section('title', 'Create tournament')

    <div class="hf-page-header hf-mb-3">
        <span class="hf-eyebrow">Organizer desk · Setup</span>
        <h1 class="hf-page-title">Create a tournament</h1>
        <p class="hf-muted">Name the competition, choose the court plan, then publish a registration page.</p>
        <div class="hf-flex hf-gap-2 hf-mt-2">
            <a class="hf-btn" href="{{ route('tournaments') }}" data-testid="create-cancel"><i class="bi bi-arrow-left"></i> Tournaments</a>
        </div>
    </div>

    <form wire:submit="save" class="hf-card" data-testid="create-form">
        <div class="hf-form-grid">
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="createName">Tournament name</label>
                <input class="hf-input" id="createName" type="text" wire:model="name" data-testid="create-name">
                @error('name') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="createSlug">Slug</label>
                <input class="hf-input" id="createSlug" type="text" wire:model="slug" data-testid="create-slug">
                @error('slug') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="createStatus">Status</label>
                <select class="hf-select" id="createStatus" wire:model="status" data-testid="create-status">
                    <option value="draft">Draft</option>
                    <option value="registration-open">Registration open</option>
                    <option value="active">Active</option>
                    <option value="upcoming">Upcoming</option>
                </select>
                @error('status') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="createStart">Start date</label>
                <input class="hf-input" id="createStart" type="date" wire:model="start_date" data-testid="create-start-date">
                @error('start_date') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="createEnd">End date</label>
                <input class="hf-input" id="createEnd" type="date" wire:model="end_date" data-testid="create-end-date">
                @error('end_date') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="createDescription">Description</label>
                <textarea class="hf-textarea" id="createDescription" rows="3" wire:model="description" data-testid="create-description"></textarea>
                @error('description') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="createDivision">Division name</label>
                <input class="hf-input" id="createDivision" type="text" wire:model="division_name" data-testid="create-division">
                @error('division_name') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="createAgeMax">Age max</label>
                <input class="hf-input" id="createAgeMax" type="number" wire:model="age_max" data-testid="create-age-max">
                @error('age_max') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="createSlots">Bracket slots</label>
                <input class="hf-input" id="createSlots" type="number" wire:model="bracketSlots" data-testid="create-slots">
                @error('slots') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="createFee">Entry fee (PHP)</label>
                <input class="hf-input" id="createFee" type="number" step="0.01" wire:model="fee" data-testid="create-fee">
                @error('fee') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="createVenue">Venue name</label>
                <input class="hf-input" id="createVenue" type="text" wire:model="venue_name" data-testid="create-venue">
                @error('venue_name') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field hf-field--full">
                <span class="hf-label">Courts</span>
                @foreach ($courts as $index => $court)
                    <div class="hf-flex hf-items-center hf-gap-2 hf-mt-1" wire:key="court-{{ $index }}">
                        <input class="hf-input" type="text" wire:model="courts.{{ $index }}" data-testid="create-court-{{ $index }}">
                        <button type="button" class="hf-icon-btn" wire:click="removeCourt({{ $index }})" data-testid="create-remove-court-{{ $index }}">
                            <i class="bi bi-trash3" aria-hidden="true"></i>
                        </button>
                    </div>
                    @error('courts.' . $index) <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
                @endforeach
                <button type="button" class="hf-btn hf-btn--sm hf-mt-2" wire:click="addCourt" data-testid="create-add-court">
                    <i class="bi bi-plus-lg"></i> Add court
                </button>
                @error('courts') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="hf-btn-row hf-mt-4">
            <button type="submit" class="hf-btn hf-btn--primary" data-testid="create-publish">
                <i class="bi bi-broadcast"></i> Publish tournament
            </button>
        </div>
    </form>
</div>
