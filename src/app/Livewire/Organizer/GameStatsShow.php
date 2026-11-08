<?php

namespace App\Livewire\Organizer;

use App\Models\MatchModel;
use App\Services\BracketService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Game Stats')]
class GameStatsShow extends Component
{
    public ?int $matchId = null;

    public ?int $score1 = null;

    public ?int $score2 = null;

    public string $reason = '';

    public bool $showScoreConsole = false;

    public bool $showCorrection = false;

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
    }

    #[Computed]
    public function match(): ?MatchModel
    {
        if ($this->matchId === null) {
            return null;
        }

        return MatchModel::query()
            ->with(['homeTeam', 'awayTeam', 'winnerTeam', 'tournament', 'playerStats.player', 'playerStats.team'])
            ->find($this->matchId);
    }

    public function openScoreConsole(int|string|null $matchId = null): void
    {
        if ($matchId !== null && is_numeric($matchId)) {
            $this->matchId = (int) $matchId;
        }

        $this->showScoreConsole = true;
        $this->showCorrection = false;

        $match = $this->match();

        $this->score1 = $match?->home_score;
        $this->score2 = $match?->away_score;
        $this->reason = '';
    }

    public function submitScore(BracketService $bracketService): void
    {
        if ($this->matchId === null) {
            return;
        }

        $this->validate([
            'score1' => ['required', 'integer', 'min:0'],
            'score2' => ['required', 'integer', 'min:0'],
        ]);

        $match = MatchModel::findOrFail($this->matchId);

        Gate::authorize('correctScore', $match);

        try {
            $bracketService->correctScore(
                $match,
                (int) ($this->score1 ?? 0),
                (int) ($this->score2 ?? 0),
                $this->reason !== '' ? $this->reason : 'Score entry',
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        $this->showScoreConsole = false;
    }

    public function openCorrection(int|string|null $matchId = null): void
    {
        if ($matchId !== null && is_numeric($matchId)) {
            $this->matchId = (int) $matchId;
        }

        $this->showCorrection = true;
        $this->showScoreConsole = false;

        $match = $this->match();

        $this->score1 = $match?->home_score;
        $this->score2 = $match?->away_score;
        $this->reason = '';
    }

    public function submitCorrection(BracketService $bracketService): void
    {
        if ($this->matchId === null) {
            return;
        }

        $this->validate([
            'score1' => ['required', 'integer', 'min:0'],
            'score2' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:1'],
        ]);

        $match = MatchModel::findOrFail($this->matchId);

        Gate::authorize('correctScore', $match);

        try {
            $bracketService->correctScore(
                $match,
                (int) ($this->score1 ?? 0),
                (int) ($this->score2 ?? 0),
                $this->reason,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        $this->showCorrection = false;
    }

    public function render(): View
    {
        return view('livewire.organizer.game-stats-show');
    }
}
