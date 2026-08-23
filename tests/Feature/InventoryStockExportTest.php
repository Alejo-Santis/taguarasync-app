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

function stockExportProduct(Tenant $tenant, Laboratory $laboratory, string $name, string $code): InventoryLot
{
    $unit = ProductUnit::factory()->create(['code' => "export-unit-{$code}"]);
    $category = ProductCategory::factory()->for($tenant)->create();
    $product = Product::factory()
        ->for($tenant)
        ->for($laboratory)
        ->for($category, 'category')
        ->for($unit, 'minimumUnit')
        ->create(['commercial_name' => $name, 'internal_code' => $code]);
    $presentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['name' => 'Caja']);

    return InventoryLot::factory()
        ->for($tenant)
        ->for($product)
        ->for($presentation, 'presentation')
        ->create([
            'lot_number' => "LOT-{$code}",
            'current_quantity' => 11,
            'initial_quantity' => 11,
            'unit_cost' => 900,
        ]);
}

test('inventory stock export can be filtered by laboratory', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('inventory.view');
    $user->givePermissionTo('inventory.view');

    $firstLaboratory = Laboratory::factory()->for($tenant)->create(['name' => 'Laboratorio Norte']);
    $secondLaboratory = Laboratory::factory()->for($tenant)->create(['name' => 'Laboratorio Sur']);

    stockExportProduct($tenant, $firstLaboratory, 'Acetaminofen Norte', 'NORTE-002');
    stockExportProduct($tenant, $secondLaboratory, 'Ibuprofeno Sur', 'SUR-002');

    $content = $this->actingAs($user)
        ->get("/inventory/export/stock-by-laboratory?laboratory_id={$firstLaboratory->id}")
        ->assertSuccessful()
        ->streamedContent();

    expect($content)->toContain('Acetaminofen Norte')
        ->toContain('LOT-NORTE-002')
        ->not->toContain('Ibuprofeno Sur');
});

test('inventory stock export includes all laboratories when none is selected', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('inventory.view');
    $user->givePermissionTo('inventory.view');

    $firstLaboratory = Laboratory::factory()->for($tenant)->create(['name' => 'Laboratorio A']);
    $secondLaboratory = Laboratory::factory()->for($tenant)->create(['name' => 'Laboratorio B']);

    stockExportProduct($tenant, $firstLaboratory, 'Producto A', 'LAB-A2');
    stockExportProduct($tenant, $secondLaboratory, 'Producto B', 'LAB-B2');

    $content = $this->actingAs($user)
        ->get('/inventory/export/stock-by-laboratory')
        ->assertSuccessful()
        ->streamedContent();

    expect($content)->toContain('Producto A')->toContain('Producto B');
});
