<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
