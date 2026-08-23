<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->foreignId('fe_resolution_id')->nullable()->after('sale_id')->constrained('fe_resolutions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropForeign(['fe_resolution_id']);
            $table->dropColumn('fe_resolution_id');
        });
    }
};
