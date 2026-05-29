<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->string('printer_name', 255)->nullable()->after('is_active');
            $table->enum('paper_width', ['58mm', '80mm'])->default('80mm')->after('printer_name');
            $table->unsignedTinyInteger('copies')->default(1)->after('paper_width');
            $table->boolean('auto_print')->default(false)->after('copies');
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn(['printer_name', 'paper_width', 'copies', 'auto_print']);
        });
    }
};
