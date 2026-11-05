<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsurePartitions extends Command
{
    protected $signature = 'hoopflow:partitions:ensure {--months=3}';

    protected $description = 'Create future monthly partitions for audit_logs and notifications';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('Partitions only supported on PostgreSQL.');

            return self::SUCCESS;
        }
        $months = (int) $this->option('months');
        foreach (['audit_logs', 'notifications'] as $table) {
            for ($i = 0; $i <= $months; $i++) {
                $start = now()->startOfMonth()->addMonths($i);
                $end = $start->copy()->addMonth();
                $name = $table.'_'.$start->format('Y_m');
                DB::statement("CREATE TABLE IF NOT EXISTS {$name} PARTITION OF {$table} FOR VALUES FROM ('{$start->format('Y-m-d')}') TO ('{$end->format('Y-m-d')}')");
            }
        }
        $this->info('Partitions ensured.');

        return self::SUCCESS;
    }
}
