<?php

/** Database migration 2026 11 06 000042 create match officials table. */

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
        Schema::create('match_officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('referee_id')->constrained('referees')->cascadeOnDelete();
            $table->string('role')->default('referee');
            $table->timestamps();
            $table->unique(['match_id', 'referee_id']);
        });
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_officials');
    }
};
