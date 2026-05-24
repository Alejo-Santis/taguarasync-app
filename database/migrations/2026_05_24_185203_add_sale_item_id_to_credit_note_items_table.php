<?php

use App\Models\SaleItem;
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
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->foreignId('sale_item_id')
                ->nullable()
                ->after('credit_note_id')
                ->constrained('sale_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropForeignIdFor(SaleItem::class, 'sale_item_id');
            $table->dropColumn('sale_item_id');
        });
    }
};
