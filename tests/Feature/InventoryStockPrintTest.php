<?php

use App\Models\InventoryLot;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function stockPrintProduct(Tenant $tenant, Laboratory $laboratory, string $name, string $code): array
{
    $unit = ProductUnit::factory()->create(['code' => "unit-{$code}"]);
    $category = ProductCategory::factory()->for($tenant)->create();
    $product = Product::factory()
        ->for($tenant)
        ->for($laboratory)
        ->for($category, 'category')
        ->for($unit, 'minimumUnit')
        ->create([
            'commercial_name' => $name,
            'internal_code' => $code,
            'minimum_stock' => 3,
        ]);
    $presentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['name' => 'Caja']);

    $lot = InventoryLot::factory()
        ->for($tenant)
        ->for($product)
        ->for($presentation, 'presentation')
        ->create([
            'lot_number' => "LOT-{$code}",
            'current_quantity' => 11,
            'initial_quantity' => 11,
            'unit_cost' => 900,
        ]);

    return compact('product', 'presentation', 'lot');
}

test('stock print sheet can be filtered by laboratory', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('inventory.view');
    $user->givePermissionTo('inventory.view');

    $firstLaboratory = Laboratory::factory()->for($tenant)->create(['name' => 'Laboratorio Norte']);
    $secondLaboratory = Laboratory::factory()->for($tenant)->create(['name' => 'Laboratorio Sur']);

    stockPrintProduct($tenant, $firstLaboratory, 'Acetaminofen Norte', 'NORTE-001');
    stockPrintProduct($tenant, $secondLaboratory, 'Ibuprofeno Sur', 'SUR-001');

    $this->actingAs($user)
        ->get("/inventory/print/stock-by-laboratory?laboratory_id={$firstLaboratory->id}")
        ->assertSuccessful()
        ->assertSee('Laboratorio Norte')
        ->assertSee('Acetaminofen Norte')
        ->assertSee('LOT-NORTE-001')
        ->assertDontSee('Ibuprofeno Sur');
});

test('stock print sheet can include all laboratories', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('inventory.view');
    $user->givePermissionTo('inventory.view');

    $firstLaboratory = Laboratory::factory()->for($tenant)->create(['name' => 'Laboratorio A']);
    $secondLaboratory = Laboratory::factory()->for($tenant)->create(['name' => 'Laboratorio B']);

    stockPrintProduct($tenant, $firstLaboratory, 'Producto A', 'LAB-A');
    stockPrintProduct($tenant, $secondLaboratory, 'Producto B', 'LAB-B');

    $this->actingAs($user)
        ->get('/inventory/print/stock-by-laboratory')
        ->assertSuccessful()
        ->assertSee('Todos los laboratorios')
        ->assertSee('Laboratorio A')
        ->assertSee('Laboratorio B')
        ->assertSee('Producto A')
        ->assertSee('Producto B')
        ->assertSee('Conteo físico semanal / periódico', false);
});
