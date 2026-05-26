<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->string('prescription_number', 60)->nullable()->after('dian_unit_measure_code');
            $table->string('patient_id_number', 30)->nullable()->after('prescription_number');
            $table->string('patient_name', 120)->nullable()->after('patient_id_number');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn(['prescription_number', 'patient_id_number', 'patient_name']);
        });
    }
};
