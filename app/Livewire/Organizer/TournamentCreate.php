<?php

namespace App\Livewire\Organizer;

use App\Models\Court;
use App\Models\Division;
use App\Models\GameRule;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\Venue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Create tournament')]
class TournamentCreate extends Component
{
    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $start_date = '';

    public string $end_date = '';

    public string $status = 'draft';

    public int $bracketSlots = 8;

    public float $fee = 0;

    public string $division_name = '';

    public ?int $age_max = null;

    public string $venue_name = '';

    /** @var array<int, string> */
    public array $courts = ['Court 1', 'Court 2'];

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', 'in:draft,registration-open,active,upcoming'],
            'bracketSlots' => ['required', 'integer', 'min:2', 'max:32'],
            'fee' => ['required', 'numeric', 'min:0'],
            'division_name' => ['required', 'string', 'min:2', 'max:255'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:99'],
            'venue_name' => ['required', 'string', 'min:2', 'max:255'],
            'courts' => ['required', 'array', 'min:1'],
            'courts.*' => ['required', 'string', 'min:2'],
        ];
    }

    public function addCourt(): void
    {
        $this->courts[] = '';
    }

    public function removeCourt(int $index): void
    {
        unset($this->courts[$index]);
        $this->courts = array_values($this->courts);
    }

    public function save(): void
    {
        $this->validate();

        $organization = Organization::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Organization'],
        );

        $user = auth()->user();
        $slug = $this->slug !== '' ? $this->slug : Str::slug($this->name);

        $tournament = DB::transaction(function () use ($organization, $user, $slug) {
            $tournament = Tournament::create([
                'organization_id' => $organization->id,
                'name' => $this->name,
                'slug' => $slug,
                'description' => $this->description !== '' ? $this->description : null,
                'start_date' => $this->start_date !== '' ? $this->start_date : null,
                'end_date' => $this->end_date !== '' ? $this->end_date : null,
                'status' => $this->status,
                'rules_json' => ['slots' => $this->bracketSlots, 'fee' => $this->fee],
                'created_by' => $user?->id,
            ]);

            $division = Division::create([
                'tournament_id' => $tournament->id,
                'name' => $this->division_name,
                'age_max' => $this->age_max,
                'max_teams' => $this->bracketSlots,
                'entry_fee' => $this->fee,
            ]);

            GameRule::create([
                'tournament_id' => $tournament->id,
                'division_id' => $division->id,
            ]);

            $venue = Venue::create([
                'organization_id' => $organization->id,
                'name' => $this->venue_name,
            ]);

            foreach ($this->courts as $courtName) {
                Court::create([
                    'venue_id' => $venue->id,
                    'name' => $courtName,
                ]);
            }

            return $tournament;
        });

        $this->redirectRoute('tournamentShow', $tournament);
    }

    public function render(): View
    {
        return view('livewire.organizer.tournament-create');
    }
}
