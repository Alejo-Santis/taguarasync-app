<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Encrypted: cada tenant puede tener su propio token de Nextpyme.
            // Si está vacío, se usa el token global de .env (FE_API_TOKEN).
            $table->text('fe_api_token')->nullable()->after('fe_environment');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('fe_api_token');
        });
    }
};
