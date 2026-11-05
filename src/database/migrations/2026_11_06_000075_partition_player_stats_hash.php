<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE player_stats ADD COLUMN IF NOT EXISTS tournament_id bigint');
        DB::statement('UPDATE player_stats SET tournament_id = (SELECT m.tournament_id FROM matches m WHERE m.id = player_stats.match_id) WHERE tournament_id IS NULL');
        DB::statement('ALTER TABLE player_stats RENAME TO player_stats_old');
        DB::statement(<<<'SQL'
CREATE TABLE player_stats (
    LIKE player_stats_old EXCLUDING CONSTRAINTS EXCLUDING INDEXES
) PARTITION BY HASH (tournament_id);
SQL);
        DB::statement('ALTER TABLE player_stats ALTER COLUMN tournament_id SET NOT NULL');
        for ($i = 0; $i < 16; $i++) {
            DB::statement("CREATE TABLE IF NOT EXISTS player_stats_p{$i} PARTITION OF player_stats FOR VALUES WITH (MODULUS 16, REMAINDER {$i})");
        }
        DB::statement('ALTER SEQUENCE player_stats_id_seq OWNED BY player_stats.id');
        DB::statement('INSERT INTO player_stats SELECT * FROM player_stats_old');
        DB::statement('DROP TABLE player_stats_old CASCADE');
        DB::statement('CREATE INDEX IF NOT EXISTS player_stats_match_idx ON player_stats (match_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS player_stats_player_idx ON player_stats (player_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS player_stats_team_idx ON player_stats (team_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS player_stats_tournament_idx ON player_stats (tournament_id)');
    }

    public function down(): void
    {
        // no-op: partitioning is additive
    }
};
