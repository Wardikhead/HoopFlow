<?php

/** Database migration 2026 11 06 000015 create payments table. */

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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('method');
            $table->string('reference_no')->nullable();
            $table->string('receipt_no')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['registration_id']);
        });
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
