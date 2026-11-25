<?php

/** Database migration 2026 11 06 000005 create divisions table. */

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

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
