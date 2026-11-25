<?php

/** Database migration 2026 11 08 000003 ensure historical audit log partitions. */

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

        // The prototype seed records a May 2026 score correction, but the
        // original partitioning migration only created future months.
        $start = now()->startOfMonth()->subMonths(12);
        $end = now()->startOfMonth();

        for ($month = $start->copy(); $month->lt($end); $month->addMonth()) {
            $from = $month->copy()->format('Y-m-d');
            $to = $month->copy()->addMonth()->format('Y-m-d');
            $name = 'audit_logs_'.$month->format('Y_m');

            DB::statement("CREATE TABLE IF NOT EXISTS {$name} PARTITION OF audit_logs FOR VALUES FROM ('{$from}') TO ('{$to}')");
        }
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
