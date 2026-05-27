<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table): void {
            // CUFE del proveedor: clave única del documento electrónico en la DIAN
            // Necesaria para enviar/consultar eventos RADIAN (030, 032, 033)
            $table->string('supplier_cufe', 120)->nullable()->after('radian_error_message');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table): void {
            $table->dropColumn('supplier_cufe');
        });
    }
};
