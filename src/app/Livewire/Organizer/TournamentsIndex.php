<?php

namespace App\Livewire\Organizer;

use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Tournaments')]
class TournamentsIndex extends Component
{
    public string $search = '';

    public string $status = '';

    public string $division = '';

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->division = '';
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function divisions(): array
    {
        return Tournament::query()
            ->with('divisions')
            ->get()
            ->flatMap(fn (Tournament $tournament) => $tournament->divisions->pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments()
    {
        $query = Tournament::query()->with(['divisions', 'registrations', 'matches', 'matchSlots.court.venue']);

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->division !== '') {
            $query->whereHas('divisions', fn ($divisionQuery) => $divisionQuery->where('name', $this->division));
        }

        $tournaments = $query->get();
        $term = mb_strtolower(trim($this->search));

        if ($term === '') {
            return $tournaments;
        }

        return $tournaments
            ->filter(function (Tournament $tournament) use ($term): bool {
                $haystack = $tournament->name.' '.$tournament->venueName().' '.$tournament->divisions->pluck('name')->implode(' ');

                return str_contains(mb_strtolower($haystack), $term);
            })
            ->values();
    }

    public function render(): View
    {
        return view('livewire.organizer.tournaments-index');
    }
}
