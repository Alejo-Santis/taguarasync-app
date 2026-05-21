<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('identification_type_code', 10);
            $table->string('identification_number', 30);
            $table->string('verification_digit', 2)->nullable();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('business_name', 220)->nullable();
            $table->string('organization_type_code', 5)->nullable();
            $table->string('regime_type_code', 10)->nullable();
            $table->json('fiscal_responsibilities')->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('municipality_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'identification_number', 'identification_type_code']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'identification_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
