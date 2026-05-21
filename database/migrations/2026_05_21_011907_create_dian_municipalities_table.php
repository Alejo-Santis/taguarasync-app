<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dian_municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 120);
            $table->string('department_code', 5);
            $table->string('department_name', 100);

            $table->index('department_code');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dian_municipalities');
    }
};
