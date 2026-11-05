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
        DB::statement('ALTER TABLE audit_logs RENAME TO audit_logs_old');
        DB::statement(<<<'SQL'
CREATE TABLE audit_logs (
    LIKE audit_logs_old EXCLUDING CONSTRAINTS EXCLUDING INDEXES
) PARTITION BY RANGE (created_at);
SQL);
        for ($i = 0; $i < 4; $i++) {
            $start = now()->startOfMonth()->addMonths($i)->format('Y-m-d');
            $end = now()->startOfMonth()->addMonths($i + 1)->format('Y-m-d');
            $name = 'audit_logs_'.now()->startOfMonth()->addMonths($i)->format('Y_m');
            DB::statement("CREATE TABLE IF NOT EXISTS {$name} PARTITION OF audit_logs FOR VALUES FROM ('{$start}') TO ('{$end}')");
        }
        DB::statement('ALTER SEQUENCE audit_logs_id_seq OWNED BY audit_logs.id');
        DB::statement('INSERT INTO audit_logs SELECT * FROM audit_logs_old');
        DB::statement('DROP TABLE audit_logs_old');
        DB::statement('CREATE INDEX IF NOT EXISTS audit_logs_org_created_idx ON audit_logs (organization_id, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS audit_logs_entity_idx ON audit_logs (entity_type, entity_id)');
    }

    public function down(): void
    {
        // no-op: partitioning is additive
    }
};
