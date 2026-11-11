<?php

namespace App\Services;

use App\Models\Division;
use App\Models\EligibilityDocument;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;

class EligibilityService
{
    public const POSITIONS = [
        'Point Guard (PG)',
        'Shooting Guard (SG)',
        'Small Forward (SF)',
        'Power Forward (PF)',
        'Center (C)',
        'PG',
        'SG',
        'SF',
        'PF',
        'C',
    ];

    public const DOCUMENT_TYPE = 'birth_certificate';

    public const COACH_DECLARED_FILE_URL = 'coach-declared';

    public function initializePlayer(Player $player): void
    {
        $player->update([
            'consent_status' => 'pending',
            'consent_at' => null,
            'consent_recorded_by' => null,
            'consent_recorded_at' => null,
        ]);

        $document = $this->documentFor($player);

        if ($document === null) {
            $document = new EligibilityDocument;
            $document->player_id = $player->id;
            $document->type = self::DOCUMENT_TYPE;
        }

        $document->file_url = null;
        $document->status = 'pending';
        $document->reviewed_by = null;
        $document->reviewed_at = null;
        $document->save();

        $this->recalculate($player);
    }

    public function recordConsent(Player $player, string $status, ?User $actor = null): void
    {
        $this->assertStatus($status, ['pending', 'verified', 'missing']);

        $player->update([
            'consent_status' => $status,
            'consent_at' => $status === 'verified' ? now() : null,
            'consent_recorded_by' => $actor?->id,
            'consent_recorded_at' => now(),
        ]);

        $this->recalculate($player, $actor);
    }

    public function reviewDocument(Player $player, string $status, ?User $actor = null): void
    {
        $this->assertStatus($status, ['pending', 'approved', 'missing']);

        $document = $this->documentFor($player);

        if ($document === null) {
            $document = new EligibilityDocument;
            $document->player_id = $player->id;
            $document->type = self::DOCUMENT_TYPE;
            $document->file_url = null;
        }

        $document->status = $status;
        $document->reviewed_by = $status === 'approved' ? $actor?->id : null;
        $document->reviewed_at = $status === 'approved' ? now() : null;
        $document->save();

        $this->recalculate($player, $actor);
    }

    public function resetDocumentReview(Player $player): void
    {
        $this->reviewDocument($player, 'pending');
    }

    public function recalculate(Player $player, ?User $actor = null): void
    {
        $document = $this->documentFor($player);

        $status = match (true) {
            $player->consent_status === 'verified' && $document?->status === 'approved' => 'verified',
            $player->consent_status === 'missing' || $document?->status === 'missing' => 'missing',
            default => 'pending',
        };

        $player->eligibility_status = $status;

        if ($status === 'verified') {
            if ($player->verified_at === null) {
                $player->verified_at = $document?->reviewed_at ?? now();
            }

            if ($player->verified_by === null) {
                $player->verified_by = $document?->reviewed_by ?? $actor?->id;
            }
        } else {
            $player->verified_by = null;
            $player->verified_at = null;
        }

        $player->save();
    }

    public function refresh(Team $team): void
    {
        $team->load('players.eligibilityDocuments');

        foreach ($team->players as $player) {
            $this->recalculate($player);
        }
    }

    /**
     * Resolve the chip status shown to coaches and organizers for one player.
     */
    public function documentStatusForPlayer(Player $player): string
    {
        $document = $this->documentFor($player);

        return match ($document?->status) {
            'approved' => 'verified',
            'missing' => 'missing',
            default => 'pending',
        };
    }

    /**
     * @return array{documentsComplete: bool, consentComplete: bool, verifiedAt: mixed}
     */
    public function status(Team $team): array
    {
        $team->load('players.eligibilityDocuments');

        $players = $team->players;

        $documentsComplete = $players->isNotEmpty()
            && $players->every(fn (Player $player): bool => $this->documentsCompleteFor($player));

        $consentComplete = $players->every(
            fn (Player $player): bool => $player->consent_status === 'verified'
        );

        $verifiedAt = null;

        if ($players->isNotEmpty() && $documentsComplete && $consentComplete) {
            $verifiedAt = $players->max('verified_at') ?? now();
        }

        return [
            'documentsComplete' => $documentsComplete,
            'consentComplete' => $consentComplete,
            'verifiedAt' => $verifiedAt,
        ];
    }

    private function documentFor(Player $player): ?EligibilityDocument
    {
        return $player->eligibilityDocuments()
            ->where('type', self::DOCUMENT_TYPE)
            ->first();
    }

