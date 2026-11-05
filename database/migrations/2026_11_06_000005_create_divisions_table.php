<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            $table->string('gender')->nullable();
            $table->unsignedInteger('max_teams')->nullable();
            $table->decimal('entry_fee', 10, 2)->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->timestamps();
            $table->index(['tournament_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
