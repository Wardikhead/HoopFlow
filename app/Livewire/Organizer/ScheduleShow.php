<?php

namespace App\Livewire\Organizer;

use App\Models\Tournament;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Schedule')]
class ScheduleShow extends Component
{
    public int $tournamentId = 0;

    public string $viewMode = 'month';

    public string $currentMonth = '';

    public ?string $selectedDay = null;

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

        $tournament = $this->tournamentId > 0 ? Tournament::find($this->tournamentId) : null;
        $raw = $tournament?->start_date;
        $anchor = $raw !== null
            ? substr((string) $raw, 0, 10)
            : now()->format('Y-m-d');

        $this->selectedDay = $anchor;
        $this->currentMonth = substr($anchor, 0, 7);
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        return Tournament::find($this->tournamentId);
    }

    public function generateSchedule(SchedulingService $schedulingService): void
    {
        $tournament = Tournament::findOrFail($this->tournamentId);

        $schedulingService->generate($tournament);
    }

    public function navigateMonth(int $delta): void
    {
        $date = Carbon::createFromFormat('Y-m', $this->currentMonth) ?: now();

        $this->currentMonth = $date->addMonths($delta)->format('Y-m');
    }

    public function selectDay(?string $day): void
    {
        $this->selectedDay = $day;

        if ($day !== null && strlen($day) >= 7) {
            $this->currentMonth = substr($day, 0, 7);
        }
    }

    public function render(): View
    {
        return view('livewire.organizer.schedule-show');
    }
}
