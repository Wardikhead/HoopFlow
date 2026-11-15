<div>
    @section('title', 'New announcement')

    @section('breadcrumbs')
        <x-breadcrumbs :items="[
            ['label' => 'Home', 'href' => route('dashboard')],
            ['label' => 'Announcements', 'href' => route('announcements')],
            ['label' => 'New announcement'],
        ]" />
    @endsection

    @if ($this->tournaments->isEmpty())
        <div class="hf-empty" data-testid="announcement-no-tournaments">
            <i class="bi bi-megaphone" aria-hidden="true"></i>
            <h1>No tournaments yet</h1>
            <p>Create a tournament before publishing a bulletin.</p>
            <a class="hf-btn hf-btn--primary" href="{{ route('tournamentCreate') }}">Create tournament</a>
        </div>
    @else
        @php
            $selectedTournament = $this->tournaments->firstWhere('id', $tournament_id);
            $author = auth()->user()?->name ?? 'Organizer';
        @endphp

        <header class="hf-page-head hf-mb-3">
            <div>
                <span class="hf-eyebrow">Matchday desk · Bulletin</span>
                <h1 class="hf-page-title">New announcement</h1>
                <p class="hf-page-desc">Publish a venue, schedule, or eligibility notice to the right audience.</p>
            </div>
            <div class="hf-page-actions">
                <a class="hf-btn" href="{{ route('announcements') }}" data-testid="announcement-composer-back"><i class="bi bi-arrow-left" aria-hidden="true"></i> Announcements</a>
            </div>
        </header>

        <div class="hf-split">
            <form wire:submit="save" id="announcementForm" novalidate data-testid="announcement-create-form">
                <x-form-errors :targets="[
                    'tournament_id' => 'announcementTournament',
                    'title' => 'announcementTitle',
                    'body' => 'announcementBody',
                    'category' => 'announcementCategory',
                    'audience' => 'announcementAudience',
                    'target_user_ids' => 'announcementTargetUsers',
                ]" />
                <x-required-note class="hf-mb-2" />
                <div class="hf-card hf-mb-3">
                    <div class="hf-form-grid">
                        <div class="hf-field hf-field--full">
                            <label class="hf-label" for="announcementTournament">Tournament</label>
                            <select class="hf-select" id="announcementTournament" wire:model.live="tournament_id" required
                                    @error('tournament_id') aria-invalid="true" aria-describedby="tournament_id-error" @enderror
                                    data-testid="announcement-tournament">
                                @foreach ($this->tournaments as $tournamentOption)
                                    <option value="{{ $tournamentOption->id }}">{{ $tournamentOption->name }}@if ($tournamentOption->divisions->isNotEmpty()) · {{ $tournamentOption->divisions->first()->name }}@endif</option>
                                @endforeach
                            </select>
                            @error('tournament_id')
                                <span class="hf-field-error" id="tournament_id-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="hf-field hf-field--full">
                            <label class="hf-label" for="announcementTitle">Headline</label>
                            <input class="hf-input" id="announcementTitle" type="text" maxlength="90" required
                                   placeholder="Championship week moves to Court 1"
                                   wire:model.live.debounce.150ms="title"
                                   @error('title') aria-invalid="true" aria-describedby="title-error" @enderror
                                   data-testid="announcement-title">
                            <span class="hf-help">Keep it short enough for the top of the feed.</span>
                            @error('title')
                                <span class="hf-field-error" id="title-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="hf-field hf-field--full">
                            <label class="hf-label" for="announcementBody">Message</label>
                            <textarea class="hf-textarea" id="announcementBody" rows="5" maxlength="320" required
                                      placeholder="Gate opens 45 minutes before tip-off. All semifinals and finals are on Court 1."
                                      wire:model.live.debounce.150ms="body"
                                      @error('body') aria-invalid="true" aria-describedby="body-error" @enderror
                                      data-testid="announcement-body"></textarea>
                            <span class="hf-help">Up to 320 characters. Coaches and fans see plain text.</span>
                            @error('body')
                                <span class="hf-field-error" id="body-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="hf-field">
                            <label class="hf-label" for="announcementCategory">Category</label>
                            <select class="hf-select" id="announcementCategory" wire:model.live="category" required
                                    @error('category') aria-invalid="true" aria-describedby="category-error" @enderror
                                    data-testid="announcement-category">
                                @foreach (\App\Models\Announcement::CATEGORIES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('category')
                                <span class="hf-field-error" id="category-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="hf-field">
                            <label class="hf-label" for="announcementAudience">Audience</label>
                            <select class="hf-select" id="announcementAudience" wire:model.live="audience" required
                                    @error('audience') aria-invalid="true" aria-describedby="audience-error" @enderror
                                    data-testid="announcement-audience">
                                @foreach (\App\Models\Announcement::AUDIENCES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('audience')
                                <span class="hf-field-error" id="audience-error">{{ $message }}</span>
                            @enderror
                        </div>

                        @if ($audience === 'specific')
                            <div class="hf-field hf-field--full" id="announcementTargetUsers" role="group" aria-labelledby="announcementTargetUsersLabel" tabindex="-1" @error('target_user_ids') aria-describedby="target_user_ids-error" @enderror data-testid="announcement-target-users">
                                <label class="hf-label" id="announcementTargetUsersLabel">Target users</label>
                                <div class="hf-card hf-card--flat hf-target-list">
                                    @foreach ($this->users as $targetUser)
                                        <label class="hf-flex hf-items-center hf-gap-2 hf-small hf-check-row" wire:key="target-user-{{ $targetUser->id }}">
                                            <input type="checkbox" value="{{ $targetUser->id }}" wire:model.live="target_user_ids" @if ($loop->first) required @endif>
                                            <span>{{ $targetUser->name }} <span class="hf-muted">({{ $targetUser->roles->first()?->key ?? 'member' }})</span></span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('target_user_ids')
                                    <span class="hf-field-error" id="target_user_ids-error">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif

                        <label class="hf-check hf-field--full">
                            <input type="checkbox" id="announcementPinned" wire:model.live="pinned" data-testid="announcement-pinned">
                            <span>
                                <strong>Pin to the top</strong>
                                <span class="hf-help hf-block">Pinned notices lead the feed and the dashboard.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="hf-sticky-actions">
                    <a class="hf-btn" href="{{ route('announcements') }}" data-testid="announcement-cancel">Cancel</a>
                    <button type="submit" class="hf-btn hf-btn--primary" wire:loading.attr="disabled" wire:loading.class="is-loading" wire:target="save" data-testid="announcement-publish">
                        <span wire:loading.remove wire:target="save"><i class="bi bi-send-fill" aria-hidden="true"></i> Publish announcement</span>
                        <span wire:loading wire:target="save">Publishing…</span>
                    </button>
                </div>
            </form>

            <aside class="hf-composer-preview" aria-label="Announcement preview">
                <span class="hf-eyebrow">Live preview</span>
                <article class="hf-card hf-announcement-preview {{ $pinned ? 'hf-announcement--pinned' : '' }}" data-preview data-testid="announcement-preview">
                    <div class="hf-between">
                        <span class="hf-chip hf-chip--slate">{{ \App\Models\Announcement::categoryLabel($category) }}</span>
                        @if ($pinned)
                            <span class="hf-chip hf-chip--gold"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i>Pinned</span>
                        @endif
                    </div>
                    <h2 class="hf-section-title hf-mt-1">{{ trim($title) !== '' ? $title : 'Your headline' }}</h2>
                    <p class="hf-muted hf-small hf-mb-0">{{ trim($body) !== '' ? $body : 'The message coaches and fans will see.' }}</p>
                    <div class="hf-match-head hf-mt-2">
                        <span>{{ $author }}</span>
                        <span>Just now</span>
                    </div>
                    <div class="hf-match-head">
                        <span>Audience: {{ \App\Models\Announcement::audienceLabel($audience) }}</span>
                        <span>{{ $selectedTournament?->name ?? 'No tournament' }}</span>
                    </div>
                </article>
                <div class="hf-card hf-card--flat">
                    <span class="hf-kpi-label">Before publishing</span>
                    <ul class="hf-small hf-muted hf-mt-1 hf-mb-0">
                        <li>Choose the tournament the notice belongs to.</li>
                        <li>Coaches see &ldquo;Coaches&rdquo; notices; fans only see &ldquo;Everyone&rdquo;.</li>
                        <li>Pinned notices lead the feed and the dashboard.</li>
                    </ul>
                </div>
                <p class="hf-small hf-muted">Published announcements appear in the feed and on the tournament desk.</p>
            </aside>
        </div>
    @endif
</div>
