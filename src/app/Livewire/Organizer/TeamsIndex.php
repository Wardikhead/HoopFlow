<?php

namespace App\Livewire\Organizer;

use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read string $role
 * @property-read Tournament|null $selectedTournament
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read array<int, string> $divisions
 * @property-read Collection<int, Team> $scopedTeams
 * @property-read Collection<int, Team> $teams
 * @property-read array{registered: int, approved: int, pending: int, cities: int} $kpis
 */
#[Layout('layouts.public')]
#[Title('Teams')]
class TeamsIndex extends Component
{
    public ?int $tournament = null;

    public string $status = '';

    public string $division = '';

    public string $search = '';

    public function mount(): void
    {
        $requested = request()->query('tournament');

        if (is_numeric($requested) && Tournament::query()->whereKey((int) $requested)->exists()) {
            $this->tournament = (int) $requested;
        }

        $user = auth()->user();

        if ($this->tournament === null && $user !== null && $user->hasRole('coach')) {
            $tournamentIds = Registration::query()
                ->whereIn('team_id', Team::query()->where('coach_user_id', $user->id)->select('id'))
                ->distinct()
                ->pluck('tournament_id');

            if ($tournamentIds->count() === 1) {
                $this->tournament = (int) $tournamentIds->first();
            }
        }

        $status = request()->query('status');

        if (is_string($status)) {
            $status = $status === 'submitted' ? 'pending' : $status;

            if (in_array($status, ['approved', 'pending', 'rejected'], true)) {
                $this->status = $status;
            }
        }

        $division = request()->query('division');

        if (is_string($division) && $division !== '' && $division !== 'all') {
            $this->division = $division;
        }

        $search = request()->query('q');

        if (is_string($search)) {
            $this->search = $search;
        }
    }

    public function clearFilters(): void
    {
        $this->status = '';
        $this->division = '';
        $this->search = '';
    }

    #[Computed]
    public function role(): string
    {
        $user = auth()->user();

        if ($user !== null && $user->hasRole('organizer')) {
            return 'organizer';
        }

        if ($user !== null && $user->hasRole('coach')) {
            return 'coach';
        }

        return 'fan';
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::query()
            ->with('divisions')
            ->orderBy('start_date')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedTournament(): ?Tournament
    {
        if ($this->tournament === null) {
            return null;
        }

        return Tournament::query()
            ->with('divisions')
            ->find($this->tournament);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function divisions(): array
    {
        if ($this->tournament === null) {
            return [];
        }

        return $this->scopedTeams
            ->map(function (Team $team): string {
                $registration = $this->registrationFor($team);

                return (string) ($registration?->division->name
                    ?? $registration?->tournament->divisions->first()->name
                    ?? '');
            })
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Every team in the selected tournament that the current role may see.
     *
     * @return Collection<int, Team>
     */
    #[Computed]
    public function scopedTeams(): Collection
    {
        if ($this->tournament === null) {
            return new Collection;
        }

        $query = Team::query()
            ->with([
                'coachUser',
                'players',
                'registrations.tournament.divisions',
                'registrations.division',
                'registrations.payment',
            ])
            ->whereHas('registrations', function ($registrationQuery): void {
                $registrationQuery->where('tournament_id', $this->tournament);
            });

        if ($this->role === 'fan') {
            $query->whereHas('registrations', function ($registrationQuery): void {
                $registrationQuery
                    ->where('tournament_id', $this->tournament)
                    ->where('status', 'approved');
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * The scoped teams after the status, division, and search filters.
     *
     * @return Collection<int, Team>
     */
    #[Computed]
    public function teams(): Collection
    {
        $term = mb_strtolower(trim($this->search));

        $teams = $this->scopedTeams
            ->filter(function (Team $team) use ($term): bool {
                $registration = $this->registrationFor($team);

                if ($this->status !== '' && $registration?->status !== $this->registrationStatus($this->status)) {
                    return false;
                }

                if ($this->division !== '') {
                    $divisionName = $registration->division->name
                        ?? $registration?->tournament?->divisions?->first()?->name;

                    if ($divisionName !== $this->division) {
                        return false;
                    }
                }

                if ($term === '') {
                    return true;
                }

                $haystack = implode(' ', array_filter([
                    $team->name,
                    $team->coach_name,
                    $team->coachUser?->name,
                    $team->city,
                    $registration?->tournament?->name,
                ]));

                return str_contains(mb_strtolower($haystack), $term);
            })
            ->sort(function (Team $a, Team $b): int {
                $rank = ['submitted' => 0, 'pending' => 0, 'approved' => 1, 'rejected' => 2];

                $aStatus = $this->registrationFor($a)->status ?? 'approved';
                $bStatus = $this->registrationFor($b)->status ?? 'approved';

                return [$rank[$aStatus] ?? 3, mb_strtolower($a->name)]
                    <=> [$rank[$bStatus] ?? 3, mb_strtolower($b->name)];
            })
            ->values();

        return new Collection($teams->all());
    }

    /**
     * @return array{registered: int, approved: int, pending: int, cities: int}
     */
    #[Computed]
    public function kpis(): array
    {
        $teams = $this->scopedTeams;

        return [
            'registered' => $teams->count(),
            'approved' => $teams->filter(
                fn (Team $team): bool => $this->registrationFor($team)?->status === 'approved'
            )->count(),
            'pending' => $teams->filter(
                fn (Team $team): bool => $this->registrationFor($team)?->status === 'submitted'
            )->count(),
            'cities' => $teams->pluck('city')->filter()->unique()->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.organizer.teams-index');
    }

    protected function registrationFor(Team $team): ?Registration
    {
        $registration = $team->registrations->firstWhere('tournament_id', $this->tournament);

        return $registration instanceof Registration ? $registration : null;
    }

    protected function registrationStatus(string $status): string
    {
        return $status === 'pending' ? 'submitted' : $status;
    }
}
