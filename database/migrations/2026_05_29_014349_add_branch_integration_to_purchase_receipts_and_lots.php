<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Agregar branch_id a purchase_receipts
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
        });

        // Backfill: asignar sucursal principal a recepciones existentes
        $mainBranches = DB::table('branches')->where('is_main', true)->pluck('id', 'tenant_id');
        foreach ($mainBranches as $tenantId => $branchId) {
            DB::table('purchase_receipts')->where('tenant_id', $tenantId)->update(['branch_id' => $branchId]);
        }

        // 2. Cambiar unique constraint en inventory_lots de [tenant,product,lot] a [tenant,branch,product,lot]
        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'product_id', 'lot_number']);
            $table->unique(['tenant_id', 'branch_id', 'product_id', 'lot_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'branch_id', 'product_id', 'lot_number']);
            $table->unique(['tenant_id', 'product_id', 'lot_number']);
        });

        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
