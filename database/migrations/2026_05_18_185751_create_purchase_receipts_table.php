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
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number', 120);
            $table->date('document_date')->nullable();
            $table->timestamp('received_at');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->string('status', 40)->default('received');
            $table->string('source_file_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'supplier_id', 'document_number']);
            $table->index(['tenant_id', 'received_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_receipts');
    }
};
