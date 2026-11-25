<?php

/** Database migration 2026 11 11 000001 add consent status to players table. */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('players', function (Blueprint $table) {
            $table->string('consent_status')->default('pending');
            $table->foreignId('consent_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consent_recorded_at')->nullable();
        });

        DB::table('players')->whereNotNull('consent_at')->update([
            'consent_status' => 'verified',
            'consent_recorded_at' => DB::raw('consent_at'),
        ]);

        Schema::table('eligibility_documents', function (Blueprint $table) {
            $table->string('file_url')->nullable()->change();
        });

        $duplicates = DB::table('eligibility_documents')
            ->select('player_id', 'type', DB::raw('MAX(id) as keep_id'))
            ->groupBy('player_id', 'type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('eligibility_documents')
                ->where('player_id', $duplicate->player_id)
                ->where('type', $duplicate->type)
                ->where('id', '<>', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('eligibility_documents', function (Blueprint $table) {
            $table->unique(['player_id', 'type']);
        });
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        Schema::table('eligibility_documents', function (Blueprint $table) {
            $table->dropUnique(['player_id', 'type']);
        });

        DB::table('eligibility_documents')->whereNull('file_url')->update([
            'file_url' => 'pending-review',
        ]);

        Schema::table('eligibility_documents', function (Blueprint $table) {
            $table->string('file_url')->nullable(false)->change();
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consent_recorded_by');
            $table->dropColumn(['consent_status', 'consent_recorded_at']);
        });
    }
};
