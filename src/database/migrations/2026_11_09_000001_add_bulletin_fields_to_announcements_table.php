<?php

/** Database migration 2026 11 09 000001 add bulletin fields to announcements table. */

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
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('category')->default('general');
            $table->boolean('pinned')->default(false);
            $table->json('target_user_ids')->nullable();
        });
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['category', 'pinned', 'target_user_ids']);
        });
    }
};
