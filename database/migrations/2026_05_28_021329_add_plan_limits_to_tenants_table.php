<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_users')->nullable()->after('subscribed_until');
            $table->unsignedSmallInteger('max_cash_registers')->nullable()->after('max_users');
            $table->boolean('offline_sync_enabled')->default(false)->after('max_cash_registers');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['max_users', 'max_cash_registers', 'offline_sync_enabled']);
        });
    }
};
