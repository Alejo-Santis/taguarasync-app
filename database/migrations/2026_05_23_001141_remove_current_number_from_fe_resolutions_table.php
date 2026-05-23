<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data already copied to fe_sends — safe to remove
        Schema::table('fe_resolutions', function (Blueprint $table) {
            $table->dropColumn('current_number');
        });
    }

    public function down(): void
    {
        Schema::table('fe_resolutions', function (Blueprint $table) {
            $table->unsignedBigInteger('current_number')->default(0)->after('to_number');
        });
    }
};
