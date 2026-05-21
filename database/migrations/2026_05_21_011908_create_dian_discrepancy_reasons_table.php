<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dian_discrepancy_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5);
            $table->string('applies_to', 20);
            $table->string('name', 220);

            $table->unique(['code', 'applies_to']);
            $table->index('applies_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dian_discrepancy_reasons');
    }
};
