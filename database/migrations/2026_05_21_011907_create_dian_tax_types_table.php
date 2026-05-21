<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dian_tax_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 120);
            $table->decimal('default_rate', 5, 2)->nullable();
            $table->string('description', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dian_tax_types');
    }
};
