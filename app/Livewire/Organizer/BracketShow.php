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

    public function render(): View
    {
        return view('livewire.organizer.bracket-show');
    }
}
