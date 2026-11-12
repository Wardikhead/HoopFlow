<?php

namespace App\Livewire\Organizer;

use App\Models\AuditLog;
use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\BracketService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Tournament|null $tournament
 * @property-read string $format
 * @property-read Collection<int, Registration> $approvedTeams
 * @property-read array{
 *     hasBracket: bool,
 *     sections: array<int, array{key: string, title: string, rounds: array<int, array{round: int, label: string, matches: Collection<int, MatchModel>}>}>,
 *     champion: Team|null
 * } $bracket
 * @property-read array{matchesPlayed: int, matchesScheduled: int, corrections: int} $kpis
 * @property-read MatchModel|null $selectedMatch
 * @property-read Collection<int, MatchModel> $selectedSeriesGames
 * @property-read bool $canGeneratePlayoffs
 * @property-read string $selectedRoundLabel
 * @property-read array{reason: string, previous1: int|null, previous2: int|null}|null $selectedCorrection
 * @property-read bool $selectedCanScore
 * @property-read bool $selectedCanCorrect
 */
#[Layout('layouts.public')]
#[Title('Bracket')]
class BracketShow extends Component
{
    public int $tournamentId = 0;

    public ?int $selectedMatchId = null;

    public bool $showMatchDetail = false;

    public bool $showScoreConsole = false;

    public bool $showCorrection = false;

    public bool $showEditSlots = false;

    public bool $showConfirm = false;

    public ?int $score1 = null;

    public ?int $score2 = null;

    public int $forfeitSide = 0;

    public string $reason = '';

    public string $confirmTitle = 'Confirm action';

    public string $confirmMessage = '';

    public string $confirmLabel = 'Confirm';

    public string $confirmTone = 'primary';

    public string $confirmAction = '';

    /** @var array<int, array{score1: int, score2: int}> */
    public array $scoreHistory = [];

    /** @var array<int, array{matchId: int, home: int|null, away: int|null}> */
    public array $slotAssignments = [];

    public function mount(int|string|null $id = null): void
    {
        if ($id !== null && is_numeric($id)) {
            $this->tournamentId = (int) $id;
        }

        if ($this->tournamentId === 0) {
            $query = request()->query('tournament') ?? request()->query('id');
            if (is_numeric($query)) {
                $this->tournamentId = (int) $query;
            }
        }

        if ($this->tournamentId === 0) {
            $first = Tournament::query()->first();
            $this->tournamentId = $first->id ?? 0;
        }

        $match = request()->query('match');

        if (is_numeric($match)) {
            $selected = $this->findTournamentMatch((int) $match);

            if ($selected instanceof MatchModel) {
                $this->selectedMatchId = (int) $selected->id;
                $this->showMatchDetail = true;
            }
        }
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        return Tournament::find($this->tournamentId);
    }

    #[Computed]
    public function format(): string
    {
        $rules = $this->tournament?->rules_json;
        $format = is_array($rules) && isset($rules['format']) && is_string($rules['format'])
            ? $rules['format']
            : 'single-elimination';

        return in_array($format, ['single-elimination', 'round-robin', 'double-elimination'], true)
            ? $format
            : 'single-elimination';
    }

