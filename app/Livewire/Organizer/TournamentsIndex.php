<?php

namespace App\Livewire\Organizer;

use App\Models\Tournament;
use App\Services\TournamentLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Tournaments')]
class TournamentsIndex extends Component
{
    public string $search = '';

    public string $status = '';

    public string $division = '';

    public bool $showConfirm = false;

    public string $confirmTitle = 'Confirm action';

    public string $confirmMessage = '';

    public string $confirmLabel = 'Confirm';

    public string $confirmTone = 'primary';

    public string $confirmAction = '';

    public ?int $confirmTournamentId = null;

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->division = '';
    }

    public function requestArchive(int $tournamentId): void
    {
        $tournament = Tournament::query()->find($tournamentId);

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('archive', $tournament);

        $this->openConfirm(
            'Archive '.$tournament->name.'?',
            'The tournament leaves the default list. Its registrations, bracket, and results are kept, and you can restore it from the Archived filter.',
            'Archive tournament',
            'primary',
            'archive-tournament',
            $tournament->id,
        );
    }

    public function requestRestore(int $tournamentId): void
    {
        $tournament = Tournament::query()->find($tournamentId);

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('unarchive', $tournament);

        $this->openConfirm(
            'Restore '.$tournament->name.'?',
            'The tournament returns to the default list with its original status and schedule intact.',
            'Restore tournament',
            'primary',
            'restore-tournament',
            $tournament->id,
        );
    }

    public function requestDelete(int $tournamentId): void
    {
        $tournament = Tournament::query()->find($tournamentId);

        if (! $tournament instanceof Tournament) {
            return;
        }

        Gate::authorize('delete', $tournament);

        $this->openConfirm(
            'Delete '.$tournament->name.'?',
            'This takes the tournament off every list and desk. The record is retained behind the scenes for recovery, but it cannot be opened again from the app.',
            'Delete tournament',
            'danger',
            'delete-tournament',
            $tournament->id,
        );
    }

    public function confirm(): void
    {
        $action = $this->confirmAction;
        $tournamentId = $this->confirmTournamentId;
        $this->resetConfirm();

        if ($tournamentId === null) {
            return;
        }

        $tournament = Tournament::query()->find($tournamentId);

        if (! $tournament instanceof Tournament) {
            return;
        }

        $lifecycle = app(TournamentLifecycleService::class);

        $handled = match ($action) {
            'archive-tournament' => Gate::allows('archive', $tournament) && $lifecycle->archive($tournament),
            'restore-tournament' => Gate::allows('unarchive', $tournament) && $lifecycle->restore($tournament),
            'delete-tournament' => Gate::allows('delete', $tournament) && $lifecycle->delete($tournament),
            default => false,
        };

        if (! $handled) {
            $this->toast('That action is not available for this tournament.', 'Nothing changed', 'warning');

            return;
        }

        unset($this->tournaments, $this->divisions);

        match ($action) {
            'archive-tournament' => $this->toast(
                $tournament->name.' is out of the default list. Restore it from the Archived filter.',
                'Tournament archived',
            ),
            'restore-tournament' => $this->toast(
                $tournament->name.' is back in the default list.',
                'Tournament restored',
            ),
            default => $this->toast(
                $tournament->name.' is hidden from every list.',
                'Tournament deleted',
            ),
        };
    }

    public function cancelConfirm(): void
    {
        $this->resetConfirm();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function divisions(): array
    {
        return Tournament::query()
            ->with('divisions')
            ->get()
            ->flatMap(fn (Tournament $tournament) => $tournament->divisions->pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments()
    {
        $query = Tournament::query()->with(['divisions', 'registrations', 'matches', 'matchSlots.court.venue']);

        if ($this->status === 'archived') {
            $query->whereNotNull('archived_at');
        } elseif ($this->status !== '') {
            $query->whereNull('archived_at')->where('status', $this->status);
        } else {
            $query->whereNull('archived_at');
        }

        if ($this->division !== '') {
            $query->whereHas('divisions', fn ($divisionQuery) => $divisionQuery->where('name', $this->division));
        }

        $tournaments = $query->get();
        $term = mb_strtolower(trim($this->search));

        if ($term === '') {
            return $tournaments;
        }

        return $tournaments
            ->filter(function (Tournament $tournament) use ($term): bool {
                $haystack = $tournament->name.' '.$tournament->venueName().' '.$tournament->divisions->pluck('name')->implode(' ');

                return str_contains(mb_strtolower($haystack), $term);
            })
            ->values();
    }

    protected function openConfirm(
        string $title,
        string $message,
        string $label,
        string $tone,
        string $action,
        int $tournamentId,
    ): void {
        $this->confirmTitle = $title;
        $this->confirmMessage = $message;
        $this->confirmLabel = $label;
        $this->confirmTone = $tone;
        $this->confirmAction = $action;
        $this->confirmTournamentId = $tournamentId;
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
        $this->confirmTournamentId = null;
    }

    protected function toast(string $message, string $title, string $tone = 'success'): void
    {
        $this->dispatch('hf-toast', title: $title, message: $message, tone: $tone);
    }

    public function render(): View
    {
        return view('livewire.organizer.tournaments-index');
    }
}
