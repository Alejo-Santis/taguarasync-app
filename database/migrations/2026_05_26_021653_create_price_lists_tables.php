<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('price_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('sale_price');
            $table->timestamps();

            $table->unique(['price_list_id', 'product_id']);
            $table->index(['tenant_id', 'price_list_id']);
        });

        // Link customer to a price list
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete()->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('price_list_id');
        });
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
