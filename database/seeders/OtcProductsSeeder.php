<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OtcProductsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            return;
        }

        $unit = ProductUnit::where('code', 'und')->first();
        $blister = ProductUnit::where('code', 'bls')->first();
        $caja = ProductUnit::where('code', 'caj')->first();
        $frasco = ProductUnit::where('code', 'fra')->first();

        if (! $unit) {
            return;
        }

        $genfar = Laboratory::where('tenant_id', $tenant->id)->where('name', 'Genfar')->first();
        $mk = Laboratory::where('tenant_id', $tenant->id)->where('name', 'MK (Tecnoquimicas)')->first();
        $bayer = Laboratory::where('tenant_id', $tenant->id)->where('name', 'Bayer Colombia')->first();
        $lafrancol = Laboratory::where('tenant_id', $tenant->id)->where('name', 'Lafrancol')->first();

        $analgesicos = ProductCategory::where('tenant_id', $tenant->id)->where('name', 'Analgesicos y antiinflamatorios')->first();
        $antibioticos = ProductCategory::where('tenant_id', $tenant->id)->where('name', 'Antibioticos')->first();
        $antihistaminicos = ProductCategory::where('tenant_id', $tenant->id)->where('name', 'Antihistaminicos')->first();
        $digestivos = ProductCategory::where('tenant_id', $tenant->id)->where('name', 'Digestivos y gastricos')->first();
        $suplementos = ProductCategory::where('tenant_id', $tenant->id)->where('name', 'Suplementos y vitaminas')->first();

        $products = [
            [
                'commercial_name' => 'Dolex Adulto 500mg',
                'generic_name' => 'Acetaminofen',
                'internal_code' => 'DOL-500',
                'pharmaceutical_form' => 'Tableta',
                'concentration' => '500mg',
                'laboratory' => $genfar,
                'category' => $analgesicos,
                'purchase_price' => 180,
                'sale_price' => 350,
                'presentations' => [
                    ['name' => 'Blister x 10', 'unit' => $blister, 'qty' => 10, 'price' => 3200],
                    ['name' => 'Caja x 100', 'unit' => $caja, 'qty' => 100, 'price' => 28000],
                ],
            ],
            [
                'commercial_name' => 'Acetaminofen MK 500mg',
                'generic_name' => 'Acetaminofen',
                'internal_code' => 'ACET-MK-500',
                'pharmaceutical_form' => 'Tableta',
                'concentration' => '500mg',
                'laboratory' => $mk,
                'category' => $analgesicos,
                'purchase_price' => 120,
                'sale_price' => 250,
                'presentations' => [
                    ['name' => 'Unidad', 'unit' => $unit, 'qty' => 1, 'price' => 250],
                    ['name' => 'Blister x 10', 'unit' => $blister, 'qty' => 10, 'price' => 2200],
                ],
            ],
            [
                'commercial_name' => 'Aspirina 500mg',
                'generic_name' => 'Acido acetilsalicilico',
                'internal_code' => 'ASP-500',
                'pharmaceutical_form' => 'Tableta efervescente',
                'concentration' => '500mg',
                'laboratory' => $bayer,
                'category' => $analgesicos,
                'purchase_price' => 250,
                'sale_price' => 500,
                'presentations' => [
                    ['name' => 'Tubo x 20', 'unit' => $caja, 'qty' => 20, 'price' => 9500],
                ],
            ],
            [
                'commercial_name' => 'Ibuprofeno 400mg',
                'generic_name' => 'Ibuprofeno',
                'internal_code' => 'IBU-400',
                'pharmaceutical_form' => 'Tableta recubierta',
                'concentration' => '400mg',
                'laboratory' => $mk,
                'category' => $analgesicos,
                'purchase_price' => 150,
                'sale_price' => 320,
                'presentations' => [
                    ['name' => 'Unidad', 'unit' => $unit, 'qty' => 1, 'price' => 320],
                    ['name' => 'Blister x 10', 'unit' => $blister, 'qty' => 10, 'price' => 2800],
                ],
            ],
            [
                'commercial_name' => 'Amoxicilina 500mg',
                'generic_name' => 'Amoxicilina',
                'internal_code' => 'AMOX-500',
                'pharmaceutical_form' => 'Capsula',
                'concentration' => '500mg',
                'laboratory' => $genfar,
                'category' => $antibioticos,
                'purchase_price' => 400,
                'sale_price' => 750,
                'presentations' => [
                    ['name' => 'Unidad', 'unit' => $unit, 'qty' => 1, 'price' => 750],
                    ['name' => 'Caja x 12', 'unit' => $caja, 'qty' => 12, 'price' => 8500],
                ],
            ],
            [
                'commercial_name' => 'Loratadina 10mg',
                'generic_name' => 'Loratadina',
                'internal_code' => 'LORA-10',
                'pharmaceutical_form' => 'Tableta',
                'concentration' => '10mg',
                'laboratory' => $mk,
                'category' => $antihistaminicos,
                'purchase_price' => 200,
                'sale_price' => 420,
                'presentations' => [
                    ['name' => 'Unidad', 'unit' => $unit, 'qty' => 1, 'price' => 420],
                    ['name' => 'Blister x 10', 'unit' => $blister, 'qty' => 10, 'price' => 3800],
                ],
            ],
            [
                'commercial_name' => 'Omeprazol 20mg',
                'generic_name' => 'Omeprazol',
                'internal_code' => 'OME-20',
                'pharmaceutical_form' => 'Capsula',
                'concentration' => '20mg',
                'laboratory' => $genfar,
                'category' => $digestivos,
                'purchase_price' => 300,
                'sale_price' => 600,
                'presentations' => [
                    ['name' => 'Unidad', 'unit' => $unit, 'qty' => 1, 'price' => 600],
                    ['name' => 'Caja x 14', 'unit' => $caja, 'qty' => 14, 'price' => 7500],
                ],
            ],
            [
                'commercial_name' => 'Vitamina C 1000mg Efervescente',
                'generic_name' => 'Acido ascorbico',
                'internal_code' => 'VITC-1000',
                'pharmaceutical_form' => 'Tableta efervescente',
                'concentration' => '1000mg',
                'laboratory' => $lafrancol,
                'category' => $suplementos,
                'purchase_price' => 350,
                'sale_price' => 700,
                'presentations' => [
                    ['name' => 'Tubo x 10', 'unit' => $caja, 'qty' => 10, 'price' => 6500],
                ],
            ],
            [
                'commercial_name' => 'Buscopan 10mg',
                'generic_name' => 'Butilescopolamina',
                'internal_code' => 'BUS-10',
                'pharmaceutical_form' => 'Tableta recubierta',
                'concentration' => '10mg',
                'laboratory' => $bayer,
                'category' => $digestivos,
                'purchase_price' => 500,
                'sale_price' => 950,
                'presentations' => [
                    ['name' => 'Unidad', 'unit' => $unit, 'qty' => 1, 'price' => 950],
                    ['name' => 'Caja x 20', 'unit' => $caja, 'qty' => 20, 'price' => 17000],
                ],
            ],
            [
                'commercial_name' => 'Diclofenaco Sodico 50mg',
                'generic_name' => 'Diclofenaco sodico',
                'internal_code' => 'DICLO-50',
                'pharmaceutical_form' => 'Tableta recubierta',
                'concentration' => '50mg',
                'laboratory' => $genfar,
                'category' => $analgesicos,
                'purchase_price' => 180,
                'sale_price' => 380,
                'presentations' => [
                    ['name' => 'Unidad', 'unit' => $unit, 'qty' => 1, 'price' => 380],
                    ['name' => 'Blister x 10', 'unit' => $blister, 'qty' => 10, 'price' => 3300],
                ],
            ],
        ];

        foreach ($products as $data) {
            if (! $data['laboratory'] || ! $data['category']) {
                continue;
            }

            $product = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'internal_code' => $data['internal_code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'laboratory_id' => $data['laboratory']->id,
                    'product_category_id' => $data['category']->id,
                    'minimum_unit_id' => $unit->id,
                    'commercial_name' => $data['commercial_name'],
                    'generic_name' => $data['generic_name'],
                    'pharmaceutical_form' => $data['pharmaceutical_form'],
                    'concentration' => $data['concentration'],
                    'purchase_price' => $data['purchase_price'],
                    'sale_price' => $data['sale_price'],
                    'tax_rate' => 0,
                    'requires_invima_registration' => true,
                    'is_controlled' => false,
                    'status' => ProductStatus::Active,
                ]
            );

            if ($product->wasRecentlyCreated) {
                $isFirst = true;
                foreach ($data['presentations'] as $pres) {
                    if (! $pres['unit']) {
                        continue;
                    }
                    ProductPresentation::create([
                        'tenant_id' => $tenant->id,
                        'product_id' => $product->id,
                        'unit_id' => $pres['unit']->id,
                        'name' => $pres['name'],
                        'minimum_unit_quantity' => $pres['qty'],
                        'sale_price' => $pres['price'],
                        'is_default' => $isFirst,
                        'is_active' => true,
                    ]);
                    $isFirst = false;
                }
            }
        }
    }
}
