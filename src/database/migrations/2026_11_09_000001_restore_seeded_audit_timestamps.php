<?php

/** Database migration 2026 11 09 000001 restore seeded audit timestamps. */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The prototype seed originally dated most audit entries in November 2026,
     * which can be in the future and push real actions down the timeline.
     * Align every seeded audit entry with the seeded tournament's May 2026 dates.
     */
    public function up(): void
    {
        $timestamps = [
            'tournament.create' => '2026-05-10 10:00:00',
            'team.register' => '2026-05-12 09:00:00',
            'announcement.publish' => '2026-05-13 14:00:00',
            'team.approve' => '2026-05-14 09:30:00',
            'schedule.generate' => '2026-05-15 17:00:00',
            'bracket.generate' => '2026-05-16 08:00:00',
            'payment.verify' => '2026-05-18 15:40:00',
            'score.correct' => '2026-05-18 16:20:00',
        ];

        foreach ($timestamps as $action => $createdAt) {
            DB::table('audit_logs')
                ->where('user_agent', 'PrototypeSeeder')
                ->where('action', $action)
                ->update(['created_at' => $createdAt]);
        }
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        // no-op: the seeded timeline correction is not reversible
    }
};
