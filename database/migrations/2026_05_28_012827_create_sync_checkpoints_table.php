<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('server_id', 32);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->string('status', 32)->default('pending'); // pending|syncing|synced|error
            $table->text('error_detail')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'server_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_checkpoints');
    }
};