    /**
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function approvedTeams(): Collection
    {
        if (! $this->tournament instanceof Tournament) {
            return new Collection;
        }

        return $this->tournament->registrations()
            ->with('team')
            ->where('status', 'approved')
            ->get()
            ->sortBy(fn (Registration $registration): int => $registration->seed ?? 999)
            ->values();
    }

    /**
     * @return array{
     *     hasBracket: bool,
     *     sections: array<int, array{key: string, title: string, rounds: array<int, array{round: int, label: string, matches: Collection<int, MatchModel>}>}>,
     *     champion: Team|null
     * }
     */
    #[Computed]
    public function bracket(): array
    {
        if (! $this->tournament instanceof Tournament) {
            return ['hasBracket' => false, 'sections' => [], 'champion' => null];
        }

        $brackets = Bracket::whereIn('division_id', $this->tournament->divisions()->pluck('id'))
            ->orderBy('phase')
            ->get();

        if ($brackets->isEmpty()) {
            return ['hasBracket' => false, 'sections' => [], 'champion' => null];
        }

        $matches = MatchModel::whereIn('bracket_id', $brackets->pluck('id'))
            ->with(['homeTeam', 'awayTeam', 'winnerTeam', 'matchSlots.court', 'bracket'])
            ->whereNull('series_id')
            ->get()
            ->reject(fn (MatchModel $match): bool => $match->status === 'cancelled')
            ->values();

        $sections = [];

        foreach ($brackets as $bracket) {
            $bracketMatches = $matches->where('bracket_id', $bracket->id)->values();

            if ($bracket->format === 'round_robin') {
                $sections[] = $this->section('group', 'Group stage', $bracketMatches, 'round_robin', true);

                continue;
            }

            if ($bracket->phase === 'playoffs') {
                $sections[] = $this->section('playoffs', 'Playoffs', $bracketMatches, 'winners', false);

                continue;
            }

            if ($bracket->format === 'double_elim') {
                $sections[] = $this->section('winners', 'Winners bracket', $bracketMatches, 'winners', true);
                $sections[] = $this->section('losers', 'Losers bracket', $bracketMatches, 'losers', true);
                $sections[] = $this->section('grand_final', 'Grand final', $bracketMatches, 'grand_final', true);

                continue;
            }

            $sections[] = $this->section('main', 'Bracket', $bracketMatches, 'winners', false);
        }

        $sections = array_values(array_filter(
            $sections,
            fn (array $section): bool => $section['rounds'] !== [],
        ));

        return [
            'hasBracket' => true,
            'sections' => $sections,
            'champion' => app(BracketService::class)->champion($this->tournament),
        ];
    }

    /**
     * @param  Collection<int, MatchModel>  $matches
     * @return array{key: string, title: string, rounds: array<int, array{round: int, label: string, matches: Collection<int, MatchModel>}>}
     */
    protected function section(string $key, string $title, Collection $matches, string $stage, bool $ascending): array
    {
        if ($stage === 'grand_final') {
            $matches = $matches
                ->whereIn('stage', ['grand_final', 'reset'])
                ->sortBy(fn (MatchModel $match): int => $match->stage === 'reset' ? 1 : 0)
                ->values();

            $rounds = $matches
                ->map(fn (MatchModel $match): array => [
                    'round' => (int) $match->round_number,
                    'label' => $match->roundLabel(),
                    'matches' => new Collection([$match]),
                ])
                ->values()
                ->all();

            return ['key' => $key, 'title' => $title, 'rounds' => $rounds];
        }

        $stageMatches = $matches
            ->where('stage', $stage)
            ->sortBy('round_number')
            ->values();

        $rounds = $stageMatches
            ->groupBy('round_number')
            ->map(function (Collection $group, $round): array {
                $group = $group->sortBy('match_number')->values();

                return [
                    'round' => (int) $round,
                    'label' => $group->first()?->roundLabel() ?? 'Round '.$round,
                    'matches' => $group,
                ];
            })
            ->values();

        if (! $ascending) {
            $rounds = $rounds->reverse()->values();
        }

        return ['key' => $key, 'title' => $title, 'rounds' => $rounds->all()];
    }

    /**
     * @return array{matchesPlayed: int, matchesScheduled: int, corrections: int}
     */
    #[Computed]
    public function kpis(): array
    {
        $statuses = MatchModel::where('tournament_id', $this->tournamentId)->pluck('status');

        $matchIds = MatchModel::where('tournament_id', $this->tournamentId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $corrections = $matchIds === []
            ? 0
            : AuditLog::query()
                ->where('entity_type', 'match')
                ->where('action', 'score.correct')
                ->whereIn('entity_id', $matchIds)
                ->count();

        return [
            'matchesPlayed' => $statuses->filter(fn ($status): bool => in_array($status, ['final', 'forfeit'], true))->count(),
            'matchesScheduled' => $statuses->filter(fn ($status): bool => $status === 'scheduled')->count(),
            'corrections' => $corrections,
        ];
    }

    #[Computed]
    public function selectedMatch(): ?MatchModel
    {
        return $this->findTournamentMatch($this->selectedMatchId);
    }

    #[Computed]
    public function selectedRoundLabel(): string
    {
        $match = $this->selectedMatch;

        return $match instanceof MatchModel ? $match->roundLabel() : 'Match';
    }

    /**
     * @return Collection<int, MatchModel>
     */
    #[Computed]
    public function selectedSeriesGames(): Collection
    {
        $match = $this->selectedMatch;

        if (! $match instanceof MatchModel || $match->best_of <= 1) {
            return new Collection;
        }

        $anchorId = $match->series_id !== null ? (int) $match->series_id : (int) $match->id;

        return MatchModel::query()
            ->where(function ($query) use ($anchorId): void {
                $query->where('id', $anchorId)->orWhere('series_id', $anchorId);
            })
            ->orderBy('game_number')
            ->get();
    }

    #[Computed]
    public function canGeneratePlayoffs(): bool
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament || $this->format !== 'round-robin') {
            return false;
        }

