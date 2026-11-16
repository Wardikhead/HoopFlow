<?php

namespace App\Livewire\Coach;

use App\Livewire\SecureComponent;
use App\Models\Division;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Rules\EligibleBirthdate;
use App\Services\AuditLogger;
use App\Services\EligibilityService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * @property-read Tournament|null $tournament
 * @property-read Team|null $existingTeam
 * @property-read Division|null $division
 * @property-read int $divisionAgeMin
 * @property-read int $divisionAgeMax
 * @property-read float $entryFee
 */
#[Layout('layouts.public')]
#[Title('Register team')]
class TeamRegister extends SecureComponent
{
    /**
     * @return array<int, string>
     */
    protected function allowedRoles(): array
    {
        return ['coach'];
    }

    /** @var array<int, array{value: string, label: string}> */
    public const COLORS = [
        ['value' => '#ea580c', 'label' => 'Orange'],
        ['value' => '#2563eb', 'label' => 'Blue'],
        ['value' => '#059669', 'label' => 'Green'],
        ['value' => '#7c3aed', 'label' => 'Violet'],
        ['value' => '#dc2626', 'label' => 'Red'],
        ['value' => '#475569', 'label' => 'Slate'],
    ];

    /** @var array<int, string> */
    public const POSITIONS = [
        'Point Guard (PG)',
        'Shooting Guard (SG)',
        'Small Forward (SF)',
        'Power Forward (PF)',
        'Center (C)',
    ];

    /** @var array<int, array{0: string, 1: int, 2: int}> */
    public const DEMO_PLAYERS = [
        ['Jayson Ramos', 4, 14],
        ['Kobe Cruz', 7, 15],
        ['Carl Gomez', 10, 14],
        ['Kai Fajardo', 11, 15],
        ['June Mar Diaz', 13, 14],
        ['Gabe Santos', 21, 15],
        ['CJ Reyes', 24, 14],
        ['Dwight Bautista', 30, 15],
    ];

    public ?int $tournament_id = null;

    public int $step = 0;

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
        $requested = request()->query('tournament');

        if ($this->tournament_id === null && is_numeric($requested)) {
            $this->tournament_id = (int) $requested;
        }

        if ($this->tournament_id === null) {
            $openId = Tournament::query()
                ->whereNull('archived_at')
                ->where('status', 'registration-open')
                ->orderBy('id')
                ->value('id');

            $this->tournament_id = is_numeric($openId) ? (int) $openId : null;
        }

        if ($this->players === []) {
            for ($i = 0; $i < 8; $i++) {
                $this->players[] = $this->blankPlayer();
            }
        }

