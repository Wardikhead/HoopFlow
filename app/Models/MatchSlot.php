<?php

/** Eloquent model for Match slot. */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['match_id', 'tournament_id', 'court_id', 'start_at', 'end_at', 'status', 'version', 'published_at'])]
class MatchSlot extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * Booted.
     *
     * @return void Nothing.
     */
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
     * Casts.
     *
     * @return array<mixed> The result.
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MatchModel, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<Court, $this>
     */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
