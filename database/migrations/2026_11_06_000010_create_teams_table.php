<?php

/** Database migration 2026 11 06 000010 create teams table. */

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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('logo_url')->nullable();
            $table->foreignId('coach_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id']);
        });
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
