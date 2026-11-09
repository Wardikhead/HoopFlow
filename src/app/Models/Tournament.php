<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $rules_json
 */
#[Fillable(['organization_id', 'name', 'slug', 'description', 'start_date', 'end_date', 'status', 'payment_method', 'payment_account', 'rules_json', 'created_by'])]
class Tournament extends Model
{
    /** @use HasFactory<Factory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rules_json' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->rules_json;

        return is_array($rules) ? $rules : [];
    }

    public function ruleValue(string $key, mixed $default = null): mixed
    {
        $rules = $this->rules();

        if (array_key_exists($key, $rules)) {
            return $rules[$key];
        }

        $snakeKey = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $key));

        return $rules[$snakeKey] ?? $default;
    }

    public function slotCount(): int
    {
        $slots = $this->ruleValue('slots');

        if (is_numeric($slots) && (int) $slots > 0) {
            return (int) $slots;
        }

        $maxTeams = $this->firstDivision()?->max_teams;

        return is_numeric($maxTeams) ? (int) $maxTeams : 0;
    }

    public function entryFee(): float
    {
        $entryFee = $this->firstDivision()?->entry_fee;

        if (is_numeric($entryFee)) {
            return (float) $entryFee;
        }

        $fee = $this->ruleValue('fee');

        return is_numeric($fee) ? (float) $fee : 0.0;
    }

    public function format(): string
    {
        $format = $this->ruleValue('format');

        return is_string($format) && in_array($format, ['single-elimination', 'double-elimination', 'round-robin'], true)
            ? $format
            : 'single-elimination';
    }

    public function formatLabel(): string
    {
        return match ($this->format()) {
            'double-elimination' => 'Double elimination',
            'round-robin' => 'Round robin',
            default => 'Single elimination',
        };
    }

    public function slotMinutes(): int
    {
        $minutes = $this->ruleValue('slotMinutes');

        return is_numeric($minutes) && (int) $minutes >= 10 ? (int) $minutes : 30;
    }

    public function restMinutes(): int
    {
        $minutes = $this->ruleValue('restMinutes');

        if (is_numeric($minutes)) {
            return max(0, (int) $minutes);
        }

        $rule = $this->firstGameRule();

        return is_numeric($rule?->rest_minutes) ? max(0, (int) $rule->rest_minutes) : 15;
    }

    public function resolvedVenue(): ?Venue
    {
        $venueId = $this->ruleValue('venue_id');

        if (is_numeric($venueId)) {
            $venue = Venue::find((int) $venueId);

            if ($venue instanceof Venue) {
                return $venue;
            }
        }

        $slot = $this->relationLoaded('matchSlots')
            ? $this->matchSlots->first()
            : $this->matchSlots()->with('court.venue')->first();

        return $slot?->court?->venue;
    }

    public function venueName(): string
    {
        $venue = $this->resolvedVenue();

        return $venue instanceof Venue ? $venue->name : 'Venue';
    }

    public function courtCount(): int
    {
        $venue = $this->resolvedVenue();

        if ($venue instanceof Venue) {
            return $venue->courts()->count();
        }

        $slots = $this->relationLoaded('matchSlots')
            ? $this->matchSlots
            : $this->matchSlots()->get();

        return $slots->pluck('court_id')->filter()->unique()->count();
    }

    public function seedsLocked(): bool
    {
        if ($this->relationLoaded('matches')) {
            return $this->matches->isNotEmpty();
        }

        return $this->matches()->exists();
    }

    protected function firstDivision(): ?Division
    {
        return $this->relationLoaded('divisions')
            ? $this->divisions->first()
            : $this->divisions()->first();
    }

    protected function firstGameRule(): ?GameRule
    {
        return $this->relationLoaded('gameRules')
            ? $this->gameRules->first()
            : $this->gameRules()->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Division, $this>
     */
    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * @return HasMany<MatchModel, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(MatchModel::class);
    }

    /**
     * @return HasMany<MatchSlot, $this>
     */
    public function matchSlots(): HasMany
    {
        return $this->hasMany(MatchSlot::class);
    }

    /**
     * @return HasMany<GameRule, $this>
     */
    public function gameRules(): HasMany
    {
        return $this->hasMany(GameRule::class);
    }

    /**
     * @return HasMany<Announcement, $this>
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }
}
