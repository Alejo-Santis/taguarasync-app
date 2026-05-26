<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->string('radian_status', 30)->default('pending')->after('status');
            $table->timestamp('radian_checked_at')->nullable()->after('radian_status');
            $table->json('radian_response')->nullable()->after('radian_checked_at');
            $table->text('radian_error_message')->nullable()->after('radian_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropColumn([
                'radian_status',
                'radian_checked_at',
                'radian_response',
                'radian_error_message',
            ]);
        });
    }
};
