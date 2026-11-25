<?php

/** Database migration 2026 11 08 000001 add identity fields to teams table. */

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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('short_name', 12)->nullable();
            $table->string('avatar_color', 32)->nullable();
            $table->string('city')->nullable();
            $table->string('coach_name')->nullable();
        });
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['short_name', 'avatar_color', 'city', 'coach_name']);
        });
    }
};
