<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('quantity');
            $table->timestamps();

            $table->unique(['inventory_transfer_id', 'inventory_lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_items');
    }
};
