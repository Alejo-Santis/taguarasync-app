<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number', 120);
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->string('status', 40)->default('draft');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'supplier_id']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_presentation_id')->nullable()->constrained('product_presentations')->nullOnDelete();
            $table->string('description', 260);
            $table->integer('quantity');
            $table->unsignedBigInteger('unit_cost');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('line_subtotal');
            $table->unsignedBigInteger('line_tax')->default(0);
            $table->unsignedBigInteger('line_total');
            $table->timestamps();

            $table->index(['tenant_id', 'product_id']);
        });

        // Link receipt to the order it was generated from
        Schema::table('purchase_receipts', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete()->after('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_order_id');
        });
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
