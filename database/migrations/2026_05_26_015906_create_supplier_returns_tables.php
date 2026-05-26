<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_returns', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_receipt_id')->nullable()->constrained('purchase_receipts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_number', 120);
            $table->date('return_date');
            $table->string('reason', 255)->nullable();
            $table->string('status', 40)->default('pending');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'document_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'supplier_id']);
        });

        Schema::create('supplier_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('supplier_return_id')->constrained('supplier_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->string('description', 260);
            $table->string('lot_number', 120);
            $table->integer('quantity');
            $table->unsignedBigInteger('unit_cost');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('line_subtotal');
            $table->unsignedBigInteger('line_tax')->default(0);
            $table->unsignedBigInteger('line_total');
            $table->timestamps();

            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'inventory_lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
        Schema::dropIfExists('supplier_returns');
    }
};
