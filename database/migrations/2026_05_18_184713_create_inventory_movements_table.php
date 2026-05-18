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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_presentation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->bigInteger('quantity_delta');
            $table->unsignedBigInteger('quantity_before');
            $table->unsignedBigInteger('quantity_after');
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_code', 160)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'occurred_at']);
            $table->index(['tenant_id', 'inventory_lot_id', 'occurred_at']);
            $table->index(['tenant_id', 'type', 'occurred_at']);
            $table->index(['tenant_id', 'reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
