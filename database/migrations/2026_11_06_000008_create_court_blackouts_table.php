<?php

/** Database migration 2026 11 06 000008 create court blackouts table. */

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
        Schema::create('court_blackouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'start_at', 'end_at']);
        });
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_blackouts');
    }
};
