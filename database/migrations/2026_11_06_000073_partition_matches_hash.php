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
        DB::statement('ALTER TABLE matches RENAME TO matches_old');
        DB::statement(<<<'SQL'
CREATE TABLE matches (
    LIKE matches_old EXCLUDING CONSTRAINTS EXCLUDING INDEXES
) PARTITION BY HASH (tournament_id);
SQL);
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

    public function down(): void
    {
        // no-op: partitioning is additive
    }
};
