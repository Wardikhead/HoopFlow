<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches')->cascadeOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'start_at', 'end_at']);
            $table->index(['tournament_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE match_slots ADD CONSTRAINT match_slots_time_order CHECK (start_at < end_at)');
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement("ALTER TABLE match_slots ADD CONSTRAINT match_slots_no_overlap EXCLUDE USING gist (court_id WITH =, tstzrange(start_at, end_at, '[)') WITH &&)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('match_slots');
    }
};
