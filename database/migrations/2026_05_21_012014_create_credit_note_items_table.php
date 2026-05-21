<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_presentation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 260);
            $table->integer('quantity');
            $table->integer('unit_price');
            $table->string('dian_unit_measure_code', 10)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->integer('discount_amount')->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->integer('line_subtotal');
            $table->integer('line_tax')->default(0);
            $table->integer('line_total');
            $table->timestamps();

            $table->index(['tenant_id', 'credit_note_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};
