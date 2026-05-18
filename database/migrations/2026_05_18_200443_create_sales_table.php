<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number', 80);
            $table->integer('subtotal')->default(0);
            $table->integer('tax_total')->default(0);
            $table->integer('total')->default(0);
            $table->string('payment_method', 20);
            $table->integer('amount_tendered')->nullable();
            $table->integer('change_amount')->nullable();
            $table->string('status', 20)->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'document_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
