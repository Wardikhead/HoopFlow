<?php

/** Database migration 2026 11 06 000009 create game rules table. */

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
        Schema::create('game_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quarter_minutes')->default(10);
            $table->unsignedSmallInteger('overtime_minutes')->default(5);
            $table->unsignedSmallInteger('foul_limit')->default(5);
            $table->unsignedSmallInteger('rest_minutes')->default(15);
            $table->unsignedSmallInteger('max_games_per_day')->nullable();
            $table->json('tiebreakers_json')->nullable();
            $table->timestamps();
            $table->index(['tournament_id']);
            $table->index(['division_id']);
        });
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_rules');
    }
};
