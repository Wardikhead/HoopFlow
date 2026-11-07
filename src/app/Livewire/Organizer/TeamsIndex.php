<?php

namespace App\Livewire\Organizer;

use App\Models\Division;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Teams')]
class TeamsIndex extends Component
{
    public ?int $tournament = null;

    public string $status = '';

    public string $division = '';

    public string $search = '';

    public function clearFilters(): void
    {
        $this->status = '';
        $this->division = '';
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
     * @return array<int, string>
     */
    #[Computed]
    public function divisions(): array
    {
        $query = Division::query();

        if ($this->tournament !== null) {
            $query->where('tournament_id', $this->tournament);
        }

        return $query
            ->pluck('name')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Team>
     */
    #[Computed]
    public function teams()
    {
        $query = Team::query()->with([
            'coachUser',
            'players',
            'registrations.tournament.divisions',
            'registrations.division',
            'registrations.payment',
        ]);

        if ($this->tournament !== null) {
            $query->whereHas('registrations', function ($registrationQuery) {
                $registrationQuery->where('tournament_id', $this->tournament);
            });
        }

        if ($this->status !== '') {
            $query->whereHas('registrations', function ($registrationQuery) {
                if ($this->tournament !== null) {
                    $registrationQuery->where('tournament_id', $this->tournament);
                }
                $registrationQuery->where('status', $this->status);
            });
        }

        if ($this->division !== '') {
            $query->whereHas('registrations', function ($registrationQuery) {
                if ($this->tournament !== null) {
                    $registrationQuery->where('tournament_id', $this->tournament);
                }
                $registrationQuery->whereHas('division', function ($divisionQuery) {
                    $divisionQuery->where('name', $this->division);
                });
            });
        }

        $term = trim($this->search);

        if ($term !== '') {
            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('name', 'like', "%{$term}%")
                    ->orWhereHas('coachUser', function ($userQuery) use ($term) {
                        $userQuery
                            ->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    })
                    ->orWhereHas('registrations.tournament', function ($tournamentQuery) use ($term) {
                        $tournamentQuery->where('name', 'like', "%{$term}%");
                    });
            });
        }

        return $query->get();
    }

    public function render(): View
    {
        return view('livewire.organizer.teams-index');
    }
}
