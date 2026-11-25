<?php

/** Database migration 2026 11 15 000001 add archive and soft deletes to tournaments table. */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('tournaments', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('status');
            $table->softDeletes();
        });
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('archived_at');
        });
    }
};
