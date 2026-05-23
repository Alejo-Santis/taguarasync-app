<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_fe_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

            // Habilitación
            $table->boolean('electronic_invoicing_enabled')->default(false);
            $table->string('environment', 20)->default('test');

            // Credenciales API (por tenant — si está vacío usa la global del .env)
            $table->text('api_token')->nullable();
            $table->string('software_id', 100)->nullable();

            // Clasificación fiscal DIAN
            $table->string('identification_type_code', 10)->nullable();
            $table->string('organization_type_code', 5)->nullable();
            $table->string('regime_type_code', 10)->nullable();
            $table->json('fiscal_responsibilities')->nullable();
            $table->string('economic_activity_code', 10)->nullable();
            $table->string('merchant_registration', 30)->nullable();

            // Integración con proveedor FE
            $table->unsignedInteger('municipality_api_id')->nullable();

            $table->timestamps();
        });

        // Data migration: copiar campos FE de tenants hacia tenant_fe_configs
        DB::statement("
            INSERT INTO tenant_fe_configs (
                tenant_id, environment, api_token,
                identification_type_code, organization_type_code, regime_type_code,
                fiscal_responsibilities, economic_activity_code, merchant_registration,
                municipality_api_id, created_at, updated_at
            )
            SELECT
                id,
                COALESCE(fe_environment, 'test'),
                fe_api_token,
                identification_type_code,
                organization_type_code,
                regime_type_code,
                fiscal_responsibilities,
                economic_activity_code,
                merchant_registration,
                fe_municipality_api_id,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM tenants
        ");
    }

    public function down(): void
    {
        // Restore data back to tenants
        DB::statement("
            UPDATE tenants t
            SET
                fe_environment       = COALESCE((SELECT c.environment FROM tenant_fe_configs c WHERE c.tenant_id = t.id LIMIT 1), 'test'),
                fe_api_token         = (SELECT c.api_token FROM tenant_fe_configs c WHERE c.tenant_id = t.id LIMIT 1),
                identification_type_code = (SELECT c.identification_type_code FROM tenant_fe_configs c WHERE c.tenant_id = t.id LIMIT 1),
                organization_type_code   = (SELECT c.organization_type_code FROM tenant_fe_configs c WHERE c.tenant_id = t.id LIMIT 1),
                regime_type_code         = (SELECT c.regime_type_code FROM tenant_fe_configs c WHERE c.tenant_id = t.id LIMIT 1),
                fiscal_responsibilities  = (SELECT c.fiscal_responsibilities FROM tenant_fe_configs c WHERE c.tenant_id = t.id LIMIT 1),
                economic_activity_code   = (SELECT c.economic_activity_code FROM tenant_fe_configs c WHERE c.tenant_id = t.id LIMIT 1),
                merchant_registration    = (SELECT c.merchant_registration FROM tenant_fe_configs c WHERE c.tenant_id = t.id LIMIT 1),
                fe_municipality_api_id   = (SELECT c.municipality_api_id FROM tenant_fe_configs c WHERE c.tenant_id = t.id LIMIT 1)
        ");

        Schema::dropIfExists('tenant_fe_configs');
    }
};
