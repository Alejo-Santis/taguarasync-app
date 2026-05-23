<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_activities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 300);
            $table->decimal('rete_ica_rate', 5, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_activities');
    }
};
