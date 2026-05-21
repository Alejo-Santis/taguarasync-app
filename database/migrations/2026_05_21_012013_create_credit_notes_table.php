<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prefix', 10)->nullable();
            $table->string('number', 20);
            $table->string('discrepancy_reason_code', 5);
            $table->integer('subtotal')->default(0);
            $table->integer('tax_total')->default(0);
            $table->integer('total')->default(0);
            $table->string('fe_cufe', 100)->nullable();
            $table->text('fe_qr_code')->nullable();
            $table->string('fe_status', 20)->default('pending');
            $table->timestamp('fe_sent_at')->nullable();
            $table->timestamp('fe_accepted_at')->nullable();
            $table->text('fe_error_message')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'prefix', 'number']);
            $table->index(['tenant_id', 'fe_status']);
            $table->index(['tenant_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
