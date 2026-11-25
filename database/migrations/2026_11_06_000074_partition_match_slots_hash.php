<?php

/** Database migration 2026 11 06 000074 partition match slots hash. */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Up.
     *
     * @return void Nothing.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement('ALTER TABLE match_slots RENAME TO match_slots_old');
        DB::statement('UPDATE match_slots_old SET tournament_id = (SELECT m.tournament_id FROM matches m WHERE m.id = match_slots_old.match_id) WHERE tournament_id IS NULL');
        DB::statement(<<<'SQL'
CREATE TABLE match_slots (
    LIKE match_slots_old EXCLUDING CONSTRAINTS EXCLUDING INDEXES
) PARTITION BY HASH (tournament_id);
SQL);
        DB::statement('ALTER TABLE match_slots ALTER COLUMN tournament_id SET NOT NULL');
        for ($i = 0; $i < 16; $i++) {
            $name = "match_slots_p{$i}";
            DB::statement("CREATE TABLE IF NOT EXISTS {$name} PARTITION OF match_slots FOR VALUES WITH (MODULUS 16, REMAINDER {$i})");
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_time_order CHECK (start_at < end_at)");
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_no_overlap EXCLUDE USING gist (court_id WITH =, tstzrange(start_at, end_at, '[)') WITH &&)");
        }
        DB::statement('ALTER SEQUENCE match_slots_id_seq OWNED BY match_slots.id');
        DB::statement('INSERT INTO match_slots SELECT * FROM match_slots_old');
        DB::statement('DROP TABLE match_slots_old CASCADE');
        DB::statement('CREATE INDEX IF NOT EXISTS match_slots_court_time_idx ON match_slots (court_id, start_at, end_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS match_slots_tournament_idx ON match_slots (tournament_id)');
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        // no-op: partitioning is additive
    }
};
