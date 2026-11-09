<?php

namespace App\Livewire\Organizer;

use App\Models\Court;
use App\Models\Division;
use App\Models\GameRule;
use App\Models\Organization;
use App\Models\Tournament;
use App\Models\Venue;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Create tournament')]
class TournamentCreate extends Component
{
    public int $step = 0;

    public string $name = 'HoopFlow Metro Invitational';

    public string $division_name = 'Under 12 (U12)';

    public string $venue_name = 'San Antonio Central Sports Complex';

    public string $start_date = '2026-08-01';

    public string $end_date = '2026-08-08';

    public string $description = 'Pre-season invitational for barangay youth teams, with accredited referees and a single-elimination bracket.';

    public string $format = 'single-elimination';

    public int $bracketSlots = 8;

    public float $fee = 1500;

    public string $paymentMethod = 'GCash';

    public string $paymentAccount = '09489666136';

    /** @var array<int, string> */
    public array $courts = ['Court 1', 'Court 2'];

    public int $slotMinutes = 30;

    public int $restMinutes = 15;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function basicsRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'division_name' => ['required', 'string', 'in:Under 12 (U12),Under 14 (U14),Under 15 (U15),Under 17 (U17),Open'],
            'venue_name' => ['required', 'string', 'min:3', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function formatRules(): array
    {
        return [
            'format' => ['required', 'string', 'in:single-elimination,double-elimination,round-robin'],
            'bracketSlots' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $slots = (int) $value;
                    $valid = $this->format === 'round-robin'
                        ? $slots >= 2 && $slots <= 16
                        : in_array($slots, [2, 4, 8, 16, 32], true);

                    if (! $valid) {
                        $fail($this->format === 'round-robin'
                            ? 'Choose 2 to 16 teams.'
                            : 'Choose 2, 4, 8, 16, or 32 slots.');
                    }
                },
            ],
            'fee' => ['required', 'numeric', 'min:0'],
            'paymentMethod' => ['required', 'string', 'in:GCash,Maya,Bank transfer,Cash'],
            'paymentAccount' => ['required', 'string', 'min:6', 'max:24'],
            'courts' => ['required', 'array', 'min:1'],
            'courts.*' => ['nullable', 'string', 'max:255'],
            'slotMinutes' => ['required', 'integer', 'min:10'],
            'restMinutes' => ['required', 'integer', 'min:0'],
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
            'division_name.required' => 'Choose a division.',
            'division_name.in' => 'Choose a division.',
            'venue_name.required' => 'Enter at least 3 characters.',
            'venue_name.min' => 'Enter at least 3 characters.',
            'start_date.required' => 'Choose a start date.',
            'end_date.required' => 'Choose an end date.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'format.in' => 'Choose a bracket format.',
            'fee.required' => 'Fee must be zero or more.',
            'fee.numeric' => 'Fee must be zero or more.',
            'fee.min' => 'Fee must be zero or more.',
            'paymentMethod.in' => 'Choose a payment method.',
            'paymentAccount.required' => 'Enter a valid payment account (at least 6 characters).',
            'paymentAccount.min' => 'Enter a valid payment account (at least 6 characters).',
            'paymentAccount.max' => 'Payment account must be 24 characters or fewer.',
            'courts.required' => 'Add at least one court.',
            'slotMinutes.min' => 'Use at least 10 minutes.',
            'restMinutes.min' => 'Rest must be zero or more minutes.',
        ];
    }

    public function updatedFormat(string $value): void
    {
        if ($value === 'round-robin') {
            if ($this->bracketSlots < 2 || $this->bracketSlots > 16) {
                $this->bracketSlots = 8;
            }

            return;
        }

        if (! in_array($this->bracketSlots, [2, 4, 8, 16, 32], true)) {
            $this->bracketSlots = 8;
        }
    }

    public function addCourt(): void
    {
        $this->courts[] = '';
    }

    public function removeCourt(int $index): void
    {
        if (count($this->courts) <= 1) {
            $this->dispatch('hf-toast', title: 'Court required', message: 'Keep at least one court for the schedule.', tone: 'warning');

            return;
        }

        unset($this->courts[$index]);
        $this->courts = array_values($this->courts);
    }

    public function nextStep(): void
    {
        $this->resetValidation();

        if ($this->step === 0) {
            $this->validate($this->basicsRules(), $this->validationMessages());
            $this->step = 1;

            return;
        }

        if ($this->step === 1) {
            $this->validateFormatStep();
            $this->step = 2;
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
            $this->validate($this->basicsRules(), $this->validationMessages());
        } catch (ValidationException $exception) {
            $this->step = 0;

            throw $exception;
        }

        try {
            $this->validateFormatStep();
        } catch (ValidationException $exception) {
            $this->step = 1;

            throw $exception;
        }

        $courts = collect($this->courts)
            ->map(fn ($court): string => trim((string) $court))
            ->filter()
            ->values();

        if ($courts->isEmpty()) {
            $this->step = 1;
            $this->addError('courts', 'Add at least one court.');

            return;
        }

        $organization = Organization::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Organization'],
        );

        $user = auth()->user();
        $slug = $this->uniqueSlug(Str::slug($this->name) ?: 'tournament');
        $ageMax = $this->ageMaxForDivision();

        $tournament = DB::transaction(function () use ($organization, $user, $slug, $courts, $ageMax) {
            $venue = Venue::firstOrCreate([
                'organization_id' => $organization->id,
                'name' => trim($this->venue_name),
            ]);

            foreach ($courts as $courtName) {
                Court::firstOrCreate([
                    'venue_id' => $venue->id,
                    'name' => $courtName,
                ]);
            }

            $tournament = Tournament::create([
                'organization_id' => $organization->id,
                'name' => trim($this->name),
                'slug' => $slug,
                'description' => trim($this->description) !== '' ? trim($this->description) : null,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'status' => 'registration-open',
                'payment_method' => $this->paymentMethod,
                'payment_account' => trim($this->paymentAccount),
                'rules_json' => [
                    'slots' => $this->bracketSlots,
                    'fee' => $this->fee,
                    'format' => $this->format,
                    'slotMinutes' => $this->slotMinutes,
                    'restMinutes' => $this->restMinutes,
                    'venue_id' => $venue->id,
                ],
                'created_by' => $user?->id,
            ]);

            $division = Division::create([
                'tournament_id' => $tournament->id,
                'name' => $this->division_name,
                'age_max' => $ageMax,
                'max_teams' => $this->bracketSlots,
                'entry_fee' => $this->fee,
                'currency' => 'PHP',
            ]);

            GameRule::create([
                'tournament_id' => $tournament->id,
                'division_id' => $division->id,
                'quarter_minutes' => 10,
                'overtime_minutes' => 5,
                'foul_limit' => 5,
                'rest_minutes' => $this->restMinutes,
            ]);

            app(AuditLogger::class)->log(
                'tournament.create',
                'tournament',
                $tournament->id,
                null,
                [
                    'slots' => $this->bracketSlots,
                    'division' => $this->division_name,
                    'fee' => $this->fee,
                ],
                $organization->id,
                $user?->id,
            );

            return $tournament;
        });

        $this->dispatch(
            'hf-toast',
            title: 'Tournament created',
            message: 'Registration is open. Share the tournament desk with coaches.',
            tone: 'success',
        );

        $this->redirectRoute('tournamentShow', ['tournament' => $tournament->id]);
    }

    protected function validateFormatStep(): void
    {
        $this->validate($this->formatRules(), $this->validationMessages());

        $hasCourt = false;

        foreach ($this->courts as $index => $court) {
            $courtName = trim((string) $court);

            if ($courtName === '') {
                continue;
            }

            $hasCourt = true;

            if (mb_strlen($courtName) < 2) {
                $this->addError('courts.'.$index, 'Court name must be at least 2 characters.');
            }
        }

        if (! $hasCourt) {
            $this->addError('courts', 'Add at least one court.');
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            throw ValidationException::withMessages($this->getErrorBag()->getMessages());
        }
    }

    protected function ageMaxForDivision(): ?int
    {
        return match ($this->division_name) {
            'Under 12 (U12)' => 12,
            'Under 14 (U14)' => 14,
            'Under 15 (U15)' => 15,
            'Under 17 (U17)' => 17,
            default => null,
        };
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (Tournament::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function render(): View
    {
        return view('livewire.organizer.tournament-create');
    }
}
