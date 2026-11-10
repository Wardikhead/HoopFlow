<?php

namespace App\Livewire\Coach;

use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\DashboardService;
use App\Services\EligibilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Team> $teams
 * @property-read Team|null $team
 * @property-read Registration|null $registration
 * @property-read Tournament|null $tournament
 * @property-read Payment|null $payment
 * @property-read array{wins: int, losses: int} $record
 * @property-read MatchModel|null $nextMatch
 * @property-read bool $rosterLocked
 * @property-read float $entryFee
 * @property-read string $venueName
 */
#[Layout('layouts.public')]
#[Title('My team')]
class MyTeam extends Component
{
    /** @var array<int, string> */
    public const POSITIONS = TeamRegister::POSITIONS;

    /** @var array<int, string> */
    public const PAYMENT_METHODS = ['GCash', 'Maya', 'Bank transfer', 'Cash'];

    /** @var array<int, string> */
    public const DOCUMENT_STATUSES = ['verified', 'pending', 'missing'];

    public ?int $teamId = null;

    public ?int $tournamentId = null;

    public bool $showPlayerDialog = false;

    public ?int $editingPlayerId = null;

    public string $playerName = '';

    public string $playerNumber = '';

    public string $playerPosition = '';

    public string $playerAge = '';

    public bool $playerConsent = false;

    public string $playerDocumentStatus = 'pending';

    public bool $showRemoveDialog = false;

    public ?int $removingPlayerId = null;

    public string $removingPlayerName = '';

    public bool $showPaymentDialog = false;

    public string $paymentMethod = 'GCash';

    public string $paymentReference = '';

    public function mount(): void
    {
        $user = auth()->user();

        $teams = $user !== null
            ? Team::query()
                ->where('coach_user_id', $user->id)
                ->with('registrations')
                ->orderBy('name')
                ->get()
            : new Collection;

        $requestedTournament = request()->query('tournament');
        $this->tournamentId = is_numeric($requestedTournament)
            ? Tournament::query()->whereKey((int) $requestedTournament)->value('id')
            : null;

        $requestedTeam = request()->query('team');
        $requestedTeamId = is_numeric($requestedTeam) ? (int) $requestedTeam : null;

        $fallbackTeamId = $teams->contains('id', $requestedTeamId)
            ? $requestedTeamId
            : $teams->first()?->id;

        if ($this->tournamentId === null) {
            $this->teamId = $fallbackTeamId;

            return;
        }

        $tournamentTeams = $teams->filter(
            fn (Team $team): bool => $team->registrations->contains('tournament_id', $this->tournamentId)
        );

        $this->teamId = $tournamentTeams->contains('id', $requestedTeamId)
            ? $requestedTeamId
            : ($tournamentTeams->first()->id ?? $fallbackTeamId);
    }

