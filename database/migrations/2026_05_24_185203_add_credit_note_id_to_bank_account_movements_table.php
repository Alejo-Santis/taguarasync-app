<?php

use App\Models\CreditNote;
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
            $table->foreignId('credit_note_id')
                ->nullable()
                ->after('sale_payment_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_account_movements', function (Blueprint $table) {
            $table->dropForeignIdFor(CreditNote::class, 'credit_note_id');
            $table->dropColumn('credit_note_id');
        });
    }
};
