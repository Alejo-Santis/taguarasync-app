<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var string[] */
    private array $feColumns = [
        'fe_environment',
        'fe_api_token',
        'fe_municipality_api_id',
        'merchant_registration',
        'identification_type_code',
        'organization_type_code',
        'regime_type_code',
        'fiscal_responsibilities',
        'economic_activity_code',
    ];

    public function up(): void
    {
        // Data already copied to tenant_fe_configs — safe to remove
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn($this->feColumns);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('fe_environment', 20)->nullable()->after('address');
            $table->text('fe_api_token')->nullable()->after('fe_environment');
            $table->unsignedInteger('fe_municipality_api_id')->nullable()->after('fe_api_token');
            $table->string('merchant_registration', 30)->nullable()->after('nit');
            $table->string('identification_type_code', 10)->nullable();
            $table->string('organization_type_code', 5)->nullable();
            $table->string('regime_type_code', 10)->nullable();
            $table->json('fiscal_responsibilities')->nullable();
            $table->string('economic_activity_code', 10)->nullable();
        });
    }
};