        $rules = is_array($tournament->rules_json) ? $tournament->rules_json : [];
        $playoffTeams = isset($rules['playoff_teams']) && is_numeric($rules['playoff_teams'])
            ? (int) $rules['playoff_teams']
            : 0;

        if (! in_array($playoffTeams, [2, 4, 8, 16], true)) {
            return false;
        }

        $divisionIds = $tournament->divisions()->pluck('id');
        $group = Bracket::whereIn('division_id', $divisionIds)->where('phase', 'group')->first();

        if (! $group instanceof Bracket) {
            return false;
        }

        if (Bracket::whereIn('division_id', $divisionIds)->where('phase', 'playoffs')->exists()) {
            return false;
        }

        return ! MatchModel::where('bracket_id', $group->id)
            ->whereNotIn('status', ['final', 'forfeit', 'cancelled'])
            ->exists();
    }

    /**
     * @return array{reason: string, previous1: int|null, previous2: int|null}|null
     */
    #[Computed]
    public function selectedCorrection(): ?array
    {
        if ($this->selectedMatchId === null) {
            return null;
        }

        $log = AuditLog::query()
            ->where('entity_type', 'match')
            ->where('action', 'score.correct')
            ->where('entity_id', $this->selectedMatchId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $log instanceof AuditLog) {
            return null;
        }

        $before = is_array($log->before_json) ? $log->before_json : [];
        $after = is_array($log->after_json) ? $log->after_json : [];

        $reason = isset($after['reason']) && is_string($after['reason'])
            ? $after['reason']
            : 'Score correction';

        $previous1 = $before['home_score'] ?? $before['previousScore1'] ?? null;
        $previous2 = $before['away_score'] ?? $before['previousScore2'] ?? null;

        return [
            'reason' => $reason,
            'previous1' => is_numeric($previous1) ? (int) $previous1 : null,
            'previous2' => is_numeric($previous2) ? (int) $previous2 : null,
        ];
    }

    #[Computed]
    public function selectedCanScore(): bool
    {
        $match = $this->selectedMatch;

        return $match instanceof MatchModel
            && $match->status === 'scheduled'
            && $match->home_team_id !== null
            && $match->away_team_id !== null
            && ! $this->isBye($match);
    }

    #[Computed]
    public function selectedCanCorrect(): bool
    {
        $match = $this->selectedMatch;

        return $match instanceof MatchModel
            && in_array($match->status, ['final', 'forfeit'], true);
    }

    public function requestGenerate(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateBracket', $tournament);

        if ($this->approvedTeams->count() < 2) {
            $this->toast('At least two approved teams are required.', 'Could not generate bracket', 'warning');

            return;
        }

        $this->openConfirm(
            'Generate bracket?',
            'Approved teams are seeded, byes fill the empty slots, and the seeds lock.',
            'Generate bracket',
            'primary',
            'generate',
        );
    }

    public function requestRegenerate(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateBracket', $tournament);

        if ($this->kpis['matchesPlayed'] > 0) {
            $this->toast('Cannot regenerate a bracket after results are recorded.', 'Bracket locked', 'warning');

            return;
        }

        $this->openConfirm(
            'Regenerate bracket?',
            'This wipes the current bracket and rebuilds from approved teams. Recorded results will be lost.',
            'Regenerate',
            'danger',
            'regenerate',
        );
    }

    public function requestGeneratePlayoffs(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateBracket', $tournament);

        if (! $this->canGeneratePlayoffs) {
            $this->toast('Finish every group game before generating playoffs.', 'Playoffs unavailable', 'warning');

            return;
        }

        $this->openConfirm(
            'Generate playoffs?',
            'The top ranked teams enter a single-elimination playoff bracket.',
            'Generate playoffs',
            'primary',
            'playoffs',
        );
    }

    public function openEditSlots(): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateBracket', $tournament);

        if ($this->kpis['matchesPlayed'] > 0) {
            $this->toast('Cannot reassign slots after results are recorded.', 'Bracket locked', 'warning');

            return;
        }

        $this->slotAssignments = $this->approvedTeams
            ->values()
            ->map(fn (Registration $registration, int $index): array => [
                'seed' => $index + 1,
                'team_id' => $registration->team_id !== null ? (int) $registration->team_id : null,
            ])
            ->all();

        $this->showEditSlots = true;
        $this->showMatchDetail = false;
        $this->showScoreConsole = false;
        $this->showCorrection = false;
        $this->showConfirm = false;
    }

    public function saveSlots(BracketService $bracketService): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateBracket', $tournament);

        if ($this->kpis['matchesPlayed'] > 0) {
            $this->addError('slots', 'Cannot reassign slots after results are recorded.');

            return;
        }

        $approvedIds = $this->approvedTeams
            ->pluck('team_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $ordered = [];
        $used = [];

        foreach ($this->slotAssignments as $assignment) {
            $teamId = $this->normalizeSlotTeam($assignment['team_id'] ?? null, $approvedIds);

            if ($teamId !== null) {
                if (in_array($teamId, $used, true)) {
                    $this->addError('slots', 'A team cannot be assigned to two slots.');

                    return;
                }

                $used[] = $teamId;
            }

            $ordered[] = $teamId;
        }

        $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
            ->whereIn('phase', ['main', 'group'])
            ->first();

        if (! $bracket instanceof Bracket) {
            $this->addError('slots', 'Generate a bracket before editing slots.');

            return;
        }

        try {
            $bracketService->updateSlots($bracket, $ordered);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('slots', $exception->getMessage());

            return;
        }

        $this->showEditSlots = false;
        $this->slotAssignments = [];
        $this->resetValidation();
        $this->toast('Slots updated.', 'Bracket updated');
    }

    public function openMatchDetail(int|string|null $matchId): void
    {
        $match = $this->findTournamentMatch($matchId);

        if (! $match instanceof MatchModel) {
            return;
        }

        $this->selectedMatchId = (int) $match->id;
        $this->showMatchDetail = true;
        $this->showScoreConsole = false;
        $this->showCorrection = false;
        $this->showConfirm = false;
        $this->resetValidation();
    }

    public function closeMatchDetail(): void
    {
        $this->showMatchDetail = false;
    }

    public function openScoreConsole(int|string|null $matchId): void
    {
        $match = $this->findTournamentMatch($matchId);

        if (! $match instanceof MatchModel) {
            return;
        }

        Gate::authorize('correctScore', $match);

        if ($match->status !== 'scheduled') {
            $this->toast('Only a scheduled game can be scored.', 'Score unavailable', 'warning');

            return;
        }

        if ($match->home_team_id === null || $match->away_team_id === null || $this->isBye($match)) {
            $this->toast('Both teams must be known before entering a score.', 'Teams pending', 'warning');

            return;
        }

        $this->selectedMatchId = (int) $match->id;
        $this->score1 = 0;
        $this->score2 = 0;
        $this->forfeitSide = 0;
        $this->scoreHistory = [];
        $this->reason = '';
        $this->showScoreConsole = true;
        $this->showMatchDetail = false;
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
        $match = $this->selectedMatch;

        if (! $match instanceof MatchModel) {
            return;
        }

        Gate::authorize('correctScore', $match);

        if ($match->status !== 'scheduled') {
            $this->addError('score2', 'Use correction to change a recorded result.');

            return;
        }

        if (in_array($this->forfeitSide, [1, 2], true)) {
            $winner = $this->forfeitSide === 1 ? $match->awayTeam : $match->homeTeam;
            $loser = $this->forfeitSide === 1 ? $match->homeTeam : $match->awayTeam;

            $this->openConfirm(
                'Record forfeit?',
                $loser->name.' forfeits. '.$winner->name.' wins 20–0.',
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

    public function openCorrection(int|string|null $matchId): void
    {
        $match = $this->findTournamentMatch($matchId);

        if (! $match instanceof MatchModel) {
            return;
        }

        Gate::authorize('correctScore', $match);

        if (! in_array($match->status, ['final', 'forfeit'], true)) {
            $this->toast('Only a recorded result can be corrected.', 'Not final yet', 'warning');

            return;
        }

        $this->selectedMatchId = (int) $match->id;
        $this->score1 = $match->home_score !== null ? (int) $match->home_score : 0;
        $this->score2 = $match->away_score !== null ? (int) $match->away_score : 0;
        $this->reason = '';
        $this->showCorrection = true;
        $this->showScoreConsole = false;
        $this->showMatchDetail = false;
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
        $match = $this->selectedMatch;

        if (! $match instanceof MatchModel) {
            return;
        }

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
            if ($action === 'generate') {
                $this->performGenerate($bracketService, false);
            } elseif ($action === 'regenerate') {
                $this->performGenerate($bracketService, true);
            } elseif ($action === 'finalize') {
                $this->performFinalize($bracketService);
            } elseif ($action === 'forfeit') {
                $this->performForfeit($bracketService);
            } elseif ($action === 'correction') {
                $this->performCorrection($bracketService);
            } elseif ($action === 'playoffs') {
                $this->performGeneratePlayoffs($bracketService);
            }
        } catch (\InvalidArgumentException $exception) {
            if ($action === 'correction') {
                $this->addError('reason', $exception->getMessage());
            } elseif (in_array($action, ['finalize', 'forfeit'], true)) {
                $this->addError('score2', $exception->getMessage());
            } else {
                $this->toast(
                    $exception->getMessage(),
                    $action === 'regenerate' ? 'Could not regenerate' : 'Could not generate bracket',
                    'danger',
                );
            }
        }
    }

    public function cancelConfirm(): void
    {
        $this->resetConfirm();
    }

    public function closeEditSlots(): void
    {
        $this->showEditSlots = false;
        $this->slotAssignments = [];
        $this->resetValidation();
    }

    protected function performGenerate(BracketService $bracketService, bool $regenerate): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateBracket', $tournament);

        $result = $bracketService->generate($tournament, $this->format);

        if ($regenerate) {
            $this->toast($result['matches'].' matches regenerated.', 'Bracket regenerated');
        } else {
            $this->toast(
                $result['matches'].' matches created with '.$result['byes'].' byes.',
                'Bracket generated',
            );
        }

        $this->selectedMatchId = null;
        $this->showEditSlots = false;
        $this->showMatchDetail = false;
        $this->showScoreConsole = false;
        $this->showCorrection = false;
        $this->slotAssignments = [];
    }

    protected function performGeneratePlayoffs(BracketService $bracketService): void
    {
        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('generateBracket', $tournament);

        $result = $bracketService->generatePlayoffs($tournament);

        $this->toast($result['matches'].' playoff matchups created.', 'Playoffs generated');
        $this->selectedMatchId = null;
        $this->showMatchDetail = false;
        $this->showScoreConsole = false;
        $this->showCorrection = false;
    }

    protected function performFinalize(BracketService $bracketService): void
    {
        $match = $this->selectedMatch;

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

        $message = $match->series_id !== null || $match->best_of > 1
            ? 'Game result recorded. The series updates automatically.'
            : ($winner?->shortLabel() ?? 'The winner').' advances to the next round.';

        $this->toast($message, 'Final score recorded');
    }

    protected function performForfeit(BracketService $bracketService): void
    {
        $match = $this->selectedMatch;

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
        $match = $this->selectedMatch;

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

    protected function isBye(MatchModel $match): bool
    {
        $hasHome = $match->home_team_id !== null;
        $hasAway = $match->away_team_id !== null;

        return $match->status === 'final'
            && $hasHome !== $hasAway
            && $match->home_score === null
            && $match->away_score === null;
    }

    protected function findTournamentMatch(int|string|null $matchId): ?MatchModel
    {
        if ($matchId === null || ! is_numeric($matchId)) {
            return null;
        }

        $match = MatchModel::query()
            ->where('tournament_id', $this->tournamentId)
            ->find((int) $matchId);

        return $match instanceof MatchModel ? $match : null;
    }

    /**
     * @param  array<int, int>  $approvedIds
     */
    protected function normalizeSlotTeam(mixed $value, array $approvedIds): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $teamId = (int) $value;

        return in_array($teamId, $approvedIds, true) ? $teamId : null;
    }

    public function render(): View
    {
        return view('livewire.organizer.bracket-show');
    }
}
