<?php

/** Livewire component for Game manage. */

namespace App\Livewire\Organizer;

use App\Livewire\SecureComponent;
use App\Models\Court;
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

/**
 * @property-read MatchModel|null $match
 * @property-read Team|null $homeTeam
 * @property-read Team|null $awayTeam
 * @property-read bool $isLocked
 * @property-read bool $hasEditableBoxScore
 * @property-read list<string> $quarterLabels
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read Collection<int, MatchModel> $matches
 * @property-read MatchModel|null $seriesAnchor
 * @property-read Collection<int, MatchModel> $seriesGames
 * @property-read string $seriesSummary
 * @property-read string $seriesGameLabel
 * @property-read string $seriesScheduleLabel
 * @property-read MatchModel|null $nextSeriesGame
 * @property-read string $nextSeriesSummary
 * @property-read string $lockedTitle
 */
#[Layout('layouts.public')]
#[Title('In-game score & player stats')]
class GameManage extends SecureComponent
{
    /**
     * @return array<int, string>
     */
    protected function allowedRoles(): array
    {
        return ['organizer'];
    }

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

    public bool $correctionMode = false;

    public string $correctionReason = '';

    /**
     * Mount.
     *
     * @param  int|string|null  $match  The match.
     * @return void Nothing.
     */
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

