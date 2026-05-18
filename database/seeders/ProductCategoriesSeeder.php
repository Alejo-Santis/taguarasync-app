<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ProductCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = Tenant::first()?->id;

        if (! $tenantId) {
            return;
        }

        $categories = [
            'Analgesicos y antiinflamatorios',
            'Antibioticos',
            'Antihipertensivos',
            'Antihistaminicos',
            'Antidiabeticos',
            'Antifungicos',
            'Antiparasitarios',
            'Cardiovasculares',
            'Dermatologicos',
            'Digestivos y gastricos',
            'Medicamentos controlados',
            'Oftalmologicos',
            'Otologicos',
            'Respiratorios y broncodilatadores',
            'Suplementos y vitaminas',
            'Tiroides y hormonas',
            'Urologicos',
            'Vacunas y biologicos',
        ];

        foreach ($categories as $name) {
            ProductCategory::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                ['is_active' => true]
            );
        }
    }
}
