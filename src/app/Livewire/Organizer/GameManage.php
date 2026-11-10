<?php

namespace App\Livewire\Organizer;

use App\Models\MatchModel;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\GameStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read MatchModel|null $match
 * @property-read Team|null $homeTeam
 * @property-read Team|null $awayTeam
 * @property-read bool $isLocked
 * @property-read list<string> $quarterLabels
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read Collection<int, MatchModel> $matches
 */
#[Layout('layouts.public')]
#[Title('In-game score & player stats')]
class GameManage extends Component
{
    public ?int $matchId = null;

    public bool $requestedMatchMissing = false;

    public string $tournamentFilter = 'all';

    public string $statusFilter = 'all';

    public string $teamSearch = '';

    /** @var array{team1: list<int>, team2: list<int>} */
    public array $quarters = ['team1' => [0, 0, 0, 0], 'team2' => [0, 0, 0, 0]];

    /** @var array{team1: int, team2: int} */
    public array $finals = ['team1' => 0, 'team2' => 0];

    /** @var array{team1: array<int, array{quarters: list<array<string, int>>}>, team2: array<int, array{quarters: list<array<string, int>>}>} */
    public array $stats = ['team1' => [], 'team2' => []];

    public int $activeQuarter = 0;

    public string $side = 'all';

    public string $search1 = '';

    public string $search2 = '';

    public bool $showConfirm = false;

    public string $confirmTitle = 'Confirm action';

    public string $confirmMessage = '';

    public string $confirmLabel = 'Confirm';

    public string $confirmTone = 'primary';

    public string $confirmAction = '';

    public string $errorMessage = '';

    public function mount(int|string|null $match = null): void
    {
        abort_unless(auth()->user()?->hasRole('organizer') ?? false, 403);

        $candidate = $match ?? request()->query('match');

        if (! is_numeric($candidate)) {
            return;
        }

        $found = MatchModel::find((int) $candidate);

        if (! $found instanceof MatchModel) {
            $this->requestedMatchMissing = true;

            return;
        }

        $this->selectMatch($found);
    }

    #[Computed]
    public function match(): ?MatchModel
    {
        if ($this->matchId === null) {
            return null;
        }

        return MatchModel::query()
            ->with([
                'homeTeam.players',
                'awayTeam.players',
                'tournament',
                'matchSlots.court',
                'playerStats',
                'playerQuarterStats',
            ])
            ->find($this->matchId);
    }

    #[Computed]
    public function homeTeam(): ?Team
    {
        return $this->match?->homeTeam;
    }

    #[Computed]
    public function awayTeam(): ?Team
    {
        return $this->match?->awayTeam;
    }

