<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sale_items already has discount_amount and discount_rate from a prior migration
        Schema::table('sales', function (Blueprint $table): void {
            $table->unsignedBigInteger('discount_total')->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn('discount_total');
        });
    }
};
