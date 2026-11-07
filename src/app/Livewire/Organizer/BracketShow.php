<?php

namespace App\Livewire\Organizer;

use App\Models\MatchModel;
use App\Models\Tournament;
use App\Services\BracketService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Bracket')]
class BracketShow extends Component
{
    public int $tournamentId = 0;

    public ?int $selectedMatchId = null;

    public ?int $score1 = null;

    public ?int $score2 = null;

    public string $reason = '';

    public bool $showEditSlots = false;

    public bool $showScoreConsole = false;

    public bool $showCorrection = false;

    /** @var array<int, mixed> */
    public array $slots = [];

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
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        return Tournament::find($this->tournamentId);
    }

    public function generateBracket(BracketService $bracketService): void
    {
        $tournament = Tournament::findOrFail($this->tournamentId);

        $bracketService->generate($tournament, 'single-elimination');
    }

    public function regenerateBracket(BracketService $bracketService): void
    {
        $tournament = Tournament::findOrFail($this->tournamentId);

        $bracketService->generate($tournament, 'single-elimination');
    }

    public function editSlots(): void
    {
        $tournament = $this->tournament();
        $matches = $tournament !== null ? $tournament->matches : collect();
        $firstRound = $matches->sortByDesc('round_number')->first()?->round_number;
        $firstRoundMatches = $firstRound !== null
            ? $matches->where('round_number', $firstRound)->sortBy('match_number')->values()
            : collect();

        $this->slots = $firstRoundMatches->map(fn ($match) => [
            'matchId' => $match->id,
            'slot' => 1,
            'teamId' => $match->home_team_id,
        ])->values()->all();

        // add away slot assignments
        $away = $firstRoundMatches->map(fn ($match) => [
            'matchId' => $match->id,
            'slot' => 2,
            'teamId' => $match->away_team_id,
        ])->values()->all();

        $this->slots = array_merge($this->slots, $away);

        $this->showEditSlots = true;
    }

    public function saveSlots(): void
    {
        foreach ($this->slots as $assignment) {
            if (! is_array($assignment)) {
                continue;
            }

            $match = MatchModel::find($assignment['matchId'] ?? null);
            if (! $match instanceof MatchModel) {
                continue;
            }

            $slot = (int) ($assignment['slot'] ?? 0);
            $teamId = $assignment['teamId'] ?? null;
            $teamId = $teamId === '' ? null : $teamId;

            if ($slot === 1) {
                $match->home_team_id = $teamId !== null ? (int) $teamId : null;
            } else {
                $match->away_team_id = $teamId !== null ? (int) $teamId : null;
            }

            $match->save();
        }

        $this->showEditSlots = false;
    }

    public function openScoreConsole(int|string|null $matchId): void
    {
        $this->selectedMatchId = $matchId !== null ? (int) $matchId : null;
        $this->showScoreConsole = true;

        $match = $this->selectedMatchId !== null
            ? MatchModel::find($this->selectedMatchId)
            : null;

        $this->score1 = $match?->home_score;
        $this->score2 = $match?->away_score;
        $this->reason = '';
    }

    public function submitScore(BracketService $bracketService): void
    {
        if ($this->selectedMatchId === null) {
            return;
        }

        $match = MatchModel::findOrFail($this->selectedMatchId);

        $bracketService->correctScore(
            $match,
            (int) ($this->score1 ?? 0),
            (int) ($this->score2 ?? 0),
            $this->reason,
        );

        $this->showScoreConsole = false;
    }

    public function openCorrection(int|string|null $matchId): void
    {
        $this->selectedMatchId = $matchId !== null ? (int) $matchId : null;
        $this->showCorrection = true;

        $match = $this->selectedMatchId !== null
            ? MatchModel::find($this->selectedMatchId)
            : null;

        $this->score1 = $match?->home_score;
        $this->score2 = $match?->away_score;
        $this->reason = '';
    }

    public function submitCorrection(BracketService $bracketService): void
    {
        if ($this->selectedMatchId === null) {
            return;
        }

        $match = MatchModel::findOrFail($this->selectedMatchId);

        $bracketService->correctScore(
            $match,
            (int) ($this->score1 ?? 0),
            (int) ($this->score2 ?? 0),
            $this->reason,
        );

        $this->showCorrection = false;
    }

    /**
     * @return array{hasBracket: bool, rounds: array<int, mixed>, champion: mixed}
     */
    #[Computed]
    public function bracket(): array
    {
        $tournament = $this->tournament();

        if (! $tournament instanceof Tournament) {
            return ['hasBracket' => false, 'rounds' => [], 'champion' => null];
        }

        $matches = $tournament->matches()->with(['homeTeam', 'awayTeam', 'winnerTeam'])->get();

        if ($matches->isEmpty()) {
            return ['hasBracket' => false, 'rounds' => [], 'champion' => null];
        }

        $roundNumbers = $matches->pluck('round_number')->unique()->values();

        // Single-elimination brackets use descending round sizes; default to descending.
        $roundNumbers = $roundNumbers->sortDesc()->values();

        $rounds = $roundNumbers->map(function ($roundNumber) use ($matches): array {
            $roundMatches = $matches
                ->where('round_number', $roundNumber)
                ->sortBy('match_number')
                ->values();

            $label = match ((int) $roundNumber) {
                2 => 'Championship Final',
                4 => 'Semifinals',
                8 => 'Quarterfinals',
                16 => 'Round of 16',
                32 => 'Round of 32',
                default => 'Round '.$roundNumber,
            };

            return [
                'round' => $roundNumber,
                'label' => $label,
                'matches' => $roundMatches,
            ];
        })->values()->all();

        $finalMatch = $matches->firstWhere('round_number', 2)
            ?? $matches->sortByDesc('round_number')->first();

        $champion = null;
        if ($finalMatch && in_array($finalMatch->status, ['final', 'forfeit'], true)) {
            $champion = $finalMatch->winnerTeam;
        }

        return [
            'hasBracket' => true,
            'rounds' => $rounds,
            'champion' => $champion,
        ];
    }

    public function render(): View
    {
        return view('livewire.organizer.bracket-show');
    }
}
