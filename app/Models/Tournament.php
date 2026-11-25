<?php

/** Eloquent model for Tournament. */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $rules_json
 * @property Carbon|null $archived_at
 */
#[Fillable(['organization_id', 'name', 'slug', 'description', 'start_date', 'end_date', 'status', 'payment_method', 'payment_account', 'rules_json', 'created_by', 'archived_at'])]
class Tournament extends Model
{
    /** @use HasFactory<Factory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * Casts.
     *
     * @return array<mixed> The result.
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rules_json' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Is archived.
     *
     * @return bool The result.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->rules_json;

        return is_array($rules) ? $rules : [];
    }

    /**
     * Rule value.
     *
     * @param  string  $key  The key.
     * @param  mixed  $default  The default.
     * @return mixed The result.
     */
    public function ruleValue(string $key, mixed $default = null): mixed
    {
        $rules = $this->rules();

        if (array_key_exists($key, $rules)) {
            return $rules[$key];
        }

        $snakeKey = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $key));

        return $rules[$snakeKey] ?? $default;
    }

    /**
     * Slot count.
     *
     * @return int The result.
     */
    public function slotCount(): int
    {
        $slots = $this->ruleValue('slots');

        if (is_numeric($slots) && (int) $slots > 0) {
            return (int) $slots;
        }

        $maxTeams = $this->firstDivision()?->max_teams;

        return is_numeric($maxTeams) ? (int) $maxTeams : 0;
    }

    /**
     * Entry fee.
     *
     * @return float The result.
     */
    public function entryFee(): float
    {
        $entryFee = $this->firstDivision()?->entry_fee;

        if (is_numeric($entryFee)) {
            return (float) $entryFee;
        }

        $fee = $this->ruleValue('fee');

        return is_numeric($fee) ? (float) $fee : 0.0;
    }

    /**
     * Format.
     *
     * @return string The result.
     */
    public function format(): string
    {
        $format = $this->ruleValue('format');

        return is_string($format) && in_array($format, ['single-elimination', 'double-elimination', 'round-robin'], true)
            ? $format
            : 'single-elimination';
    }

    /**
     * Grand final best of.
     *
     * @return int The result.
     */
    public function grandFinalBestOf(): int
    {
        $bestOf = $this->ruleValue('best_of_final', 1);

        return is_numeric($bestOf) ? max(1, (int) $bestOf) : 1;
    }

    /**
     * A double-elimination grand final played as a series drops the bracket
     * reset, so the format is no longer true double elimination.
     */
    public function isModifiedDoubleElimination(): bool
    {
        return $this->format() === 'double-elimination' && $this->grandFinalBestOf() > 1;
    }

    /**
     * Format label.
     *
     * @return string The result.
     */
    public function formatLabel(): string
    {
        return match ($this->format()) {
            'double-elimination' => $this->isModifiedDoubleElimination()
                ? 'Modified double elimination'
                : 'Double elimination',
            'round-robin' => 'Round robin',
            default => 'Single elimination',
        };
    }

    /**
     * Status label.
     *
     * @return string The result.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'registration-open' => 'Registration open',
            'active' => 'Active',
            'upcoming' => 'Upcoming',
            'completed' => 'Completed',
            default => ucwords(str_replace('-', ' ', (string) $this->status)),
        };
    }

    /**
     * Slot minutes.
     *
     * @return int The result.
     */
    public function slotMinutes(): int
    {
        $minutes = $this->ruleValue('slotMinutes');

        return is_numeric($minutes) && (int) $minutes >= 10 ? (int) $minutes : 30;
    }

    /**
     * Rest minutes.
     *
     * @return int The result.
     */
    public function restMinutes(): int
    {
        $minutes = $this->ruleValue('restMinutes');

        if (is_numeric($minutes)) {
            return max(0, (int) $minutes);
        }

        $rule = $this->firstGameRule();

        return is_numeric($rule?->rest_minutes) ? max(0, (int) $rule->rest_minutes) : 15;
    }

    /**
     * Resolved venue.
     *
     * @return ?Venue The result.
     */
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

    /**
     * Venue name.
     *
     * @return string The result.
     */
    public function venueName(): string
    {
        $venue = $this->resolvedVenue();

        return $venue instanceof Venue ? $venue->name : 'Venue';
    }

    /**
     * Court count.
     *
     * @return int The result.
     */
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

    /**
     * Seeds locked.
     *
     * @return bool The result.
     */
    public function seedsLocked(): bool
    {
        if ($this->relationLoaded('matches')) {
            return $this->matches->isNotEmpty();
        }

        return $this->matches()->exists();
    }

    /**
     * First division.
     *
     * @return ?Division The result.
     */
    protected function firstDivision(): ?Division
    {
        return $this->relationLoaded('divisions')
            ? $this->divisions->first()
            : $this->divisions()->first();
    }

    /**
     * First game rule.
     *
     * @return ?GameRule The result.
     */
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
