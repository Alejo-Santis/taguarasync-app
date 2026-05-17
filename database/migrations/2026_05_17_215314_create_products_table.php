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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('laboratory_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('active_ingredient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('minimum_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->string('internal_code', 60);
            $table->string('barcode', 120)->nullable();
            $table->string('commercial_name', 260);
            $table->string('generic_name', 260)->nullable();
            $table->string('cum', 80)->nullable();
            $table->string('health_registration', 120)->nullable();
            $table->string('pharmaceutical_form', 120)->nullable();
            $table->string('concentration', 120)->nullable();
            $table->unsignedBigInteger('purchase_price')->default(0);
            $table->unsignedBigInteger('sale_price');
            $table->unsignedBigInteger('regulated_price')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('requires_invima_registration')->default(true);
            $table->boolean('is_controlled')->default(false);
            $table->unsignedTinyInteger('control_level')->nullable();
            $table->string('status', 40)->default('active');
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'internal_code']);
            $table->index(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'cum']);
            $table->index(['tenant_id', 'commercial_name']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'is_controlled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
