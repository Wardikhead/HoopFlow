<?php

/** Livewire component for Announcement create. */

namespace App\Livewire\Organizer;

use App\Exceptions\AnnouncementDraftException;
use App\Livewire\SecureComponent;
use App\Models\Announcement;
use App\Models\Tournament;
use App\Models\User;
use App\Services\AnnouncementDraftService;
use App\Services\AnnouncementService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * @property-read bool $aiDraftAvailable
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read Collection<int, User> $users
 */
#[Layout('layouts.public')]
#[Title('New announcement')]
class AnnouncementCreate extends SecureComponent
{
    /**
     * @return array<int, string>
     */
    protected function allowedRoles(): array
    {
        return ['organizer'];
    }

    public ?int $tournament_id = null;

    public string $title = '';

    public string $body = '';

    public string $category = 'general';

    public string $audience = 'all';

    /** @var array<int, int|string> */
    public array $target_user_ids = [];

    public bool $pinned = false;

    public string $aiNotes = '';

    public ?string $aiDraftError = null;

    /**
     * Mount.
     *
     * @return void Nothing.
     */
    public function mount(): void
    {
        $requested = request()->query('id') ?? request()->query('tournament');

        if (is_numeric($requested)) {
            $tournament = Tournament::query()->whereNull('archived_at')->find((int) $requested);

            if ($tournament instanceof Tournament) {
                $this->tournament_id = (int) $tournament->id;
            }
        }

        if ($this->tournament_id === null) {
            $default = Tournament::query()->whereNull('archived_at')->where('status', 'active')->orderBy('id')->first()
                ?? Tournament::query()->whereNull('archived_at')->orderBy('id')->first();

            $this->tournament_id = $default instanceof Tournament ? (int) $default->id : null;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'tournament_id' => ['required', 'integer', Rule::exists('tournaments', 'id')],
            'title' => ['required', 'string', 'min:4', 'max:90'],
            'body' => ['required', 'string', 'min:10', 'max:320'],
            'category' => ['required', 'string', Rule::in(array_keys(Announcement::CATEGORIES))],
            'audience' => ['required', 'string', Rule::in(array_keys(Announcement::AUDIENCES))],
            'target_user_ids' => ['array', Rule::requiredIf($this->audience === 'specific')],
            'target_user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'pinned' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'tournament_id.required' => 'Choose a tournament.',
            'tournament_id.exists' => 'Choose a valid tournament.',
            'title.required' => 'Use at least 4 characters.',
            'title.min' => 'Use at least 4 characters.',
            'title.max' => 'Keep the headline under 90 characters.',
            'body.required' => 'Write at least 10 characters.',
            'body.min' => 'Write at least 10 characters.',
            'body.max' => 'Keep the message under 320 characters.',
            'category.required' => 'Choose a category.',
            'audience.required' => 'Choose an audience.',
            'target_user_ids.required_if' => 'Select at least one target user.',
            'target_user_ids.*.exists' => 'One of the selected users is no longer available.',
        ];
    }

    /**
     * Ai draft available.
     *
     * @return bool The result.
     */
    #[Computed]
    public function aiDraftAvailable(): bool
    {
        return $this->hasGeminiKey();
    }

    /**
     * Draft with ai.
     *
     * @return void Nothing.
     */
    public function draftWithAi(): void
    {
        if (! $this->hasGeminiKey()) {
            return;
        }

        $this->aiDraftError = null;
        $this->resetErrorBag('aiNotes');

        $validated = $this->validate([
            'aiNotes' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $allowed = RateLimiter::attempt(
            'announcement-draft:'.$user->id,
            5,
            function () use ($validated): bool {
                $tournament = null;

                if ($this->tournament_id !== null) {
                    $candidate = Tournament::query()
                        ->whereNull('archived_at')
                        ->find($this->tournament_id);

                    if ($candidate instanceof Tournament) {
                        $tournament = $candidate;
                    }
                }

                try {
                    $draft = app(AnnouncementDraftService::class)->draft(
                        (string) $validated['aiNotes'],
                        $tournament,
                        $this->category,
                    );
                } catch (AnnouncementDraftException $exception) {
                    $this->aiDraftError = $this->draftErrorMessage($exception);

                    return true;
                }

                $this->title = $draft['title'];
                $this->body = $draft['body'];

                return true;
            },
            60,
        );

        if ($allowed === false) {
            $this->aiDraftError = "You've drafted a few times already. Wait a minute.";
        }
    }

    /**
     * Draft error message.
     *
     * @param  AnnouncementDraftException  $exception  The exception.
     * @return string The result.
     */
    private function draftErrorMessage(AnnouncementDraftException $exception): string
    {
        return $exception->reason === 'busy'
            ? 'AI drafting is busy. Try again in a minute.'
            : "Couldn't draft right now. Your notes are safe — try again or write it manually.";
    }

    /**
     * Has gemini key.
     *
     * @return bool The result.
     */
    private function hasGeminiKey(): bool
    {
        $key = config('services.gemini.key');

        return is_string($key) && trim($key) !== '';
    }

    /**
     * Save.
     *
     * @return void Nothing.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $user = auth()->user();
        $actor = $user instanceof User ? $user : null;

        $targetUserIds = $validated['audience'] === 'specific'
            ? array_values(array_map('intval', $validated['target_user_ids']))
            : [];

        $announcement = app(AnnouncementService::class)->publish([
            'tournament_id' => (int) $validated['tournament_id'],
            'title' => trim((string) $validated['title']),
            'body' => trim((string) $validated['body']),
            'category' => (string) $validated['category'],
            'audience' => (string) $validated['audience'],
            'target_user_ids' => $targetUserIds,
            'pinned' => (bool) $validated['pinned'],
        ], $actor);

        session()->flash('announcement_toast', [
            'title' => 'Announcement published',
            'message' => 'The bulletin is live for its audience.',
            'tone' => 'success',
        ]);

        $this->redirect(route('announcements', ['id' => $announcement->tournament_id]), navigate: true);
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::query()
            ->whereNull('archived_at')
            ->with('divisions')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::query()
            ->with('roles')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Render.
     *
     * @return View The result.
     */
    public function render(): View
    {
        return view('livewire.organizer.announcement-create');
    }
}