    #[Computed]
    public function isLocked(): bool
    {
        return in_array($this->match?->status, ['final', 'forfeit'], true);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function quarterLabels(): array
    {
        return array_map(
            fn (int $index): string => $index < 4 ? 'Q'.($index + 1) : 'OT'.($index - 3),
            array_keys($this->quarters['team1']),
        );
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
     * @return Collection<int, MatchModel>
     */
    #[Computed]
    public function matches(): Collection
    {
        $query = MatchModel::query()->with(['homeTeam', 'awayTeam', 'tournament', 'matchSlots.court']);

        if ($this->tournamentFilter !== 'all' && is_numeric($this->tournamentFilter)) {
            $query->where('tournament_id', (int) $this->tournamentFilter);
        }

        if (in_array($this->statusFilter, ['scheduled', 'final', 'forfeit'], true)) {
            $query->where('status', $this->statusFilter);
        }

        $term = trim($this->teamSearch);

        if ($term !== '') {
            $query->where(function ($builder) use ($term): void {
                $builder->whereHas('homeTeam', fn ($team) => $team->where('name', 'like', '%'.$term.'%'))
                    ->orWhereHas('awayTeam', fn ($team) => $team->where('name', 'like', '%'.$term.'%'));
            });
        }

        return $query->get()
            ->sortBy(fn (MatchModel $match): int => $match->matchSlots->first()?->start_at->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * @return array<int, array{id: int, name: string, number: int|string|null, position: string|null, totals: array<string, int>}>
     */
    public function playersFor(string $side): array
    {
        $match = $this->match;
        $team = $side === 'team1' ? $match?->homeTeam : $match?->awayTeam;

        if (! $team instanceof Team) {
            return [];
        }

        $term = mb_strtolower(trim($side === 'team1' ? $this->search1 : $this->search2));

        return $this->sortedPlayers($team)
            ->map(function (Player $player) use ($side): array {
                $quarters = $this->stats[$side][(int) $player->id]['quarters'] ?? [];
                $name = trim(($player->first_name ?? '').' '.($player->last_name ?? ''));

                return [
                    'id' => (int) $player->id,
                    'name' => $name !== '' ? $name : 'Player',
                    'number' => $player->jersey_number,
                    'position' => $player->position,
                    'totals' => $this->sumStatQuarters($quarters),
                ];
            })
            ->filter(fn (array $row): bool => $term === '' || str_contains(mb_strtolower($row['name']), $term))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $quarter
     */
    public function statPoints(array $quarter): int
    {
        return app(GameStatsService::class)->pointsFor($quarter);
    }

    public function setActiveQuarter(int $index): void
    {
        $this->activeQuarter = max(0, min($index, count($this->quarters['team1']) - 1));
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'quarters.')) {
            $parts = explode('.', $name);

            if (count($parts) === 3 && in_array($parts[1], ['team1', 'team2'], true)) {
                $side = $parts[1];
                $index = (int) $parts[2];
                $this->quarters[$side][$index] = $this->clampInt($this->quarters[$side][$index] ?? 0);
                $this->syncFinal($side);
            }

            return;
        }

        if (preg_match('/^stats\.(team1|team2)\.(\d+)\.quarters\.(\d+)\.([a-z]+)$/', $name, $match) !== 1) {
            return;
        }

        $side = $match[1];
        $playerId = (int) $match[2];
        $quarter = (int) $match[3];
        $field = $match[4];

        if (! array_key_exists($field, GameStatsService::FIELDS)) {
            return;
        }

        $value = $this->stats[$side][$playerId]['quarters'][$quarter][$field] ?? 0;
        $this->stats[$side][$playerId]['quarters'][$quarter][$field] = $this->clampInt($value);

        if ($quarter === $this->activeQuarter) {
            $this->syncTeamQuarter($side);
        }
    }

    public function addOvertime(): void
    {
        if ($this->editableMatch() === null) {
            return;
        }

        if (count($this->quarters['team1']) >= GameStatsService::MAX_QUARTERS) {
            $this->toast('A game can have at most 9 quarters.', 'Overtime limit', 'warning');

            return;
        }

        foreach (['team1', 'team2'] as $side) {
            $this->quarters[$side][] = 0;
            $this->finals[$side] = array_sum($this->quarters[$side]);

            foreach (array_keys($this->stats[$side]) as $playerId) {
                $this->stats[$side][$playerId]['quarters'][] = $this->emptyQuarter();
            }
        }

        $this->activeQuarter = count($this->quarters['team1']) - 1;
    }

    public function removeOvertime(): void
    {
        if ($this->editableMatch() === null) {
            return;
        }

        $count = count($this->quarters['team1']);

        if ($count <= GameStatsService::MIN_QUARTERS) {
            $this->toast('Regulation has four quarters. There is no overtime to remove.', 'Nothing to remove', 'warning');

            return;
        }

        $last = $count - 1;
        $dirty = ($this->quarters['team1'][$last] ?? 0) !== 0 || ($this->quarters['team2'][$last] ?? 0) !== 0;

        foreach (['team1', 'team2'] as $side) {
            foreach ($this->stats[$side] as $entry) {
                foreach (($entry['quarters'][$last] ?? []) as $value) {
                    if ((int) $value !== 0) {
                        $dirty = true;
                    }
                }
            }
        }

        if ($dirty) {
            $this->openConfirm(
                'Remove overtime?',
                'The last overtime period has scores or player stats. Removing it deletes that data.',
                'Remove OT',
                'danger',
                'doRemoveOvertime',
            );

            return;
        }

        $this->doRemoveOvertime();
    }

    public function doRemoveOvertime(): void
    {
        $this->showConfirm = false;

        if (count($this->quarters['team1']) <= GameStatsService::MIN_QUARTERS) {
            return;
        }

        foreach (['team1', 'team2'] as $side) {
            array_pop($this->quarters[$side]);
            $this->finals[$side] = array_sum($this->quarters[$side]);

            foreach (array_keys($this->stats[$side]) as $playerId) {
                array_pop($this->stats[$side][$playerId]['quarters']);
            }
        }

        $this->activeQuarter = min($this->activeQuarter, count($this->quarters['team1']) - 1);
    }

    public function setSide(string $side): void
    {
        if (in_array($side, ['all', 'team1', 'team2'], true)) {
            $this->side = $side;
        }
    }

    public function clearFilters(): void
    {
        $this->tournamentFilter = 'all';
        $this->statusFilter = 'all';
        $this->teamSearch = '';
    }

    public function saveDraft(): void
    {
        $match = $this->editableMatch();

        if ($match === null) {
            return;
        }

        try {
            app(GameStatsService::class)->saveDraft($match, $this->payload());
        } catch (\InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());

            return;
        }

        $this->errorMessage = '';
        $this->toast('Draft saved. The game stays scheduled until you publish.', 'Saved');
    }

    public function requestPublish(): void
    {
        if ($this->editableMatch() === null) {
            return;
        }

        $sum1 = array_sum($this->quarters['team1']);
        $sum2 = array_sum($this->quarters['team2']);

        if ((int) $this->finals['team1'] !== $sum1 || (int) $this->finals['team2'] !== $sum2) {
            $this->fail('Final score must equal the sum of the quarters.');

            return;
        }

        if ($sum1 === $sum2) {
            $this->fail('Elimination games cannot end in a tie.');

            return;
        }

        $this->errorMessage = '';
        $this->openConfirm(
            'Publish final result?',
            'Publish '.$sum1.'–'.$sum2.'? The winner advances, standings update, and the game locks. Corrections will require a reason.',
            'Publish result',
            'primary',
            'confirmPublish',
        );
    }

    public function confirmPublish(): void
    {
        $this->showConfirm = false;
        $match = $this->editableMatch();

        if ($match === null) {
            return;
        }

        try {
            app(GameStatsService::class)->publish($match, $this->payload());
        } catch (\InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());

            return;
        }

        $this->loadEditor($match->refresh());
        $this->errorMessage = '';
        $this->toast('Result published. The winner advances to the next round.', 'Result published');
    }

    public function closeConfirm(): void
    {
        $this->showConfirm = false;
    }

    public function render(): View
    {
        return view('livewire.organizer.game-manage');
    }

    protected function selectMatch(MatchModel $match): void
    {
        $this->matchId = (int) $match->id;
        $this->requestedMatchMissing = false;
        $this->loadEditor($match);
    }

    protected function loadEditor(MatchModel $match): void
    {
        $loaded = app(GameStatsService::class)->load($match);

        $this->quarters = $loaded['quarters'];
        $this->finals = $loaded['finals'];
        $this->stats = $loaded['stats'];
        $this->activeQuarter = 0;
        $this->side = 'all';
        $this->search1 = '';
        $this->search2 = '';
        $this->errorMessage = '';
        $this->showConfirm = false;
    }

    /**
     * @return array{quarters: array{team1: list<int>, team2: list<int>}, finals: array{team1: int, team2: int}, stats: array<string, mixed>}
     */
    protected function payload(): array
    {
        return [
            'quarters' => $this->quarters,
            'finals' => $this->finals,
            'stats' => $this->stats,
        ];
    }

    protected function editableMatch(): ?MatchModel
    {
        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return null;
        }

        if (in_array($match->status, ['final', 'forfeit'], true)) {
            $this->fail('This result is already final. Use correction to change it.');

            return null;
        }

        Gate::authorize('enterStats', $match);

        if ($match->home_team_id === null || $match->away_team_id === null) {
            $this->fail('Both teams must be known before entering stats.');

            return null;
        }

        return $match;
    }

