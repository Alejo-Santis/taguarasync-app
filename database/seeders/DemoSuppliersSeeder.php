<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSuppliersSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = Tenant::first()?->id;

        if (! $tenantId) {
            return;
        }

        $suppliers = [
            ['name' => 'Copidrogas SA', 'nit' => '860002184-1', 'contact_name' => 'Ventas', 'contact_phone' => '6012345678'],
            ['name' => 'Drogueria el Descuento Distribuidor', 'nit' => '900456789-2', 'contact_name' => 'Pedidos', 'contact_phone' => '3101234567'],
            ['name' => 'Distribuciones Pharma Caribe SAS', 'nit' => '900123456-3', 'contact_name' => 'Representante', 'contact_phone' => '3151234567'],
            ['name' => 'Audifarma SA', 'nit' => '816000919-5', 'contact_name' => 'Comercial', 'contact_phone' => '6063456789'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $supplier['name']],
                [...$supplier, 'tenant_id' => $tenantId, 'uuid' => (string) Str::uuid(), 'is_active' => true]
            );
        }
    }
}
