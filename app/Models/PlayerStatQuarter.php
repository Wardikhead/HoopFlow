<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'match_id',
    'player_id',
    'team_id',
    'quarter_index',
    'field_goals_made',
    'field_goals_attempted',
    'three_points_made',
    'three_points_attempted',
    'free_throws_made',
    'free_throws_attempted',
    'rebounds',
    'assists',
    'steals',
    'blocks',
    'turnovers',
    'fouls',
    'points',
])]
class PlayerStatQuarter extends Model
{
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
}
