<?php

namespace App\Livewire\Organizer;

use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\PlayerStat;
use App\Models\PlayerStatQuarter;
use App\Models\Team;
use App\Services\BracketService;
use App\Services\GameStatsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read MatchModel|null $match
 * @property-read bool $isOrganizer
 * @property-read string $roleLabel
 * @property-read bool $canScore
 * @property-read bool $canCorrect
 * @property-read array{team1: list<int>, team2: list<int>}|null $quarters
 * @property-read bool $hasStats
 * @property-read list<array{key: string, label: string, index: int|null}> $periods
 * @property-read string $periodLabel
 * @property-read bool $hasQuarterData
 * @property-read array{previous1: int|null, previous2: int|null, reason: string, corrected_at: Carbon|null}|null $correction
 */
#[Layout('layouts.public')]
#[Title('Game stats')]
class GameStatsShow extends Component
{
    public ?int $matchId = null;

    public ?int $score1 = null;

    public ?int $score2 = null;

    public int $forfeitSide = 0;

    public string $reason = '';

    public bool $showScoreConsole = false;

    public bool $showCorrection = false;

    public bool $showConfirm = false;

    public string $confirmTitle = 'Confirm action';

    public string $confirmMessage = '';

    public string $confirmLabel = 'Confirm';

    public string $confirmTone = 'primary';

    public string $confirmAction = '';

    /** @var array<int, array{score1: int, score2: int}> */
    public array $scoreHistory = [];

    public string $side = 'all';

    #[Url(as: 'period', history: true, except: 'all')]
    public string $period = 'all';

    public string $search = '';

    public string $sortKey = 'points';

    public string $sortDirection = 'desc';

    public function mount(int|string|null $match = null): void
    {
        if ($match !== null && is_numeric($match)) {
            $this->matchId = (int) $match;
        }

        if ($this->matchId === null) {
            $query = request()->query('match');

            if (is_numeric($query)) {
                $this->matchId = (int) $query;
            }
        }

        if ($this->matchId !== null && request()->boolean('correct') && $this->isOrganizer) {
            $this->openCorrection();
        }

        $this->period = $this->resolvePeriod($this->period);
    }

    #[Computed]
    public function isOrganizer(): bool
    {
        return auth()->user()?->hasRole('organizer') ?? false;
    }

    #[Computed]
    public function roleLabel(): string
    {
        $key = auth()->user()?->roles()->value('key');

        return is_string($key) && $key !== '' ? $key : 'fan';
    }

    #[Computed]
    public function match(): ?MatchModel
    {
        if ($this->matchId === null) {
            return null;
        }

        return MatchModel::query()
            ->with([
                'homeTeam',
                'awayTeam',
                'winnerTeam',
                'tournament.divisions',
                'matchSlots.court',
                'playerStats.player',
                'playerStats.team',
                'playerQuarterStats',
            ])
            ->find($this->matchId);
    }

    #[Computed]
    public function canScore(): bool
    {
        $match = $this->match;

        return $this->isOrganizer
            && $match instanceof MatchModel
            && $match->status === 'scheduled'
            && $match->home_team_id !== null
            && $match->away_team_id !== null;
    }

    #[Computed]
    public function canCorrect(): bool
    {
        $match = $this->match;

        return $this->isOrganizer
            && $match instanceof MatchModel
            && in_array($match->status, ['final', 'forfeit'], true);
    }

    /**
     * @return array{team1: list<int>, team2: list<int>}|null
     */
    #[Computed]
    public function quarters(): ?array
    {
        $raw = $this->match?->quarters_json;

        if (! is_array($raw) || ! is_array($raw['team1'] ?? null) || ! is_array($raw['team2'] ?? null)) {
            return null;
        }

        $team1 = array_values($raw['team1']);
        $team2 = array_values($raw['team2']);
        $count = min(9, max(4, count($team1), count($team2)));

        $normalize = function (array $values) use ($count): array {
            $values = array_values($values);
            $out = [];

            for ($index = 0; $index < $count; $index++) {
                $out[] = is_numeric($values[$index] ?? null) ? (int) $values[$index] : 0;
            }

            return $out;
        };

        return [
            'team1' => $normalize($team1),
            'team2' => $normalize($team2),
        ];
    }

    #[Computed]
    public function hasStats(): bool
    {
        $match = $this->match;

        return $match instanceof MatchModel && $match->playerStats->isNotEmpty();
    }

    /**
     * @return list<array{key: string, label: string, index: int|null}>
     */
    #[Computed]
    public function periods(): array
    {
        $periods = [['key' => 'all', 'label' => 'All', 'index' => null]];
        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return $periods;
        }

