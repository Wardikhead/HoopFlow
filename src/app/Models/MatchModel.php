<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['bracket_id', 'tournament_id', 'round_number', 'match_number', 'home_team_id', 'away_team_id', 'home_score', 'away_score', 'winner_team_id', 'status', 'completed_at', 'forfeit_reason', 'quarters_json'])]
class MatchModel extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    /** @use HasFactory<Factory> */
    use HasFactory;

    // Transient routing metadata used while generating double-elimination brackets.
    public ?int $nextMatchId = null;

    public ?int $nextSlot = null;

    public ?int $loserMatchId = null;

    public ?int $loserSlot = null;

    public bool $isBye = false;

    protected $table = 'matches';

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'quarters_json' => 'array',
        ];
    }

    /**
     * A bye is a final game with one team, no opponent, and no recorded score.
     */
    public function isByeGame(): bool
    {
        return $this->status === 'final'
            && (($this->home_team_id !== null) !== ($this->away_team_id !== null))
            && $this->home_score === null
            && $this->away_score === null;
    }

    public function roundLabel(): string
    {
        $rules = $this->tournament?->rules_json;
        $format = is_array($rules) && isset($rules['format']) && is_string($rules['format'])
            ? $rules['format']
            : 'single-elimination';

        if ($format === 'round-robin') {
            return 'Matchday '.$this->round_number;
        }

        return match ((int) $this->round_number) {
            2 => 'Championship Final',
            4 => 'Semifinals',
            8 => 'Quarterfinals',
            16 => 'Round of 16',
            32 => 'Round of 32',
            default => 'Round '.$this->round_number,
        };
    }

    /**
     * @return BelongsTo<Bracket, $this>
     */
    public function bracket(): BelongsTo
    {
        return $this->belongsTo(Bracket::class);
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    /**
     * @return HasMany<MatchSlot, $this>
     */
    public function matchSlots(): HasMany
    {
        return $this->hasMany(MatchSlot::class, 'match_id');
    }

    /**
     * @return HasMany<MatchOfficial, $this>
     */
    public function matchOfficials(): HasMany
    {
        return $this->hasMany(MatchOfficial::class, 'match_id');
    }

    /**
     * @return HasMany<PlayerStat, $this>
     */
    public function playerStats(): HasMany
    {
        return $this->hasMany(PlayerStat::class, 'match_id');
    }

    /**
     * @return HasMany<PlayerStat, $this>
     */
    public function stats(): HasMany
    {
        return $this->playerStats();
    }

    /**
     * @return HasMany<PlayerStatQuarter, $this>
     */
    public function playerQuarterStats(): HasMany
    {
        return $this->hasMany(PlayerStatQuarter::class, 'match_id')
            ->orderBy('player_id')
            ->orderBy('quarter_index');
    }
}
