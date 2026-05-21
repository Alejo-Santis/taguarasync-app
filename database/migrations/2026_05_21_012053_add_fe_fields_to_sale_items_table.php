<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('dian_unit_measure_code', 10)->nullable()->after('description');
            $table->integer('discount_amount')->default(0)->after('unit_price');
            $table->decimal('discount_rate', 5, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['dian_unit_measure_code', 'discount_amount', 'discount_rate']);
        });
    }
};
