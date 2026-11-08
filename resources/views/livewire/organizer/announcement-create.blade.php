<div>
    @section('title', 'New announcement')

    <div class="hf-page-header hf-mb-3">
        <span class="hf-eyebrow">Matchday desk · Bulletin</span>
        <h1 class="hf-page-title">New announcement</h1>
        <p class="hf-muted">Publish a venue, schedule, or eligibility notice to the right audience.</p>
        <div class="hf-flex hf-gap-2 hf-mt-2">
            <a class="hf-btn" href="{{ route('announcements') }}" data-testid="announcement-composer-back"><i class="bi bi-arrow-left"></i> Announcements</a>
        </div>
    </div>

    <form wire:submit="save" class="hf-card" data-testid="announcement-create-form">
        <div class="hf-form-grid">
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="announcementTournament">Tournament</label>
                <select class="hf-select" id="announcementTournament" wire:model="tournament_id" data-testid="announcement-tournament">
                    <option value="">Choose a tournament</option>
                    @foreach ($this->tournaments as $tournament)
                        <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                    @endforeach
                </select>
                @error('tournament_id') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="announcementTitle">Headline</label>
                <input class="hf-input" id="announcementTitle" type="text" maxlength="90" wire:model="title" data-testid="announcement-title">
                @error('title') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field hf-field--full">
                <label class="hf-label" for="announcementBody">Message</label>
                <textarea class="hf-textarea" id="announcementBody" rows="5" maxlength="320" wire:model="body" data-testid="announcement-body"></textarea>
                @error('body') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="announcementAudience">Audience</label>
                <select class="hf-select" id="announcementAudience" wire:model="audience" data-testid="announcement-audience">
                    <option value="all">Everyone</option>
                    <option value="coaches">Coaches</option>
                    <option value="organizers">Organizers</option>
                    <option value="fans">Fans</option>
                    <option value="specific">Specific users</option>
                </select>
                @error('audience') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="hf-field">
                <label class="hf-label" for="announcementStatus">Status</label>
                <select class="hf-select" id="announcementStatus" wire:model="status" data-testid="announcement-status">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
                @error('status') <p class="hf-small hf-text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="hf-btn-row hf-mt-4">
            <a class="hf-btn" href="{{ route('announcements') }}" data-testid="announcement-cancel">Cancel</a>
            <button type="submit" class="hf-btn hf-btn--primary" data-testid="announcement-publish">
                <i class="bi bi-send-fill"></i> Publish announcement
            </button>
        </div>
    </form>
</div>
