<?php

/** Database migration 2026 11 06 000073 partition matches hash. */

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
        DB::statement('ALTER TABLE matches RENAME TO matches_old');
        DB::statement('UPDATE matches_old SET tournament_id = (SELECT d.tournament_id FROM brackets b JOIN divisions d ON d.id = b.division_id WHERE b.id = matches_old.bracket_id) WHERE tournament_id IS NULL');
        DB::statement(<<<'SQL'
CREATE TABLE matches (
    LIKE matches_old EXCLUDING CONSTRAINTS EXCLUDING INDEXES
) PARTITION BY HASH (tournament_id);
SQL);
        DB::statement('ALTER TABLE matches ALTER COLUMN tournament_id SET NOT NULL');
        for ($i = 0; $i < 16; $i++) {
            DB::statement("CREATE TABLE IF NOT EXISTS matches_p{$i} PARTITION OF matches FOR VALUES WITH (MODULUS 16, REMAINDER {$i})");
        }
        DB::statement('ALTER SEQUENCE matches_id_seq OWNED BY matches.id');
        DB::statement('INSERT INTO matches SELECT * FROM matches_old');
        DB::statement('DROP TABLE matches_old CASCADE');
        DB::statement('CREATE INDEX IF NOT EXISTS matches_bracket_idx ON matches (bracket_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS matches_tournament_idx ON matches (tournament_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS matches_status_idx ON matches (status)');
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
