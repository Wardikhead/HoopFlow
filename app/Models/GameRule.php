<?php

/** Eloquent model for Game rule. */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tournament_id', 'division_id', 'quarter_minutes', 'overtime_minutes', 'foul_limit', 'rest_minutes', 'max_games_per_day', 'tiebreakers_json'])]
class GameRule extends Model
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
            'tiebreakers_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