        if (request()->boolean('correct')) {
            $this->enterCorrectionMode();
        }
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
                'seriesAnchor.homeTeam',
                'seriesAnchor.awayTeam',
                'seriesAnchor.matchSlots.court',
            ])
            ->find($this->matchId);
    }

    /**
     * Home team.
     *
     * @return ?Team The result.
     */
    #[Computed]
    public function homeTeam(): ?Team
    {
        return $this->match?->homeTeam;
    }

    /**
     * Away team.
     *
     * @return ?Team The result.
     */
    #[Computed]
    public function awayTeam(): ?Team
    {
        return $this->match?->awayTeam;
    }

    /**
     * Is locked.
     *
     * @return bool The result.
     */
    #[Computed]
    public function isLocked(): bool
    {
        return ! $this->correctionMode
            && in_array($this->match?->status, ['final', 'forfeit', 'in_progress'], true);
    }

    /**
     * Series anchor.
     *
     * @return ?MatchModel The result.
     */
    #[Computed]
    public function seriesAnchor(): ?MatchModel
    {
        $match = $this->match;

        if (! $match instanceof MatchModel || $match->best_of <= 1) {
            return null;
        }

        if ($match->series_id !== null) {
            return $match->seriesAnchor;
        }

        return $match;
    }

    /**
     * @return Collection<int, MatchModel>
     */
    #[Computed]
    public function seriesGames(): Collection
    {
        $anchor = $this->seriesAnchor;

        if (! $anchor instanceof MatchModel) {
            return new Collection;
        }

        return MatchModel::query()
            ->where(function ($query) use ($anchor): void {
                $query->where('id', $anchor->id)->orWhere('series_id', $anchor->id);
            })
            ->with(['matchSlots.court', 'homeTeam', 'awayTeam'])
            ->orderBy('game_number')
            ->get();
    }

    /**
     * Series summary.
     *
     * @return string The result.
     */
    #[Computed]
    public function seriesSummary(): string
    {
        $anchor = $this->seriesAnchor;

        if (! $anchor instanceof MatchModel) {
            return '';
        }

        $homeWins = (int) $anchor->series_home_wins;
        $awayWins = (int) $anchor->series_away_wins;
        $home = $anchor->homeTeam?->shortLabel() ?? 'Home';
        $away = $anchor->awayTeam?->shortLabel() ?? 'Away';

        if ($anchor->series_winner_team_id !== null) {
            $winner = (int) $anchor->series_winner_team_id === (int) $anchor->home_team_id ? $home : $away;

            return $winner.' wins the series '.max($homeWins, $awayWins).'–'.min($homeWins, $awayWins);
        }

        if ($homeWins === 0 && $awayWins === 0) {
            return 'Series not started';
        }

        if ($homeWins === $awayWins) {
            return 'Series tied '.$homeWins.'–'.$awayWins;
        }

        $leader = $homeWins > $awayWins ? $home : $away;

        return $leader.' leads '.max($homeWins, $awayWins).'–'.min($homeWins, $awayWins);
    }

    /**
     * Series game label.
     *
     * @return string The result.
     */
    #[Computed]
    public function seriesGameLabel(): string
    {
        return $this->match?->seriesGameLabel() ?? '';
    }

    /**
     * Series schedule label.
     *
     * @return string The result.
     */
    #[Computed]
    public function seriesScheduleLabel(): string
    {
        $slot = $this->match?->matchSlots->first();

        if ($slot === null || $slot->start_at === null) {
            return 'Schedule TBD';
        }

        $court = $slot->court;
        $courtName = $court instanceof Court ? $court->name : 'Court TBD';

        return $courtName.' · '.$slot->start_at->format('M j, Y · g:i A');
    }

    /**
     * Next series game.
     *
     * @return ?MatchModel The result.
     */
    #[Computed]
    public function nextSeriesGame(): ?MatchModel
    {
        $anchor = $this->seriesAnchor;
        $match = $this->match;

        if (! $anchor instanceof MatchModel || ! $match instanceof MatchModel) {
            return null;
        }

        $active = $anchor->activeGame();

        if (! $active instanceof MatchModel || (int) $active->id === (int) $match->id) {
            return null;
        }

        return $this->seriesGames->firstWhere('id', $active->id);
    }

    /**
     * Next series summary.
     *
     * @return string The result.
     */
    #[Computed]
    public function nextSeriesSummary(): string
    {
        $next = $this->nextSeriesGame;

        if (! $next instanceof MatchModel) {
            return '';
        }

        $slot = $next->matchSlots->first();
        $schedule = 'Schedule TBD';

        if ($slot !== null && $slot->start_at !== null) {
            $court = $slot->court;
            $courtName = $court instanceof Court ? $court->name : 'Court TBD';
            $schedule = $courtName.' · '.$slot->start_at->format('M j · g:i A');
        }

        return 'Game '.$next->game_number.' is next · '.$schedule;
    }

    /**
     * Locked title.
     *
     * @return string The result.
     */
    #[Computed]
    public function lockedTitle(): string
    {
        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return 'Result';
        }

        return $match->best_of > 1 ? 'Game '.$match->game_number.' recorded' : 'Locked result';
    }

    /**
     * Is active series game.
     *
     * @param  MatchModel  $game  The game.
     * @return bool The result.
     */
    public function isActiveSeriesGame(MatchModel $game): bool
    {
        $anchor = $this->seriesAnchor;

        if (! $anchor instanceof MatchModel) {
            return false;
        }

        $active = $anchor->activeGame();

        return $active instanceof MatchModel && (int) $active->id === (int) $game->id;
    }

    /**
     * Series status label.
     *
     * @param  MatchModel  $game  The game.
     * @return string The result.
     */
    public function seriesStatusLabel(MatchModel $game): string
    {
        $status = $this->seriesGameStatus($game);
        $current = $this->match;

        if ($current instanceof MatchModel && (int) $game->id === (int) $current->id) {
            return $status === 'in_progress' ? 'In progress' : 'Current';
        }

        if ($this->isActiveSeriesGame($game)) {
            return 'Next';
        }

        if ($status !== $game->status) {
            return match ($status) {
                'final' => 'Final',
                'forfeit' => 'Forfeit',
                default => ucfirst(str_replace('_', ' ', $status)),
            };
        }

        return $game->statusLabel();
    }

    /**
     * The series anchor doubles as Game 1 and stores the aggregate series state.
     * Once Game 1 has a game winner, report it as a completed game even when the
     * series itself is still in progress.
     */
    public function seriesGameStatus(MatchModel $game): string
    {
        if ($game->series_id === null && $game->best_of > 1 && $game->game_winner_team_id !== null) {
            return $game->forfeit_reason !== null || $game->status === 'forfeit' ? 'forfeit' : 'final';
        }

        return $game->status;
    }

    /**
     * Series status tone.
     *
     * @param  MatchModel  $game  The game.
     * @return string The result.
     */
    public function seriesStatusTone(MatchModel $game): string
    {
        $status = $this->seriesGameStatus($game);

        if ($this->isActiveSeriesGame($game)) {
            return 'primary';
        }

        return match ($status) {
            'final' => 'success',
            'forfeit' => 'danger',
            'in_progress' => 'gold',
            'scheduled' => 'primary',
            default => 'slate',
        };
    }

    /**
     * Series game unavailable reason.
     *
     * @param  MatchModel  $game  The game.
     * @return ?string The result.
     */
    public function seriesGameUnavailableReason(MatchModel $game): ?string
    {
        if ($game->status === 'conditional') {
            return 'Available after Game '.($game->game_number - 1).' is final.';
        }

        if ($game->status === 'cancelled') {
            $anchor = $this->seriesAnchor;

            if ($anchor instanceof MatchModel) {
                return 'Series ended '.max((int) $anchor->series_home_wins, (int) $anchor->series_away_wins).'–'.min((int) $anchor->series_home_wins, (int) $anchor->series_away_wins).'. This game is not needed.';
            }

            return 'This game is not needed.';
        }

        return null;
    }

    /**
     * Has editable box score.
     *
     * @return bool The result.
     */
    #[Computed]
    public function hasEditableBoxScore(): bool
    {
        $match = $this->match;

        return $match instanceof MatchModel
            && $match->playerStats->isNotEmpty()
            && $match->playerQuarterStats->isNotEmpty();
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

    /**
     * Stat points for side.
     *
     * @param  string  $side  The side.
     * @return int The result.
     */
    public function statPointsForSide(string $side): int
    {
        $entries = match ($side) {
            'team1' => $this->stats['team1'],
            'team2' => $this->stats['team2'],
            default => [],
        };

        $service = app(GameStatsService::class);
        $total = 0;

        foreach ($entries as $entry) {
            foreach ($entry['quarters'] as $quarter) {
                $total += $service->pointsFor($quarter);
            }
        }

        return $total;
    }

    /**
     * Set active quarter.
     *
     * @param  int  $index  The index.
     * @return void Nothing.
     */
    public function setActiveQuarter(int $index): void
    {
        $this->activeQuarter = max(0, min($index, count($this->quarters['team1']) - 1));
    }

    /**
     * Updated.
     *
     * @param  string  $name  The name.
     * @return void Nothing.
     */
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

    /**
     * Add overtime.
     *
     * @return void Nothing.
     */
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

    /**
     * Remove overtime.
     *
     * @return void Nothing.
     */
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

    /**
     * Do remove overtime.
     *
     * @return void Nothing.
     */
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

    /**
     * Set side.
     *
     * @param  string  $side  The side.
     * @return void Nothing.
     */
    public function setSide(string $side): void
    {
        if (in_array($side, ['all', 'team1', 'team2'], true)) {
            $this->side = $side;
        }
    }

    /**
     * Clear filters.
     *
     * @return void Nothing.
     */
    public function clearFilters(): void
    {
        $this->tournamentFilter = 'all';
        $this->statusFilter = 'all';
        $this->teamSearch = '';
    }

    /**
     * Save draft.
     *
     * @return void Nothing.
     */
    public function saveDraft(): void
    {
        if ($this->correctionMode) {
            return;
        }

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
        $message = $match->best_of > 1
            ? 'Draft saved for '.$match->seriesGameLabel().'. Publish to lock it and update the series.'
            : 'Draft saved. The game stays scheduled until you publish.';
        $this->toast($message, 'Saved');
    }

    /**
     * Request publish.
     *
     * @return void Nothing.
     */
    public function requestPublish(): void
    {
        if ($this->correctionMode) {
            return;
        }

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
        $match = $this->match;
        $isSeries = $match instanceof MatchModel && $match->best_of > 1;
        $title = $isSeries ? 'Publish Game '.$match->game_number.'?' : 'Publish final result?';
        $label = $isSeries ? 'Publish Game '.$match->game_number : 'Publish result';
        $message = $isSeries
            ? 'Publish Game '.$match->game_number.', '.$sum1.'–'.$sum2.'? This locks the game and updates the series.'
            : 'Publish '.$sum1.'–'.$sum2.'? The winner advances, standings update, and the game locks. Corrections will require a reason.';

        $anchor = $this->seriesAnchor;

        if ($isSeries && $anchor instanceof MatchModel) {
            $currentWins = $sum1 > $sum2
                ? (int) $anchor->series_home_wins
                : (int) $anchor->series_away_wins;
            $needed = intdiv($anchor->best_of, 2) + 1;

            if ($currentWins + 1 >= $needed) {
                $message .= ' A win here closes out the series.';
            }
        }

        $this->openConfirm($title, $message, $label, 'primary', 'confirmPublish');
    }

    /**
     * Confirm publish.
     *
     * @return void Nothing.
     */
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

        $published = $match->refresh();
        $this->loadEditor($published);
        $this->errorMessage = '';

        if ($published->best_of > 1) {
            $anchor = $published->series_id !== null
                ? MatchModel::find($published->series_id)
                : $published;

            if ($anchor instanceof MatchModel) {
                $anchor->refresh();
                [$title, $message] = $this->seriesPublishToast($published, $anchor);
                $this->toast($message, $title);

                return;
            }
        }

        $this->toast('Result published. The winner advances to the next round.', 'Result published');
    }

    /**
     * Request save correction.
     *
     * @return void Nothing.
     */
    public function requestSaveCorrection(): void
    {
        if (! $this->correctionMode) {
            return;
        }

        $match = $this->editableMatch();

        if ($match === null) {
            return;
        }

        $this->validate([
            'correctionReason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'correctionReason.required' => 'Enter at least 5 characters for the correction reason.',
            'correctionReason.min' => 'Enter at least 5 characters for the correction reason.',
        ]);

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
            'Save correction?',
            'This updates the score and box score, may rewrite the downstream bracket if the winner changes, and is written to the audit trail.',
            'Save correction',
            'danger',
            'confirmSaveCorrection',
        );
    }

    /**
     * Confirm save correction.
     *
     * @return void Nothing.
     */
    public function confirmSaveCorrection(): void
    {
        $this->showConfirm = false;
        $match = $this->editableMatch();

        if ($match === null) {
            return;
        }

        try {
            app(GameStatsService::class)->correct($match, $this->payload(), $this->correctionReason);
        } catch (\InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());

            return;
        }

        $this->redirect(route('gameStats', ['match' => $match->id]), navigate: true);
    }

    /**
     * Cancel correction.
     *
     * @return void Nothing.
     */
    public function cancelCorrection(): void
    {
        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return;
        }

        $this->redirect(route('gameStats', ['match' => $match->id]), navigate: true);
    }

    /**
     * Close confirm.
     *
     * @return void Nothing.
     */
    public function closeConfirm(): void
    {
        $this->showConfirm = false;
    }

    /**
     * Render.
     *
     * @return View The result.
     */
    public function render(): View
    {
        return view('livewire.organizer.game-manage');
    }

    /**
     * Select match.
     *
     * @param  MatchModel  $match  The match.
     * @return void Nothing.
     */
    protected function selectMatch(MatchModel $match): void
    {
        $match = $this->resolveActiveSeriesMatch($match);

        $this->matchId = (int) $match->id;
        $this->requestedMatchMissing = false;
        $this->loadEditor($match);
    }

    /**
     * Resolve active series match.
     *
     * @param  MatchModel  $match  The match.
     * @return MatchModel The result.
     */
    protected function resolveActiveSeriesMatch(MatchModel $match): MatchModel
    {
        if ($match->best_of <= 1) {
            return $match;
        }

        $anchor = $match->series_id !== null
            ? MatchModel::find($match->series_id)
            : $match;

        if (! $anchor instanceof MatchModel) {
            return $match;
        }

        $isAnchorRequest = (int) $match->id === (int) $anchor->id;

        if (! $isAnchorRequest && in_array($match->status, ['scheduled', 'in_progress', 'final', 'forfeit'], true)) {
            return $match;
        }

        if (in_array($match->status, ['conditional', 'cancelled'], true)) {
            $active = $anchor->activeGame();

            if ($active instanceof MatchModel) {
                $message = $match->status === 'conditional'
                    ? 'Game '.$match->game_number.' is available after Game '.($match->game_number - 1).' is final.'
                    : 'The series ended '.max((int) $anchor->series_home_wins, (int) $anchor->series_away_wins).'–'.min((int) $anchor->series_home_wins, (int) $anchor->series_away_wins).'. Game '.$match->game_number.' is not needed.';

                $this->toast($message, 'Game unavailable', 'warning');

                return $active;
            }

            $this->toast(
                $match->status === 'conditional'
                    ? 'Game '.$match->game_number.' is available after Game '.($match->game_number - 1).' is final.'
                    : 'The series ended. Game '.$match->game_number.' is not needed.',
                'Game unavailable',
                'warning',
            );

            return $anchor;
        }

        return $anchor->activeGame() ?? $match;
    }

    /**
     * Enter correction mode.
     *
     * @return void Nothing.
     */
    protected function enterCorrectionMode(): void
    {
        $match = $this->match;

        if (! $match instanceof MatchModel || $match->status !== 'final') {
            return;
        }

        Gate::authorize('correctScore', $match);

        if (! $this->hasEditableBoxScore) {
            $this->redirect(route('gameStats', ['match' => $match->id, 'correct' => 1]), navigate: true);

            return;
        }

        $this->correctionMode = true;
        $this->correctionReason = '';
        $this->errorMessage = '';
    }

    /**
     * Load editor.
     *
     * @param  MatchModel  $match  The match.
     * @return void Nothing.
     */
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

    /**
     * Editable match.
     *
     * @return ?MatchModel The result.
     */
    protected function editableMatch(): ?MatchModel
    {
        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return null;
        }

        if ($this->correctionMode) {
            if ($match->status !== 'final') {
                $this->fail('Only a final result can be corrected.');

                return null;
            }

            Gate::authorize('correctScore', $match);
            Gate::authorize('enterStats', $match);

            if ($match->home_team_id === null || $match->away_team_id === null) {
                $this->fail('Both teams must be known before entering stats.');

                return null;
            }

            return $match;
        }

        if (in_array($match->status, ['final', 'forfeit'], true)) {
            $this->fail('This result is already final. Use correction to change it.');

            return null;
        }

        if ($match->best_of > 1 && $match->status === 'in_progress') {
            $this->fail('This game is already recorded. Open the next active game to continue.');

            return null;
        }

        Gate::authorize('enterStats', $match);

        if ($match->home_team_id === null || $match->away_team_id === null) {
            $this->fail('Both teams must be known before entering stats.');

            return null;
        }

        return $match;
    }

    /**
     * Sync team quarter.
     *
     * @param  string  $side  The side.
     * @return void Nothing.
     */
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

    /**
     * Sync final.
     *
     * @param  string  $side  The side.
     * @return void Nothing.
     */
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

    /**
     * Clamp int.
     *
     * @param  mixed  $value  The value.
     * @return int The result.
     */
    protected function clampInt(mixed $value): int
    {
        return max(0, min(GameStatsService::MAX_VALUE, is_numeric($value) ? (int) $value : 0));
    }

    /**
     * Fail.
     *
     * @param  string  $message  The message.
     * @return void Nothing.
     */
    protected function fail(string $message): void
    {
        $this->errorMessage = $message;
        $this->toast($message, 'Unable to save', 'danger');
    }

    /**
     * Open confirm.
     *
     * @param  string  $title  The title.
     * @param  string  $message  The message.
     * @param  string  $label  The label.
     * @param  string  $tone  The tone.
     * @param  string  $action  The action.
     * @return void Nothing.
     */
    protected function openConfirm(string $title, string $message, string $label, string $tone, string $action): void
    {
        $this->confirmTitle = $title;
        $this->confirmMessage = $message;
        $this->confirmLabel = $label;
        $this->confirmTone = $tone;
        $this->confirmAction = $action;
        $this->showConfirm = true;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function seriesPublishToast(MatchModel $game, MatchModel $anchor): array
    {
        $label = 'Game '.$game->game_number;
        $home = $anchor->homeTeam?->shortLabel() ?? 'Home';
        $away = $anchor->awayTeam?->shortLabel() ?? 'Away';
        $homeWins = (int) $anchor->series_home_wins;
        $awayWins = (int) $anchor->series_away_wins;

        if ($anchor->series_winner_team_id !== null) {
            $winner = (int) $anchor->series_winner_team_id === (int) $anchor->home_team_id ? $home : $away;

            return ['Series complete', $winner.' wins the series '.max($homeWins, $awayWins).'–'.min($homeWins, $awayWins).'.'];
        }

        $active = $anchor->activeGame();
        $nextLabel = $active instanceof MatchModel ? 'Game '.$active->game_number : 'The next game';
        $slot = $active instanceof MatchModel
            ? $active->matchSlots()->with('court')->first()
            : null;
        $schedule = '';

        if ($slot !== null && $slot->start_at !== null) {
            $court = $slot->court;
            $courtName = $court instanceof Court ? $court->name : 'Court TBD';
            $schedule = ' on '.$courtName.' at '.$slot->start_at->format('M j, g:i A');
        }
        $series = $homeWins === $awayWins
            ? 'Series tied '.$homeWins.'–'.$awayWins
            : (($homeWins > $awayWins ? $home : $away).' leads '.max($homeWins, $awayWins).'–'.min($homeWins, $awayWins));

        return [$label.' recorded', $series.'. '.$nextLabel.' is next'.$schedule.'.'];
    }

    /**
     * Toast.
     *
     * @param  string  $message  The message.
     * @param  string  $title  The title.
     * @param  string  $tone  The tone.
     * @return void Nothing.
     */
    protected function toast(string $message, string $title, string $tone = 'success'): void
    {
        $this->dispatch('hf-toast', title: $title, message: $message, tone: $tone);
    }
}
