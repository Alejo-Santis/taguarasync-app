<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\ProductUnitKind;
use App\Models\ActiveIngredient;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = $this->seedUnits();

        Tenant::query()->each(function (Tenant $tenant) use ($units): void {
            $laboratory = Laboratory::firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => 'Genfar',
            ], [
                'country' => 'CO',
                'is_active' => true,
            ]);

            $category = ProductCategory::firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => 'Analgesicos',
            ], [
                'description' => 'Medicamentos de venta comercial para dolor y fiebre.',
                'is_active' => true,
            ]);

            $ingredient = ActiveIngredient::firstOrCreate([
                'dci_name' => 'Acetaminofen',
            ], [
                'pharmacological_group' => 'Analgesico y antipiretico',
                'atc_classification' => 'N02BE01',
            ]);

            $product = Product::firstOrCreate([
                'tenant_id' => $tenant->id,
                'internal_code' => 'ACET-500-TAB',
            ], [
                'uuid' => (string) Str::uuid(),
                'laboratory_id' => $laboratory->id,
                'product_category_id' => $category->id,
                'active_ingredient_id' => $ingredient->id,
                'minimum_unit_id' => $units['unit']->id,
                'barcode' => '7700000000011',
                'commercial_name' => 'Acetaminofen 500mg Tableta',
                'generic_name' => 'Acetaminofen',
                'cum' => null,
                'health_registration' => null,
                'pharmaceutical_form' => 'Tableta',
                'concentration' => '500mg',
                'purchase_price' => 180,
                'sale_price' => 300,
                'tax_rate' => 0,
                'requires_invima_registration' => true,
                'is_controlled' => false,
                'status' => ProductStatus::Active,
            ]);

            $this->presentation($tenant, $product, $units['unit'], 'Unidad', 1, 300, true);
            $this->presentation($tenant, $product, $units['blister'], 'Blister x 10 tabletas', 10, 3000);
            $this->presentation($tenant, $product, $units['box'], 'Caja x 100 tabletas', 100, 28000);
        });
    }

    /**
     * @return array<string, ProductUnit>
     */
    private function seedUnits(): array
    {
        $definitions = [
            'unit' => ['Unidad', 'unit', ProductUnitKind::Minimum],
            'tablet' => ['Tableta', 'tablet', ProductUnitKind::Minimum],
            'capsule' => ['Capsula', 'capsule', ProductUnitKind::Minimum],
            'bottle' => ['Frasco', 'bottle', ProductUnitKind::Minimum],
            'tube' => ['Tubo', 'tube', ProductUnitKind::Minimum],
            'ampoule' => ['Ampolla', 'ampoule', ProductUnitKind::Minimum],
            'sachet' => ['Sobre', 'sachet', ProductUnitKind::Minimum],
            'blister' => ['Blister', 'blister', ProductUnitKind::Package],
            'box' => ['Caja', 'box', ProductUnitKind::Package],
        ];

        $units = [];

        foreach ($definitions as $key => [$name, $code, $kind]) {
            $units[$key] = ProductUnit::firstOrCreate([
                'code' => $code,
            ], [
                'name' => $name,
                'kind' => $kind,
                'allows_decimals' => false,
                'is_active' => true,
            ]);
        }

        return $units;
    }

    private function presentation(
        Tenant $tenant,
        Product $product,
        ProductUnit $unit,
        string $name,
        int $minimumUnitQuantity,
        int $salePrice,
        bool $isDefault = false,
    ): void {
        ProductPresentation::firstOrCreate([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'name' => $name,
        ], [
            'unit_id' => $unit->id,
            'minimum_unit_quantity' => $minimumUnitQuantity,
            'sale_price' => $salePrice,
            'is_default' => $isDefault,
            'is_active' => true,
        ]);
    }
}
