<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dian_fiscal_responsibilities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 15)->unique();
            $table->string('name', 220);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dian_fiscal_responsibilities');
    }
};
