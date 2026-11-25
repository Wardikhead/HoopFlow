<?php

/** Database migration 2026 11 06 000072 partition notifications monthly. */

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
        DB::statement('ALTER TABLE notifications RENAME TO notifications_old');
        DB::statement('UPDATE notifications_old SET created_at = COALESCE(created_at, now()) WHERE created_at IS NULL');
        DB::statement(<<<'SQL'
CREATE TABLE notifications (
    LIKE notifications_old EXCLUDING CONSTRAINTS EXCLUDING INDEXES
) PARTITION BY RANGE (created_at);
SQL);
        DB::statement('ALTER TABLE notifications ALTER COLUMN created_at SET NOT NULL');
        DB::statement('ALTER TABLE notifications ALTER COLUMN created_at SET DEFAULT now()');
        for ($i = 0; $i < 4; $i++) {
            $start = now()->startOfMonth()->addMonths($i)->format('Y-m-d');
            $end = now()->startOfMonth()->addMonths($i + 1)->format('Y-m-d');
            $name = 'notifications_'.now()->startOfMonth()->addMonths($i)->format('Y_m');
            DB::statement("CREATE TABLE IF NOT EXISTS {$name} PARTITION OF notifications FOR VALUES FROM ('{$start}') TO ('{$end}')");
        }
        DB::statement('ALTER SEQUENCE notifications_id_seq OWNED BY notifications.id');
        DB::statement('INSERT INTO notifications SELECT * FROM notifications_old');
        DB::statement('DROP TABLE notifications_old');
        DB::statement('CREATE INDEX IF NOT EXISTS notifications_user_status_idx ON notifications (user_id, status)');
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
