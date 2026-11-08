<?php

namespace App\Livewire\Organizer;

use App\Models\MatchModel;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Matches')]
class MatchesIndex extends Component
{
    public ?int $tournament = null;

    public ?int $round = null;

    public function clearFilters(): void
    {
        $this->tournament = null;
        $this->round = null;
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
     * @return array<int, int>
     */
    #[Computed]
    public function rounds(): array
    {
        $query = MatchModel::query();

        if ($this->tournament !== null) {
            $query->where('tournament_id', $this->tournament);
        }

        return $query
            ->select('round_number')
            ->distinct()
            ->orderByDesc('round_number')
            ->pluck('round_number')
            ->map(fn ($round) => (int) $round)
            ->all();
    }

    /**
     * @return Collection<int, MatchModel>
     */
    #[Computed]
    public function matches()
    {
        $query = MatchModel::query()->with([
            'homeTeam',
            'awayTeam',
            'winnerTeam',
            'tournament',
            'matchSlots.court',
        ]);

        if ($this->tournament !== null) {
            $query->where('tournament_id', $this->tournament);
        }

        if ($this->round !== null) {
            $query->where('round_number', $this->round);
        }

        return $query
            ->orderByDesc('round_number')
            ->orderBy('match_number')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.organizer.matches-index');
    }
}
