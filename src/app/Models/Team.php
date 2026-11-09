<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'short_name', 'avatar_color', 'city', 'coach_name', 'slug', 'logo_url', 'coach_user_id'])]
class Team extends Model
{
    /** @use HasFactory<Factory> */
    use BelongsToOrganization, HasFactory;

    /**
     * The compact label used by bracket shields and score consoles.
     */
    public function shortLabel(): string
    {
        if (is_string($this->short_name) && trim($this->short_name) !== '') {
            return mb_strtoupper(trim($this->short_name));
        }

        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->map(fn (string $part): string => mb_substr($part, 0, 1))
            ->take(3)
            ->implode('');

        return $initials !== '' ? mb_strtoupper($initials) : 'TBD';
    }

    /**
     * A safe CSS color for the team shield.
     */
    public function displayColor(): string
    {
        if (is_string($this->avatar_color) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $this->avatar_color) === 1) {
            return $this->avatar_color;
        }

        return '#64748b';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coachUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * @return HasMany<TeamMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    /**
     * @return HasMany<TeamMembership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->memberships();
    }

    /**
     * @return HasMany<Player, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /**
     * @return HasMany<MatchModel, $this>
     */
    public function homeMatches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'home_team_id');
    }

    /**
     * @return HasMany<MatchModel, $this>
     */
    public function awayMatches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'away_team_id');
    }

    /**
     * @return HasMany<PlayerStat, $this>
     */
    public function playerStats(): HasMany
    {
        return $this->hasMany(PlayerStat::class);
    }
}
