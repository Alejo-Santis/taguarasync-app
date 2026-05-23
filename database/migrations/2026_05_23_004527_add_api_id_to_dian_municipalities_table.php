<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dian_municipalities', function (Blueprint $table) {
            // Nextpyme internal municipality ID — used in invoice payloads
            $table->unsignedInteger('api_id')->nullable()->after('code');
            $table->index('api_id');
        });
    }

    public function down(): void
    {
        Schema::table('dian_municipalities', function (Blueprint $table) {
            $table->dropIndex(['api_id']);
            $table->dropColumn('api_id');
        });
    }
};
