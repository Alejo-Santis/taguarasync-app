<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_presentation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 260);
            $table->integer('quantity');
            $table->integer('unit_price');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->integer('line_subtotal');
            $table->integer('line_tax')->default(0);
            $table->integer('line_total');
            $table->timestamps();

            $table->index(['tenant_id', 'sale_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
