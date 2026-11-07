<?php

namespace Database\Factories;

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<MatchModel>
 */
class MatchModelFactory extends Factory
{
    public function definition(): array
    {
        $id = DB::getDriverName() === 'pgsql'
            ? DB::selectOne("select nextval('matches_id_seq') as v")->v
            : ((MatchModel::max('id') ?? 0) + 1);

        return [
            'id' => $id,
            'bracket_id' => Bracket::factory(),
            'tournament_id' => fn (array $attributes) => Bracket::find($attributes['bracket_id'])->division->tournament_id ?? Tournament::factory(),
            'round_number' => 1,
            'match_number' => 1,
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
            'status' => 'scheduled',
            'completed_at' => null,
            'forfeit_reason' => null,
        ];
    }
}
