<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bracket_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bracket_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->unsignedInteger('match_number');
            $table->string('slot');
            $table->string('source_type')->default('seed');
            $table->foreignId('source_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->string('source_outcome')->nullable();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['bracket_id', 'round_number', 'match_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bracket_slots');
    }
};
