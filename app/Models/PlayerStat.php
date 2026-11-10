<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'match_id',
    'player_id',
    'team_id',
    'tournament_id',
    'points',
    'assists',
    'rebounds',
    'steals',
    'blocks',
    'fouls',
    'field_goals_made',
    'field_goals_attempted',
    'three_points_made',
    'three_points_attempted',
    'free_throws_made',
    'free_throws_attempted',
    'turnovers',
])]
class PlayerStat extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (static::max('id') ?? 0) + 1;
            }
        });
    }

    /** @use HasFactory<Factory> */
    use HasFactory;

    /**
     * @return BelongsTo<MatchModel, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
