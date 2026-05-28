<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_conflicts_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('table_name', 64);
            $table->uuid('record_uuid');
            $table->string('strategy_applied', 32); // append|lww|cloud_wins
            $table->json('local_data')->nullable();
            $table->json('cloud_data')->nullable();
            $table->json('resolved_data')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'table_name']);
            $table->index('record_uuid');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts_log');
    }
};
