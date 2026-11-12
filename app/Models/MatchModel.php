<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['bracket_id', 'tournament_id', 'stage', 'round_number', 'match_number', 'home_team_id', 'away_team_id', 'home_score', 'away_score', 'winner_team_id', 'status', 'completed_at', 'forfeit_reason', 'quarters_json', 'series_id', 'game_number', 'best_of', 'series_home_wins', 'series_away_wins', 'series_winner_team_id', 'game_winner_team_id'])]
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
            'series_id' => 'integer',
            'game_number' => 'integer',
            'best_of' => 'integer',
            'series_home_wins' => 'integer',
            'series_away_wins' => 'integer',
            'series_winner_team_id' => 'integer',
            'game_winner_team_id' => 'integer',
        ];
    }

    public function isSeriesAnchor(): bool
    {
        return $this->series_id === null;
    }

    public function isSeriesGame(): bool
    {
        return $this->series_id !== null;
    }

    public function isBye(): bool
    {
        return $this->isByeGame();
    }

    /**
     * @return HasMany<MatchModel, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'series_id')->orderBy('game_number');
    }

    /**
     * @return BelongsTo<MatchModel, $this>
     */
    public function seriesAnchor(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'series_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function seriesWinnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'series_winner_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function gameWinnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'game_winner_team_id');
    }

    public function activeGame(): ?MatchModel
    {
        if ($this->best_of <= 1) {
            return $this->status === 'scheduled' ? $this : null;
        }

        if ($this->status === 'scheduled') {
            return $this;
        }

        if ($this->status !== 'in_progress') {
            return null;
        }

        return $this->series()
            ->where('status', 'scheduled')
            ->orderBy('game_number')
            ->first();
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
        if ($this->stage === 'round_robin') {
            return 'Matchday '.$this->round_number;
        }

        if ($this->stage === 'grand_final') {
            return 'Grand Final';
        }

        if ($this->stage === 'reset') {
            return 'Grand Final Reset (if needed)';
        }

        $round = (int) $this->round_number;

        $isDoubleElim = $this->bracket instanceof Bracket
            ? $this->bracket->format === 'double_elim'
            : $this->tournament?->format() === 'double-elimination';

        if ($isDoubleElim) {
            $stage = $this->stage === 'losers' ? 'losers' : 'winners';
            $totalRounds = $this->bracket instanceof Bracket
                ? $this->bracket->roundCount($stage)
                : 0;
            $fromEnd = $totalRounds > 0 ? $totalRounds - $round + 1 : 0;

            if ($stage === 'losers') {
                return match ($fromEnd) {
                    1 => 'Losers Final',
                    2 => 'Losers Semifinal',
                    3 => 'Losers Quarterfinal',
                    default => 'Losers Round '.$round,
                };
            }

            return match ($fromEnd) {
                1 => 'Winners Final',
                2 => 'Winners Semifinal',
                3 => 'Winners Quarterfinal',
                default => 'Winners Round '.$round,
            };
        }

        // Single elimination numbers rounds by bracket size (2, 4, 8, 16, 32),
        // so log2(round_number) is the distance from the final.
        $fromEnd = $round > 1 ? (int) round(log($round, 2)) : 0;

        return match ($fromEnd) {
            1 => 'Championship Final',
            2 => 'Semifinals',
            3 => 'Quarterfinals',
            4 => 'Round of 16',
            5 => 'Round of 32',
            default => 'Round '.$round,
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
