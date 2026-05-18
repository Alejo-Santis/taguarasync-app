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
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_presentation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lot_number', 120);
            $table->date('expires_on')->nullable();
            $table->unsignedBigInteger('initial_quantity')->default(0);
            $table->unsignedBigInteger('current_quantity')->default(0);
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->string('status', 40)->default('available');
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'lot_number']);
            $table->index(['tenant_id', 'product_id', 'status']);
            $table->index(['tenant_id', 'expires_on']);
            $table->index(['tenant_id', 'current_quantity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_lots');
    }
};
