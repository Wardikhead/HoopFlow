<?php

/** Eloquent model for Bracket slot. */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bracket_id', 'stage', 'round_number', 'match_number', 'slot', 'source_type', 'source_match_id', 'source_outcome', 'team_id'])]
class BracketSlot extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    /**
     * @return BelongsTo<Bracket, $this>
     */
    public function bracket(): BelongsTo
    {
        return $this->belongsTo(Bracket::class);
    }

    /**
     * @return BelongsTo<MatchModel, $this>
     */
    public function sourceMatch(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'source_match_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
