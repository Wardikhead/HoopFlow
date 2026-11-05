<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('assists')->default(0);
            $table->unsignedInteger('rebounds')->default(0);
            $table->unsignedInteger('steals')->default(0);
            $table->unsignedInteger('blocks')->default(0);
            $table->unsignedInteger('fouls')->default(0);
            $table->timestamps();
            $table->unique(['match_id', 'player_id']);
            $table->index(['match_id']);
            $table->index(['player_id']);
            $table->index(['team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};
