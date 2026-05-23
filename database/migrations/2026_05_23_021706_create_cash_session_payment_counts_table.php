<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_session_payment_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->integer('expected_amount')->default(0);
            $table->integer('counted_amount')->default(0);
            $table->integer('difference')->default(0);
            $table->unsignedInteger('transactions_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['cash_session_id', 'payment_method_id']);
            $table->index(['tenant_id', 'cash_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_session_payment_counts');
    }
};
