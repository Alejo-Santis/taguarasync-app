<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // { printer_name: "EPSON TM-T20", paper_width: "80mm", copies: 1, auto_print: false }
            $table->jsonb('printer_settings')->nullable()->after('municipality_code');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('printer_settings');
        });
    }
};