    /**
     * @return Collection<int, Team>
     */
    #[Computed]
    public function teams(): Collection
    {
        $userId = auth()->id();

        if ($userId === null) {
            return new Collection;
        }

        return Team::query()
            ->where('coach_user_id', $userId)
            ->with('registrations')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function team(): ?Team
    {
        $userId = auth()->id();

        if ($this->teamId === null || $userId === null) {
            return null;
        }

        return Team::query()
            ->with([
                'coachUser',
                'players' => fn ($query) => $query->orderBy('jersey_number')->orderBy('id'),
                'registrations.tournament',
                'registrations.division',
                'registrations.payment',
            ])
            ->where('coach_user_id', $userId)
            ->find($this->teamId);
    }

    protected function registrationForTeam(Team $team): ?Registration
    {
        $registrations = $team->registrations;

        if ($this->tournamentId !== null) {
            $registrations = $registrations->where('tournament_id', $this->tournamentId);
        }

        $registration = $registrations->sortByDesc('id')->first();

        return $registration instanceof Registration ? $registration : null;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function registrationStatuses(): array
    {
        $statuses = [];

        foreach ($this->teams as $team) {
            $statuses[(int) $team->id] = $this->registrationForTeam($team)->status ?? 'submitted';
        }

        return $statuses;
    }

    #[Computed]
    public function registration(): ?Registration
    {
        $team = $this->team;

        return $team instanceof Team ? $this->registrationForTeam($team) : null;
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        $tournament = $this->registration?->tournament;

        return $tournament instanceof Tournament ? $tournament : null;
    }

    #[Computed]
    public function payment(): ?Payment
    {
        $payment = $this->registration?->payment;

        return $payment instanceof Payment ? $payment : null;
    }

    /**
     * @return array{wins: int, losses: int}
     */
    #[Computed]
    public function record(): array
    {
        $team = $this->team;

        return $team instanceof Team
            ? app(DashboardService::class)->teamRecord($team)
            : ['wins' => 0, 'losses' => 0];
    }

    #[Computed]
    public function nextMatch(): ?MatchModel
    {
        $team = $this->team;
        $tournament = $this->tournament;

        if (! $team instanceof Team || ! $tournament instanceof Tournament) {
            return null;
        }

        $matches = MatchModel::query()
            ->with(['tournament', 'matchSlots.court'])
            ->where('tournament_id', $tournament->id)
            ->where(function ($query) use ($team): void {
                $query->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id);
            })
            ->where('status', 'scheduled')
            ->get();

        return $matches
            ->sortBy(function (MatchModel $match): int {
                $start = $match->matchSlots->min('start_at');

                return $start instanceof \DateTimeInterface ? $start->getTimestamp() : PHP_INT_MAX;
            })
            ->first();
    }

    #[Computed]
    public function rosterLocked(): bool
    {
        $registration = $this->registration;

        return $registration instanceof Registration
            && ($registration->roster_locked_at !== null || $registration->status === 'approved');
    }

    #[Computed]
    public function entryFee(): float
    {
        $payment = $this->payment;

        if ($payment instanceof Payment) {
            return (float) $payment->amount;
        }

        return (float) ($this->tournament?->entryFee() ?? 0.0);
    }

    #[Computed]
    public function venueName(): string
    {
        return $this->tournament?->venueName() ?? 'Venue';
    }

    public function paymentChipStatus(): string
    {
        $status = $this->payment->status ?? 'pending';

        return $status === 'verified' ? 'paid' : $status;
    }

    public function paymentStatusText(): string
    {
        return $this->paymentChipStatus();
    }

    public function coachName(): string
    {
        $team = $this->team;

        if (! $team instanceof Team) {
            return 'Coach';
        }

        if (is_string($team->coach_name) && trim($team->coach_name) !== '') {
            return $team->coach_name;
        }

        return $team->coachUser->name ?? 'Coach';
    }

    public function openPlayerDialog(?int $playerId = null): void
    {
        $team = $this->team;

        if (! $team instanceof Team) {
            return;
        }

        if ($this->rosterLocked) {
            $this->dispatch('hf-toast', title: 'Roster locked', message: 'The organizer verified this roster. Contact the committee to make a change.', tone: 'warning');

            return;
        }

        $this->resetValidation();

        if ($playerId !== null) {
            $player = $team->players->firstWhere('id', $playerId);

            if (! $player instanceof Player) {
                return;
            }

            $this->editingPlayerId = (int) $player->id;
            $this->playerName = trim($player->first_name.' '.$player->last_name);
            $this->playerNumber = (string) $player->jersey_number;
            $this->playerPosition = self::POSITIONS[$this->positionIndex($player->position)] ?? self::POSITIONS[0];
            $this->playerAge = $player->birthdate !== null ? (string) $player->birthdate->age : '';
            $this->playerConsent = $player->consent_at !== null;
            $this->playerDocumentStatus = in_array($player->eligibility_status, self::DOCUMENT_STATUSES, true)
                ? $player->eligibility_status
                : 'pending';
        } else {
            if ($team->players->count() >= 12) {
                $this->dispatch('hf-toast', title: 'Roster full', message: 'A roster can have at most 12 players.', tone: 'warning');

                return;
            }

            $this->editingPlayerId = null;
            $this->playerName = '';
            $this->playerNumber = '';
            $this->playerPosition = self::POSITIONS[0];
            $this->playerAge = '';
            $this->playerConsent = false;
            $this->playerDocumentStatus = 'pending';
        }

        $this->showPlayerDialog = true;
    }

    public function closePlayerDialog(): void
    {
        $this->showPlayerDialog = false;
        $this->editingPlayerId = null;
        $this->resetValidation();
    }

    public function savePlayer(): void
    {
        $team = $this->team;

        if (! $team instanceof Team) {
            return;
        }

        if ($this->rosterLocked) {
            $this->closePlayerDialog();
            $this->dispatch('hf-toast', title: 'Roster locked', message: 'The organizer verified this roster. Contact the committee to make a change.', tone: 'warning');

            return;
        }

        $this->validate($this->playerRules(), $this->playerMessages());

        $editing = $this->editingPlayerId !== null;
        $player = $editing
            ? $team->players->firstWhere('id', $this->editingPlayerId)
            : null;

        if ($editing && ! $player instanceof Player) {
            return;
        }

        if (! $editing && $team->players->count() >= 12) {
            $this->dispatch('hf-toast', title: 'Roster full', message: 'A roster can have at most 12 players.', tone: 'warning');

            return;
        }

        $name = trim($this->playerName);
        $parts = preg_split('/\s+/', $name, 2) ?: [];
        $firstName = $parts[0] ?? $name;
        $lastName = $parts[1] ?? '';
        $documentStatus = $this->playerDocumentStatus;
        $consent = $this->playerConsent;
        $number = (int) $this->playerNumber;
        $age = (int) $this->playerAge;

        DB::transaction(function () use ($team, $player, $firstName, $lastName, $name, $number, $age, $consent, $documentStatus): void {
            $eligibility = app(EligibilityService::class);

            $attributes = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birthdate' => now()->subYears($age)->format('Y-m-d'),
                'jersey_number' => $number,
                'position' => $this->playerPosition,
                'consent_at' => $consent ? ($player instanceof Player ? ($player->consent_at ?? now()) : now()) : null,
                'eligibility_status' => $documentStatus,
                'verified_at' => $documentStatus === 'verified'
                    ? ($player instanceof Player ? ($player->verified_at ?? now()) : now())
                    : null,
            ];

            if ($player instanceof Player) {
                $before = [
                    'name' => trim($player->first_name.' '.$player->last_name),
                    'number' => $player->jersey_number,
                    'documentStatus' => $player->eligibility_status,
                ];

                $player->update($attributes);
                $eligibility->syncDocumentStatus($player, $documentStatus);

                $this->writeAudit($team, 'team.roster.update', $before, [
                    'name' => $name,
                    'number' => $number,
                    'documentStatus' => $documentStatus,
                ]);

                return;
            }

            $createdPlayer = $team->players()->create($attributes);
            $eligibility->syncDocumentStatus($createdPlayer, $documentStatus);

            $this->writeAudit($team, 'team.roster.add', null, [
                'name' => $name,
                'number' => $number,
                'documentStatus' => $documentStatus,
            ]);
        });

        unset($this->team, $this->registration, $this->tournament, $this->payment, $this->nextMatch);

        $this->closePlayerDialog();
        $this->dispatch(
            'hf-toast',
            title: 'Roster updated',
            message: $editing ? $name.' updated.' : $name.' added to the roster.',
            tone: 'success',
        );
    }

