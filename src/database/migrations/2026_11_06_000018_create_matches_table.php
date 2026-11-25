<?php

/** Database migration 2026 11 06 000018 create matches table. */

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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bracket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('round_number');
            $table->unsignedInteger('match_number');
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->unsignedInteger('home_score')->nullable();
            $table->unsignedInteger('away_score')->nullable();
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('status')->default('scheduled');
            $table->timestamp('completed_at')->nullable();
            $table->text('forfeit_reason')->nullable();
            $table->timestamps();
            $table->index(['bracket_id']);
            $table->index(['tournament_id']);
            $table->index(['status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_scores_nonneg CHECK (home_score IS NULL OR home_score >= 0)');
            DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_away_nonneg CHECK (away_score IS NULL OR away_score >= 0)');
            DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_winner_valid CHECK (winner_team_id IS NULL OR winner_team_id IN (home_team_id, away_team_id))');
        }
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
