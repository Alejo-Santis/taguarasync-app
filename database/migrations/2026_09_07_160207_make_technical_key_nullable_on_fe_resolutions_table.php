<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La clave técnica solo la emite la DIAN para resoluciones de factura de
     * venta (numeración autorizada). Notas crédito/débito no la llevan, así
     * que la columna no puede seguir siendo NOT NULL.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE fe_resolutions ALTER COLUMN technical_key DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE fe_resolutions SET technical_key = '' WHERE technical_key IS NULL");
        DB::statement('ALTER TABLE fe_resolutions ALTER COLUMN technical_key SET NOT NULL');
    }
};