        $indexes = $match->playerQuarterStats
            ->pluck('quarter_index')
            ->filter(fn (mixed $index): bool => is_numeric($index))
            ->map(fn (mixed $index): int => (int) $index)
            ->unique()
            ->filter(fn (int $index): bool => $index >= 0 && $index < GameStatsService::MAX_QUARTERS)
            ->sort()
            ->values();

        foreach ($indexes as $index) {
            $periods[] = [
                'key' => (string) $index,
                'label' => $index < 4 ? 'Q'.($index + 1) : 'OT'.($index - 3),
                'index' => $index,
            ];
        }

        return $periods;
    }

    #[Computed]
    public function periodLabel(): string
    {
        foreach ($this->periods as $period) {
            if ($period['key'] === $this->period) {
                return $period['label'];
            }
        }

        return 'All';
    }

    #[Computed]
    public function hasQuarterData(): bool
    {
        return count($this->periods) > 1;
    }

    /**
     * @return array{previous1: int|null, previous2: int|null, reason: string, corrected_at: Carbon|null}|null
     */
    #[Computed]
    public function correction(): ?array
    {
        if ($this->matchId === null) {
            return null;
        }

        $log = AuditLog::query()
            ->where('entity_type', 'match')
            ->where('action', 'score.correct')
            ->where('entity_id', $this->matchId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $log instanceof AuditLog) {
            return null;
        }

        $before = is_array($log->before_json) ? $log->before_json : [];
        $after = is_array($log->after_json) ? $log->after_json : [];

        $previous1 = $before['home_score'] ?? $before['previousScore1'] ?? null;
        $previous2 = $before['away_score'] ?? $before['previousScore2'] ?? null;

        return [
            'previous1' => is_numeric($previous1) ? (int) $previous1 : null,
            'previous2' => is_numeric($previous2) ? (int) $previous2 : null,
            'reason' => isset($after['reason']) && is_string($after['reason']) ? $after['reason'] : 'Score correction',
            'corrected_at' => $log->created_at,
        ];
    }

    /**
     * @return array<int, array{
     *   playerId: int,
     *   name: string,
     *   number: int|string|null,
     *   position: string|null,
     *   fgm: int, fga: int,
     *   tpm: int, tpa: int,
     *   ftm: int, fta: int,
     *   reb: int, ast: int, stl: int, blk: int, to: int, pf: int,
     *   points: int
     * }>
     */
    public function rowsFor(?Team $team): array
    {
        $match = $this->match;

        if (! $team instanceof Team || ! $match instanceof MatchModel) {
            return [];
        }

        $term = mb_strtolower(trim($this->search));
        $key = in_array($this->sortKey, ['points', 'assists', 'rebounds'], true) ? $this->sortKey : 'points';
        $sortField = match ($key) {
            'assists' => 'ast',
            'rebounds' => 'reb',
            default => 'points',
        };
        $factor = $this->sortDirection === 'asc' ? 1 : -1;
        $quarterIndex = $this->period === 'all' ? null : (int) $this->period;
        $quarterRows = $match->playerQuarterStats->groupBy('player_id');

        return $match->playerStats
            ->where('team_id', $team->id)
            ->map(function (PlayerStat $stat) use ($quarterRows, $quarterIndex): array {
                $player = $stat->player;
                $name = trim(($player->first_name ?? '').' '.($player->last_name ?? ''));
                $line = $quarterIndex === null
                    ? $this->statLineFromAggregate($stat)
                    : $this->statLineFromQuarter(
                        $quarterRows->get((int) $stat->player_id, collect())
                            ->firstWhere('quarter_index', $quarterIndex)
                    );

                return [
                    'playerId' => (int) $stat->player_id,
                    'name' => $name !== '' ? $name : 'Player',
                    'number' => $player->jersey_number,
                    'position' => $player->position,
                    ...$line,
                ];
            })
            ->filter(fn (array $row): bool => $term === '' || str_contains(mb_strtolower($row['name']), $term))
            ->sort(fn (array $a, array $b): int => (($a[$sortField] <=> $b[$sortField]) * $factor) ?: strcasecmp($a['name'], $b['name']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{fgm: int, fga: int, tpm: int, tpa: int, ftm: int, fta: int, reb: int, ast: int, stl: int, blk: int, to: int, pf: int, points: int}
     */
    public function totalsFor(array $rows): array
    {
        $totals = array_fill_keys(
            ['fgm', 'fga', 'tpm', 'tpa', 'ftm', 'fta', 'reb', 'ast', 'stl', 'blk', 'to', 'pf', 'points'],
            0,
        );

        foreach ($rows as $row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += (int) ($row[$key] ?? 0);
            }
        }

        return $totals;
    }

    /**
     * @return array{fgm: int, fga: int, tpm: int, tpa: int, ftm: int, fta: int, reb: int, ast: int, stl: int, blk: int, to: int, pf: int, points: int}
     */
    protected function statLineFromAggregate(PlayerStat $stat): array
    {
        return [
            'fgm' => (int) ($stat->field_goals_made ?? 0),
            'fga' => (int) ($stat->field_goals_attempted ?? 0),
            'tpm' => (int) ($stat->three_points_made ?? 0),
            'tpa' => (int) ($stat->three_points_attempted ?? 0),
            'ftm' => (int) ($stat->free_throws_made ?? 0),
            'fta' => (int) ($stat->free_throws_attempted ?? 0),
            'reb' => (int) ($stat->rebounds ?? 0),
            'ast' => (int) ($stat->assists ?? 0),
            'stl' => (int) ($stat->steals ?? 0),
            'blk' => (int) ($stat->blocks ?? 0),
            'to' => (int) ($stat->turnovers ?? 0),
            'pf' => (int) ($stat->fouls ?? 0),
            'points' => (int) ($stat->points ?? 0),
        ];
    }

    /**
     * @return array{fgm: int, fga: int, tpm: int, tpa: int, ftm: int, fta: int, reb: int, ast: int, stl: int, blk: int, to: int, pf: int, points: int}
     */
    protected function statLineFromQuarter(?PlayerStatQuarter $row): array
    {
        if (! $row instanceof PlayerStatQuarter) {
            return array_fill_keys(
                ['fgm', 'fga', 'tpm', 'tpa', 'ftm', 'fta', 'reb', 'ast', 'stl', 'blk', 'to', 'pf', 'points'],
                0,
            );
        }

        $fgm = (int) ($row->field_goals_made ?? 0);
        $tpm = (int) ($row->three_points_made ?? 0);
        $ftm = (int) ($row->free_throws_made ?? 0);
        $points = (int) ($row->points ?? 0);

        return [
            'fgm' => $fgm,
            'fga' => (int) ($row->field_goals_attempted ?? 0),
            'tpm' => $tpm,
            'tpa' => (int) ($row->three_points_attempted ?? 0),
            'ftm' => $ftm,
            'fta' => (int) ($row->free_throws_attempted ?? 0),
            'reb' => (int) ($row->rebounds ?? 0),
            'ast' => (int) ($row->assists ?? 0),
            'stl' => (int) ($row->steals ?? 0),
            'blk' => (int) ($row->blocks ?? 0),
            'to' => (int) ($row->turnovers ?? 0),
            'pf' => (int) ($row->fouls ?? 0),
            'points' => $points > 0 ? $points : $fgm * 2 + $tpm * 3 + $ftm,
        ];
    }

    public function setPeriod(string $period): void
    {
        $this->period = $this->resolvePeriod($period);
    }

    protected function resolvePeriod(string $period): string
    {
        return in_array($period, array_column($this->periods, 'key'), true) ? $period : 'all';
    }

    public function sortBy(string $key): void
    {
        if (! in_array($key, ['points', 'assists', 'rebounds'], true)) {
            return;
        }

        if ($this->sortKey === $key) {
            $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';

            return;
        }

        $this->sortKey = $key;
        $this->sortDirection = 'desc';
    }

    public function openScoreConsole(int|string|null $matchId = null): void
    {
        if ($matchId !== null && is_numeric($matchId)) {
            $this->matchId = (int) $matchId;
        }

        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return;
        }

        abort_unless($this->isOrganizer, 403);
        Gate::authorize('correctScore', $match);

        if ($match->status !== 'scheduled') {
            $this->toast('Only a scheduled game can be scored.', 'Score unavailable', 'warning');

            return;
        }

        if ($match->home_team_id === null || $match->away_team_id === null) {
            $this->toast('Both teams must be known before entering a score.', 'Teams pending', 'warning');

            return;
        }

        $this->score1 = 0;
        $this->score2 = 0;
        $this->forfeitSide = 0;
        $this->scoreHistory = [];
        $this->reason = '';
        $this->showScoreConsole = true;
        $this->showCorrection = false;
        $this->showConfirm = false;
        $this->resetValidation();
    }

    public function closeScoreConsole(): void
    {
        $this->showScoreConsole = false;
        $this->scoreHistory = [];
        $this->resetValidation();
    }

    public function addScore(int $side, int $points): void
    {
        if (! $this->showScoreConsole || $this->forfeitSide !== 0) {
            return;
        }

        if (! in_array($side, [1, 2], true) || ! in_array($points, [1, 2, 3], true)) {
            return;
        }

        $current1 = (int) ($this->score1 ?? 0);
        $current2 = (int) ($this->score2 ?? 0);

        $this->scoreHistory[] = ['score1' => $current1, 'score2' => $current2];

        if ($side === 1) {
            $this->score1 = min(199, $current1 + $points);
        } else {
            $this->score2 = min(199, $current2 + $points);
        }
    }

    public function undoScore(): void
    {
        if ($this->forfeitSide !== 0) {
            return;
        }

        $previous = array_pop($this->scoreHistory);

        if (! is_array($previous)) {
            return;
        }

        $this->score1 = $previous['score1'];
        $this->score2 = $previous['score2'];
    }

    public function requestFinalizeScore(): void
    {
        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return;
        }

        abort_unless($this->isOrganizer, 403);
        Gate::authorize('correctScore', $match);

        if ($match->status !== 'scheduled') {
            $this->addError('score2', 'Use correction to change a recorded result.');

            return;
        }

        if (in_array($this->forfeitSide, [1, 2], true)) {
            $winner = $this->forfeitSide === 1 ? $match->awayTeam : $match->homeTeam;
            $loser = $this->forfeitSide === 1 ? $match->homeTeam : $match->awayTeam;
            $winnerName = $winner?->name;
            $loserName = $loser?->name;

            $this->openConfirm(
                'Record forfeit?',
                ($loserName ?? 'The selected team').' forfeits. '.($winnerName ?? 'The opponent').' wins 20–0.',
                'Record forfeit',
                'danger',
                'forfeit',
            );

            return;
        }

        $this->validate([
            'score1' => ['required', 'integer', 'min:0', 'max:199'],
            'score2' => ['required', 'integer', 'min:0', 'max:199'],
        ], $this->scoreValidationMessages());

        $score1 = (int) ($this->score1 ?? 0);
        $score2 = (int) ($this->score2 ?? 0);

        if ($score1 === $score2) {
            $this->addError('score2', 'Elimination games cannot end in a tie.');

            return;
        }

        $this->openConfirm(
            'Finalize score?',
            ($match->homeTeam?->shortLabel() ?? 'Home').' '.$score1.'–'.$score2.' '.($match->awayTeam?->shortLabel() ?? 'Away').'. The winner advances and standings update immediately.',
            'Finalize score',
            'primary',
            'finalize',
        );
    }

    public function openCorrection(int|string|null $matchId = null): void
    {
        if ($matchId !== null && is_numeric($matchId)) {
            $this->matchId = (int) $matchId;
        }

        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return;
        }

        abort_unless($this->isOrganizer, 403);
        Gate::authorize('correctScore', $match);

        if (! in_array($match->status, ['final', 'forfeit'], true)) {
            $this->toast('Only a recorded result can be corrected.', 'Not final yet', 'warning');

            return;
        }

        $this->score1 = $match->home_score !== null ? (int) $match->home_score : 0;
        $this->score2 = $match->away_score !== null ? (int) $match->away_score : 0;
        $this->reason = '';
        $this->showCorrection = true;
        $this->showScoreConsole = false;
        $this->showConfirm = false;
        $this->resetValidation();
    }

    public function closeCorrection(): void
    {
        $this->showCorrection = false;
        $this->resetValidation();
    }

    public function requestCorrection(): void
    {
        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return;
        }

        abort_unless($this->isOrganizer, 403);
        Gate::authorize('correctScore', $match);

        if (! in_array($match->status, ['final', 'forfeit'], true)) {
            $this->addError('reason', 'Only a recorded result can be corrected.');

            return;
        }

        $this->validate([
            'score1' => ['required', 'integer', 'min:0', 'max:199'],
            'score2' => ['required', 'integer', 'min:0', 'max:199'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            ...$this->scoreValidationMessages(),
            'reason.required' => 'Enter at least 5 characters for the correction reason.',
            'reason.min' => 'Enter at least 5 characters for the correction reason.',
        ]);

        $score1 = (int) ($this->score1 ?? 0);
        $score2 = (int) ($this->score2 ?? 0);

        if ($score1 === $score2) {
            $this->addError('score2', 'Elimination games cannot end in a tie.');

            return;
        }

        $this->openConfirm(
            'Apply correction?',
            'Change '.($match->homeTeam?->shortLabel() ?? 'Home').' '.($match->home_score ?? '–').'–'.($match->away_score ?? '–').' '.($match->awayTeam?->shortLabel() ?? 'Away').' to '.$score1.'–'.$score2.'. The audit log keeps the previous values.',
            'Apply correction',
            'danger',
            'correction',
        );
    }

    public function confirm(BracketService $bracketService): void
    {
        $action = $this->confirmAction;
        $this->resetConfirm();

        try {
            if ($action === 'finalize') {
                $this->performFinalize($bracketService);
            } elseif ($action === 'forfeit') {
                $this->performForfeit($bracketService);
            } elseif ($action === 'correction') {
                $this->performCorrection($bracketService);
            }
        } catch (\InvalidArgumentException $exception) {
            if ($action === 'correction') {
                $this->addError('reason', $exception->getMessage());
            } else {
                $this->addError('score2', $exception->getMessage());
            }
        }
    }

    public function cancelConfirm(): void
    {
        $this->resetConfirm();
    }

    protected function performFinalize(BracketService $bracketService): void
    {
        $match = $this->freshMatch();

        if (! $match instanceof MatchModel) {
            return;
        }

        Gate::authorize('correctScore', $match);

        $score1 = (int) ($this->score1 ?? 0);
        $score2 = (int) ($this->score2 ?? 0);
        $winner = $score1 > $score2 ? $match->homeTeam : $match->awayTeam;

        $bracketService->recordScore($match, $score1, $score2);

        $this->showScoreConsole = false;
        $this->scoreHistory = [];
        $this->resetValidation();
        $this->toast(
            ($winner?->shortLabel() ?? 'The winner').' advances to the next round.',
            'Final score recorded',
        );
    }

    protected function performForfeit(BracketService $bracketService): void
    {
        $match = $this->freshMatch();

        if (! $match instanceof MatchModel) {
            return;
        }

        Gate::authorize('correctScore', $match);

        $winner = $this->forfeitSide === 1 ? $match->awayTeam : $match->homeTeam;

        $bracketService->recordForfeit($match, $this->forfeitSide);

        $this->showScoreConsole = false;
        $this->scoreHistory = [];
        $this->resetValidation();
        $this->toast(
            ($winner?->shortLabel() ?? 'The winner').' advances on a 20–0 forfeit.',
            'Forfeit recorded',
        );
    }

    protected function performCorrection(BracketService $bracketService): void
    {
        $match = $this->freshMatch();

        if (! $match instanceof MatchModel) {
            return;
        }

        Gate::authorize('correctScore', $match);

        $bracketService->correctScore(
            $match,
            (int) ($this->score1 ?? 0),
            (int) ($this->score2 ?? 0),
            $this->reason,
        );

        $this->showCorrection = false;
        $this->resetValidation();
        $this->toast('Correction saved and the bracket path was recalculated.', 'Score corrected');
    }

    protected function freshMatch(): ?MatchModel
    {
        if ($this->matchId === null) {
            return null;
        }

        return MatchModel::query()
            ->with(['homeTeam', 'awayTeam'])
            ->find($this->matchId);
    }

    protected function openConfirm(
        string $title,
        string $message,
        string $label,
        string $tone,
        string $action,
    ): void {
        $this->confirmTitle = $title;
        $this->confirmMessage = $message;
        $this->confirmLabel = $label;
        $this->confirmTone = $tone;
        $this->confirmAction = $action;
        $this->showConfirm = true;
    }

    protected function resetConfirm(): void
    {
        $this->showConfirm = false;
        $this->confirmTitle = 'Confirm action';
        $this->confirmMessage = '';
        $this->confirmLabel = 'Confirm';
        $this->confirmTone = 'primary';
        $this->confirmAction = '';
    }

    /**
     * @return array<string, string>
     */
    protected function scoreValidationMessages(): array
    {
        return [
            'score1.required' => 'Scores must be zero or positive whole numbers.',
            'score1.integer' => 'Scores must be zero or positive whole numbers.',
            'score1.min' => 'Scores must be zero or positive whole numbers.',
            'score1.max' => 'Scores cannot exceed 199.',
            'score2.required' => 'Scores must be zero or positive whole numbers.',
            'score2.integer' => 'Scores must be zero or positive whole numbers.',
            'score2.min' => 'Scores must be zero or positive whole numbers.',
            'score2.max' => 'Scores cannot exceed 199.',
        ];
    }

    protected function toast(string $message, string $title, string $tone = 'success'): void
    {
        $this->dispatch('hf-toast', title: $title, message: $message, tone: $tone);
    }

    public function render(): View
    {
        return view('livewire.organizer.game-stats-show');
    }
}
