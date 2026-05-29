<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('address', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // Crea una sucursal "Principal" para cada tenant existente
        $tenants = DB::table('tenants')->pluck('id');
        foreach ($tenants as $tenantId) {
            DB::table('branches')->insert([
                'tenant_id' => $tenantId,
                'name' => 'Principal',
                'is_main' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
