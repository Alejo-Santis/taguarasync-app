<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fe_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('prefix', 10)->nullable();
            $table->string('resolution_number', 40);
            $table->date('resolution_date');
            $table->text('technical_key');
            $table->unsignedBigInteger('from_number');
            $table->unsignedBigInteger('to_number');
            $table->unsignedBigInteger('current_number')->default(0);
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('environment', 20)->default('test');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'is_active']);
            $table->index(['tenant_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fe_resolutions');
    }
};
