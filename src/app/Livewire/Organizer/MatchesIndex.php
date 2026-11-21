<?php

namespace App\Livewire\Organizer;

use App\Models\Court;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Tournament;
use App\Support\RescheduledMatches;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Tournament|null $currentTournament
 * @property-read Collection<int, MatchModel> $allMatches
 * @property-read SupportCollection<int, MatchModel> $matches
 * @property-read list<int> $rescheduledMatchIds
 * @property-read SupportCollection<int, Court> $courts
 */
#[Layout('layouts.public')]
#[Title('Matchups')]
class MatchesIndex extends Component
{
    public ?int $tournament = null;

    public string $search = '';

    public string $day = '';

    public string $court = 'all';

    public string $status = 'all';

    public function mount(int|string|null $id = null): void
    {
        $requested = $id ?? request()->query('id') ?? request()->query('tournament');

        if ($requested !== null && $requested !== '') {
            if (is_numeric($requested)) {
                $tournament = Tournament::find((int) $requested);
                $this->tournament = $tournament instanceof Tournament ? (int) $tournament->id : null;
            }
        } else {
            $first = Tournament::query()->orderBy('id')->first();
            $this->tournament = $first instanceof Tournament ? (int) $first->id : null;
        }

        $this->search = (string) (request()->query('q') ?? '');

        $date = request()->query('date');
        $this->day = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';

        $court = request()->query('court');
        $this->court = is_string($court) && $court !== '' ? $court : 'all';

        $status = request()->query('status');
        $this->status = is_string($status) && in_array($status, ['scheduled', 'final', 'forfeit'], true)
            ? $status
            : 'all';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->day = '';
        $this->court = 'all';
        $this->status = 'all';
    }

    #[Computed]
    public function currentTournament(): ?Tournament
    {
        if ($this->tournament === null) {
            return null;
        }

        return Tournament::query()->with('divisions')->find($this->tournament);
    }

    /**
     * @return Collection<int, MatchModel>
     */
    #[Computed]
    public function allMatches(): Collection
    {
        if ($this->tournament === null) {
            return new Collection;
        }

        return MatchModel::query()
            ->where('tournament_id', $this->tournament)
            ->with(['homeTeam', 'awayTeam', 'winnerTeam', 'tournament', 'matchSlots.court'])
            ->get();
    }

    /**
     * @return SupportCollection<int, MatchModel>
     */
    #[Computed]
    public function matches(): SupportCollection
    {
        $term = mb_strtolower(trim($this->search));
        $day = $this->day;
        $court = $this->court;
        $status = $this->status;

        return $this->allMatches
            ->filter(function (MatchModel $match) use ($term, $day, $court, $status): bool {
                $slot = $match->matchSlots->first();
                $slotCourtId = $slot?->court_id;

                if ($court !== 'all' && (string) ($slotCourtId ?? '') !== (string) $court) {
                    return false;
                }

                if ($status !== 'all' && $match->status !== $status) {
                    return false;
                }

                if ($day !== '' && $slot?->start_at?->format('Y-m-d') !== $day) {
                    return false;
                }

                if ($term !== '') {
                    $homeName = $match->homeTeam?->name;
                    $awayName = $match->awayTeam?->name;
                    $haystack = mb_strtolower(
                        ($homeName ?? '').' '.
                        ($awayName ?? '').' '.
                        $match->roundLabel()
                    );

                    if (! str_contains($haystack, $term)) {
                        return false;
                    }
                }

                return true;
            })
            ->sort(function (MatchModel $a, MatchModel $b): int {
                $aStart = $a->matchSlots->first()?->start_at?->getTimestamp() ?? PHP_INT_MAX;
                $bStart = $b->matchSlots->first()?->start_at?->getTimestamp() ?? PHP_INT_MAX;

                return ($aStart <=> $bStart) ?: ((int) $a->match_number <=> (int) $b->match_number);
            })
            ->values();
    }

    /**
     * Match ids with a recorded reschedule event, so matchup cards can flag moved games.
     *
     * @return list<int>
     */
    #[Computed]
    public function rescheduledMatchIds(): array
    {
        return RescheduledMatches::ids($this->allMatches->pluck('id'));
    }

    /**
     * @return SupportCollection<int, Court>
     */
    #[Computed]
    public function courts(): SupportCollection
    {
        if ($this->tournament === null) {
            return collect();
        }

        return MatchSlot::query()
            ->where('tournament_id', $this->tournament)
            ->with('court')
            ->get()
            ->pluck('court')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    public function render(): View
    {
        return view('livewire.organizer.matches-index');
    }
}
