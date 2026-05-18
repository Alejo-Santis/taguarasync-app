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
        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_presentation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 260);
            $table->string('lot_number', 120);
            $table->date('expires_on')->nullable();
            $table->unsignedBigInteger('quantity');
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
    }
};
