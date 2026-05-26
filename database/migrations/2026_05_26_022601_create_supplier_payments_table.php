<?php

use App\Models\SupplierPayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('payment_date');
            $table->unsignedBigInteger('amount');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('bank_account_movements', function (Blueprint $table) {
            $table->foreignId('supplier_payment_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_account_movements', function (Blueprint $table) {
            $table->dropForeignIdFor(SupplierPayment::class);
            $table->dropColumn('supplier_payment_id');
        });

        Schema::dropIfExists('supplier_payments');
    }
};
