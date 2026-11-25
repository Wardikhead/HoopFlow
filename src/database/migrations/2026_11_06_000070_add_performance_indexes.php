<?php

/** Database migration 2026 11 06 000070 add performance indexes. */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Up.
     *
     * @return void Nothing.
     */
    public function up(): void
    {
        Schema::table('match_slots', function ($table) {
            $table->index(['court_id', 'start_at', 'end_at'], 'match_slots_court_time_idx');
        });
        Schema::table('court_blackouts', function ($table) {
            $table->index(['court_id', 'start_at', 'end_at'], 'court_blackouts_time_idx');
        });
        Schema::table('tournaments', function ($table) {
            $table->index(['status'], 'tournaments_status_idx');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS notifications_unread_idx ON notifications (user_id) WHERE read_at IS NULL');
            DB::statement("CREATE INDEX IF NOT EXISTS tournaments_published_idx ON tournaments (status) WHERE status = 'published'");
            DB::statement("CREATE INDEX IF NOT EXISTS matches_in_progress_idx ON matches (status) WHERE status = 'in_progress'");
        }
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        // drop indexes if present
    }
};
