<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['division_id', 'name', 'format', 'seeding_method', 'status', 'generated_at', 'phase'])]
class Bracket extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    /** @var array<string, int> */
    private array $roundCountCache = [];

    public function roundCount(string $stage): int
    {
        if (! array_key_exists($stage, $this->roundCountCache)) {
            $this->roundCountCache[$stage] = (int) $this->matches()
                ->where('stage', $stage)
                ->whereNull('series_id')
                ->distinct()
                ->count('round_number');
        }

        return $this->roundCountCache[$stage];
    }

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return HasMany<BracketSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(BracketSlot::class);
    }

    /**
     * @return HasMany<BracketSlot, $this>
     */
    public function bracketSlots(): HasMany
    {
        return $this->slots();
    }

    /**
     * @return HasMany<MatchModel, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(MatchModel::class);
    }
}
