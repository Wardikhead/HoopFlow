<?php

namespace App\Livewire\Coach;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Register team')]
class TeamRegister extends Component
{
    public ?int $tournament_id = null;

    public string $name = '';

    public string $shortName = '';

    public string $city = '';

    public string $coach = '';

    public string $avatarColor = '#ea580c';

    /** @var array<int, array<string, mixed>> */
    public array $players = [];

    public string $paymentMethod = 'GCash';

    public string $paymentRef = '';

    public function mount(): void
    {
        if ($this->tournament_id === null) {
            $query = request()->query('tournament');
            if (is_numeric($query)) {
                $this->tournament_id = (int) $query;
            }
        }

        if ($this->players === []) {
            for ($i = 0; $i < 8; $i++) {
                $this->players[] = $this->blankPlayer();
            }
        }

        if ($this->coach === '') {
            $user = auth()->user();
            if ($user !== null) {
                $this->coach = trim($user->first_name.' '.$user->last_name);
            }
        }
    }

    /** @return array<string, mixed> */
    protected function blankPlayer(): array
    {
        return [
            'name' => '',
            'number' => '',
            'position' => 'Point Guard (PG)',
            'age' => '',
            'guardianConsent' => false,
            'documentStatus' => 'pending',
        ];
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments()
    {
        return Tournament::query()->orderBy('name')->get();
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        return $this->tournament_id !== null ? Tournament::find($this->tournament_id) : null;
    }

    public function addPlayer(): void
    {
        if (count($this->players) >= 12) {
            return;
        }

        $this->players[] = $this->blankPlayer();
    }

    public function removePlayer(int $index): void
    {
        if (count($this->players) <= 8) {
            return;
        }

        unset($this->players[$index]);
        $this->players = array_values($this->players);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'shortName' => ['required', 'string', 'min:2', 'max:3'],
            'city' => ['required', 'string', 'min:2', 'max:255'],
            'coach' => ['required', 'string', 'min:3', 'max:255'],
            'avatarColor' => ['required', 'string', 'max:32'],
            'players' => ['required', 'array', 'min:8', 'max:12'],
            'players.*.name' => ['required', 'string', 'min:2', 'max:255'],
            'players.*.number' => ['required', 'integer', 'min:1', 'max:99'],
            'players.*.position' => ['required', 'string', 'max:64'],
            'players.*.age' => ['required', 'integer', 'min:8', 'max:99'],
            'players.*.guardianConsent' => ['accepted'],
            'players.*.documentStatus' => ['required', 'string', 'in:pending,verified'],
            'paymentMethod' => ['required', 'string', 'in:GCash,Maya,Bank transfer,Cash'],
            'paymentRef' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{6,24}$/'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $numbers = [];
        $duplicateFound = false;
        foreach ($this->players as $index => $player) {
            $number = (int) ($player['number'] ?? 0);
            if (in_array($number, $numbers, true)) {
                $this->addError("players.$index.number", 'Jersey number already taken.');
                $duplicateFound = true;
            }
            $numbers[] = $number;
        }

        if ($duplicateFound) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournament_id);
        $division = $tournament->divisions()->firstOrFail();
        $user = auth()->user();

        $organization = Organization::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Organization'],
        );

        $teamId = DB::transaction(function () use ($tournament, $division, $user, $organization) {
            $team = Team::create([
                'organization_id' => $organization->id,
                'name' => $this->name,
                'slug' => Str::slug($this->name.'-'.$this->shortName),
                'coach_user_id' => $user?->id,
            ]);

            foreach ($this->players as $player) {
                $name = trim((string) $player['name']);
                $parts = preg_split('/\s+/', $name, 2) ?: [];
                $firstName = $parts[0] ?? $name;
                $lastName = $parts[1] ?? '';

                Player::create([
                    'team_id' => $team->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'birthdate' => now()->subYears((int) $player['age'])->format('Y-m-d'),
                    'jersey_number' => (int) $player['number'],
                    'position' => (string) $player['position'],
                    'consent_at' => (bool) $player['guardianConsent'] ? now() : null,
                    'eligibility_status' => $player['documentStatus'] === 'verified' ? 'verified' : 'pending',
                ]);
            }

            $registration = Registration::create([
                'tournament_id' => $tournament->id,
                'division_id' => $division->id,
                'team_id' => $team->id,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            Payment::create([
                'registration_id' => $registration->id,
                'amount' => $division->entry_fee ?? 0,
                'currency' => 'PHP',
                'method' => $this->paymentMethod,
                'reference_no' => $this->paymentRef,
                'status' => 'pending',
                'recorded_by' => $user?->id,
            ]);

            return $team->id;
        });

        $this->redirectRoute('myTeam');
    }

    public function render(): View
    {
        return view('livewire.coach.team-register');
    }
}
