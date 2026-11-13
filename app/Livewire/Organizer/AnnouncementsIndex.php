<?php

namespace App\Livewire\Organizer;

use App\Models\Announcement;
use App\Models\Tournament;
use App\Models\User;
use App\Services\AnnouncementService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Announcement> $announcements
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read array<string, string> $categories
 * @property-read array{visible: int, pinned: int, tournaments: int, categories: int} $kpis
 * @property-read Announcement|null $viewingAnnouncement
 */
#[Layout('layouts.public')]
#[Title('Announcements')]
class AnnouncementsIndex extends Component
{
    public string $tournament = 'all';

    public string $category = 'all';

    public string $search = '';

    public ?int $viewingId = null;

    public bool $confirmingDelete = false;

    public function mount(): void
    {
        $requested = request()->query('id') ?? request()->query('tournament');

        if (is_numeric($requested) && Tournament::query()->whereKey((int) $requested)->exists()) {
            $this->tournament = (string) (int) $requested;
        }

        $category = request()->query('category');

        if (is_string($category) && array_key_exists($category, Announcement::CATEGORIES)) {
            $this->category = $category;
        }

        $this->search = (string) (request()->query('q') ?? '');
    }

    public function clearFilters(): void
    {
        $this->tournament = 'all';
        $this->category = 'all';
        $this->search = '';
    }

    public function openAnnouncement(int $announcementId): void
    {
        if (! Announcement::query()->whereKey($announcementId)->exists()) {
            return;
        }

        $this->viewingId = $announcementId;
        $this->confirmingDelete = false;
    }

    public function closeAnnouncement(): void
    {
        $this->viewingId = null;
        $this->confirmingDelete = false;
    }

    public function togglePin(): void
    {
        $this->ensureOrganizer();

        $announcement = $this->viewingAnnouncement;

        if (! $announcement instanceof Announcement) {
            return;
        }

        $pinned = ! $announcement->pinned;

        app(AnnouncementService::class)->setPinned($announcement, $pinned, $this->actor());

        $this->closeAnnouncement();
        $this->dispatch('hf-toast', message: $pinned ? 'Pinned.' : 'Unpinned.', tone: 'success');
    }

    public function confirmDelete(): void
    {
        if (! $this->viewingAnnouncement instanceof Announcement) {
            return;
        }

        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteAnnouncement(): void
    {
        $this->ensureOrganizer();

        $announcement = $this->viewingAnnouncement;

        if (! $announcement instanceof Announcement) {
            return;
        }

        app(AnnouncementService::class)->delete($announcement, $this->actor());

        $this->closeAnnouncement();
        $this->dispatch('hf-toast', message: 'Announcement deleted.', tone: 'success');
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::query()->orderBy('name')->get();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function categories(): array
    {
        return ['all' => 'All'] + Announcement::CATEGORIES;
    }

    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function announcements(): Collection
    {
        $query = Announcement::query()
            ->with(['tournament', 'createdBy'])
            ->where('status', 'published');

        if ($this->tournament !== 'all' && is_numeric($this->tournament)) {
            $query->where('tournament_id', (int) $this->tournament);
        }

        $announcements = $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        $user = auth()->user();
        $userId = $user instanceof User ? (int) $user->id : null;
        $role = $user instanceof User
            ? ($user->hasRole('organizer') ? 'organizer' : ($user->hasRole('coach') ? 'coach' : 'fan'))
            : 'fan';

        $allowedAudiences = match ($role) {
            'organizer' => ['all', 'coaches', 'organizers', 'fans'],
            'coach' => ['all', 'coaches', 'fans'],
            default => ['all', 'fans'],
        };

        $term = mb_strtolower(trim($this->search));

        return $announcements
            ->filter(function (Announcement $announcement) use ($allowedAudiences, $userId): bool {
                if ($announcement->audience === 'specific') {
                    $targets = is_array($announcement->target_user_ids) ? $announcement->target_user_ids : [];

                    return $userId !== null && in_array($userId, array_map('intval', $targets), true);
                }

                return in_array($announcement->audience, $allowedAudiences, true);
            })
            ->filter(fn (Announcement $announcement): bool => $this->category === 'all' || ($announcement->category ?: 'general') === $this->category)
            ->filter(function (Announcement $announcement) use ($term): bool {
                if ($term === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', [
                    (string) $announcement->title,
                    (string) $announcement->body,
                    (string) ($announcement->createdBy->name ?? ''),
                ]));

                return str_contains($haystack, $term);
            })
            ->values();
    }

    /**
     * @return array{visible: int, pinned: int, tournaments: int, categories: int}
     */
    #[Computed]
    public function kpis(): array
    {
        $items = $this->announcements;

        return [
            'visible' => $items->count(),
            'pinned' => $items->filter(fn (Announcement $announcement): bool => $announcement->pinned)->count(),
            'tournaments' => $items->pluck('tournament_id')->filter()->unique()->count(),
            'categories' => $items->pluck('category')->map(fn (?string $category): string => $category ?: 'general')->unique()->count(),
        ];
    }

    #[Computed]
    public function viewingAnnouncement(): ?Announcement
    {
        if ($this->viewingId === null) {
            return null;
        }

        return Announcement::query()
            ->with(['tournament', 'createdBy'])
            ->find($this->viewingId);
    }

    protected function ensureOrganizer(): void
    {
        abort_unless(auth()->user()?->hasRole('organizer') === true, 403);
    }

    protected function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public function render(): View
    {
        return view('livewire.organizer.announcements-index');
    }
}
