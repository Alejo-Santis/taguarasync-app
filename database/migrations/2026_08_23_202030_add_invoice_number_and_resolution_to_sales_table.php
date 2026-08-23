<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedInteger('invoice_number')->nullable()->after('invoice_prefix');
            $table->foreignId('fe_resolution_id')->nullable()->after('invoice_number')->constrained('fe_resolutions')->nullOnDelete();

            $table->unique(['tenant_id', 'invoice_prefix', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'invoice_prefix', 'invoice_number']);
            $table->dropForeign(['fe_resolution_id']);
            $table->dropColumn(['invoice_number', 'fe_resolution_id']);
        });
    }
};
