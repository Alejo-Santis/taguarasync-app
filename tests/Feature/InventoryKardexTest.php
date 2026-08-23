<?php

use App\Actions\Inventory\RegisterInventoryMovement;
use App\Models\Branch;
use App\Models\InventoryLot;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function kardexSetup(): array
{
    $tenant = Tenant::factory()->create();
    $unit = ProductUnit::factory()->create(['name' => 'Unidad', 'code' => "kardex-unit-{$tenant->id}"]);
    $product = Product::factory()
        ->for($tenant)
        ->for($unit, 'minimumUnit')
        ->create(['commercial_name' => 'Producto Kardex Visible', 'internal_code' => 'KDX-001']);
    $presentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['name' => 'Unidad', 'minimum_unit_quantity' => 1]);
    $lot = InventoryLot::factory()
        ->for($tenant)
        ->for($product)
        ->for($presentation, 'presentation')
        ->create([
            'lot_number' => 'LOT-KDX',
            'initial_quantity' => 0,
            'current_quantity' => 0,
            'unit_cost' => 250,
        ]);
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('inventory.view');
    $user->givePermissionTo('inventory.view');

    return compact('tenant', 'unit', 'product', 'presentation', 'lot', 'user');
}

test('kardex lists inventory movements with balances', function () {
    ['tenant' => $tenant, 'lot' => $lot, 'user' => $user] = kardexSetup();

    app(RegisterInventoryMovement::class)->purchase($lot, 12, $user, [
        'reference_code' => 'RC-001',
        'notes' => 'Compra inicial',
        'occurred_at' => now()->subHour(),
    ]);

    app(RegisterInventoryMovement::class)->sale($lot->fresh(), 4, $user, [
        'reference_code' => 'VTA-001',
        'notes' => 'Venta POS',
        'occurred_at' => now(),
    ]);

    $otherTenant = Tenant::factory()->create();
    InventoryLot::factory()->for($otherTenant)->create(['lot_number' => 'LOT-HIDDEN']);

    $this->actingAs($user)
        ->get('/inventory/kardex?q=KDX-001')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Kardex')
            ->where('summary.movements_count', 2)
            ->where('summary.entries', 12)
            ->where('summary.exits', 4)
            ->where('summary.net', 8)
            ->has('movements.data', 2)
            ->where('movements.data.0.reference_code', 'VTA-001')
            ->where('movements.data.0.quantity_before', 12)
            ->where('movements.data.0.quantity_after', 8)
            ->where('movements.data.1.reference_code', 'RC-001')
            ->etc()
        );
});

test('kardex can filter by movement type', function () {
    ['lot' => $lot, 'user' => $user] = kardexSetup();

    app(RegisterInventoryMovement::class)->purchase($lot, 8, $user, [
        'reference_code' => 'RC-002',
    ]);

    app(RegisterInventoryMovement::class)->sale($lot->fresh(), 3, $user, [
        'reference_code' => 'VTA-002',
    ]);

    $this->actingAs($user)
        ->get('/inventory/kardex?type=sale')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Kardex')
            ->where('filters.type', 'sale')
            ->where('summary.movements_count', 1)
            ->where('summary.entries', 0)
            ->where('summary.exits', 3)
            ->has('movements.data', 1)
            ->where('movements.data.0.reference_code', 'VTA-002')
            ->etc()
        );
});

function kardexLotForLab(Tenant $tenant, ProductUnit $unit, Laboratory $laboratory, string $code): InventoryLot
{
    $product = Product::factory()
        ->for($tenant)
        ->for($unit, 'minimumUnit')
        ->for($laboratory)
        ->create(['commercial_name' => "Producto {$code}", 'internal_code' => $code]);
    $presentation = ProductPresentation::factory()
        ->for($tenant)
        ->for($product)
        ->for($unit, 'unit')
        ->create(['name' => 'Unidad', 'minimum_unit_quantity' => 1]);

    return InventoryLot::factory()
        ->for($tenant)
        ->for($product)
        ->for($presentation, 'presentation')
        ->create(['lot_number' => "LOT-{$code}", 'initial_quantity' => 0, 'current_quantity' => 0, 'unit_cost' => 250]);
}

test('kardex can filter by laboratory', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    Permission::findOrCreate('inventory.view');
    $user->givePermissionTo('inventory.view');

    $unit = ProductUnit::factory()->create(['code' => 'kardex-lab-unit-'.$tenant->id]);
    $labA = Laboratory::factory()->for($tenant)->create(['name' => 'Laboratorio A']);
    $labB = Laboratory::factory()->for($tenant)->create(['name' => 'Laboratorio B']);

    $lotA = kardexLotForLab($tenant, $unit, $labA, 'LAB-A');
    $lotB = kardexLotForLab($tenant, $unit, $labB, 'LAB-B');

    app(RegisterInventoryMovement::class)->purchase($lotA, 5, $user, ['reference_code' => 'RC-LAB-A']);
    app(RegisterInventoryMovement::class)->purchase($lotB, 7, $user, ['reference_code' => 'RC-LAB-B']);

    $this->actingAs($user)
        ->get("/inventory/kardex?laboratory_id={$labA->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Kardex')
            ->has('movements.data', 1)
            ->where('movements.data.0.reference_code', 'RC-LAB-A')
            ->etc()
        );
});

test('kardex can filter by branch', function () {
    ['tenant' => $tenant, 'lot' => $lot, 'user' => $user] = kardexSetup();
    $otherBranch = Branch::factory()->for($tenant)->create(['name' => 'Sucursal Norte']);

    app(RegisterInventoryMovement::class)->purchase($lot, 6, $user, ['reference_code' => 'RC-NO-BRANCH']);

    $branchLot = InventoryLot::factory()
        ->for($tenant)
        ->for($lot->product)
        ->for($lot->presentation, 'presentation')
        ->for($otherBranch)
        ->create(['lot_number' => 'LOT-BRANCH', 'current_quantity' => 0]);

    app(RegisterInventoryMovement::class)->purchase($branchLot, 9, $user, ['reference_code' => 'RC-BRANCH']);

    $this->actingAs($user)
        ->get("/inventory/kardex?branch_id={$otherBranch->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Kardex')
            ->has('movements.data', 1)
            ->where('movements.data.0.reference_code', 'RC-BRANCH')
            ->etc()
        );
});

test('kardex export streams a csv with the filtered movements', function () {
    ['lot' => $lot, 'user' => $user] = kardexSetup();

    app(RegisterInventoryMovement::class)->purchase($lot, 10, $user, ['reference_code' => 'RC-EXPORT']);

    $content = $this->actingAs($user)
        ->get('/inventory/kardex/export')
        ->assertSuccessful()
        ->streamedContent();

    expect($content)->toContain('RC-EXPORT')
        ->toContain('Producto Kardex Visible')
        ->toContain('KDX-001');
});
