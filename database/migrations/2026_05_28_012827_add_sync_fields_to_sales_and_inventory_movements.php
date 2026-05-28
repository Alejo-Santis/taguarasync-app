<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('server_id', 32)->default('cloud')->after('tenant_id')->index();
            $table->timestamp('synced_at')->nullable()->after('server_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('server_id', 32)->default('cloud')->after('tenant_id')->index();
            $table->timestamp('synced_at')->nullable()->after('server_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['server_id', 'synced_at']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn(['server_id', 'synced_at']);
        });
    }
};