    public function confirmRemovePlayer(int $playerId): void
    {
        $team = $this->team;

        if (! $team instanceof Team || $this->rosterLocked) {
            return;
        }

        $player = $team->players->firstWhere('id', $playerId);

        if (! $player instanceof Player) {
            return;
        }

        $this->removingPlayerId = (int) $player->id;
        $this->removingPlayerName = trim($player->first_name.' '.$player->last_name);
        $this->showRemoveDialog = true;
    }

    public function cancelRemovePlayer(): void
    {
        $this->showRemoveDialog = false;
        $this->removingPlayerId = null;
        $this->removingPlayerName = '';
    }

    public function removePlayer(): void
    {
        $team = $this->team;

        if (! $team instanceof Team || $this->removingPlayerId === null) {
            return;
        }

        $player = $team->players->firstWhere('id', $this->removingPlayerId);

        if (! $player instanceof Player) {
            $this->cancelRemovePlayer();

            return;
        }

        if ($team->players->count() <= 5) {
            $this->cancelRemovePlayer();
            $this->dispatch('hf-toast', title: 'Roster minimum', message: 'A team must keep at least five players.', tone: 'warning');

            return;
        }

        $name = trim($player->first_name.' '.$player->last_name);
        $before = ['name' => $name, 'number' => $player->jersey_number];

        DB::transaction(function () use ($team, $player, $before): void {
            $player->delete();
            $this->writeAudit($team, 'team.roster.remove', $before, null);
        });

        unset($this->team, $this->registration, $this->tournament, $this->payment, $this->nextMatch);

        $this->cancelRemovePlayer();
        $this->dispatch('hf-toast', title: 'Roster updated', message: $name.' removed from the roster.', tone: 'success');
    }

