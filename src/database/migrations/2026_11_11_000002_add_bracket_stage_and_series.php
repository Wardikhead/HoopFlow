<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brackets', function (Blueprint $table): void {
            $table->string('phase', 20)->default('main');
            $table->index('phase');
        });

        Schema::table('bracket_slots', function (Blueprint $table): void {
            $table->string('stage', 20)->default('winners');
            $table->index(['bracket_id', 'stage', 'round_number', 'match_number'], 'bracket_slots_stage_identity_idx');
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->string('stage', 20)->default('winners');
            $table->unsignedBigInteger('series_id')->nullable();
            $table->unsignedSmallInteger('game_number')->default(1);
            $table->unsignedSmallInteger('best_of')->default(1);
            $table->unsignedSmallInteger('series_home_wins')->default(0);
            $table->unsignedSmallInteger('series_away_wins')->default(0);
            $table->unsignedBigInteger('series_winner_team_id')->nullable();
            $table->unsignedBigInteger('game_winner_team_id')->nullable();
            $table->index(['bracket_id', 'stage', 'round_number', 'match_number'], 'matches_stage_identity_idx');
            $table->index('series_id');
            $table->index('series_winner_team_id');
            $table->index('game_winner_team_id');
        });

        $roundRobinBracketIds = DB::table('brackets')->where('format', 'round_robin')->select('id');
        DB::table('brackets')->where('format', 'round_robin')->update(['phase' => 'group']);
        DB::table('matches')->whereIn('bracket_id', $roundRobinBracketIds)->update(['stage' => 'round_robin']);
        DB::table('bracket_slots')->whereIn('bracket_id', $roundRobinBracketIds)->update(['stage' => 'round_robin']);

        $doubleElimBracketIds = DB::table('brackets')->where('format', 'double_elim')->select('id');
        DB::table('matches')
            ->whereIn('bracket_id', $doubleElimBracketIds)
            ->where('round_number', '>=', 1000)
            ->update(['stage' => 'losers']);
        DB::table('bracket_slots')
            ->whereIn('bracket_id', $doubleElimBracketIds)
            ->where('round_number', '>=', 1000)
            ->update(['stage' => 'losers']);
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropIndex('matches_stage_identity_idx');
            $table->dropIndex(['series_id']);
            $table->dropIndex(['series_winner_team_id']);
            $table->dropIndex(['game_winner_team_id']);
            $table->dropColumn([
                'stage',
                'series_id',
                'game_number',
                'best_of',
                'series_home_wins',
                'series_away_wins',
                'series_winner_team_id',
                'game_winner_team_id',
            ]);
        });

        Schema::table('bracket_slots', function (Blueprint $table): void {
            $table->dropIndex('bracket_slots_stage_identity_idx');
            $table->dropColumn('stage');
        });

        Schema::table('brackets', function (Blueprint $table): void {
            $table->dropIndex(['phase']);
            $table->dropColumn('phase');
        });
    }
};
