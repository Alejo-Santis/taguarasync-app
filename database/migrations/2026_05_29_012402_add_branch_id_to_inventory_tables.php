<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // branch_id en inventory_lots
        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->index(['branch_id', 'product_id', 'status']);
        });

        // branch_id en inventory_movements
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
        });

        // branch_id en cash_registers
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
        });

        // Asignar la sucursal principal a todos los registros existentes
        $mainBranches = DB::table('branches')
            ->where('is_main', true)
            ->pluck('id', 'tenant_id');

        foreach ($mainBranches as $tenantId => $branchId) {
            DB::table('inventory_lots')->where('tenant_id', $tenantId)->update(['branch_id' => $branchId]);
            DB::table('inventory_movements')->where('tenant_id', $tenantId)->update(['branch_id' => $branchId]);
            DB::table('cash_registers')->where('tenant_id', $tenantId)->update(['branch_id' => $branchId]);
        }
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'product_id', 'status']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
