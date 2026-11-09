<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('short_name', 12)->nullable();
            $table->string('avatar_color', 32)->nullable();
            $table->string('city')->nullable();
            $table->string('coach_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['short_name', 'avatar_color', 'city', 'coach_name']);
        });
    }
};