        if ($this->coach === '') {
            $this->coach = $this->defaultCoachName();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function blankPlayer(): array
    {
        return [
            'name' => '',
            'number' => '',
            'position' => self::POSITIONS[0],
            'birthdate' => '',
        ];
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        return $this->tournament_id !== null
            ? Tournament::query()->whereNull('archived_at')->find($this->tournament_id)
            : null;
    }

    #[Computed]
    public function existingTeam(): ?Team
    {
        $user = auth()->user();
        $tournament = $this->tournament;

        if ($user === null || ! $tournament instanceof Tournament) {
            return null;
        }

        return Team::query()
            ->where('coach_user_id', $user->id)
            ->whereHas('registrations', fn ($query) => $query->where('tournament_id', $tournament->id))
            ->first();
    }

    #[Computed]
    public function division(): ?Division
    {
        return $this->tournament?->divisions()->first();
    }

    #[Computed]
    public function divisionAgeMin(): int
    {
        return $this->division->age_min ?? 8;
    }

    #[Computed]
    public function divisionAgeMax(): int
    {
        return $this->division->age_max ?? 99;
    }

    #[Computed]
    public function entryFee(): float
    {
        return $this->tournament?->entryFee() ?? 0.0;
    }

    public function fillDemo(): void
    {
        $this->name = 'San Antonio Vipers U17';
        $this->shortName = 'V17';
        $this->city = 'Pasig City';
        $this->coach = $this->defaultCoachName();
        $this->avatarColor = '#ea580c';

        $this->players = array_map(function (array $player, int $index): array {
            return [
                'name' => $player[0],
                'number' => (string) $player[1],
                'position' => self::POSITIONS[$index % count(self::POSITIONS)],
                'birthdate' => $this->demoBirthdate($player[2]),
            ];
        }, self::DEMO_PLAYERS, array_keys(self::DEMO_PLAYERS));

        $this->paymentMethod = 'GCash';
        $this->paymentRef = 'GCASH-9090909';
        $this->resetValidation();

        $this->dispatch(
            'hf-toast',
            title: 'Demo data loaded',
            message: 'Demo team, roster, and payment reference filled in. Review before submitting.',
            tone: 'info',
        );
    }

    protected function demoBirthdate(int $age): string
    {
        $reference = $this->tournament->start_date ?? now();

        return $reference->copy()->subYears($age)->format('Y-m-d');
    }

    public function ageAtTournamentStart(?string $birthdate): int|string
    {
        if ($birthdate === null || $birthdate === '') {
            return '—';
        }

        $reference = $this->tournament->start_date ?? now();

        return (int) Carbon::parse($birthdate)->diffInYears($reference);
    }

    public function addPlayer(): void
    {
        if (count($this->players) >= 12) {
            $this->dispatch('hf-toast', title: 'Roster full', message: 'A roster can have at most 12 players.', tone: 'warning');

            return;
        }

        $this->players[] = $this->blankPlayer();
    }

    public function removePlayer(int $index): void
    {
        if (count($this->players) <= 8) {
            $this->dispatch('hf-toast', title: 'Roster minimum', message: 'Keep at least 8 players on the roster.', tone: 'warning');

            return;
        }

        unset($this->players[$index]);
        $this->players = array_values($this->players);
    }

    public function nextStep(): void
    {
        $this->resetValidation();

        if ($this->step === 0) {
            $this->validate($this->teamRules(), $this->validationMessages());
            $this->step = 1;

            return;
        }

        if ($this->step === 1) {
            $this->validate($this->rosterRules(), $this->validationMessages());

            if (! $this->validateJerseyNumbers()) {
                return;
            }

            $this->step = 2;

            return;
        }

        if ($this->step === 2) {
            $this->validate($this->paymentRules(), $this->validationMessages());
            $this->step = 3;
        }
    }

    public function previousStep(): void
    {
        $this->resetValidation();
        $this->step = max(0, $this->step - 1);
    }

    public function save(): void
    {
        $this->resetValidation();

        try {
            $this->validate($this->teamRules(), $this->validationMessages());
        } catch (ValidationException $exception) {
            $this->step = 0;

            throw $exception;
        }

        try {
            $this->validate($this->rosterRules(), $this->validationMessages());
        } catch (ValidationException $exception) {
            $this->step = 1;

            throw $exception;
        }

        if (! $this->validateJerseyNumbers()) {
            $this->step = 1;

            return;
        }

        try {
            $this->validate($this->paymentRules(), $this->validationMessages());
        } catch (ValidationException $exception) {
            $this->step = 2;

            throw $exception;
        }

        $tournament = $this->tournament;

        if (! $tournament instanceof Tournament) {
            $this->step = 0;
            $this->addError('tournament_id', 'Choose an open tournament from the directory.');

            return;
        }

        if ($tournament->status !== 'registration-open') {
            $this->step = 0;
            $this->addError('tournament_id', 'Registration is not open for this tournament.');

            return;
        }

        if ($this->existingTeam instanceof Team) {
            $this->step = 0;
            $this->addError('name', 'Your team is already registered for this tournament.');

            return;
        }

        $division = $tournament->divisions()->firstOrFail();
        $user = auth()->user();

        $organization = Organization::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Organization'],
        );

        $duplicateName = Team::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($this->name))])
            ->whereHas('registrations', fn ($query) => $query->where('tournament_id', $tournament->id))
            ->exists();

        if ($duplicateName) {
            $this->step = 0;
            $this->addError('name', 'A team with that name is already registered.');

            return;
        }

        $createdTeam = null;

        DB::transaction(function () use ($tournament, $division, $user, $organization, &$createdTeam): void {
            $eligibility = app(EligibilityService::class);

            $team = Team::create([
                'organization_id' => $organization->id,
                'name' => trim($this->name),
                'short_name' => mb_strtoupper(trim($this->shortName)),
                'avatar_color' => $this->avatarColor,
                'city' => trim($this->city),
                'coach_name' => trim($this->coach),
                'slug' => Str::slug(trim($this->name).'-'.trim($this->shortName)),
                'coach_user_id' => $user?->id,
            ]);

            foreach ($this->players as $player) {
                $name = trim((string) $player['name']);
                $parts = preg_split('/\s+/', $name, 2) ?: [];
                $firstName = $parts[0] ?? $name;
                $lastName = $parts[1] ?? '';

                $createdPlayer = Player::create([
                    'team_id' => $team->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'birthdate' => (string) $player['birthdate'],
                    'jersey_number' => (int) $player['number'],
                    'position' => (string) $player['position'],
                ]);

                $eligibility->initializePlayer($createdPlayer);
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
                'amount' => $tournament->entryFee(),
                'currency' => 'PHP',
                'method' => $this->paymentMethod,
                'reference_no' => trim($this->paymentRef),
                'status' => 'pending',
                'recorded_by' => $user?->id,
            ]);

            app(AuditLogger::class)->log(
                'team.register',
                'team',
                $team->id,
                null,
                [
                    'players' => count($this->players),
                    'ref' => trim($this->paymentRef),
                ],
                $organization->id,
                $user?->id,
            );

            $createdTeam = $team;
        });

        $this->dispatch(
            'hf-toast',
            title: 'Team submitted',
            message: 'Registration submitted. The organizer will review the roster and payment.',
            tone: 'success',
        );

        $this->redirectRoute('myTeam', array_filter([
            'team' => $createdTeam?->id,
            'tournament' => $tournament->id,
        ]));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function teamRules(): array
    {
        return [
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'shortName' => ['required', 'string', 'min:2', 'max:3'],
            'city' => ['required', 'string', 'min:2', 'max:255'],
            'coach' => ['required', 'string', 'min:3', 'max:255'],
            'avatarColor' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rosterRules(): array
    {
        return [
            'players' => ['required', 'array', 'min:8', 'max:12'],
            'players.*.name' => ['required', 'string', 'min:2', 'max:255'],
            'players.*.number' => ['required', 'integer', 'min:1', 'max:99'],
            'players.*.position' => ['required', 'string', 'in:'.implode(',', self::POSITIONS)],
            'players.*.birthdate' => ['required', new EligibleBirthdate($this->division, $this->tournament)],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function paymentRules(): array
    {
        return [
            'paymentMethod' => ['required', 'string', 'in:GCash,Maya,Bank transfer,Cash'],
            'paymentRef' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{6,24}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationMessages(): array
    {
        return [
            'name.required' => 'Enter at least 3 characters.',
            'name.min' => 'Enter at least 3 characters.',
            'shortName.required' => 'Use 2 or 3 letters.',
            'shortName.min' => 'Use 2 or 3 letters.',
            'city.required' => 'Enter a city.',
            'city.min' => 'Enter a city.',
            'coach.required' => 'Enter the coach name.',
            'coach.min' => 'Enter the coach name.',
            'players.min' => 'A roster needs 8 to 12 players.',
            'players.max' => 'A roster needs 8 to 12 players.',
            'players.*.name.required' => 'Enter the player name.',
            'players.*.name.min' => 'Enter the player name.',
            'players.*.number.integer' => 'Use 1–99.',
            'players.*.number.min' => 'Use 1–99.',
            'players.*.number.max' => 'Use 1–99.',
            'players.*.birthdate.required' => 'Enter the player birthdate.',
            'paymentRef.regex' => 'Use 6–24 letters, numbers, or dashes.',
        ];
    }

    protected function validateJerseyNumbers(): bool
    {
        $numbers = [];
        $valid = true;

        foreach ($this->players as $index => $player) {
            $number = (int) ($player['number'] ?? 0);

            if (in_array($number, $numbers, true)) {
                $this->addError('players.'.$index.'.number', 'Jersey '.$number.' is already taken.');
                $valid = false;
            }

            $numbers[] = $number;
        }

        return $valid;
    }

    protected function defaultCoachName(): string
    {
        $user = auth()->user();

        if ($user === null) {
            return '';
        }

        return trim((string) preg_replace('/^Coach\s+/i', '', $user->name));
    }

    public function render(): View
    {
        return view('livewire.coach.team-register');
    }
}
