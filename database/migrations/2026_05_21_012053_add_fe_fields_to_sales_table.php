<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('invoice_prefix', 10)->nullable()->after('document_number');
            $table->string('fe_cufe', 100)->nullable()->after('status');
            $table->text('fe_qr_code')->nullable()->after('fe_cufe');
            $table->string('fe_status', 20)->nullable()->after('fe_qr_code');
            $table->timestamp('fe_sent_at')->nullable()->after('fe_status');
            $table->timestamp('fe_accepted_at')->nullable()->after('fe_sent_at');
            $table->text('fe_error_message')->nullable()->after('fe_accepted_at');
            $table->string('payment_form', 2)->nullable()->after('payment_method');
            $table->date('payment_due_date')->nullable()->after('payment_form');

            $table->index(['tenant_id', 'fe_status']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['tenant_id', 'fe_status']);
            $table->dropColumn([
                'customer_id',
                'invoice_prefix',
                'fe_cufe',
                'fe_qr_code',
                'fe_status',
                'fe_sent_at',
                'fe_accepted_at',
                'fe_error_message',
                'payment_form',
                'payment_due_date',
            ]);
        });
    }
};
