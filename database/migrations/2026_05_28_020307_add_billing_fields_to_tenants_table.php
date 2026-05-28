<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan')->nullable()->after('trial_ends_at');
            $table->string('billing_cycle')->default('monthly')->after('plan');
            $table->timestamp('subscribed_until')->nullable()->after('billing_cycle');
            $table->timestamp('last_payment_at')->nullable()->after('subscribed_until');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['plan', 'billing_cycle', 'subscribed_until', 'last_payment_at']);
        });
    }
};
