<?php

namespace App\Livewire\Organizer;

use App\Models\Announcement;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Announcements')]
class AnnouncementsIndex extends Component
{
    public ?int $tournament = null;

    public string $search = '';

    public function clearFilters(): void
    {
        $this->tournament = null;
        $this->search = '';
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments()
    {
        return Tournament::query()->orderBy('name')->get();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function categories(): array
    {
        return [
            ['value' => 'all', 'label' => 'All'],
            ['value' => 'schedule', 'label' => 'Schedule'],
            ['value' => 'venue', 'label' => 'Venue'],
            ['value' => 'eligibility', 'label' => 'Eligibility'],
            ['value' => 'general', 'label' => 'General'],
        ];
    }

    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function announcements()
    {
        $query = Announcement::query()->with(['tournament', 'createdBy']);

        if ($this->tournament !== null) {
            $query->where('tournament_id', $this->tournament);
        }

        $announcements = $query->orderByDesc('published_at')->get();

        $user = auth()->user();
        $role = $user instanceof User
            ? ($user->hasRole('organizer') ? 'organizer' : ($user->hasRole('coach') ? 'coach' : 'fan'))
            : 'fan';

        $allowedAudiences = match ($role) {
            'organizer' => ['all', 'coaches', 'organizers', 'fans'],
            'coach' => ['all', 'coaches', 'fans'],
            default => ['all'],
        };

        $term = trim($this->search);

        return $announcements
            ->filter(fn (Announcement $announcement) => in_array($announcement->audience, $allowedAudiences, true))
            ->filter(function (Announcement $announcement) use ($term) {
                if ($term === '') {
                    return true;
                }

                $haystack = strtolower(implode(' ', [
                    (string) $announcement->title,
                    (string) $announcement->body,
                    (string) ($announcement->createdBy->name ?? ''),
                    (string) ($announcement->tournament->name ?? ''),
                ]));

                return str_contains($haystack, strtolower($term));
            })
            ->values();
    }

    public function render(): View
    {
        return view('livewire.organizer.announcements-index');
    }
}
