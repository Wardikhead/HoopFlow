<?php

namespace App\Livewire\Organizer;

use App\Models\Announcement;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('New announcement')]
class AnnouncementCreate extends Component
{
    public ?int $tournament_id = null;

    public string $title = '';

    public string $body = '';

    public string $audience = 'all';

    public string $status = 'draft';

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'title' => ['required', 'string', 'min:4', 'max:90'],
            'body' => ['required', 'string', 'min:10', 'max:320'],
            'audience' => ['required', 'string', 'in:all,coaches,organizers,fans,specific'],
            'status' => ['required', 'string', 'in:draft,published'],
        ];
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments()
    {
        return Tournament::query()->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->validate();

        $organization = Organization::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Organization'],
        );

        $user = auth()->user();

        Announcement::create([
            'organization_id' => $organization->id,
            'tournament_id' => $this->tournament_id,
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience,
            'status' => $this->status,
            'published_at' => $this->status === 'published' ? now() : null,
            'created_by' => $user instanceof User ? $user->id : null,
        ]);

        $this->redirectRoute('announcements');
    }

    public function render(): View
    {
        return view('livewire.organizer.announcement-create');
    }
}