    protected function syncTeamQuarter(string $side): void
    {
        $sum = 0;

        foreach ($this->stats[$side] as $entry) {
            $quarter = $entry['quarters'][$this->activeQuarter] ?? [];
            $sum += app(GameStatsService::class)->pointsFor($quarter);
        }

        $this->quarters[$side][$this->activeQuarter] = $sum;
        $this->syncFinal($side);
    }

    protected function syncFinal(string $side): void
    {
        $this->finals[$side] = array_sum($this->quarters[$side]);
    }

    /**
     * @return Collection<int, Player>
     */
    protected function sortedPlayers(Team $team): Collection
    {
        return $team->players
            ->sortBy(fn (Player $player): string => sprintf(
                '%05d-%s',
                (int) ($player->jersey_number ?? 999),
                mb_strtolower(($player->first_name ?? '').' '.($player->last_name ?? '')),
            ))
            ->values();
    }

    /**
     * @param  list<array<string, int>>  $quarters
     * @return array<string, int>
     */
    protected function sumStatQuarters(array $quarters): array
    {
        $totals = array_fill_keys(array_keys(GameStatsService::FIELDS), 0);

        foreach ($quarters as $quarter) {
            foreach (array_keys(GameStatsService::FIELDS) as $field) {
                $totals[$field] += (int) ($quarter[$field] ?? 0);
            }
        }

        $totals['pts'] = app(GameStatsService::class)->pointsFor($totals);

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    protected function emptyQuarter(): array
    {
        return array_fill_keys(array_keys(GameStatsService::FIELDS), 0);
    }

    protected function clampInt(mixed $value): int
    {
        return max(0, min(GameStatsService::MAX_VALUE, is_numeric($value) ? (int) $value : 0));
    }

    protected function fail(string $message): void
    {
        $this->errorMessage = $message;
        $this->toast($message, 'Unable to save', 'danger');
    }

    protected function openConfirm(string $title, string $message, string $label, string $tone, string $action): void
    {
        $this->confirmTitle = $title;
        $this->confirmMessage = $message;
        $this->confirmLabel = $label;
        $this->confirmTone = $tone;
        $this->confirmAction = $action;
        $this->showConfirm = true;
    }

    protected function toast(string $message, string $title, string $tone = 'success'): void
    {
        $this->dispatch('hf-toast', title: $title, message: $message, tone: $tone);
    }
}
