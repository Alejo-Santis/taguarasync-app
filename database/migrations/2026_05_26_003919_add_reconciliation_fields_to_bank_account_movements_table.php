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
        Schema::table('bank_account_movements', function (Blueprint $table) {
            $table->timestamp('reconciled_at')->nullable()->after('status');
            $table->foreignId('reconciled_by_user_id')->nullable()->after('reconciled_at')->constrained('users')->nullOnDelete();
            $table->text('reconciliation_notes')->nullable()->after('reconciled_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_account_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciled_by_user_id');
            $table->dropColumn(['reconciled_at', 'reconciliation_notes']);
        });
    }
};
