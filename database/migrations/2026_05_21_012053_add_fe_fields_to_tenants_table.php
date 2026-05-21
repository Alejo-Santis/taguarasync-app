<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('verification_digit', 2)->nullable()->after('nit');
            $table->string('identification_type_code', 10)->nullable()->after('verification_digit');
            $table->string('organization_type_code', 5)->nullable()->after('identification_type_code');
            $table->string('regime_type_code', 10)->nullable()->after('organization_type_code');
            $table->json('fiscal_responsibilities')->nullable()->after('regime_type_code');
            $table->string('municipality_code', 10)->nullable()->after('city');
            $table->string('economic_activity_code', 10)->nullable()->after('municipality_code');
            $table->string('fe_environment', 20)->default('test')->after('economic_activity_code');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'verification_digit',
                'identification_type_code',
                'organization_type_code',
                'regime_type_code',
                'fiscal_responsibilities',
                'municipality_code',
                'economic_activity_code',
                'fe_environment',
            ]);
        });
    }
};
