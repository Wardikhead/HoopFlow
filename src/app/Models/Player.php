<?php

/** Eloquent model for Player. */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'first_name', 'last_name', 'birthdate', 'jersey_number', 'position', 'photo_url', 'guardian_name', 'guardian_phone', 'consent_at', 'consent_status', 'consent_recorded_by', 'consent_recorded_at', 'consent_version', 'eligibility_status', 'verified_by', 'verified_at'])]
class Player extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    /**
     * Casts.
     *
     * @return array<mixed> The result.
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'consent_at' => 'datetime',
            'consent_recorded_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function consentRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_recorded_by');
    }

    /**
     * @return HasMany<EligibilityDocument, $this>
     */
    public function eligibilityDocuments(): HasMany
    {
        return $this->hasMany(EligibilityDocument::class);
    }

    /**
     * @return HasMany<PlayerStat, $this>
     */
    public function playerStats(): HasMany
    {
        return $this->hasMany(PlayerStat::class);
    }
}
