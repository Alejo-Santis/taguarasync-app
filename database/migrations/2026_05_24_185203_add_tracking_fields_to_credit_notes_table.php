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
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->timestamp('inventory_returned_at')->nullable()->after('fe_error_message');
            $table->timestamp('payments_reversed_at')->nullable()->after('inventory_returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropColumn(['inventory_returned_at', 'payments_reversed_at']);
        });
    }
};
