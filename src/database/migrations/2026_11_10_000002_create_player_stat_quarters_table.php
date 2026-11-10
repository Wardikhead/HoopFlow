<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_stat_quarters', function (Blueprint $table) {
            $table->id();
            // matches is hash-partitioned on tournament_id, so PostgreSQL cannot
            // reference matches.id from a foreign key. Index it through the
            // composite (match_id, team_id) index below instead.
            $table->unsignedBigInteger('match_id');
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter_index');
            $table->unsignedInteger('field_goals_made')->default(0);
            $table->unsignedInteger('field_goals_attempted')->default(0);
            $table->unsignedInteger('three_points_made')->default(0);
            $table->unsignedInteger('three_points_attempted')->default(0);
            $table->unsignedInteger('free_throws_made')->default(0);
            $table->unsignedInteger('free_throws_attempted')->default(0);
            $table->unsignedInteger('rebounds')->default(0);
            $table->unsignedInteger('assists')->default(0);
            $table->unsignedInteger('steals')->default(0);
            $table->unsignedInteger('blocks')->default(0);
            $table->unsignedInteger('turnovers')->default(0);
            $table->unsignedInteger('fouls')->default(0);
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();

            $table->unique(['match_id', 'player_id', 'quarter_index']);
            $table->index(['match_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stat_quarters');
    }
};