    public function openPaymentDialog(): void
    {
        $payment = $this->payment;

        $this->resetValidation();
        $this->paymentMethod = $payment instanceof Payment && in_array($payment->method, self::PAYMENT_METHODS, true)
            ? $payment->method
            : 'GCash';
        $this->paymentReference = $payment->reference_no ?? '';
        $this->showPaymentDialog = true;
    }

    public function closePaymentDialog(): void
    {
        $this->showPaymentDialog = false;
        $this->resetValidation();
    }

    public function savePayment(): void
    {
        $team = $this->team;
        $registration = $this->registration;
        $payment = $this->payment;

        if (! $team instanceof Team || ! $registration instanceof Registration) {
            return;
        }

        if ($payment instanceof Payment && in_array($payment->status, ['verified', 'paid'], true)) {
            $this->closePaymentDialog();
            $this->dispatch('hf-toast', title: 'Payment verified', message: 'A verified payment reference cannot be changed.', tone: 'warning');

            return;
        }

        $this->validate([
            'paymentMethod' => ['required', 'string', 'in:'.implode(',', self::PAYMENT_METHODS)],
            'paymentReference' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{6,24}$/'],
        ], [
            'paymentReference.required' => 'Use 6–24 letters, numbers, or dashes.',
            'paymentReference.regex' => 'Use 6–24 letters, numbers, or dashes.',
        ]);

        $method = $this->paymentMethod;
        $reference = trim($this->paymentReference);
        $amount = (float) ($this->tournament?->entryFee() ?? $payment->amount ?? 0);
        $before = $payment instanceof Payment
            ? ['method' => $payment->method, 'reference' => $payment->reference_no, 'status' => $payment->status]
            : null;

        DB::transaction(function () use ($team, $registration, $payment, $method, $reference, $amount, $before): void {
            $attributes = [
                'amount' => $amount,
                'currency' => $payment->currency ?? 'PHP',
                'method' => $method,
                'reference_no' => $reference,
                'status' => 'pending',
                'paid_at' => null,
                'recorded_by' => auth()->id(),
            ];

            if ($payment instanceof Payment) {
                $payment->update($attributes);
            } else {
                $registration->payment()->create($attributes);
            }

            $this->writeAudit($team, 'payment.submit', $before, [
                'method' => $method,
                'reference' => $reference,
                'status' => 'pending',
            ]);
        });

        unset($this->team, $this->registration, $this->tournament, $this->payment);

        $this->closePaymentDialog();
        $this->dispatch('hf-toast', title: 'Reference saved', message: 'Payment reference submitted for verification.', tone: 'success');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function playerRules(): array
    {
        $team = $this->team;
        $unique = Rule::unique('players', 'jersey_number')
            ->where(fn ($query) => $query->where('team_id', $team?->id));

        if ($this->editingPlayerId !== null) {
            $unique->ignore($this->editingPlayerId);
        }

        return [
            'playerName' => ['required', 'string', 'min:2', 'max:255'],
            'playerNumber' => ['required', 'integer', 'min:1', 'max:99', $unique],
            'playerPosition' => ['required', 'string', 'in:'.implode(',', self::POSITIONS)],
            'playerAge' => ['required', 'integer', 'min:8', 'max:99'],
            'playerConsent' => ['boolean'],
            'playerDocumentStatus' => ['required', 'string', 'in:'.implode(',', self::DOCUMENT_STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function playerMessages(): array
    {
        return [
            'playerName.required' => 'Enter the player name.',
            'playerName.min' => 'Enter the player name.',
            'playerNumber.required' => 'Use 1–99.',
            'playerNumber.min' => 'Use 1–99.',
            'playerNumber.max' => 'Use 1–99.',
            'playerNumber.unique' => 'That jersey number is already taken.',
            'playerAge.required' => 'Enter the player age.',
            'playerAge.min' => 'Age must be 8–99.',
            'playerAge.max' => 'Age must be 8–99.',
        ];
    }

    protected function positionIndex(?string $position): int
    {
        $index = array_search($position, self::POSITIONS, true);

        return $index === false ? 0 : (int) $index;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function writeAudit(Team $team, string $action, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'id' => (int) (AuditLog::max('id') ?? 0) + 1,
            'organization_id' => $team->organization_id,
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => 'team',
            'entity_id' => $team->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function render(): View
    {
        return view('livewire.coach.my-team');
    }
}
