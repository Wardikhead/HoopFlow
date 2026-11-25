<?php

/** Database support for Match slot factory. */

namespace Database\Factories;

use App\Models\Court;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<MatchSlot>
 */
class MatchSlotFactory extends Factory
{
    /**
     * Definition.
     *
     * @return array<mixed> The result.
     */
    public function definition(): array
    {
        return [
            'id' => DB::getDriverName() === 'pgsql'
                ? DB::selectOne("select nextval('match_slots_id_seq') as v")->v
                : null,
            'match_id' => MatchModel::factory(),
            'tournament_id' => fn (array $attributes) => MatchModel::find($attributes['match_id'])->tournament_id ?? Tournament::factory(),
            'court_id' => Court::factory(),
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'status' => 'draft',
            'version' => 1,
            'published_at' => null,
        ];
    }
}
