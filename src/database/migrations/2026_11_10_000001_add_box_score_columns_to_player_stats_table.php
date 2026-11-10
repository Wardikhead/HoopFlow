<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $table->unsignedInteger('field_goals_made')->default(0);
            $table->unsignedInteger('field_goals_attempted')->default(0);
            $table->unsignedInteger('three_points_made')->default(0);
            $table->unsignedInteger('three_points_attempted')->default(0);
            $table->unsignedInteger('free_throws_made')->default(0);
            $table->unsignedInteger('free_throws_attempted')->default(0);
            $table->unsignedInteger('turnovers')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $table->dropColumn([
                'field_goals_made',
                'field_goals_attempted',
                'three_points_made',
                'three_points_attempted',
                'free_throws_made',
                'free_throws_attempted',
                'turnovers',
            ]);
        });
    }
};