    private function documentsCompleteFor(Player $player): bool
    {
        $document = $player->eligibilityDocuments
            ->firstWhere('type', self::DOCUMENT_TYPE);

        return $document instanceof EligibilityDocument && $document->status === 'approved';
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function assertStatus(string $status, array $allowed): void
    {
        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Unsupported eligibility status [{$status}].");
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public function validatePlayer(
        array $input,
        Division $division,
        ?int $ignoreNumber = null,
        mixed $numbers = null
    ): array {
        $errors = [];

        if (array_key_exists('first_name', $input) || array_key_exists('last_name', $input)) {
            $firstName = trim((string) ($input['first_name'] ?? ''));
            $lastName = trim((string) ($input['last_name'] ?? ''));

            if ($firstName === '') {
                $errors['first_name'] = 'Player first name is required.';
            }

            if ($lastName === '') {
                $errors['last_name'] = 'Player last name is required.';
            }
        } else {
            $name = $input['name'] ?? null;

            if (! is_string($name) || trim($name) === '' || mb_strlen(trim($name)) < 2) {
                $errors['name'] = 'Player name is required.';
            }
        }

        $position = $input['position'] ?? null;

        if (! is_string($position) || ! in_array($position, self::POSITIONS, true)) {
            $errors['position'] = 'Choose a valid position.';
        }

        $numberKey = array_key_exists('jersey_number', $input)
            ? 'jersey_number'
            : (array_key_exists('number', $input) ? 'number' : 'jersey_number');

        $rawNumber = $input['jersey_number'] ?? $input['number'] ?? null;
        $number = null;

        if ($rawNumber === null || $rawNumber === '' || ! is_numeric($rawNumber)) {
            $errors[$numberKey] = 'Jersey number must be between 1 and 99.';
        } else {
            $number = (int) $rawNumber;

            if ($number < 1 || $number > 99) {
                $errors[$numberKey] = 'Jersey number must be between 1 and 99.';
            }
        }

        $existingNumbers = $this->resolveNumbers($input, $numbers);

        if ($number !== null && ! isset($errors[$numberKey])) {
            if ($number !== $ignoreNumber && in_array($number, $existingNumbers, true)) {
                $errors[$numberKey] = "Jersey number {$number} is already taken.";
            }
        }

        $ageMin = $division->age_min ?? 8;
        $ageMax = $division->age_max ?? 99;

        if (array_key_exists('birthdate', $input) || array_key_exists('birth_date', $input)) {
            $birthdate = $input['birthdate'] ?? $input['birth_date'] ?? null;

            if (! is_string($birthdate)
                || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)
                || strtotime($birthdate) === false) {
                $errors['birthdate'] = 'Birthdate must be a valid date.';
            } else {
                $age = Carbon::parse($birthdate)->age;

                if ($age < $ageMin || $age > $ageMax) {
                    $errors['birthdate'] = "Age must be between {$ageMin} and {$ageMax} for {$division->name}.";
                }
            }
        } elseif (array_key_exists('age', $input)) {
            $age = $input['age'];

            if (! is_numeric($age)) {
                $errors['age'] = 'Age must be a valid integer.';
            } else {
                $age = (int) $age;

                if ($age < $ageMin || $age > $ageMax) {
                    $errors['age'] = "Age must be between {$ageMin} and {$ageMax} for {$division->name}.";
                }
            }
        } else {
            $errors['birthdate'] = 'Birthdate is required.';
        }

        if (isset($errors['jersey_number']) && ! isset($errors['number'])) {
            $errors['number'] = $errors['jersey_number'];
        }

        if (isset($errors['number']) && ! isset($errors['jersey_number'])) {
            $errors['jersey_number'] = $errors['number'];
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, int>
     */
    protected function resolveNumbers(array $input, mixed $numbers): array
    {
        if (is_array($numbers)) {
            return array_values(array_filter($numbers, fn ($value): bool => $value !== null));
        }

        if (isset($input['numbers']) && is_array($input['numbers'])) {
            return array_values(array_filter($input['numbers'], fn ($value): bool => $value !== null));
        }

        if (isset($input['existing_numbers']) && is_array($input['existing_numbers'])) {
            return array_values(array_filter($input['existing_numbers'], fn ($value): bool => $value !== null));
        }

        if (isset($input['team']) && $input['team'] instanceof Team) {
            return $input['team']->players()->pluck('jersey_number')->filter()->values()->all();
        }

        if (isset($input['team_id'])) {
            return Player::where('team_id', $input['team_id'])
                ->pluck('jersey_number')
                ->filter()
                ->values()
                ->all();
        }

        return [];
    }
}
