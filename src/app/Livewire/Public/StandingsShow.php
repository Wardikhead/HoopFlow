<?php

namespace App\Livewire\Public;

use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\StandingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Tournament|null $tournament
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read SupportCollection<int, array{
 *     team_id: int,
 *     team_name: string|null,
 *     team: Team|null,
 *     played: int,
 *     wins: int,
 *     losses: int,
 *     points: int,
 *     points_for: int,
 *     points_against: int,
 *     point_diff: int,
 *     seed: int|null,
 *     rank: int,
 *     qualified: bool
 * }> $allRows
 * @property-read SupportCollection<int, array{
 *     team_id: int,
 *     team_name: string|null,
 *     team: Team|null,
 *     played: int,
 *     wins: int,
 *     losses: int,
 *     points: int,
 *     points_for: int,
 *     points_against: int,
 *     point_diff: int,
 *     seed: int|null,
 *     rank: int,
 *     qualified: bool
 * }> $rows
 * @property-read int $cut
 * @property-read array{leader_short: string, leader_hint: string, cut: int, qualified: int, best_diff: int, best_diff_short: string, results: int} $kpis
 */
#[Layout('layouts.public')]
#[Title('Standings')]
class StandingsShow extends Component
{
    public int $tournamentId = 0;

    public string $search = '';

    public string $filter = 'all';

    public function mount(int|string|null $id = null): void
    {
        $requested = $id ?? request()->query('id') ?? request()->query('tournament');

        if (is_numeric($requested) && (int) $requested > 0) {
            $this->tournamentId = (int) $requested;
        } else {
            $first = Tournament::query()->whereNull('archived_at')->where('status', 'active')->orderBy('id')->first()
                ?? Tournament::query()->whereNull('archived_at')->orderBy('id')->first();

            $this->tournamentId = $first instanceof Tournament ? (int) $first->id : 0;
        }

        $filter = request()->query('filter');
        $this->filter = is_string($filter) && in_array($filter, ['all', 'qualified', 'wins', 'winless'], true)
            ? $filter
            : 'all';

        $this->search = (string) (request()->query('q') ?? '');
    }

    public function changeTournament(int|string $tournamentId): void
    {
        if (! is_numeric($tournamentId) || (int) $tournamentId <= 0) {
            return;
        }

        $this->redirect(route('standings', ['id' => (int) $tournamentId]), navigate: true);
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        return Tournament::query()
            ->with('divisions')
            ->find($this->tournamentId);
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::query()->whereNull('archived_at')->orderBy('name')->get();
    }

    /**
     * @return SupportCollection<int, array{
     *     team_id: int,
     *     team_name: string|null,
     *     team: Team|null,
     *     played: int,
     *     wins: int,
     *     losses: int,
     *     points: int,
     *     points_for: int,
     *     points_against: int,
     *     point_diff: int,
     *     seed: int|null,
     *     rank: int,
     *     qualified: bool
     * }>
     */
    #[Computed]
    public function allRows(): SupportCollection
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return new SupportCollection;
        }

        return app(StandingsService::class)->compute($tournament);
    }

    /**
     * @return SupportCollection<int, array{
     *     team_id: int,
     *     team_name: string|null,
     *     team: Team|null,
     *     played: int,
     *     wins: int,
     *     losses: int,
     *     points: int,
     *     points_for: int,
     *     points_against: int,
     *     point_diff: int,
     *     seed: int|null,
     *     rank: int,
     *     qualified: bool
     * }>
     */
    #[Computed]
    public function rows(): SupportCollection
    {
        $term = mb_strtolower(trim($this->search));

        return $this->allRows
            ->filter(function (array $row) use ($term): bool {
                if ($term !== '') {
                    $haystack = mb_strtolower(implode(' ', [
                        (string) ($row['team']->name ?? $row['team_name'] ?? ''),
                        (string) ($row['team']->short_name ?? ''),
                        (string) ($row['team']->city ?? ''),
                    ]));

                    if (! str_contains($haystack, $term)) {
                        return false;
                    }
                }

                return match ($this->filter) {
                    'qualified' => $row['qualified'],
                    'wins' => $row['wins'] > 0,
                    'winless' => $row['wins'] === 0,
                    default => true,
                };
            })
            ->values();
    }

    #[Computed]
    public function cut(): int
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return 0;
        }

        return app(StandingsService::class)->playoffCut($tournament);
    }

    /**
     * @return array{leader_short: string, leader_hint: string, cut: int, qualified: int, best_diff: int, best_diff_short: string, results: int}
     */
    #[Computed]
    public function kpis(): array
    {
        $rows = $this->allRows;
        $leader = $rows->first();
        $bestDiff = $rows->reduce(function (?array $best, array $row): array {
            return $best === null || $row['point_diff'] > $best['point_diff'] ? $row : $best;
        }, null);

        return [
            'leader_short' => ($leader['team'] ?? null)?->shortLabel() ?? 'TBD',
            'leader_hint' => $leader !== null
                ? $leader['wins'].'-'.$leader['losses'].' · '.($leader['team_name'] ?? 'No results yet')
                : 'No results yet',
            'cut' => $this->cut,
            'qualified' => $rows->filter(fn (array $row): bool => $row['qualified'])->count(),
            'best_diff' => $bestDiff['point_diff'] ?? 0,
            'best_diff_short' => ($bestDiff['team'] ?? null)?->shortLabel() ?? 'No games recorded',
            'results' => MatchModel::query()
                ->where('tournament_id', $this->tournamentId)
                ->whereIn('status', ['final', 'forfeit'])
                ->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.public.standings-show');
    }
}
